<?php

namespace App\Observers;

use App\Models\DeliveryOrder;
use App\Models\StockReservation;
use App\Models\SaleOrder;
use App\Services\ProductService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeliveryOrderObserver
{
    protected ProductService $productService;

    public function __construct()
    {
        $this->productService = app(ProductService::class);
    }

    /**
     * Handle the DeliveryOrder "created" event.
     * Stok bebas DIKUNCI (reservasi) begitu DO dibuat; stok fisik baru berkurang saat barang berangkat (Dikirim).
     * Aturan bisnis: "Stok bebas terkunci saat DO dibuat" dan "Stok bebas = stok fisik − stok terkunci DO".
     */
    public function created(DeliveryOrder $deliveryOrder): void
    {
        if (! in_array($deliveryOrder->status, ['closed', 'reject'], true)) {
            $this->createStockMovementsForDeliveryOrder($deliveryOrder);
        }
    }

    /**
     * Handle the DeliveryOrder "updated" event.
     */
    public function updated(DeliveryOrder $deliveryOrder): void
    {
        $originalStatus = $deliveryOrder->getOriginal('status');
        $newStatus = $deliveryOrder->status;

        // T2.3 (flag stock.strict_dispatch): status item + log + pengembalian stok gagal-kirim seragam untuk SEMUA pintu.
        if ($deliveryOrder->wasChanged('status') && \App\Services\DeliveryOrderTransitions::enabled()) {
            $this->handleStrictStatusEffects($deliveryOrder, (string) $originalStatus, (string) $newStatus);
        }

        // Disetujui (stok dikonfirmasi gudang): pastikan stok bebas terkunci sesuai isi DO
        if ($originalStatus !== 'approved' && $newStatus === 'approved') {
            $this->handleApprovedStatus($deliveryOrder);
        }

        // Jika status berubah ke 'sent', lepaskan stock reservations (pemotongan stok idempoten)
        if ($originalStatus !== 'sent' && $newStatus === 'sent') {
            $this->handleReservationReleaseStatus($deliveryOrder);
        }

        // Jika status berubah ke 'completed', posting jurnal dan update related sales orders
        // (termasuk lompatan approved → completed tanpa melewati sent: stok fisik dipotong di handler ini)
        if ($originalStatus !== 'completed' && $newStatus === 'completed') {
            $this->handleCompletedStatus($deliveryOrder);
        }

        // Jika status sudah 'completed' dan ada perubahan quantity, update journal entries
        if ($newStatus === 'completed' && $this->hasQuantityChanges($deliveryOrder)) {
            $this->handleQuantityUpdateAfterCompleted($deliveryOrder);
        }

        // DO ditutup/ditolak → lepas kunci stok DO; stok fisik yang sempat keluar (mis. gagal kirim lalu ditutup) dikembalikan.
        if ($deliveryOrder->wasChanged('status') && in_array($newStatus, ['closed', 'reject'], true)) {
            app(\App\Services\DeliveryShipments::class)->reverse($deliveryOrder, "DO {$deliveryOrder->do_number} {$newStatus}");
            app(\App\Services\StockReservationLedger::class)->releaseForDeliveryOrder($deliveryOrder->id, "DO {$deliveryOrder->do_number} {$newStatus}");
            StockReservation::where('delivery_order_id', $deliveryOrder->id)->get()->each->delete();
        }

        // Perubahan status lain (mis. delivery_failed, closed, reject, kembali ke draft) juga
        // dapat mengubah kuantitas terkirim/terikat -> sinkronkan progres SO.
        // sent & completed sudah disinkronkan di handler masing-masing.
        if ($deliveryOrder->wasChanged('status') && ! in_array($newStatus, ['sent', 'completed'], true)) {
            $this->syncDeliveryProgress($deliveryOrder);
        }
    }

    /**
     * T2.3: efek yang harus sama di semua pintu status — status item mengikuti status DO, log berjejak (alasan/penerima/pengecualian),
     * dan pengiriman gagal SETELAH barang berangkat mengembalikan stok fisik (D18) serta membuat ulang reservasi DO untuk dijadwalkan ulang.
     */
    protected function handleStrictStatusEffects(DeliveryOrder $deliveryOrder, string $from, string $to): void
    {
        $itemStatus = \App\Services\DeliveryOrderTransitions::itemStatusFor($to);
        if ($itemStatus !== null) {
            $deliveryOrder->deliveryOrderItem()->update(['status' => $itemStatus]);
        }

        if ($to === 'delivery_failed' && in_array($from, ['sent', 'received'], true)) {
            $context = \App\Services\DeliveryOrderTransitions::context();
            $reason = (string) ($context['reason'] ?? 'pengiriman gagal');

            app(\App\Services\DeliveryShipments::class)->reverse($deliveryOrder, $reason);
            app(\App\Services\DeliveryOrderReservations::class)->sync($deliveryOrder, "DO {$deliveryOrder->do_number} gagal kirim — reservasi dibuat ulang untuk penjadwalan ulang");
        }

        \App\Services\DeliveryOrderTransitions::writeLog($deliveryOrder, $from, $to);
    }

    /**
     * DO dipulihkan dari soft-delete: kuantitasnya kembali terikat ke SO dan stoknya dikunci (atau dipotong bila sudah berangkat) kembali.
     */
    public function restored(DeliveryOrder $deliveryOrder): void
    {
        if (! in_array($deliveryOrder->status, ['closed', 'reject'], true)) {
            $this->createStockMovementsForDeliveryOrder($deliveryOrder);
        }
        $this->syncDeliveryProgress($deliveryOrder);
    }

    /** Buku besar reservasi & pengiriman idempoten (flag sales.stock.ledger, T2.1). */
    protected function ledgerEnabled(): bool
    {
        return \App\Services\StockReservationLedger::enabled();
    }

    /**
     * Hitung ulang cache delivered_quantity dan status SO untuk SO yang disentuh DO ini.
     */
    protected function syncDeliveryProgress(DeliveryOrder $deliveryOrder): void
    {
        app(\App\Services\SaleOrderDeliveryProgress::class)->syncForDeliveryOrder($deliveryOrder);

        // T2.4 (flag stock.reserve_on_so_approve): kebutuhan/penahanan SO berubah bersama DO → reservasi level-SO disusun ulang.
        app(\App\Services\SaleOrderReservationSynchronizer::class)->syncForDeliveryOrder($deliveryOrder, "DO {$deliveryOrder->do_number} → {$deliveryOrder->status}");
    }

    /**
     * Handle when Delivery Order status becomes 'approved' (stok dikonfirmasi gudang, siap dicek/dikirim).
     * Stok TIDAK dipotong di sini — hanya dipastikan terkunci sesuai isi DO terbaru (idempoten).
     */
    protected function handleApprovedStatus(DeliveryOrder $deliveryOrder): void
    {
        Log::info('DeliveryOrderObserver: Handling approved status', [
            'delivery_order_id' => $deliveryOrder->id,
            'do_number' => $deliveryOrder->do_number,
        ]);

        $this->createStockMovementsForDeliveryOrder($deliveryOrder);
    }

    /**
     * DO Dikirim (barang berangkat): stok fisik dipotong di sini dan kunci stok DO dikonsumsi.
     * Idempoten — kuantitas yang sudah keluar (netShipped) tidak dipotong ulang.
     */
    protected function handleReservationReleaseStatus(DeliveryOrder $deliveryOrder): void
    {
        Log::info('DeliveryOrderObserver: Handling reservation release', [
            'delivery_order_id' => $deliveryOrder->id,
            'do_number' => $deliveryOrder->do_number,
        ]);

        // Barang berangkat: potong stok fisik (idempoten)
        $this->deductShippedStock($deliveryOrder);

        // Catat jurnal pengiriman (Dr Barang Terkirim / Cr Persediaan) saat barang berangkat
        $this->createJournalEntriesForDelivery($deliveryOrder);

        // Reservasi DO dilepas bila masih ada
        app(\App\Services\StockReservationLedger::class)->releaseForDeliveryOrder(
            $deliveryOrder->id,
            "DO {$deliveryOrder->do_number} dikirim — reservasi dikonsumsi",
            \App\Models\StockReservationEvent::CONSUMED
        );
        StockReservation::where('delivery_order_id', $deliveryOrder->id)->get()->each->delete();

        // Progres SO (cache delivered_quantity + status SO) dihitung ulang oleh satu service.
        $this->syncDeliveryProgress($deliveryOrder);
    }

    /**
     * Saat true, efek stok DO DITUNDA (hook item/sumber gudang tidak mengunci/memotong). Dipakai halaman Create DO: item DO
     * tersimpan lebih dulu daripada sumber gudangnya, sehingga tanpa penundaan hook item mengunci SELURUH kuantitas di
     * gudang header DO (fallback single-warehouse) sebelum sumber gudang pilihan user ada. Efek dijalankan sekali setelah semua relasi tersimpan.
     */
    public static bool $deferStockDeduction = false;

    /** DO yang barangnya sudah berangkat — stok fisiknya sudah/harus berkurang. Sebelum itu stok hanya dikunci. */
    public static function isDispatched(DeliveryOrder $deliveryOrder): bool
    {
        return in_array($deliveryOrder->status, DeliveryOrder::DELIVERED_STATUSES, true);
    }

    /**
     * Terapkan efek stok DO sesuai statusnya (nama lama dipertahankan karena dipanggil dari banyak tempat):
     *  - belum berangkat (draft s/d approved): stok bebas DIKUNCI lewat reservasi level-DO — stok fisik tidak berubah;
     *  - sudah berangkat (sent/received/completed): stok fisik DIPOTONG (idempoten).
     * DO ditutup/ditolak tidak diproses.
     *
     * @throws \Illuminate\Validation\ValidationException bila stok bebas gudang sumber tidak cukup untuk dikunci
     */
    public function createStockMovementsForDeliveryOrder(DeliveryOrder $deliveryOrder, bool $force = false): void
    {
        if (self::$deferStockDeduction && ! $force) {
            return;
        }

        if (in_array($deliveryOrder->status, ['closed', 'reject'], true)) {
            return;
        }

        if (self::isDispatched($deliveryOrder)) {
            $this->deductShippedStock($deliveryOrder);

            return;
        }

        $this->lockStockForDeliveryOrder($deliveryOrder);
    }

    /**
     * Kunci stok bebas untuk DO yang belum berangkat: reservasi level-DO = isi DO (item × gudang sumber), idempoten.
     * Kuantitas yang dikunci per produk×gudang tidak boleh melebihi stok bebas (stok bebas + kunci yang sudah dimiliki DO ini
     * + reservasi level-SO milik SO-nya sendiri), kecuali pengecualian stok negatif Owner/Super Admin (D15).
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function lockStockForDeliveryOrder(DeliveryOrder $deliveryOrder): void
    {
        $deliveryOrder->unsetRelation('deliveryOrderItem');
        $deliveryOrder->load('deliveryOrderItem.product', 'deliveryOrderItem.warehouseSources', 'deliveryOrderItem.saleOrderItem');

        $overrideNegative = (bool) (\App\Services\DeliveryOrderTransitions::context()['override'] ?? false);
        if (! $overrideNegative) {
            $this->assertFreeStockForLock($deliveryOrder);
        }

        app(\App\Services\DeliveryOrderReservations::class)->sync(
            $deliveryOrder,
            "Stok dikunci untuk DO {$deliveryOrder->do_number} (belum dikirim)",
            strict: false
        );
    }

    /** @throws \Illuminate\Validation\ValidationException */
    private function assertFreeStockForLock(DeliveryOrder $deliveryOrder): void
    {
        $needs = [];   // product|warehouse => [product, warehouse, qty, sale order ids]
        foreach ($deliveryOrder->deliveryOrderItem as $item) {
            $remaining = max(0.0, (float) ($item->quantity ?? 0));
            if ($remaining <= 0 || ! $item->product_id) {
                continue;
            }
            $soId = $item->saleOrderItem?->sale_order_id;
            $rows = [];
            if ($item->warehouseSources->isNotEmpty()) {
                foreach ($item->warehouseSources as $source) {
                    $take = min(max(0.0, (float) $source->quantity), $remaining);
                    if ($source->warehouse_id && $take > 0) {
                        $rows[] = [(int) $source->warehouse_id, $take];
                        $remaining -= $take;
                    }
                }
            } elseif ($deliveryOrder->warehouse_id || $item->warehouse_id) {
                $rows[] = [(int) ($deliveryOrder->warehouse_id ?: $item->warehouse_id), $remaining];
            }
            foreach ($rows as [$warehouseId, $qty]) {
                $key = $item->product_id.'|'.$warehouseId;
                $needs[$key] ??= ['product' => $item->product, 'warehouse_id' => $warehouseId, 'qty' => 0.0, 'so' => []];
                $needs[$key]['qty'] += $qty;
                if ($soId) {
                    $needs[$key]['so'][(int) $soId] = (int) $soId;
                }
            }
        }

        $availability = app(\App\Services\StockAvailability::class);
        $messages = [];
        foreach ($needs as $need) {
            $productId = (int) $need['product']->id;
            $alreadyLocked = (float) StockReservation::query()
                ->where('delivery_order_id', $deliveryOrder->id)
                ->where('product_id', $productId)->where('warehouse_id', $need['warehouse_id'])
                ->sum('quantity');
            $free = $availability->freeForSaleOrders($productId, $need['warehouse_id'], array_values($need['so'])) + $alreadyLocked;

            if ($need['qty'] - $free > 0.00001) {
                $warehouse = \App\Models\Warehouse::query()->whereKey($need['warehouse_id'])->value('name') ?? ('Gudang #'.$need['warehouse_id']);
                $messages[] = sprintf(
                    'Stok bebas %s di %s tidak mencukupi: tersedia %s, diminta %s.',
                    $need['product']->name ?? ('Produk #'.$productId),
                    $warehouse,
                    rtrim(rtrim(number_format(max(0.0, $free), 4, ',', '.'), '0'), ','),
                    rtrim(rtrim(number_format($need['qty'], 4, ',', '.'), '0'), ',')
                );
            }
        }

        if ($messages !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages(['quantity' => $messages]);
        }
    }

    /**
     * Potong stok fisik untuk DO yang barangnya berangkat. Idempoten: kuantitas yang sudah keluar (netShipped) tidak dipotong ulang.
     */
    public function deductShippedStock(DeliveryOrder $deliveryOrder): void
    {
        $deliveryOrder->unsetRelation('deliveryOrderItem');
        $deliveryOrder->load('deliveryOrderItem.product', 'deliveryOrderItem.warehouseSources');

        $date = $deliveryOrder->delivery_date ?? now()->toDateString();

        // Pengecualian stok negatif (D15) oleh Owner/Super Admin sudah divalidasi & dicatat di DeliveryOrderTransitions;
        // di sini hanya diteruskan ke penjaga stok agar pemotongannya benar-benar diizinkan melewati nol.
        $overrideNegative = (bool) (\App\Services\DeliveryOrderTransitions::context()['override'] ?? false);

        foreach ($deliveryOrder->deliveryOrderItem as $item) {
            $qtyToShip = max(0, (float) ($item->quantity ?? 0));
            if ($qtyToShip <= 0) {
                continue;
            }

            $product = $item->product;
            if (!$product) {
                continue;
            }

            $productService = app(\App\Services\ProductService::class);

            // Handle multi-warehouse sources — dibatasi kuantitas item (sumber terakhir terpotong lebih dulu), sama dengan aturan kunci stok
            $sources = $item->warehouseSources;
            if ($sources->isNotEmpty()) {
                $remainingForItem = $qtyToShip;
                foreach ($sources as $source) {
                    $sourceQty = min(max(0, (float) ($source->quantity ?? 0)), $remainingForItem);
                    if ($sourceQty <= 0 || !$source->warehouse_id) {
                        continue;
                    }
                    $remainingForItem -= $sourceQty;

                    // Idempoten: jangan memotong stok dua kali
                    $alreadyShipped = \App\Services\DeliveryShipments::netShipped($item, (int) $source->warehouse_id, $source->rak_id);
                    $needed = max(0.0, $sourceQty - $alreadyShipped);
                    if ($needed <= 0.00001) {
                        continue;
                    }

                    $productService->createStockMovement(
                        product_id: $product->id,
                        warehouse_id: $source->warehouse_id,
                        quantity: $needed,
                        type: 'sales',
                        date: $date,
                        notes: "Barang keluar: DO {$deliveryOrder->do_number} dikirim",
                        rak_id: $source->rak_id,
                        fromModel: $item,
                        value: $product->cost_price * $needed,
                        meta: [
                            'delivery_status' => $deliveryOrder->status,
                            'dispatch_deduction' => true,
                            'source' => 'delivery_order_observer',
                            'allow_negative_stock' => $overrideNegative,
                        ]
                    );
                }
                continue;
            }

            // Single warehouse fallback
            $warehouseId = $deliveryOrder->warehouse_id ?: $item->warehouse_id;
            if (!$warehouseId) {
                continue;
            }

            $alreadyShipped = \App\Services\DeliveryShipments::netShipped($item, (int) $warehouseId, $item->rak_id);
            $needed = max(0.0, $qtyToShip - $alreadyShipped);
            if ($needed <= 0.00001) {
                continue;
            }

            $productService->createStockMovement(
                product_id: $product->id,
                warehouse_id: $warehouseId,
                quantity: $needed,
                type: 'sales',
                date: $date,
                notes: "Barang keluar: DO {$deliveryOrder->do_number} dikirim",
                rak_id: $item->rak_id,
                fromModel: $item,
                value: $product->cost_price * $needed,
                meta: [
                    'delivery_status' => $deliveryOrder->status,
                    'dispatch_deduction' => true,
                    'source' => 'delivery_order_observer',
                    'allow_negative_stock' => $overrideNegative,
                ]
            );
        }
    }

    /**
     * Menyelaraskan efek stok dengan isi DO terbaru setelah kuantitas item atau kuantitas/gudang/rak sumber diedit (mis. checker
     * mengurangi qty). DO yang belum berangkat: kunci stok disusun ulang. DO yang sudah berangkat: stok fisik dipotong/dikembalikan
     * SELISIHNYA saja (tanpa menghapus gerakan lama).
     *
     * Kuantitas tiap sumber dibatasi sisa kuantitas item (sumber terakhir terpotong lebih dulu), sama seperti aturan reservasi.
     * Idempoten: dipanggil berulang tidak menambah gerakan.
     */
    public function reconcileStockMovementsForDeliveryOrder(DeliveryOrder $deliveryOrder): void
    {
        if (self::$deferStockDeduction || in_array($deliveryOrder->status, ['closed', 'reject'], true)) {
            return;
        }

        // Belum berangkat: cukup susun ulang kunci stok. (Pemotongan lama dari DO yang belum berangkat dikembalikan
        // sekali lewat `php artisan stock:convert-open-do-deductions`, bukan di sini.)
        if (! self::isDispatched($deliveryOrder)) {
            $this->lockStockForDeliveryOrder($deliveryOrder);

            return;
        }

        $deliveryOrder->unsetRelation('deliveryOrderItem');
        $deliveryOrder->load('deliveryOrderItem.product', 'deliveryOrderItem.warehouseSources');
        $date = $deliveryOrder->delivery_date ?? now()->toDateString();
        $productService = app(\App\Services\ProductService::class);

        foreach ($deliveryOrder->deliveryOrderItem as $item) {
            $product = $item->product;
            if (! $product) {
                continue;
            }

            // Target per (gudang, rak)
            $desired = [];
            $remaining = max(0.0, (float) ($item->quantity ?? 0));
            if ($item->warehouseSources->isNotEmpty()) {
                foreach ($item->warehouseSources as $source) {
                    if (! $source->warehouse_id) {
                        continue;
                    }
                    $take = min(max(0.0, (float) $source->quantity), $remaining);
                    $key = $source->warehouse_id.'|'.($source->rak_id ?: 0);
                    $desired[$key] = ['warehouse_id' => (int) $source->warehouse_id, 'rak_id' => $source->rak_id ? (int) $source->rak_id : null, 'quantity' => ($desired[$key]['quantity'] ?? 0.0) + $take];
                    $remaining -= $take;
                }
            } elseif ($deliveryOrder->warehouse_id || $item->warehouse_id) {
                $warehouseId = (int) ($deliveryOrder->warehouse_id ?: $item->warehouse_id);
                $desired[$warehouseId.'|'.($item->rak_id ?: 0)] = ['warehouse_id' => $warehouseId, 'rak_id' => $item->rak_id ? (int) $item->rak_id : null, 'quantity' => $remaining];
            }

            // Kunci yang pernah bergerak tetapi tidak lagi dibutuhkan → target 0 (dikembalikan)
            $moved = \App\Models\StockMovement::query()
                ->where('from_model_type', \App\Models\DeliveryOrderItem::class)->where('from_model_id', $item->id)
                ->whereIn('type', ['sales', 'adjustment_in'])
                ->get(['warehouse_id', 'rak_id']);
            foreach ($moved as $row) {
                $key = $row->warehouse_id.'|'.($row->rak_id ?: 0);
                $desired[$key] ??= ['warehouse_id' => (int) $row->warehouse_id, 'rak_id' => $row->rak_id ? (int) $row->rak_id : null, 'quantity' => 0.0];
            }

            foreach ($desired as $target) {
                $net = \App\Services\DeliveryShipments::netShipped($item, $target['warehouse_id'], $target['rak_id']);
                $delta = $target['quantity'] - $net;
                if (abs($delta) <= 0.00001) {
                    continue;
                }

                $productService->createStockMovement(
                    product_id: $product->id,
                    warehouse_id: $target['warehouse_id'],
                    quantity: abs($delta),
                    type: $delta > 0 ? 'sales' : 'adjustment_in',
                    date: $date,
                    notes: ($delta > 0
                        ? "Penyesuaian stok: kuantitas DO {$deliveryOrder->do_number} bertambah"
                        : "Penyesuaian stok: kuantitas DO {$deliveryOrder->do_number} berkurang, stok dikembalikan"),
                    rak_id: $target['rak_id'],
                    fromModel: $item,
                    value: $product->cost_price * abs($delta),
                    meta: $delta > 0
                        ? ['dispatch_deduction' => true, 'source' => 'do_quantity_reconcile']
                        : ['delivery_reversal' => true, 'source' => 'do_quantity_reconcile']
                );
            }
        }
    }

    /**
     * Backward-compatibility alias
     */
    public function createStockMovementsForShippingStart(DeliveryOrder $deliveryOrder): void
    {
        $this->createStockMovementsForDeliveryOrder($deliveryOrder);
    }

    /**
     * Handle when Delivery Order status becomes 'completed'
     * Update all related sales orders to completed status and create stock movements
     *
     * NOTE: StockMovement for qty_available reduction is now created in
     * handleReservationReleaseStatus() when status changes to 'sent'.
     * This method only handles journal entries and sale order updates.
     */
    protected function handleCompletedStatus(DeliveryOrder $deliveryOrder): void
    {
        Log::info('DeliveryOrderObserver: Handling completed status', [
            'delivery_order_id' => $deliveryOrder->id,
            'do_number' => $deliveryOrder->do_number,
        ]);

        // approved → completed langsung (tanpa sent): barang berangkat di sini. Idempoten bila sudah dipotong saat sent.
        $this->deductShippedStock($deliveryOrder);

        $this->createJournalEntriesForDelivery($deliveryOrder);

        // =========================================================
        // MODIFIKASI: Skip StockMovement creation here
        // StockMovement sudah dibuat saat status berubah ke 'sent'
        // di handleReservationReleaseStatus()
        // =========================================================

        // Pastikan reservasi dilepas jika DO langsung selesai
        app(\App\Services\StockReservationLedger::class)->releaseForDeliveryOrder(
            $deliveryOrder->id,
            "DO {$deliveryOrder->do_number} selesai — reservasi dikonsumsi",
            \App\Models\StockReservationEvent::CONSUMED
        );
        StockReservation::where('delivery_order_id', $deliveryOrder->id)->get()->each->delete();

        // Progres SO: hitung ulang delivered_quantity dan turunkan status SO dari kuantitas
        // (approved -> partially_delivered -> completed) lewat satu service, bukan dari event DO.
        $this->syncDeliveryProgress($deliveryOrder);

        // B25 (UAT 13 Okt 2026): invoice TIDAK lagi terbit otomatis saat DO diselesaikan — invoice dibuat manual dari Surat Jalan
        // (Customer → SO → centang SJ). Jalur lama hanya hidup bila `sales.invoice.auto_on_do_complete` diaktifkan.
        if (config('sales.invoice.auto_on_do_complete', false)) {
            $this->createInvoiceForCompletedDeliveryOrder($deliveryOrder);
        }
    }

    /**
     * Create invoice automatically for the items delivered in this Delivery Order
     */
    protected function createInvoiceForCompletedDeliveryOrder(DeliveryOrder $deliveryOrder): void
    {
        $deliveryOrder->loadMissing('salesOrders.customer', 'deliveryOrderItem.saleOrderItem', 'deliveryOrderItem.product');

        $primarySo = $deliveryOrder->salesOrders->first();
        if (!$primarySo) {
            Log::warning('DeliveryOrderObserver: Cannot create invoice, no related sale order found', [
                'delivery_order_id' => $deliveryOrder->id,
            ]);
            return;
        }

        // Cek apakah DO ini atau SO terkait sudah pernah dibuatkan invoice aktif
        $existingInvoice = \App\Models\Invoice::where('from_model_type', SaleOrder::class)
            ->whereNotIn('status', ['canceled', 'cancelled'])
            ->where(function ($q) use ($deliveryOrder, $primarySo) {
                $q->whereJsonContains('delivery_orders', $deliveryOrder->id)
                  ->orWhere(function ($sub) use ($primarySo) {
                      $sub->where('from_model_id', $primarySo->id)
                          ->where(function ($emptyDo) {
                              $emptyDo->whereNull('delivery_orders')
                                      ->orWhereJsonLength('delivery_orders', 0);
                          });
                  });
            })
            ->first();

        if ($existingInvoice) {
            // Jika invoice SO sudah ada tanpa DO ID ini, kaitkan DO ke invoice tersebut
            $currentDos = (array) ($existingInvoice->delivery_orders ?? []);
            if (!in_array($deliveryOrder->id, $currentDos)) {
                $currentDos[] = $deliveryOrder->id;
                $existingInvoice->update(['delivery_orders' => array_values(array_unique($currentDos))]);
            }

            Log::info('DeliveryOrderObserver: Invoice already exists for delivery order or sale order', [
                'do_id' => $deliveryOrder->id,
                'invoice_id' => $existingInvoice->id,
            ]);
            return;
        }

        // Satu-satunya perhitungan nilai DO (juga dipakai label pilihan DO & PDF DO): DeliveryOrderValuation.
        $valuation = app(\App\Services\DeliveryOrderValuation::class)->forDeliveryOrder($deliveryOrder);
        $ppnRate = $valuation['ppn_rate'];
        $tipePajak = $valuation['tipe_pajak'];
        $subtotal = $valuation['dpp'];
        $totalTax = $valuation['tax'];
        $lineTotals = $valuation['goods_total'];
        $invoiceItems = $valuation['lines'];

        if (empty($invoiceItems) || $subtotal <= 0) {
            Log::warning('DeliveryOrderObserver: Skipping invoice creation, no valid items or subtotal is 0', [
                'do_id' => $deliveryOrder->id,
                'subtotal' => $subtotal,
            ]);
            return;
        }

        $additionalCosts = (float) ($deliveryOrder->additional_cost ?? 0);
        $otherFees = [];
        if ($additionalCosts > 0) {
            $otherFees[] = [
                'amount' => $additionalCosts,
                'description' => $deliveryOrder->additional_cost_description ?: 'Biaya pengiriman DO ' . $deliveryOrder->do_number,
                'type' => 'delivery_cost',
                'reference' => $deliveryOrder->do_number,
            ];
        }

        $grandTotal = round($lineTotals + $additionalCosts, 2);
        $invoiceNumber = (new \App\Services\InvoiceService())->generateSalesInvoiceNumber($deliveryOrder->cabang_id ? (int) $deliveryOrder->cabang_id : null, ($tipePajak ?? 'none') !== 'none');

        $invoiceData = [
            'invoice_number' => $invoiceNumber,
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $primarySo->id,
            'customer_name' => $primarySo->customer?->name,
            'customer_phone' => $primarySo->customer?->phone,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(
                $primarySo->tempo_pembayaran
                    ?? $primarySo->customer?->tempo_kredit
                    ?? 30
            )->toDateString(),
            'subtotal' => $subtotal,
            'tax' => $ppnRate,
            'ppn_rate' => $ppnRate,
            'tipe_pajak' => $tipePajak,
            'dpp' => $subtotal,
            'total' => $grandTotal,
            'currency_id' => $primarySo->currency_id ?? \App\Support\CurrencyConversionResolver::resolveCurrencyIdByCode('IDR'),
            'exchange_rate' => (float) ($primarySo->exchange_rate ?? 1.0),
            'other_fee' => $otherFees,
            'delivery_orders' => [$deliveryOrder->id],
            'cabang_id' => $deliveryOrder->cabang_id ?? $primarySo->cabang_id,
            'status' => 'unpaid',
            'notes' => 'Auto-generated dari Delivery Order ' . $deliveryOrder->do_number,
        ];

        $invoice = new \App\Models\Invoice($invoiceData);
        $invoice->save();

        foreach ($invoiceItems as $itemData) {
            \App\Models\InvoiceItem::create(array_merge($itemData, ['invoice_id' => $invoice->id]));
        }

        // Post journal entries immediately
        $invoiceObserver = new \App\Observers\InvoiceObserver();
        $invoiceObserver->postSalesInvoice($invoice);

        Log::info('DeliveryOrderObserver: Invoice auto-created for DO', [
            'delivery_order_id' => $deliveryOrder->id,
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'subtotal' => $subtotal,
            'total' => $grandTotal,
        ]);
    }

    /**
     * Handle the DeliveryOrder "deleted" event.
     * Delete related journal entries when delivery order is soft deleted
     */
    public function deleted(DeliveryOrder $deliveryOrder): void
    {
        Log::info('DeliveryOrderObserver: Handling deleted event', [
            'delivery_order_id' => $deliveryOrder->id,
            'do_number' => $deliveryOrder->do_number,
        ]);

        // Tahap 3: Kembalikan stok fisik bila DO dihapus
        app(\App\Services\DeliveryShipments::class)->reverse($deliveryOrder, "DO {$deliveryOrder->do_number} dihapus");

        // Delete all journal entries related to this delivery order
        $journalEntries = \App\Models\JournalEntry::where('source_type', \App\Models\DeliveryOrder::class)
            ->where('source_id', $deliveryOrder->id)
            ->get();

        foreach ($journalEntries as $entry) {
            Log::info('DeliveryOrderObserver: Deleting journal entry', [
                'journal_entry_id' => $entry->id,
                'coa_code' => $entry->coa->code ?? 'unknown',
                'amount' => $entry->debit > 0 ? $entry->debit : $entry->credit,
            ]);
            $entry->delete();
        }

        // Delete related stock reservations
        app(\App\Services\StockReservationLedger::class)->releaseForDeliveryOrder($deliveryOrder->id, "DO {$deliveryOrder->do_number} dihapus");
        StockReservation::where('delivery_order_id', $deliveryOrder->id)->get()->each->delete();

        // DO dihapus: hitung ulang progres SO (kuantitas DO ini kembali ke SO).
        $this->syncDeliveryProgress($deliveryOrder);
    }

    /**
     * Check if delivery order items have quantity changes
     */
    protected function hasQuantityChanges(DeliveryOrder $deliveryOrder): bool
    {
        foreach ($deliveryOrder->deliveryOrderItem as $item) {
            $originalQuantity = $item->getOriginal('quantity');
            $currentQuantity = $item->quantity;

            if ($originalQuantity != $currentQuantity) {
                return true;
            }
        }
        return false;
    }

    /**
    * Handle quantity updates after delivery order status is 'completed'
     * Update journal entries to reflect new quantities
     */
    public function handleQuantityUpdateAfterCompleted(DeliveryOrder $deliveryOrder): void
    {
        Log::info('DeliveryOrderObserver: Handling quantity update after completed', [
            'delivery_order_id' => $deliveryOrder->id,
            'do_number' => $deliveryOrder->do_number,
        ]);

        // Delete existing journal entries for this delivery order
        $existingEntries = \App\Models\JournalEntry::where('source_type', \App\Models\DeliveryOrder::class)
            ->where('source_id', $deliveryOrder->id)
            ->get();

        foreach ($existingEntries as $entry) {
            Log::info('DeliveryOrderObserver: Deleting old journal entry for quantity update', [
                'journal_entry_id' => $entry->id,
                'coa_code' => $entry->coa->code ?? 'unknown',
            ]);
            $entry->delete();
        }

        // Recreate journal entries with updated quantities
        $this->createJournalEntriesForDelivery($deliveryOrder);

        // Kuantitas berubah setelah completed: hitung ulang progres SO.
        $this->syncDeliveryProgress($deliveryOrder);
    }

    /**
    * Create journal entries for delivery order (extracted from the status transition handlers)
     */
    protected function createJournalEntriesForDelivery(DeliveryOrder $deliveryOrder): void
    {
        // Cegah pembuatan jurnal duplikat jika sudah pernah dibuat (misal saat sent)
        $hasJournals = \App\Models\JournalEntry::where('source_type', DeliveryOrder::class)
            ->where('source_id', $deliveryOrder->id)
            ->exists();
        if ($hasJournals) {
            return;
        }

        // Load delivery order items with related data
        $deliveryOrder->load('deliveryOrderItem.product.inventoryCoa', 'deliveryOrderItem.product.goodsDeliveryCoa');

        $date = $deliveryOrder->delivery_date ?? now()->toDateString();

        // Build journal entries for cost-of-goods-sold (goods delivery) and inventory credit
        $defaultInventoryCoa = app(\App\Services\AccountingSettings::class)->anyOf('inventory');
        $defaultGoodsDeliveryCoa = app(\App\Services\AccountingSettings::class)->anyOf('goods_in_transit');

        $debitTotals = [];
        $creditTotals = [];

        foreach ($deliveryOrder->deliveryOrderItem as $item) {
            $qtyDelivered = max(0, $item->quantity ?? 0);
            if ($qtyDelivered <= 0) {
                continue;
            }

            $product = $item->product;
            $costPerUnit = $product?->cost_price ?? 0;
            if ($costPerUnit <= 0) {
                continue;
            }

            $lineAmount = round($qtyDelivered * $costPerUnit, 2);
            if ($lineAmount <= 0) {
                continue;
            }

            $inventoryCoa = $product?->resolveInventoryCoaOrDefault() ?? $defaultInventoryCoa;
            $goodsDeliveryCoa = $product?->resolveGoodsDeliveryCoaOrDefault() ?? $defaultGoodsDeliveryCoa;

            if (!$inventoryCoa || !$goodsDeliveryCoa) {
                $settings = app(\App\Services\AccountingSettings::class);
                throw new \App\Exceptions\ProductCoaMissingException(
                    'Akun COA untuk produk "' . ($product?->name ?? 'tidak diketahui') . '" tidak ditemukan. '
                    . 'Diperlukan: Persediaan (' . ($inventoryCoa ? "\u{2713}" : ($settings->codes('inventory')[0] ?? '-')) . ') dan '
                    . 'Penyerahan Barang (' . ($goodsDeliveryCoa ? "\u{2713}" : ($settings->codes('goods_in_transit')[0] ?? '-')) . '). '
                    . 'Silakan konfigurasi COA produk tersebut (Master Data > Produk) sebelum mengirim Delivery Order.'
                );
            }

            $debitTotals[$goodsDeliveryCoa->id]['coa'] = $goodsDeliveryCoa;
            $debitTotals[$goodsDeliveryCoa->id]['amount'] = ($debitTotals[$goodsDeliveryCoa->id]['amount'] ?? 0) + $lineAmount;

            $creditTotals[$inventoryCoa->id]['coa'] = $inventoryCoa;
            $creditTotals[$inventoryCoa->id]['amount'] = ($creditTotals[$inventoryCoa->id]['amount'] ?? 0) + $lineAmount;
        }

        // Create journal entries
        if (!empty($debitTotals) && !empty($creditTotals)) {
            foreach ($debitTotals as $debitData) {
                \App\Models\JournalEntry::create([
                    'coa_id' => $debitData['coa']->id,
                    'date' => $date,
                    'reference' => $deliveryOrder->do_number,
                    'description' => 'Goods Delivery - Cost of Goods Sold for ' . $deliveryOrder->do_number,
                    'debit' => round($debitData['amount'], 2),
                    'credit' => 0,
                    'journal_type' => 'sales',
                    'source_type' => \App\Models\DeliveryOrder::class,
                    'source_id' => $deliveryOrder->id,
                    'cabang_id' => $deliveryOrder->cabang_id,
                ]);
            }

            foreach ($creditTotals as $creditData) {
                \App\Models\JournalEntry::create([
                    'coa_id' => $creditData['coa']->id,
                    'date' => $date,
                    'reference' => $deliveryOrder->do_number,
                    'description' => 'Goods Delivery - Inventory Reduction for ' . $deliveryOrder->do_number,
                    'debit' => 0,
                    'credit' => round($creditData['amount'], 2),
                    'journal_type' => 'sales',
                    'source_type' => \App\Models\DeliveryOrder::class,
                    'source_id' => $deliveryOrder->id,
                    'cabang_id' => $deliveryOrder->cabang_id,
                ]);
            }
        }
    }
}