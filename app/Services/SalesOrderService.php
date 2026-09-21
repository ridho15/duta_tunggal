<?php

namespace App\Services;

use App\Http\Controllers\HelperController;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Quotation;
use App\Models\SaleOrder;
use App\Models\StockReservation;
use App\Support\CurrencyConversionResolver;
use App\Helpers\MoneyHelper;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesOrderService
{
    /**
     * Tempo default (hari) bila tidak ada satu pun sumber yang terisi.
     */
    public const DEFAULT_TEMPO_PEMBAYARAN = 30;

    /**
     * Tentukan tempo pembayaran (hari) untuk Sales Order.
     *
     * Urutan: nilai eksplisit -> tempo quotation -> tempo master customer -> default.
     * Nilai 0 (tunai/COD) adalah nilai SAH dan tidak boleh dianggap kosong; hanya
     * null / string kosong / non-numerik yang jatuh ke sumber berikutnya. Aturan
     * ini sengaja sama dengan perhitungan due date invoice (SO -> customer -> 30).
     */
    public function resolveTempoPembayaran(mixed $explicit = null, ?Customer $customer = null, ?Quotation $quotation = null): int
    {
        foreach ([$explicit, $quotation?->tempo_pembayaran, $customer?->tempo_kredit] as $candidate) {
            if ($candidate !== null && $candidate !== '' && is_numeric($candidate)) {
                return max(0, (int) $candidate);
            }
        }

        return self::DEFAULT_TEMPO_PEMBAYARAN;
    }

    /**
     * ID mata uang default (IDR; fallback mata uang pertama).
     */
    public function defaultCurrencyId(): ?int
    {
        return CurrencyConversionResolver::resolveCurrencyIdByCode('IDR')
            ?? Currency::query()->orderBy('id')->value('id');
    }

    /**
     * Guard sisi server: hanya quotation Approved, belum kedaluwarsa, dan belum digantikan revisi
     * yang boleh dijadikan Sales Order. Dipakai modal Quotation, API getQuotation, dan store SO.
     *
     * @throws ValidationException
     */
    public function assertQuotationUsable(Quotation $quotation, string $field = 'quotation_id'): void
    {
        $reason = $quotation->unusableReasonForSaleOrder();

        if ($reason !== null) {
            throw ValidationException::withMessages([$field => $reason]);
        }
    }

    /**
     * SATU-SATUNYA pemetaan header Quotation -> Sales Order.
     *
     * Dipakai oleh modal di halaman View, aksi baris di tabel, dan endpoint API
     * getQuotation() supaya ketiga jalur menyalin data yang identik.
     * Nilai yang tidak tersedia dikembalikan null (bukan placeholder seperti '-').
     */
    public function headerFromQuotation(Quotation $quotation): array
    {
        $quotation->loadMissing('customer');

        $customer = $quotation->customer;
        $customer = ($customer && $customer->exists) ? $customer : null;

        $currencyId = is_numeric($quotation->currency_id)
            ? (int) $quotation->currency_id
            : $this->defaultCurrencyId();

        // Kurs snapshot dari quotation (kurs yang disepakati); fallback kurs saat ini.
        $snapshotRate = (float) ($quotation->exchange_rate ?? 0);
        $exchangeRate = $snapshotRate > 0
            ? $snapshotRate
            : CurrencyConversionResolver::resolveRate($currencyId);

        $shippedTo = collect([$quotation->shipped_to, $customer?->address])
            ->map(fn ($value) => is_string($value) ? trim($value) : '')
            ->first(fn ($value) => $value !== '' && $value !== '-');

        return [
            'customer_id' => $quotation->customer_id,
            'quotation_id' => $quotation->id,
            'cabang_id' => $quotation->cabang_id,
            'currency_id' => $currencyId,
            'exchange_rate' => $exchangeRate,
            'tempo_pembayaran' => $this->resolveTempoPembayaran(null, $customer, $quotation),
            'shipped_to' => $shippedTo,
            'notes' => filled($quotation->notes) ? $quotation->notes : null,
        ];
    }

    public function updateTotalAmount($salesOrder)
    {
        $total_amount = 0;
        foreach ($salesOrder->saleOrderItem as $item) {
            $taxType = \App\Services\TaxService::normalizeType($item->tipe_pajak ?? 'PPN Excluded');
            $subtotal = HelperController::hitungSubtotal(
                $item->quantity,
                $item->unit_price,
                $item->discount,
                $item->tax,
                $taxType
            );
            $total_amount += CurrencyConversionResolver::convertToIdr(MoneyHelper::parseHighPrecision($subtotal), is_numeric($item->currency_id ?? null) ? (int) $item->currency_id : null, false);
        }

        return $salesOrder->update([
            'total_amount' => $total_amount
        ]);
    }

    public function cancel($salesOrder)
    {
        DB::transaction(function () use ($salesOrder) {
            // Buku besar aktif: SaleOrderObserver melepas SEMUA reservasi SO/DO-nya lewat StockReservationLedger (tercatat sebagai event).
            // Alur lama (semua flag mati): reservasi dihapus di sini agar qty_reserved turun.
            if (! StockReservationLedger::enabled()) {
                StockReservation::where('sale_order_id', $salesOrder->id)->each(function ($reservation) {
                    $reservation->delete(); // StockReservationObserver::deleted → menurunkan qty_reserved
                });
            }

            $salesOrder->update(['status' => 'canceled']);
        });

        return true;
    }

    public function requestApprove($saleOrder)
    {
        return $saleOrder->update([
            'status' => 'request_approve',
            'request_approve_by' => Auth::user()->id,
            'request_approve_at' => Carbon::now()
        ]);
    }

    public function requestClose($saleOrder)
    {
        return $saleOrder->update([
            'status' => 'request_close',
            'request_close_by' => Auth::user()->id,
            'request_close_at' => Carbon::now()
        ]);
    }

    /**
     * @param  array{backorder?: bool, reason?: string|null, override_reason?: string|null, credit_override_reason?: string|null}  $options  backorder=true: setujui walau stok kurang (D2, alasan wajib);
     *                                                                                                  override_reason: alasan override pembuat=penyetuju (Owner/Super Admin, D24)
     */
    public function approve($saleOrder, array $options = [])
    {
        // T3.1 (flag sales.controls.approval_rules): aturan persetujuan ditegakkan DI SERVICE, bukan hanya di policy/aksi.
        app(ApprovalControlService::class)->enforce(Auth::user(), $saleOrder, $options);

        // T3.2 (flag credit_policy): baris customer dikunci sampai persetujuan SO tersimpan, sehingga dua persetujuan bersamaan
        // untuk customer yang sama tidak dapat sama-sama lolos memakai sisa limit yang sama.
        if (config('sales.controls.credit_policy', false) && $saleOrder->customer_id) {
            return DB::transaction(function () use ($saleOrder, $options) {
                \App\Models\Customer::withoutGlobalScopes()->whereKey($saleOrder->customer_id)->lockForUpdate()->first();

                return $this->approveLocked($saleOrder, $options);
            });
        }

        return $this->approveLocked($saleOrder, $options);
    }

    private function approveLocked($saleOrder, array $options)
    {
        // Validate customer credit limit before approving
        $saleOrder->loadMissing('customer');
        if ($saleOrder->customer && $saleOrder->customer->tipe_pembayaran === 'Kredit') {
            $creditService = app(CreditValidationService::class);
            $check = $creditService->canCustomerMakePurchase($saleOrder->customer, (float) $saleOrder->total_amount);
            if (! $check['can_purchase'] && ! $this->creditOverrideGranted($saleOrder, $options, $check['messages'])) {
                $messages = implode('; ', $check['messages']);
                if ($creditService->policyEnabled()) {
                    $messages .= ' — Owner, Super Admin atau Finance Manager dapat mengecualikan dengan alasan tercatat.';
                }
                Notification::make()
                    ->title('Persetujuan Ditolak')
                    ->body($messages)
                    ->danger()
                    ->send();

                throw ValidationException::withMessages([
                    'customer_id' => $messages,
                ]);
            }
        }

        $backorder = null;

        if (config('sales.stock.block_short_approval', false)) {
            // T2.5 (D2): SEMUA item diperiksa (dengan/tanpa alokasi/gudang) terhadap stok bebas sadar-reservasi.
            $backorder = $this->assertStockOrBackorder($saleOrder, $options);
        } else {
            $this->assertAllocationStock($saleOrder);
        }

        return $saleOrder->update([
            'status' => 'approved',
            'approve_by' => Auth::id() ?? auth()->id(),
            'approve_at' => Carbon::now(),
        ] + ($backorder ?? []));
    }

    /**
     * T3.2 (D24): pengecualian limit kredit — hanya Owner/Super Admin/Finance Manager, alasan >= 10 karakter, tercatat di audit.
     *
     * @param  array<int, string>  $messages  alasan penolakan yang dikecualikan
     */
    private function creditOverrideGranted($saleOrder, array $options, array $messages): bool
    {
        if (! config('sales.controls.credit_policy', false)) {
            return false;
        }

        $user = Auth::user();
        $reason = trim((string) ($options['credit_override_reason'] ?? ''));
        if (! $user || ! $user->hasRole(['Super Admin', 'Owner', 'Finance Manager']) || mb_strlen($reason) < 10) {
            return false;
        }

        \App\Models\ApprovalOverride::create([
            'document_type' => 'sale_order',
            'document_id' => $saleOrder->getKey(),
            'user_id' => $user->getKey(),
            'amount' => (float) $saleOrder->total_amount,
            'reason' => $reason,
            'context' => ['kind' => 'credit_limit', 'document_number' => $saleOrder->so_number, 'blocked_by' => $messages],
        ]);

        return true;
    }

    /**
     * Setujui SO sebagai BACKORDER (stok kurang): alasan wajib; hanya oleh yang berwenang menyetujui SO (peran Sales Manager ke atas,
     * pemisahan tugas dengan pembuat SO tetap berlaku — sama seperti approve biasa). SO tanpa kekurangan disetujui biasa.
     *
     * @throws ValidationException
     */
    public function approveAsBackorder($saleOrder, ?string $reason, ?string $overrideReason = null, ?string $creditOverrideReason = null)
    {
        $allowed = app(ApprovalControlService::class)->canApproveSaleOrder(Auth::user(), $saleOrder);
        if (! $allowed['allowed']) {
            throw ValidationException::withMessages(['approval' => $allowed['reason'] ?? 'Akses persetujuan ditolak.']);
        }

        return $this->approve($saleOrder, ['backorder' => true, 'reason' => $reason, 'override_reason' => $overrideReason, 'credit_override_reason' => $creditOverrideReason]);
    }

    /** Perilaku lama (flag block_short_approval mati): hanya alokasi gudang yang diperiksa, item tanpa alokasi lolos. */
    private function assertAllocationStock($saleOrder): void
    {
        // Validate warehouse free stock for all allocations
        $saleOrder->loadMissing(['saleOrderItem.warehouseAllocations.warehouse', 'saleOrderItem.product']);
        $insufficientItems = [];
        foreach ($saleOrder->saleOrderItem as $item) {
            foreach ($item->warehouseAllocations as $allocation) {
                $whId = $allocation->warehouse_id;
                $whQty = (float) $allocation->quantity;
                $freeStock = app(\App\Services\StockAvailability::class)->freeForSaleOrders((int) $item->product_id, (int) $whId, [(int) $saleOrder->id]);
                if ($whQty > $freeStock) {
                    $whName = $allocation->warehouse?->name ?? "Gudang #{$whId}";
                    $prodName = $item->product?->name ?? "Produk #{$item->product_id}";
                    $insufficientItems[] = "{$prodName} di {$whName} (Dibutuhkan: {$whQty}, Stok bebas: {$freeStock})";
                }
            }
        }

        if (! empty($insufficientItems)) {
            $msg = 'Stok gudang tidak mencukupi untuk item: '.implode('; ', $insufficientItems);
            Notification::make()
                ->title('Persetujuan Ditolak: Stok Kurang')
                ->body($msg)
                ->danger()
                ->send();

            throw ValidationException::withMessages([
                'stock' => $msg,
            ]);
        }
    }

    /**
     * T2.5: stok kurang → ditolak kecuali backorder beralasan. Mengembalikan atribut backorder untuk disimpan bersama persetujuan (atau null).
     *
     * @return array<string, mixed>|null
     */
    private function assertStockOrBackorder($saleOrder, array $options): ?array
    {
        $availability = app(\App\Services\StockAvailability::class);
        $check = $availability->check($saleOrder);

        if (! $check['has_shortage']) {
            return null;
        }

        $lines = $availability->describeShortages($check);

        if (! ($options['backorder'] ?? false)) {
            $msg = 'Stok tidak mencukupi — '.implode('; ', $lines).'. Kurangi qty, tunggu stok masuk, atau gunakan "Setujui sebagai Backorder" (alasan wajib) bila memang pre-order.';
            Notification::make()->title('Persetujuan Ditolak: Stok Kurang')->body($msg)->danger()->send();

            throw ValidationException::withMessages(['stock' => $msg]);
        }

        $reason = trim((string) ($options['reason'] ?? ''));
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Alasan backorder wajib diisi (mis. barang dalam perjalanan, PO ke supplier sudah terbit).']);
        }

        return [
            'is_backorder' => true,
            'backorder_reason' => $reason,
            'backorder_approved_by' => Auth::id(),
            'backorder_approved_at' => Carbon::now(),
        ];
    }

    public function close($saleOrder)
    {
        return $saleOrder->update([
            'status' => 'closed',
            'close_by' => Auth::id() ?? auth()->id(),
            'close_at' => Carbon::now()
        ]);
    }

    public function reject($saleOrder)
    {
        return $saleOrder->update([
            'status' => 'reject',
            'reject_by' => Auth::id() ?? auth()->id(),
            'reject_at' => Carbon::now()
        ]);
    }

    public function completed($saleOrder)
    {
        try {
            return $saleOrder->update([
                'status' => 'completed',
                'completed_at' => Carbon::now()
            ]);
        } catch (\App\Exceptions\DeliveryOrderTransitionException $e) {
            // T2.3 (D15): stok fisik "Ambil Sendiri" tidak cukup
            Notification::make()->title('Penyelesaian Ditolak: Stok Kurang')->body($e->getMessage())->danger()->send();

            throw ValidationException::withMessages(['stock' => $e->getMessage()]);
        }
    }

    public function createPurchaseOrder($saleOrder, $data)
    {
        $selectedItemIds = collect($data['selected_sale_order_item_ids'] ?? [])->filter()->values();
        $itemsQuery = $saleOrder->saleOrderItem()->whereDoesntHave('purchaseOrderItem');

        if ($selectedItemIds->isNotEmpty()) {
            $itemsQuery->whereIn('id', $selectedItemIds->all());
        }

        $saleOrderItems = $itemsQuery->with('product')->get();
        if ($saleOrderItems->isEmpty()) {
            Notification::make()
                ->title('Gagal Membuat Purchase Order')
                ->body('Tidak ada item Sales Order yang valid untuk dibuatkan Purchase Order.')
                ->danger()
                ->send();

            throw ValidationException::withMessages([
                'selected_sale_order_item_ids' => 'Tidak ada item Sales Order yang valid untuk dibuatkan Purchase Order.',
            ]);
        }

        // Create Purchase order
        $purchaseOrder = $saleOrder->purchaseOrder()->create([
            'po_number' => $data['po_number'],
            'supplier_id' => $data['supplier_id'],
            'order_date' => $data['order_date'],
            'note' => $data['note'],
            'warehouse_id' => $data['warehouse_id'],
            'expected_date' => $data['expected_date'],
            'tempo_hutang' => $data['tempo_hutang'],
        ]);

        $rupiahCurrencyId = Currency::where('name', 'Rupiah')->value('id');

        foreach ($saleOrderItems as $saleOrderItem) {
            $saleOrderItem->purchaseOrderItem()->create([
                'purchase_order_id' => $purchaseOrder->id,
                'product_id' => $saleOrderItem->product_id,
                'quantity' => $saleOrderItem->quantity,
                'currency_id' => $rupiahCurrencyId,
                'unit_price' => $saleOrderItem->product->sell_price,
                'discount' => 0,
                'tax' => 0,
            ]);
        }
        return $saleOrder;
    }

    public function generateSoNumber()
    {
        $prefix = 'SO-';

        // Find the highest existing sequence number globally (ignoring branch scopes)
        $max = SaleOrder::withoutGlobalScopes()
            ->where('so_number', 'like', $prefix . '%')
            ->max('so_number');

        $next = 1;
        if ($max !== null) {
            $suffix = substr((string) $max, strlen($prefix));
            if (is_numeric($suffix)) {
                $next = (int) $suffix + 1;
            }
        }

        // Guard against concurrent inserts
        do {
            $candidate = $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
            $exists = SaleOrder::withoutGlobalScopes()
                ->where('so_number', $candidate)
                ->exists();
            if ($exists) {
                $next++;
            }
        } while ($exists);

        return $candidate;
    }

    public function titipSaldo($saleOrder, $data)
    {
        if ($saleOrder->customer->deposit->id == null) {
            $deposit = $saleOrder->customer->deposit()->create([
                'amount' => $data['titip_saldo'],
                'used_amount' => 0,
                'remaining_amount' => $data['titip_saldo'],
                'coa_id' => $data['coa_id'],
                'created_by' => Auth::user()->id,
            ]);
        } else {
            $deposit = $saleOrder->customer->deposit()->update([
                'amount' => $saleOrder->customer->deposit->amount + $data['titip_saldo'],
                'remaining_amount' => $saleOrder->customer->deposit->amount + $data['titip_saldo']
            ]);

            $saleOrder->customer->deposit->depositLog()->create([
                'deposit_id' => $deposit->id,
                'type' => 'add',
                'amount' => $data['titip_saldo'],
                'note' => $data['note'],
                'created_by' => Auth::user()->id,
            ]);

            $saleOrder->depositLog()->create([
                'deposit_id' => $deposit->id,
                'type' => 'add',
                'amount' => $data['titip_saldo'],
                'note' => $data['note'],
                'created_by' => Auth::user()->id,
            ]);
        }
    }

    public function confirmWarehouse($saleOrder, $confirmationData)
    {
        // Validate that SO is approved
        if ($saleOrder->status !== 'approved') {
            Notification::make()
                ->title('Gagal Konfirmasi Gudang')
                ->body('Sales Order harus disetujui terlebih dahulu sebelum konfirmasi gudang.')
                ->danger()
                ->send();

            throw ValidationException::withMessages([
                'status' => 'Sales Order harus disetujui terlebih dahulu sebelum konfirmasi gudang.',
            ]);
        }

        // Create warehouse confirmation record (polymorphic confirmable)
        $confirmation = \App\Models\WarehouseConfirmation::create([
            'confirmable_type' => SaleOrder::class,
            'confirmable_id'   => $saleOrder->id,
            'confirmation_type' => 'sales_order',
            'status'           => $confirmationData['status'] ?? 'confirmed',
            'note'             => $confirmationData['notes'] ?? null,
            'confirmed_by'     => Auth::user()->id,
            'confirmed_at'     => Carbon::now(),
        ]);

        // Process each item
        foreach ($confirmationData['items'] as $itemData) {
            $confirmation->warehouseConfirmationItems()->create([
                'sale_order_item_id' => $itemData['sale_order_item_id'],
                'confirmed_qty' => $itemData['confirmed_qty'],
                'warehouse_id' => $itemData['warehouse_id'],
                'rak_id' => $itemData['rak_id'],
                'status' => $itemData['status']
            ]);
        }

        // Update SO status based on confirmation
        $overallStatus = $this->determineOverallStatus($confirmationData['items']);
        $saleOrder->update([
            'status' => $overallStatus,
            'warehouse_confirmed_at' => Carbon::now()
        ]);

        // Update warehouse confirmation status based on overall status
        $confirmationStatus = match ($overallStatus) {
            'confirmed' => 'confirmed',
            'partial_confirmed' => 'partial_confirmed',
            'reject' => 'rejected',
            default => 'confirmed'
        };
        $confirmation->update(['status' => $confirmationStatus]);

        return true;
    }

    private function determineOverallStatus($items)
    {
        $allConfirmed = true;
        $allRejected = true;
        $hasPartial = false;

        foreach ($items as $item) {
            if ($item['status'] === 'confirmed') {
                $allRejected = false;
            } elseif ($item['status'] === 'partial_confirmed') {
                $allConfirmed = false;
                $allRejected = false;
                $hasPartial = true;
            } elseif ($item['status'] === 'rejected') {
                $allConfirmed = false;
            }
        }

        if ($allConfirmed) {
            return 'confirmed';
        } elseif ($allRejected) {
            return 'reject';
        } elseif ($hasPartial) {
            return 'partial_confirmed';
        }

        return 'confirmed'; // default
    }

    public function createDeliveryOrder($saleOrder, $deliveryData)
    {
        // Validate that SO is confirmed
        if (!in_array($saleOrder->status, ['confirmed', 'partial_confirmed'])) {
            Notification::make()
                ->title('Gagal Membuat Delivery Order')
                ->body('Sales Order harus sudah konfirmasi gudang sebelum Delivery Order dibuat.')
                ->danger()
                ->send();

            throw ValidationException::withMessages([
                'status' => 'Sales Order harus sudah konfirmasi gudang sebelum Delivery Order dibuat.',
            ]);
        }

        // Create delivery order
        $warehouseId = $deliveryData['warehouse_id'] ?? $saleOrder->warehouseConfirmation->warehouseConfirmationItems->first()->warehouse_id ?? null;
        if (!$warehouseId) {
            Notification::make()
                ->title('Gagal Membuat Delivery Order')
                ->body('Gudang harus dipilih untuk membuat Delivery Order.')
                ->danger()
                ->send();

            throw ValidationException::withMessages([
                'warehouse_id' => 'Gudang harus dipilih untuk membuat Delivery Order.',
            ]);
        }

        // Resolve a real driver and vehicle to satisfy NOT NULL FK constraints
        $driverId  = $deliveryData['driver_id']  ?? \App\Models\Driver::first()?->id;
        $vehicleId = $deliveryData['vehicle_id'] ?? \App\Models\Vehicle::first()?->id;

        if (!$driverId || !$vehicleId) {
            \Filament\Notifications\Notification::make()
                ->title('Gagal Membuat Delivery Order')
                ->danger()
                ->body('Tidak ditemukan driver atau kendaraan di database. Silakan pastikan data master sudah terisi untuk auto-creation Delivery Order.')
                ->send();
            throw ValidationException::withMessages([
                'driver_id' => 'Driver dan kendaraan harus tersedia sebelum Delivery Order dapat dibuat.',
            ]);
        }

        $deliveryOrder = $saleOrder->deliveryOrder()->create([
            'do_number'     => $this->generateDoNumber(),
            'delivery_date' => $deliveryData['delivery_date'],
            'warehouse_id'  => $warehouseId,
            'driver_id'     => $driverId,
            'vehicle_id'    => $vehicleId,
            'status'        => 'draft',
            'notes'         => $deliveryData['notes'] ?? null,
            'created_by'    => Auth::user()->id,
        ]);

        // Copy confirmed items to delivery order
        $wc = $saleOrder->warehouseConfirmation()->where('status', 'confirmed')->latest()->first();
        if ($wc) {
            foreach ($wc->warehouseConfirmationItems as $confirmedItem) {
                if ($confirmedItem->status === 'confirmed' || $confirmedItem->status === 'partial_confirmed') {
                    $deliveryOrder->deliveryOrderItem()->create([
                        'sale_order_item_id' => $confirmedItem->sale_order_item_id,
                        'product_id'         => $confirmedItem->saleOrderItem->product_id,
                        'quantity'           => $confirmedItem->confirmed_qty,
                        'warehouse_id'       => $confirmedItem->warehouse_id,
                        'rak_id'             => $confirmedItem->rak_id,
                    ]);
                }
            }
        }

        return true;
    }

    public function generateDoNumber()
    {
        return \App\Services\DeliveryOrderService::generateStaticDoNumber();
    }
}
