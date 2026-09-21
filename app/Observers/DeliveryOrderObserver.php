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

        // Jika status berubah ke 'approved', buat stock reservations
        if ($originalStatus !== 'approved' && $newStatus === 'approved') {
            $this->handleApprovedStatus($deliveryOrder);
        }

        // Jika status berubah ke 'sent', lepaskan stock reservations
        if ($originalStatus !== 'sent' && $newStatus === 'sent') {
            $this->handleReservationReleaseStatus($deliveryOrder);
        }

        // Jika status berubah ke 'completed', posting jurnal dan update related sales orders
        if ($originalStatus !== 'completed' && $newStatus === 'completed') {
            $this->handleCompletedStatus($deliveryOrder);
        }

        // Jika status sudah 'completed' dan ada perubahan quantity, update journal entries
        if ($newStatus === 'completed' && $this->hasQuantityChanges($deliveryOrder)) {
            $this->handleQuantityUpdateAfterCompleted($deliveryOrder);
        }

        // T2.1 (flag stock.ledger): DO ditutup/ditolak → reservasi DO dilepas lewat buku besar.
        if ($deliveryOrder->wasChanged('status') && in_array($newStatus, ['closed', 'reject'], true) && $this->ledgerEnabled()) {
            app(\App\Services\StockReservationLedger::class)->releaseForDeliveryOrder($deliveryOrder->id, "DO {$deliveryOrder->do_number} {$newStatus}");
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
     * DO dipulihkan dari soft-delete: kuantitasnya kembali terikat ke SO.
     */
    public function restored(DeliveryOrder $deliveryOrder): void
    {
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
     * Handle when Delivery Order status becomes 'approved'
     * Move qty_available to qty_reserved by creating stock reservations
     */
    protected function handleApprovedStatus(DeliveryOrder $deliveryOrder): void
    {
        Log::info('DeliveryOrderObserver: Handling approved status', [
            'delivery_order_id' => $deliveryOrder->id,
            'do_number' => $deliveryOrder->do_number,
        ]);

        // T2.1 (flag stock.ledger): reservasi DO lewat buku besar (idempoten, tercatat, kuantitas ≤ item DO).
        if ($this->ledgerEnabled()) {
            app(\App\Services\DeliveryOrderReservations::class)->sync($deliveryOrder, "DO {$deliveryOrder->do_number} disetujui (Siap Kirim)");

            return;
        }

        $deliveryOrder->loadMissing('deliveryOrderItem.warehouseSources');

        foreach ($deliveryOrder->deliveryOrderItem as $item) {
            $quantity = max(0, $item->quantity ?? 0);
            if ($quantity <= 0) {
                continue;
            }

            $sources = $item->warehouseSources;
            if ($sources->isNotEmpty()) {
                foreach ($sources as $source) {
                    $sourceQty = max(0, (float) ($source->quantity ?? 0));
                    $sourceWarehouseId = $source->warehouse_id;

                    if ($sourceQty <= 0 || !$sourceWarehouseId) {
                        Log::error('DeliveryOrderObserver: invalid source warehouse configuration', [
                            'delivery_order_id' => $deliveryOrder->id,
                            'item_id' => $item->id,
                            'product_id' => $item->product_id,
                        ]);
                        throw new \Exception('Warehouse source configuration is required for stock reservation');
                    }

                    StockReservation::create([
                        'sale_order_id' => $item->saleOrderItem->sale_order_id ?? null,
                        'product_id' => $item->product_id,
                        'warehouse_id' => $sourceWarehouseId,
                        'rak_id' => $source->rak_id,
                        'quantity' => $sourceQty,
                        'delivery_order_id' => $deliveryOrder->id,
                    ]);
                }

                continue;
            }

            // Buat stock reservation untuk memindahkan available ke reserved
            $warehouseId = $deliveryOrder->warehouse_id ?? $item->warehouse_id;
            if (!$warehouseId) {
                Log::error('DeliveryOrderObserver: warehouse_id is null for delivery order item', [
                    'delivery_order_id' => $deliveryOrder->id,
                    'item_id' => $item->id,
                    'product_id' => $item->product_id,
                ]);
                throw new \Exception('Warehouse ID is required for stock reservation');
            }
            StockReservation::create([
                'sale_order_id' => $item->saleOrderItem->sale_order_id ?? null,
                'product_id' => $item->product_id,
                'warehouse_id' => $warehouseId,
                'rak_id' => $item->rak_id,
                'quantity' => $quantity,
                'delivery_order_id' => $deliveryOrder->id,
            ]);

            // Note: Stock movement 'sales' will be created when status becomes 'completed'
        }
    }

    /**
     * Handle when Delivery Order enters the reservation-release stage.
     * Create stock movements to reduce qty_available (barang sudah keluar gudang).
     * NOTE: StockReservation is NOT deleted - qty_reserved remains for tracking
     * until delivery is completed.
     */
    protected function handleReservationReleaseStatus(DeliveryOrder $deliveryOrder): void
    {
        Log::info('DeliveryOrderObserver: Handling reservation release', [
            'delivery_order_id' => $deliveryOrder->id,
            'do_number' => $deliveryOrder->do_number,
        ]);

        // =========================================================
        // MODIFIKASI: Buat StockMovement untuk mengurangi qty_available
        // saat barang mulai dikirim (status = 'sent')
        // =========================================================
        $this->createStockMovementsForShippingStart($deliveryOrder);

        // T2.1 (flag stock.ledger): reservasi DO DIKONSUMSI saat barang berangkat — gerakan stok fisik sudah dibuat di atas.
        // Tanpa ini reservasi tidak pernah dilepas dan stok bebas terus menyusut (reservasi yatim, temuan X1).
        if ($this->ledgerEnabled()) {
            app(\App\Services\StockReservationLedger::class)->releaseForDeliveryOrder(
                $deliveryOrder->id,
                "DO {$deliveryOrder->do_number} dikirim — reservasi dikonsumsi",
                \App\Models\StockReservationEvent::CONSUMED
            );
        }

        // =========================================================
        // JANGAN hapus StockReservation - biarkan untuk tracking
        // qty_reserved tetap ada sampai delivery selesai
        // Ini memastikan free_qty tidak berubah secara tidak sengaja
        // =========================================================

        // Progres SO (cache delivered_quantity + status SO) dihitung ulang oleh satu service.
        $this->syncDeliveryProgress($deliveryOrder);
    }

    /**
     * Buat StockMovement untuk mengurangi qty_available
     * saat DO mulai dikirim (status = 'sent')
     *
     * Ini memastikan:
     * - qty_available (stok fisik) BERKURANG saat barang meninggalkan gudang
     * - qty_reserved TETAP ADA (untuk tracking sampai delivery selesai)
     * - free_qty = qty_available - qty_reserved (tidak berubah secara tidak sengaja)
     */
    protected function createStockMovementsForShippingStart(DeliveryOrder $deliveryOrder): void
    {
        $deliveryOrder->load('deliveryOrderItem.product', 'deliveryOrderItem.warehouseSources');

        $date = $deliveryOrder->delivery_date ?? now()->toDateString();

        foreach ($deliveryOrder->deliveryOrderItem as $item) {
            $qtyToShip = max(0, $item->quantity ?? 0);
            if ($qtyToShip <= 0) {
                continue;
            }

            $product = $item->product;
            if (!$product) {
                continue;
            }

            $productService = app(\App\Services\ProductService::class);

            // Handle multi-warehouse sources
            $sources = $item->warehouseSources;
            if ($sources->isNotEmpty()) {
                foreach ($sources as $source) {
                    $sourceQty = max(0, (float) ($source->quantity ?? 0));
                    if ($sourceQty <= 0 || !$source->warehouse_id) {
                        continue;
                    }

                    // T2.1 (flag stock.ledger): idempoten — jangan memotong stok dua kali untuk kuantitas yang sudah keluar
                    // (mis. DO gagal kirim lalu dijadwalkan ulang).
                    if ($this->ledgerEnabled()) {
                        $sourceQty = max(0, $sourceQty - \App\Services\DeliveryShipments::netShipped($item, (int) $source->warehouse_id, $source->rak_id));
                        if ($sourceQty <= 0) {
                            continue;
                        }
                    }

                    $productService->createStockMovement(
                        product_id: $product->id,
                        warehouse_id: $source->warehouse_id,
                        quantity: $sourceQty,
                        type: 'sales',
                        date: $date,
                        notes: "Shipping start for DO {$deliveryOrder->do_number}",
                        rak_id: $source->rak_id,
                        fromModel: $item,
                        value: $product->cost_price * $sourceQty,
                        meta: [
                            'delivery_status' => 'sent',
                            'shipping_start' => true,
                            'source' => 'delivery_order_observer',
                        ]
                    );
                }
                continue;
            }

            // Single warehouse
            if (!$deliveryOrder->warehouse_id) {
                continue;
            }

            if ($this->ledgerEnabled()) {
                $qtyToShip = max(0, $qtyToShip - \App\Services\DeliveryShipments::netShipped($item, (int) $deliveryOrder->warehouse_id, $item->rak_id));
                if ($qtyToShip <= 0) {
                    continue;
                }
            }

            $productService->createStockMovement(
                product_id: $product->id,
                warehouse_id: $deliveryOrder->warehouse_id,
                quantity: $qtyToShip,
                type: 'sales',
                date: $date,
                notes: "Shipping start for DO {$deliveryOrder->do_number}",
                rak_id: $item->rak_id,
                fromModel: $item,
                value: $product->cost_price * $qtyToShip,
                meta: [
                    'delivery_status' => 'sent',
                    'shipping_start' => true,
                    'source' => 'delivery_order_observer',
                ]
            );
        }
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

        $this->createJournalEntriesForDelivery($deliveryOrder);

        // =========================================================
        // MODIFIKASI: Skip StockMovement creation here
        // StockMovement sudah dibuat saat status berubah ke 'sent'
        // di handleReservationReleaseStatus()
        // =========================================================

        // Progres SO: hitung ulang delivered_quantity dan turunkan status SO dari kuantitas
        // (approved -> partially_delivered -> completed) lewat satu service, bukan dari event DO.
        $this->syncDeliveryProgress($deliveryOrder);

        // Terbitkan invoice otomatis khusus untuk item dan kuantitas yang dikirim pada Delivery Order ini
        $this->createInvoiceForCompletedDeliveryOrder($deliveryOrder);
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
            ->where('status', '!=', 'canceled')
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
        $invoiceNumber = (new \App\Services\InvoiceService())->generateInvoiceNumber();

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
        if ($this->ledgerEnabled()) {
            app(\App\Services\StockReservationLedger::class)->releaseForDeliveryOrder($deliveryOrder->id, "DO {$deliveryOrder->do_number} dihapus");
        } else {
            $reservations = StockReservation::where('delivery_order_id', $deliveryOrder->id)->get();
            foreach ($reservations as $reservation) {
                $reservation->delete();
            }
        }

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
        // Load delivery order items with related data
        $deliveryOrder->load('deliveryOrderItem.product.inventoryCoa', 'deliveryOrderItem.product.goodsDeliveryCoa');

        $date = $deliveryOrder->delivery_date ?? now()->toDateString();

        // Build journal entries for cost-of-goods-sold (goods delivery) and inventory credit
        $defaultInventoryCoa = \App\Models\ChartOfAccount::whereIn('code', ['1140.10', '1140.01'])->first();
        $defaultGoodsDeliveryCoa = \App\Models\ChartOfAccount::whereIn('code', ['1140.20', '1180.10'])->first();

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
                throw new \Exception(
                    'Akun COA untuk produk "' . ($product?->name ?? 'tidak diketahui') . '" tidak ditemukan. '
                    . 'Diperlukan: Persediaan (' . ($inventoryCoa ? '\u2713' : '1140.10') . ') dan '
                    . 'Penyerahan Barang (' . ($goodsDeliveryCoa ? '\u2713' : '1140.20') . '). '
                    . 'Silakan konfigurasi COA produk tersebut sebelum mengirim Delivery Order.'
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