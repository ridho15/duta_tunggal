<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\ReturnProduct;
use App\Models\ReturnProductItem;
use App\Models\StockMovement;
use App\Support\LineAmounts;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReturnProductService
{
    /** Ringkasan pemrosesan retur DO terakhir (Stock Adjustment, karantina, DO pengganti) untuk ditampilkan di notifikasi. */
    public static array $lastSummary = [];

    public function updateQuantityFromModel($returnProduct)
    {
        return DB::transaction(function () use ($returnProduct) {
            $returnProduct->loadMissing([
                'returnProductItem.product.inventoryCoa',
                'returnProductItem.product.goodsDeliveryCoa',
                'returnProductItem.product.unbilledPurchaseCoa',
                'returnProductItem.fromItemModel',
                'warehouse',
                'fromModel'
            ]);

            $isSalesReturn = ($returnProduct->from_model_type === \App\Models\DeliveryOrder::class);

            foreach ($returnProduct->returnProductItem as $returnProductItem) {
                $fromItemModel = $returnProductItem->fromItemModel;
                if ($fromItemModel) {
                    $defaultQuantity = (float) $fromItemModel->quantity;
                    $newQuantity = max(0.0, $defaultQuantity - (float) $returnProductItem->quantity);

                    // Update without triggering DeliveryOrderItem::updated (which rewrites original DO sales movements)
                    if ($fromItemModel instanceof \Illuminate\Database\Eloquent\Model) {
                        $fromItemModel::withoutEvents(function () use ($fromItemModel, $newQuantity) {
                            $fromItemModel->update([
                                'quantity' => $newQuantity,
                            ]);
                        });

                        if ($fromItemModel instanceof \App\Models\DeliveryOrderItem && $fromItemModel->sale_order_item_id) {
                            app(\App\Services\SaleOrderDeliveryProgress::class)
                                ->syncForSaleOrderItems([$fromItemModel->sale_order_item_id]);
                        }
                    } else {
                        $fromItemModel->update([
                            'quantity' => $newQuantity,
                        ]);
                    }
                }

                // Create stock movement if not already created
                // Retur DO (penjualan) dicatat terpusat oleh DeliveryOrderReturnProcessor (Stock Adjustment + jenis mutasi `do_return` + karantina);
                // alur ini hanya untuk retur pembelian (Purchase Receipt).
                $existingMovement = $isSalesReturn ? true : StockMovement::where('from_model_type', ReturnProduct::class)
                    ->where('from_model_id', $returnProduct->id)
                    ->where('meta->return_product_item_id', $returnProductItem->id)
                    ->first();

                if (! $existingMovement && (float) $returnProductItem->quantity > 0) {
                    $costPrice = (float) ($returnProductItem->product?->cost_price ?? 0);
                    $type = $isSalesReturn ? 'customer_return' : 'purchase_return';
                    $notes = ($isSalesReturn ? 'Retur Penjualan (Customer Return): ' : 'Retur Pembelian (Vendor Return): ') . $returnProduct->return_number;

                    // Barang retur kembali ke gudang/rak ASAL pengiriman per baris sumber (DO multi-gudang), bukan gudang header DO.
                    foreach ($this->resolveReturnDestinations($returnProductItem, $returnProduct) as $destination) {
                        StockMovement::create([
                            'product_id' => $returnProductItem->product_id,
                            'warehouse_id' => $destination['warehouse_id'],
                            'rak_id' => $destination['rak_id'],
                            'quantity' => $destination['quantity'],
                            'value' => round($destination['quantity'] * $costPrice, 2),
                            'type' => $type,
                            'reference_id' => $returnProduct->id,
                            'date' => now()->toDateString(),
                            'notes' => $notes,
                            'from_model_type' => ReturnProduct::class,
                            'from_model_id' => $returnProduct->id,
                            'meta' => [
                                'return_product_id' => $returnProduct->id,
                                'return_product_item_id' => $returnProductItem->id,
                                'from_model_type' => $returnProduct->from_model_type,
                                'from_model_id' => $returnProduct->from_model_id,
                            ],
                        ]);
                    }
                }
            }

            // Retur DO: stok kembali lewat Stock Adjustment (gudang sumber / karantina) dengan jenis mutasi `do_return`
            $processor = null;
            if ($isSalesReturn) {
                $processor = app(DeliveryOrderReturnProcessor::class);
                $processor->restock($returnProduct);
            }

            // Create reversing journal entries if not already created
            $this->createReversingJournalEntries($returnProduct);

            // If this is a sales return from a delivery order, adjust linked invoice if present
            if ($isSalesReturn && $returnProduct->fromModel instanceof DeliveryOrder) {
                $this->adjustLinkedSalesInvoice($returnProduct, $returnProduct->fromModel);
            }

            $returnProduct->update([
                'status' => 'approved'
            ]);

            // Check if all quantities are returned and close SO/DO partial if needed
            $this->handleReturnAction($returnProduct);

            // Tandai DO asal "sudah diretur" dan buat DO pengganti (DO…C)
            if ($processor) {
                $processor->afterApproval($returnProduct->fresh());
                static::$lastSummary = $processor->summary;
            }

            return $returnProduct;
        });
    }

    /**
     * Tujuan stok retur per item: untuk item DO, dibagi mengikuti baris sumber gudang pengiriman (urut, maks. sebesar qty
     * tiap sumber); sisanya / item lain ke gudang item atau gudang header retur.
     *
     * @return array<int, array{warehouse_id: int|null, rak_id: int|null, quantity: float}>
     */
    public function resolveReturnDestinations(ReturnProductItem $returnProductItem, ReturnProduct $returnProduct): array
    {
        $remaining = (float) $returnProductItem->quantity;
        $fromItem = $returnProductItem->fromItemModel;
        $destinations = [];

        if ($fromItem instanceof DeliveryOrderItem) {
            foreach ($fromItem->warehouseSources()->orderBy('id')->get() as $source) {
                if ($remaining <= 0.00001 || ! $source->warehouse_id) {
                    continue;
                }
                $take = min($remaining, (float) $source->quantity);
                if ($take > 0) {
                    $destinations[] = ['warehouse_id' => (int) $source->warehouse_id, 'rak_id' => $source->rak_id ? (int) $source->rak_id : null, 'quantity' => $take];
                    $remaining -= $take;
                }
            }
        }

        if ($remaining > 0.00001) {
            $destinations[] = [
                'warehouse_id' => $fromItem?->warehouse_id ?? $returnProduct->warehouse_id,
                'rak_id' => $returnProductItem->rak_id ?? $fromItem?->rak_id,
                'quantity' => $remaining,
            ];
        }

        return $destinations;
    }

    /**
     * Create reversing journal entries for approved ReturnProduct
     */
    protected function createReversingJournalEntries(ReturnProduct $returnProduct): void
    {
        $hasJournals = JournalEntry::where('source_type', ReturnProduct::class)
            ->where('source_id', $returnProduct->id)
            ->exists();

        if ($hasJournals) {
            return;
        }

        $isSalesReturn = ($returnProduct->from_model_type === \App\Models\DeliveryOrder::class);
        $isPurchaseReturn = ($returnProduct->from_model_type === \App\Models\PurchaseReceipt::class);

        if (! $isSalesReturn && ! $isPurchaseReturn) {
            return;
        }

        $date = now()->toDateString();
        $cabangId = $returnProduct->warehouse?->cabang_id ?? $returnProduct->fromModel?->cabang_id;
        $defaultInventoryCoa = app(\App\Services\AccountingSettings::class)->anyOf('inventory');
        $defaultGoodsDeliveryCoa = app(\App\Services\AccountingSettings::class)->anyOf('goods_in_transit')
            ?? app(\App\Services\AccountingSettings::class)->anyOf('cogs');

        if ($isSalesReturn) {
            $debitTotals = [];
            $creditTotals = [];
            $deliveryOrder = $returnProduct->fromModel;
            $isInvoiced = ($deliveryOrder instanceof DeliveryOrder) && $this->isDeliveryOrderInvoiced($deliveryOrder);

            foreach ($returnProduct->returnProductItem as $item) {
                $qty = max(0, (float) ($item->quantity ?? 0));
                $product = $item->product;
                $cost = (float) ($product?->cost_price ?? 0);
                $lineAmount = round($qty * $cost, 2);

                if ($lineAmount <= 0) {
                    continue;
                }

                $inventoryCoa = $product?->resolveInventoryCoaOrDefault() ?? $defaultInventoryCoa;
                $creditCoa = $isInvoiced
                    ? ($product?->resolveCogsCoaOrDefault() ?? app(\App\Services\AccountingSettings::class)->anyOf('cogs'))
                    : ($product?->resolveGoodsDeliveryCoaOrDefault() ?? $defaultGoodsDeliveryCoa);

                if ($inventoryCoa && $creditCoa) {
                    $debitTotals[$inventoryCoa->id] = [
                        'coa' => $inventoryCoa,
                        'amount' => ($debitTotals[$inventoryCoa->id]['amount'] ?? 0) + $lineAmount,
                    ];
                    $creditTotals[$creditCoa->id] = [
                        'coa' => $creditCoa,
                        'amount' => ($creditTotals[$creditCoa->id]['amount'] ?? 0) + $lineAmount,
                        'is_cogs' => $isInvoiced,
                    ];
                }
            }

            foreach ($debitTotals as $data) {
                JournalEntry::create([
                    'coa_id' => $data['coa']->id,
                    'date' => $date,
                    'reference' => $returnProduct->return_number,
                    'description' => 'Product Return - Inventory Restoration for ' . $returnProduct->return_number,
                    'debit' => round($data['amount'], 2),
                    'credit' => 0,
                    'journal_type' => 'sales',
                    'source_type' => ReturnProduct::class,
                    'source_id' => $returnProduct->id,
                    'cabang_id' => $cabangId,
                ]);
            }

            foreach ($creditTotals as $data) {
                $desc = !empty($data['is_cogs'])
                    ? 'Product Return - COGS Reversal for ' . $returnProduct->return_number
                    : 'Product Return - Delivery Reversal for ' . $returnProduct->return_number;

                JournalEntry::create([
                    'coa_id' => $data['coa']->id,
                    'date' => $date,
                    'reference' => $returnProduct->return_number,
                    'description' => $desc,
                    'debit' => 0,
                    'credit' => round($data['amount'], 2),
                    'journal_type' => 'sales',
                    'source_type' => ReturnProduct::class,
                    'source_id' => $returnProduct->id,
                    'cabang_id' => $cabangId,
                ]);
            }
        } elseif ($isPurchaseReturn) {
            $debitTotals = [];
            $creditTotals = [];

            foreach ($returnProduct->returnProductItem as $item) {
                $qty = max(0, (float) ($item->quantity ?? 0));
                $product = $item->product;
                $cost = (float) ($product?->cost_price ?? 0);
                $lineAmount = round($qty * $cost, 2);

                if ($lineAmount <= 0) {
                    continue;
                }

                $inventoryCoa = $product?->resolveInventoryCoaOrDefault() ?? $defaultInventoryCoa;
                $unbilledCoa = $product?->resolveUnbilledPurchaseCoaOrDefault();

                if ($inventoryCoa && $unbilledCoa) {
                    $debitTotals[$unbilledCoa->id] = [
                        'coa' => $unbilledCoa,
                        'amount' => ($debitTotals[$unbilledCoa->id]['amount'] ?? 0) + $lineAmount,
                    ];
                    $creditTotals[$inventoryCoa->id] = [
                        'coa' => $inventoryCoa,
                        'amount' => ($creditTotals[$inventoryCoa->id]['amount'] ?? 0) + $lineAmount,
                    ];
                }
            }

            foreach ($debitTotals as $data) {
                JournalEntry::create([
                    'coa_id' => $data['coa']->id,
                    'date' => $date,
                    'reference' => $returnProduct->return_number,
                    'description' => 'Product Return - Unbilled Purchase Reversal for ' . $returnProduct->return_number,
                    'debit' => round($data['amount'], 2),
                    'credit' => 0,
                    'journal_type' => 'purchase',
                    'source_type' => ReturnProduct::class,
                    'source_id' => $returnProduct->id,
                    'cabang_id' => $cabangId,
                ]);
            }

            foreach ($creditTotals as $data) {
                JournalEntry::create([
                    'coa_id' => $data['coa']->id,
                    'date' => $date,
                    'reference' => $returnProduct->return_number,
                    'description' => 'Product Return - Inventory Reduction for ' . $returnProduct->return_number,
                    'debit' => 0,
                    'credit' => round($data['amount'], 2),
                    'journal_type' => 'purchase',
                    'source_type' => ReturnProduct::class,
                    'source_id' => $returnProduct->id,
                    'cabang_id' => $cabangId,
                ]);
            }
        }
    }

    public function createReturnProduct($fromModel, $data)
    {
        return $fromModel->returnProduct()->create($data);
    }

    /**
     * D23: buat draft Return Product otomatis dari item yang ditandai reject saat "Konfirmasi Diterima" DO.
     * Mengikuti pola PurchaseReturnService::createFromQualityControl() — draft, tidak auto-approve, dan
     * dijaga tidak membuat duplikat (DeliveryOrder::returnProduct() adalah morphOne).
     *
     * @param  array<int, array{item_id?: int, qty_rejected?: float|string, notes?: string}>  $receivedItems
     */
    public function createFromDeliveryOrderReceiving(DeliveryOrder $deliveryOrder, array $receivedItems): ?ReturnProduct
    {
        $rejectedLines = collect($receivedItems)
            ->filter(fn ($it) => (float) ($it['qty_rejected'] ?? 0) > 0 && !empty($it['item_id']));

        if ($rejectedLines->isEmpty()) {
            return null;
        }

        return DB::transaction(function () use ($deliveryOrder, $rejectedLines) {
            $locked = DeliveryOrder::lockForUpdate()->find($deliveryOrder->id);
            $existing = $locked ? ReturnProduct::where('from_model_type', DeliveryOrder::class)
                ->where('from_model_id', $locked->id)
                ->first() : null;

            if ($existing) {
                Log::info('Return Product already exists for this Delivery Order, skip auto-create', [
                    'delivery_order_id' => $deliveryOrder->id,
                    'return_product_id' => $existing->id,
                ]);
                return $existing;
            }

            $returnProduct = ReturnProduct::create([
                'return_number' => $this->generateReturnNumber(DeliveryOrder::class, $deliveryOrder->id),
                'from_model_id' => $deliveryOrder->id,
                'from_model_type' => DeliveryOrder::class,
                // DO tanpa gudang header (mis. DO warisan): pakai gudang sumber pertama agar retur tetap punya gudang
                'warehouse_id' => $deliveryOrder->warehouse_id
                    ?? $rejectedLines->map(fn ($line) => DeliveryOrderItem::with('warehouseSources')->find($line['item_id'])?->warehouseSources->first()?->warehouse_id)->filter()->first(),
                'status' => 'draft',
                'reason' => 'Dibuat otomatis dari Konfirmasi Diterima DO ' . $deliveryOrder->do_number . ' (item ditandai reject saat penerimaan).',
                'return_action' => 'reduce_quantity_only',
            ]);

            foreach ($rejectedLines as $line) {
                $doItem = DeliveryOrderItem::with('warehouseSources')->find($line['item_id']);
                if (! $doItem) {
                    continue;
                }

                ReturnProductItem::create([
                    'return_product_id' => $returnProduct->id,
                    'from_item_model_id' => $doItem->id,
                    'from_item_model_type' => DeliveryOrderItem::class,
                    'product_id' => $doItem->product_id,
                    'quantity' => (float) $line['qty_rejected'],
                    'rak_id' => $doItem->warehouseSources->first()?->rak_id,
                    'condition' => 'damage',
                    'note' => $line['notes'] ?? 'Reject saat konfirmasi diterima di toko',
                ]);
            }

            return $returnProduct;
        });
    }

    /**
     * Nomor retur. Retur dari Delivery Order mengikuti DO asal: `<nomor DO>R` (UAT B13; bila sudah ada: R2, R3, …).
     * Sumber lain tetap `RN-YYYYMMDD-XXXX`.
     */
    public function generateReturnNumber(?string $fromModelType = null, int|string|null $fromModelId = null)
    {
        if ($fromModelType === DeliveryOrder::class && $fromModelId) {
            $doNumber = DeliveryOrder::withoutGlobalScopes()->whereKey($fromModelId)->value('do_number');
            if ($doNumber) {
                $candidate = $doNumber.'R';
                $n = 1;
                while (ReturnProduct::withTrashed()->where('return_number', $candidate)->exists()) {
                    $candidate = $doNumber.'R'.(++$n);
                }

                return $candidate;
            }
        }

        $date = now()->format('Ymd');
        $prefix = 'RN-' . $date . '-';

        do {
            $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = $prefix . $random;
            $exists = ReturnProduct::where('return_number', $candidate)->exists();
        } while ($exists);

        return $candidate;
    }

    /** Kalimat ringkasan hasil persetujuan retur DO untuk notifikasi (Stock Adjustment, karantina, DO pengganti). */
    public static function approvalSummaryText(): string
    {
        $summary = static::$lastSummary;
        if ($summary === []) {
            return '';
        }

        $parts = [];
        if (! empty($summary['adjustments'])) {
            $parts[] = 'Tercatat di Stock Adjustment '.implode(', ', $summary['adjustments']).'.';
        }
        if (($summary['quarantine_qty'] ?? 0) > 0) {
            $parts[] = rtrim(rtrim(number_format($summary['quarantine_qty'], 2, ',', '.'), '0'), ',').' unit rusak masuk gudang karantina (bukan stok jual).';
        }
        if (! empty($summary['replacement'])) {
            $parts[] = 'DO pengganti '.$summary['replacement'].' dibuat'.($summary['replacement_allocated'] ? ' dan menunggu konfirmasi gudang.' : ' sebagai draf — stok belum cukup, lengkapi sumber gudangnya.');
        }

        return implode(' ', $parts);
    }

    /** Format nomor yang sah: RN-YYYYMMDD-XXXX, atau untuk retur DO: `<nomor DO asal>R` / `R2`, … */
    public function isValidReturnNumber(string $number, ?string $fromModelType = null, int|string|null $fromModelId = null): bool
    {
        if (preg_match('/^RN-\d{8}-\d{4}$/', $number)) {
            return true;
        }

        if ($fromModelType === DeliveryOrder::class && $fromModelId) {
            $doNumber = DeliveryOrder::withoutGlobalScopes()->whereKey($fromModelId)->value('do_number');

            return $doNumber && preg_match('/^'.preg_quote($doNumber, '/').'R\d*$/', $number) === 1;
        }

        return false;
    }

    /**
     * Handle different return actions based on user selection
     */
    private function handleReturnAction($returnProduct)
    {
        $action = $returnProduct->return_action ?? 'reduce_quantity_only';

        switch ($action) {
            case 'reduce_quantity_only':
                // Only reduce quantity, don't close anything automatically
                break;

            case 'close_do_partial':
                // Force close DO regardless of remaining quantity
                $this->forceCloseDeliveryOrder($returnProduct);
                break;

            case 'close_so_complete':
                // Force close both DO and SO
                $this->forceCloseDeliveryOrder($returnProduct);
                $this->forceCloseRelatedSalesOrders($returnProduct);
                break;
        }
    }

    /**
     * Force close delivery order regardless of remaining quantity
     */
    private function forceCloseDeliveryOrder($returnProduct)
    {
        $fromModel = $returnProduct->fromModel;

        if ($fromModel instanceof \App\Models\DeliveryOrder) {
            $fromModel->update([
                'status' => 'completed',
                'notes' => ($fromModel->notes ? $fromModel->notes . ' | ' : '') . 'Force closed due to return action (RN: ' . $returnProduct->return_number . ')'
            ]);
        }
    }

    /**
     * Force close related sales orders
     */
    private function forceCloseRelatedSalesOrders($returnProduct)
    {
        $fromModel = $returnProduct->fromModel;

        if ($fromModel instanceof \App\Models\DeliveryOrder) {
            foreach ($fromModel->salesOrders as $saleOrder) {
                if (in_array($saleOrder->status, ['confirmed', 'approved'])) {
                    // Instead of direct 'completed', use 'request_close' for approval workflow
                    $saleOrder->update([
                        'status' => 'request_close',
                        'request_close_by' => $returnProduct->created_by,
                        'request_close_at' => now(),
                        'reason_close' => 'Force close requested due to return action (RN: ' . $returnProduct->return_number . ')'
                    ]);

                    // Log the request close action
                    Log::info('SO close requested due to return action', [
                        'so_number' => $saleOrder->so_number,
                        'return_number' => $returnProduct->return_number,
                        'requested_by' => $returnProduct->created_by,
                        'reason' => 'Return action: close_so_complete'
                    ]);
                }
            }
        }
    }

    /**
     * Check if all quantities are returned and close SO/DO partial if needed (legacy method)
     */
    public function checkAndClosePartialOrders($returnProduct)
    {
        $fromModel = $returnProduct->fromModel;

        if ($fromModel instanceof \App\Models\DeliveryOrder) {
            $this->checkAndCloseDeliveryOrderPartial($returnProduct, $fromModel);
        } elseif ($fromModel instanceof \App\Models\PurchaseReceipt) {
            // Handle purchase receipt if needed
            $this->checkAndClosePurchaseReceiptPartial($returnProduct, $fromModel);
        }
    }

    /**
     * Check and close Delivery Order partial if all items are fully returned
     */
    private function checkAndCloseDeliveryOrderPartial($returnProduct, $deliveryOrder)
    {
        $allItemsReturned = true;

        foreach ($deliveryOrder->deliveryOrderItem as $doItem) {
            if ($doItem->quantity > 0) {
                $allItemsReturned = false;
                break;
            }
        }

        if ($allItemsReturned) {
            // Close delivery order as completed due to full return
            $deliveryOrder->update([
                'status' => 'completed',
                'notes' => ($deliveryOrder->notes ? $deliveryOrder->notes . ' | ' : '') . 'Closed due to full return (RN: ' . $returnProduct->return_number . ')'
            ]);

            // Also close related sales orders if all delivery orders are completed
            $this->checkAndCloseRelatedSalesOrders($deliveryOrder);
        }
    }

    /**
     * Check and close related Sales Orders if all their delivery orders are completed
     */
    private function checkAndCloseRelatedSalesOrders($deliveryOrder)
    {
        foreach ($deliveryOrder->salesOrders as $saleOrder) {
            $allDOCompleted = true;

            foreach ($saleOrder->deliveryOrder as $do) {
                if ($do->status !== 'completed') {
                    $allDOCompleted = false;
                    break;
                }
            }

            if ($allDOCompleted && in_array($saleOrder->status, ['confirmed', 'approved'])) {
                $saleOrder->update([
                    'status' => 'completed',
                    'reason_close' => 'All delivery orders completed due to returns'
                ]);
            }
        }
    }

    /**
     * Check and close Purchase Receipt partial if all items are fully returned
     */
    private function checkAndClosePurchaseReceiptPartial($returnProduct, $purchaseReceipt)
    {
        // Implement logic for purchase receipt if needed
        // Similar to delivery order logic but for purchase receipts
    }

    /**
     * Memeriksa apakah Delivery Order sudah diterbitkan Invoice Penjualan aktif.
     */
    public function isDeliveryOrderInvoiced(DeliveryOrder $deliveryOrder): bool
    {
        // 1. Direct match on delivery_orders JSON column in non-cancelled invoices
        $invoicedViaDoList = Invoice::where('from_model_type', \App\Models\SaleOrder::class)
            ->whereNotIn('status', [Invoice::STATUS_CANCELLED])
            ->where(function ($q) use ($deliveryOrder) {
                $q->whereJsonContains('delivery_orders', (int) $deliveryOrder->id)
                  ->orWhereJsonContains('delivery_orders', (string) $deliveryOrder->id);
            })
            ->exists();

        if ($invoicedViaDoList) {
            return true;
        }

        // 2. Or check if the linked SO has an active posted invoice without explicit DOs list
        if ($deliveryOrder->sale_order_id) {
            $hasPostedSoInvoice = Invoice::where('from_model_type', \App\Models\SaleOrder::class)
                ->where('from_model_id', $deliveryOrder->sale_order_id)
                ->whereNotIn('status', [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED])
                ->where(function ($q) use ($deliveryOrder) {
                    $q->whereNull('delivery_orders')
                      ->orWhereJsonContains('delivery_orders', (int) $deliveryOrder->id)
                      ->orWhereJsonContains('delivery_orders', (string) $deliveryOrder->id);
                })
                ->exists();

            if ($hasPostedSoInvoice) {
                return true;
            }
        }

        return false;
    }

    /**
     * Menyesuaikan kuantitas dan total pada Invoice Penjualan yang terhubung saat DO diretur.
     */
    public function adjustLinkedSalesInvoice(ReturnProduct $returnProduct, DeliveryOrder $deliveryOrder): ?Invoice
    {
        $invoice = Invoice::whereIn('from_model_type', [\App\Models\SaleOrder::class, \App\Models\DeliveryOrder::class])
            ->whereNotIn('status', [Invoice::STATUS_CANCELLED])
            ->where(function ($q) use ($deliveryOrder) {
                $q->whereJsonContains('delivery_orders', (int) $deliveryOrder->id)
                  ->orWhereJsonContains('delivery_orders', (string) $deliveryOrder->id)
                  ->orWhere(function ($doQ) use ($deliveryOrder) {
                      $doQ->where('from_model_type', \App\Models\DeliveryOrder::class)
                          ->where('from_model_id', $deliveryOrder->id);
                  });
                if ($deliveryOrder->sale_order_id) {
                    $q->orWhere(function ($soQ) use ($deliveryOrder) {
                        $soQ->where('from_model_id', $deliveryOrder->sale_order_id)
                            ->whereNull('delivery_orders');
                    });
                }
            })
            ->orderByRaw("CASE WHEN status = '" . Invoice::STATUS_DRAFT . "' THEN 0 ELSE 1 END")
            ->first();

        if (! $invoice) {
            return null;
        }

        // Jangan pernah memodifikasi invoice yang sudah diposting atau lunas secara in-place!
        // Hanya invoice berstatus draft yang aman untuk disesuaikan kuantitasnya sebelum posting GL.
        // Jika invoice sudah posted/sent/paid/partially_paid/overdue, retur finansial wajib diproses
        // melalui modul Retur Penjualan (Customer Return / Credit Note).
        if ($invoice->status !== Invoice::STATUS_DRAFT) {
            Log::info("adjustLinkedSalesInvoice skipped: Invoice {$invoice->invoice_number} is in '{$invoice->status}' state. In-place modification skipped to protect GL and sub-ledger integrity.");
            return $invoice;
        }

        $invoice->loadMissing('invoiceItem');
        $invoiceChanged = false;

        foreach ($returnProduct->returnProductItem as $retItem) {
            $retQty = (float) $retItem->quantity;
            if ($retQty <= 0) {
                continue;
            }

            // Match invoice item by product_id
            $invItem = $invoice->invoiceItem->firstWhere('product_id', $retItem->product_id);
            if ($invItem) {
                $newQty = max(0.0, (float) $invItem->quantity - $retQty);
                $rate = (float) ($invoice->ppn_rate ?? 0);
                $type = ($invoice->tipe_pajak ?? 'None') === 'None' ? 'Non Pajak' : $invoice->tipe_pajak;
                $amounts = LineAmounts::calculate($newQty, (float) $invItem->price, (float) $invItem->discount, $rate, $type);

                $invItem->update([
                    'quantity' => $newQty,
                    'subtotal' => $amounts['dpp'],
                    'tax_amount' => $amounts['ppn'],
                    'total' => $amounts['total'],
                ]);
                $invoiceChanged = true;
            }
        }

        if ($invoiceChanged) {
            $newSubtotal = round((float) $invoice->invoiceItem()->sum('subtotal'), 2);
            $otherFee = (float) $invoice->getOtherFeeTotalAttribute();
            $newTotal = round((float) $invoice->invoiceItem()->sum('total') + $otherFee, 2);

            $updatePayload = [
                'subtotal' => $newSubtotal,
                'dpp' => $newSubtotal,
                'total' => $newTotal,
            ];

            if ($newTotal <= 0.05) {
                $updatePayload['status'] = Invoice::STATUS_CANCELLED;
            }

            // Updating header triggers InvoiceObserver::updated (financialChanged)
            // which automatically deletes old journals, updates AccountReceivable,
            // and reposts new balanced journals!
            $invoice->update($updatePayload);
        }

        return $invoice;
    }
}
