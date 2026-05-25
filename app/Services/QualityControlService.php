<?php

namespace App\Services;

use App\Http\Controllers\HelperController;
use App\Models\PurchaseOrder;
use App\Models\QualityControl;
use App\Models\JournalEntry;
use App\Models\StockMovement;
use App\Models\ChartOfAccount;
use App\Services\ReturnProductService;
use App\Support\JournalCurrencyAmountResolver;
use App\Support\OrderRequestQuantityLock;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QualityControlService
{
    protected static $coaCache = [];

    public function resolveQcJournalUnitPriceIdr(mixed $unitPrice, ?int $currencyId = null, ?float $historicalRate = null): array
    {
        return JournalCurrencyAmountResolver::resolve($unitPrice, $currencyId, $historicalRate);
    }

    public function generateQcNumber()
    {
        $date = now()->format('Ymd');
        $prefix = 'QC-' . $date . '-';

        do {
            $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = $prefix . $random;
            $exists = QualityControl::where('qc_number', $candidate)->exists();
        } while ($exists);

        return $candidate;
    }

    public function generateQcManufactureNumber()
    {
        $date = now()->format('Ymd');
        $prefix = 'QC-M-' . $date . '-';

        do {
            $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = $prefix . $random;
            $exists = QualityControl::where('qc_number', $candidate)->exists();
        } while ($exists);

        return $candidate;
    }

    private function purchaseOrderItemReceiptLimitForQualityControl(QualityControl $qualityControl, int $purchaseOrderItemId): array
    {
        $excludeReceiptItemId = null;

        if (filled($qualityControl->qc_number)) {
            $excludeReceiptItemId = \App\Models\PurchaseReceiptItem::where('purchase_order_item_id', $purchaseOrderItemId)
                ->whereHas('purchaseReceipt', function ($query) use ($qualityControl) {
                    $query->where('notes', 'like', '%' . $qualityControl->qc_number . '%');
                })
                ->value('id');
        }

        return OrderRequestQuantityLock::purchaseOrderItemReceiptLimit(
            $purchaseOrderItemId,
            $excludeReceiptItemId ? (int) $excludeReceiptItemId : null
        );
    }

    /**
     * Create a Quality Control record from a PurchaseReceiptItem.
     * This does not create receipts immediately; when QC is completed, a receipt
     * will be generated from the QC result.
     *
     * @param \App\Models\PurchaseReceiptItem $purchaseReceiptItem
     * @param array $data
     * @return \App\Models\QualityControl
     */
    /**
     * @deprecated QC should originate from PurchaseOrderItem only.
     * This wrapper redirects legacy calls to the PO-item flow so that
     * PurchaseReceipt remains purely a record.
     */
    public function createQCFromPurchaseReceiptItem($purchaseReceiptItem, $data)
    {
        Log::warning('createQCFromPurchaseReceiptItem called; redirecting to PO-item QC');
        $poItem = $purchaseReceiptItem->purchaseOrderItem;
        if (! $poItem) {
            throw new \Exception('Cannot create QC from receipt without associated PurchaseOrderItem');
        }
        // do not modify receipt item; receipt is purely record now
        return $this->createQCFromPurchaseOrderItem($poItem, array_merge([
            'warehouse_id' => $purchaseReceiptItem->warehouse_id ?? null,
            'rak_id' => $purchaseReceiptItem->rak_id ?? null,
            'quantity_received' => $purchaseReceiptItem->qty_received ?: null,
            'passed_quantity' => $purchaseReceiptItem->qty_accepted ?: null,
            'rejected_quantity' => $purchaseReceiptItem->qty_rejected ?: 0,
        ], $data));
    }

    /**
     * Create a Quality Control record from a PurchaseOrderItem.
     * This creates QC directly from PO item without requiring a receipt first.
     *
     * @param \App\Models\PurchaseOrderItem $purchaseOrderItem
     * @param array $data
     * @return \App\Models\QualityControl
     */
    public function createQCFromPurchaseOrderItem($purchaseOrderItem, $data)
    {
        $limit = OrderRequestQuantityLock::purchaseOrderItemReceiptLimit((int) $purchaseOrderItem->id);

        // Validate passed_quantity doesn't exceed ordered quantity
        $passedQuantity = $data['passed_quantity'] ?? min((float) $purchaseOrderItem->quantity, (float) $limit['remaining_accepted']);
        $rejectedQuantity = $data['rejected_quantity'] ?? 0;
        if ($passedQuantity > $purchaseOrderItem->quantity) {
            throw new \Exception("QC passed quantity ({$passedQuantity}) cannot exceed ordered quantity ({$purchaseOrderItem->quantity}) in purchase order.");
        }

        $inspectedQuantity = (float) $passedQuantity + (float) $rejectedQuantity;
        $quantityReceived = $data['quantity_received'] ?? $inspectedQuantity;
        if ((float) $passedQuantity > (float) $quantityReceived) {
            throw new \Exception("QC passed quantity ({$passedQuantity}) tidak boleh melebihi Qty Received ({$quantityReceived}).");
        }

        if ($inspectedQuantity > (float) $quantityReceived) {
            throw new \Exception("Total QC passed dan rejected ({$inspectedQuantity}) tidak boleh melebihi Qty Received ({$quantityReceived}).");
        }
        if ($inspectedQuantity > $limit['remaining_received']) {
            throw new \Exception("Total QC passed dan rejected ({$inspectedQuantity}) tidak boleh melebihi sisa PO/Order Request ({$limit['remaining_received']}).");
        }
        if ((float) $passedQuantity > $limit['remaining_accepted']) {
            throw new \Exception("QC passed quantity ({$passedQuantity}) tidak boleh melebihi sisa PO/Order Request ({$limit['remaining_accepted']}).");
        }

        $qualityControl = QualityControl::create([
            'qc_number'         => $this->generateQcNumber(),
            'passed_quantity'   => $passedQuantity,
            'rejected_quantity' => $rejectedQuantity,
            'quantity_received' => $quantityReceived,
            'notes'             => $data['notes'] ?? null,
            'status'            => 0,
            'inspected_by'      => $data['inspected_by'] ?? null,
            'warehouse_id'      => $data['warehouse_id'] ?? $purchaseOrderItem->purchaseOrder->warehouse_id,
            'product_id'        => $purchaseOrderItem->product_id,
            'rak_id'            => $data['rak_id'] ?? null,
            'from_model_type'   => \App\Models\PurchaseOrderItem::class,
            'from_model_id'     => $purchaseOrderItem->id,
            'cabang_id'         => $purchaseOrderItem->purchaseOrder->cabang_id ?? Auth::user()?->cabang_id,
        ]);

        return $qualityControl;
    }

    /**
     * Create a Quality Control record from a Production.
     * This creates QC automatically when production is finished.
     *
     * @param \App\Models\Production $production
     * @return \App\Models\QualityControl
     */
    public function createQCFromProduction($production)
    {
        $manufacturingOrder = $production->manufacturingOrder;
        $product = $manufacturingOrder->productionPlan->product;

        $qualityControl = QualityControl::create([
            'qc_number' => $this->generateQcManufactureNumber(),
            'passed_quantity' => $production->quantity_produced ?? $manufacturingOrder->productionPlan->quantity,
            'rejected_quantity' => 0,
            'status' => 0, // Not processed yet
            'inspected_by' => Auth::id() ?? 1, // Default to admin user if no auth
            'warehouse_id' => $production->warehouse_id ?? $manufacturingOrder->productionPlan->warehouse_id,
            'rak_id' => $production->rak_id ?? null,
            'product_id' => $product->id,
            'from_model_type' => \App\Models\Production::class,
            'from_model_id' => $production->id,
            'date_send_stock' => Carbon::now(),
            'cabang_id' => $manufacturingOrder->cabang_id ?? Auth::user()?->cabang_id,
        ]);

        return $qualityControl;
    }

    public function completeQualityControl($qualityControl, $data)
    {
        $productService = app(ProductService::class);

        $qualityControl->loadMissing(['fromModel', 'product']);

        if ($qualityControl->from_model_type === 'App\\Models\\Production') {
            $qualityControl->fromModel->loadMissing('manufacturingOrder.productionPlan');
        }

        $updatedAttributes = [];
        foreach (['passed_quantity', 'rejected_quantity', 'quantity_received', 'warehouse_id', 'rak_id', 'reason_reject', 'inspected_by'] as $field) {
            if (array_key_exists($field, $data)) {
                $updatedAttributes[$field] = $data[$field];
            }
        }

        if (!empty($updatedAttributes)) {
            $qualityControl->fill($updatedAttributes);
        }

        if ($qualityControl->from_model_type === 'App\Models\Production') {
            $production = $qualityControl->fromModel;
            $targetQuantity = (float) ($production?->quantity_produced ?? $production?->manufacturingOrder?->productionPlan?->quantity ?? 0);
            $passedQuantity = (float) ($qualityControl->passed_quantity ?? 0);
            $rejectedQuantity = (float) ($qualityControl->rejected_quantity ?? 0);

            if ($passedQuantity < 0 || $rejectedQuantity < 0) {
                throw new \Exception('Passed quantity dan rejected quantity tidak boleh bernilai negatif.');
            }

            if ($targetQuantity > 0 && ($passedQuantity + $rejectedQuantity) > $targetQuantity) {
                throw new \Exception("Total passed dan rejected ({$passedQuantity} + {$rejectedQuantity}) tidak boleh melebihi quantity produksi ({$targetQuantity}).");
            }
        }

        if ($qualityControl->from_model_type === 'App\Models\PurchaseOrderItem') {
            $purchaseOrderItem = $qualityControl->fromModel;
            if ($purchaseOrderItem) {
                $limit = $this->purchaseOrderItemReceiptLimitForQualityControl($qualityControl, (int) $purchaseOrderItem->id);
                $inspectedQuantity = (float) $qualityControl->passed_quantity + (float) $qualityControl->rejected_quantity;
                $quantityReceived = (float) ($qualityControl->quantity_received ?? 0);

                if ((float) $qualityControl->passed_quantity > $quantityReceived) {
                    throw new \Exception("QC passed quantity ({$qualityControl->passed_quantity}) tidak boleh melebihi Qty Received ({$quantityReceived}).");
                }

                if ($inspectedQuantity > $quantityReceived) {
                    throw new \Exception("Total QC passed dan rejected ({$inspectedQuantity}) tidak boleh melebihi Qty Received ({$quantityReceived}).");
                }

                if ($inspectedQuantity > $limit['remaining_received']) {
                    throw new \Exception("Total QC passed dan rejected ({$inspectedQuantity}) tidak boleh melebihi sisa PO/Order Request ({$limit['remaining_received']}).");
                }

                if ((float) $qualityControl->passed_quantity > $limit['remaining_accepted']) {
                    throw new \Exception("QC passed quantity ({$qualityControl->passed_quantity}) tidak boleh melebihi sisa PO/Order Request ({$limit['remaining_accepted']}).");
                }
            }
        }

        if ($qualityControl->isDirty()) {
            $qualityControl->save();
        }

        // Validate QC passed quantity against receipt quantity for PurchaseReceiptItem
        if ($qualityControl->from_model_type === 'App\Models\PurchaseReceiptItem') {
            $purchaseReceiptItem = $qualityControl->fromModel;
            if ($purchaseReceiptItem && $qualityControl->passed_quantity > $purchaseReceiptItem->qty_received) {
                throw new \Exception("QC passed quantity ({$qualityControl->passed_quantity}) cannot exceed received quantity ({$purchaseReceiptItem->qty_received}) in purchase receipt.");
            }
        }

        if ($qualityControl->from_model_type === 'App\Models\PurchaseOrderItem') {
            $purchaseOrderItem = $qualityControl->fromModel;
            if ($purchaseOrderItem) {
                $limit = $this->purchaseOrderItemReceiptLimitForQualityControl($qualityControl, (int) $purchaseOrderItem->id);
                $inspectedQuantity = (float) $qualityControl->passed_quantity + (float) $qualityControl->rejected_quantity;
                $quantityReceived = (float) ($qualityControl->quantity_received ?? 0);

                if ((float) $qualityControl->passed_quantity > $quantityReceived) {
                    throw new \Exception("QC passed quantity ({$qualityControl->passed_quantity}) tidak boleh melebihi Qty Received ({$quantityReceived}).");
                }

                if ($inspectedQuantity > $quantityReceived) {
                    throw new \Exception("Total QC passed dan rejected ({$inspectedQuantity}) tidak boleh melebihi Qty Received ({$quantityReceived}).");
                }

                if ($inspectedQuantity > $limit['remaining_received']) {
                    throw new \Exception("Total QC passed dan rejected ({$inspectedQuantity}) tidak boleh melebihi sisa PO/Order Request ({$limit['remaining_received']}).");
                }

                if ((float) $qualityControl->passed_quantity > $limit['remaining_accepted']) {
                    throw new \Exception("QC passed quantity ({$qualityControl->passed_quantity}) tidak boleh melebihi sisa PO/Order Request ({$limit['remaining_accepted']}).");
                }
            }
        }

        if ($qualityControl->rejected_quantity > 0) {
            // Only create a sales-side ReturnProduct for non-purchase QC types.
            // Purchase QC (from PurchaseOrderItem) uses PurchaseReturn instead,
            // which is created by PurchaseReturnService::createFromQualityControl()
            // BEFORE completeQualityControl() is called (from the process_qc action form).
            if ($qualityControl->from_model_type !== 'App\Models\PurchaseOrderItem') {
                $returnProductService = app(ReturnProductService::class);
                $returnData = array_merge($data, [
                    'return_number' => $returnProductService->generateReturnNumber(),
                    'from_model_id' => $qualityControl->id,
                    'from_model_type' => QualityControl::class,
                    'warehouse_id' => $data['warehouse_id'] ?? $qualityControl->warehouse_id,
                    'status' => 'draft',
                ]);
                $returnProduct = $qualityControl->returnProduct()->create($returnData);
                $qualityControl->returnProductItem()->create([
                    'return_product_id' => $returnProduct->id,
                    'product_id' => $qualityControl->product_id,
                    'quantity' => $qualityControl->rejected_quantity,
                    'condition' => $data['item_condition'] ?? 'damage',
                    'rak_id' => $data['rak_id'] ?? null,
                ]);
            }
        }

        // Load manufacturing order relationship only for Production model
        if ($qualityControl->from_model_type == 'App\Models\Production') {
            $qualityControl->fromModel->load('manufacturingOrder.productionPlan');

            $inspectedQuantity = (float) $qualityControl->passed_quantity + (float) $qualityControl->rejected_quantity;
            $targetQuantity = (float) ($qualityControl->fromModel->quantity_produced ?? $qualityControl->fromModel->manufacturingOrder->productionPlan->quantity ?? 0);

            if ($targetQuantity > 0 && $inspectedQuantity >= $targetQuantity) {
                $qualityControl->fromModel->manufacturingOrder->update([
                    'status' => 'completed'
                ]);
                HelperController::sendNotification(isSuccess: true, title: "Information", message: "Manufacturing Completed");
            }
        }

        $qualityControl->update([
            'status' => 1,
            'date_send_stock' => Carbon::now()
        ]);

        // Note: qty_accepted on PurchaseReceiptItem should NOT be updated here
        // QC only provides inspection results, acceptance decision is separate process
        // Update qty_accepted on PurchaseReceiptItem if QC is from PurchaseReceiptItem
        // if ($qualityControl->from_model_type === 'App\Models\PurchaseReceiptItem') {
        //     $purchaseReceiptItem = $qualityControl->fromModel;
        //     if ($purchaseReceiptItem) {
        //         $purchaseReceiptItem->update([
        //             'qty_accepted' => $qualityControl->passed_quantity
        //         ]);
        //     }
        // }

        // Create journal entries and inventory stock for passed QC items from PurchaseOrderItem or PurchaseReceiptItem
        // For PurchaseReceiptItem QC (legacy flow), journal entries are created when the receipt is posted
        // For PurchaseOrderItem QC (new flow), journal entries are created here since receipt posting happens later
        // if ($qualityControl->from_model_type === 'App\Models\PurchaseOrderItem' && $qualityControl->passed_quantity > 0) {
        //     $this->createJournalEntriesAndInventoryForQC($qualityControl);
        // }

        // Handle Purchase Receipt and Purchase Order completion based on QC results
        if ($qualityControl->from_model_type === 'App\Models\PurchaseReceiptItem') {
            $this->handlePurchaseReceiptCompletion($qualityControl);
        }

        // NEW FLOW: Auto-create Purchase Receipt after QC for PurchaseOrderItem
        if ($qualityControl->from_model_type === 'App\Models\PurchaseOrderItem' && $qualityControl->passed_quantity > 0) {
            $purchaseReceipt = $this->autoCreatePurchaseReceiptFromQC($qualityControl, $data);
            if ($purchaseReceipt) {
                try {
                    // Post the receipt so journals and stock movements are created via PurchaseReceiptService
                    // Add retry-with-backoff to handle transient ordering/race conditions where
                    // the receipt items may not be fully available immediately after creation.
                    $purchaseReceiptService = app(\App\Services\PurchaseReceiptService::class);
                    $maxAttempts = config('procurement.auto_post_retries', 3);
                    $delayMs = [200, 500, 1000];
                    $result = null;

                    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                        // Refresh receipt to pick up any related items that may have been attached
                        $purchaseReceipt = $purchaseReceipt->fresh();
                        $result = $purchaseReceiptService->postPurchaseReceipt($purchaseReceipt);

                        Log::info('Auto postPurchaseReceipt returned (attempt ' . $attempt . ')', [
                            'qc' => $qualityControl->id,
                            'receipt_id' => $purchaseReceipt->id,
                            'attempt' => $attempt,
                            'result' => $result,
                        ]);

                        // Stop retrying when posted or an explicit error occurred
                        if (($result['status'] ?? null) === 'posted' || ($result['status'] ?? null) === 'error') {
                            break;
                        }

                        // Backoff before next attempt
                        if ($attempt < $maxAttempts) {
                            $sleepMs = $delayMs[$attempt - 1] ?? 500;
                            usleep($sleepMs * 1000);
                        }
                    }

                    if (($result['status'] ?? null) !== 'posted') {
                        Log::warning('Auto postPurchaseReceipt did not post after retries', [
                            'qc' => $qualityControl->id,
                            'receipt_id' => $purchaseReceipt->id,
                            'final_result' => $result,
                            'attempts' => $maxAttempts,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Auto postPurchaseReceipt failed for QC ' . $qualityControl->id . ': ' . $e->getMessage(), ['qc' => $qualityControl->id]);
                }
            }
        }

        // NEW: Check and auto-complete Purchase Order if all items are received
        if ($qualityControl->from_model_type === 'App\Models\PurchaseOrderItem') {
            $this->checkAndCompletePurchaseOrder($qualityControl);
        }

        // Create journal entries and stock movement for passed QC items from Production
        if ($qualityControl->from_model_type === 'App\Models\Production' && $qualityControl->passed_quantity > 0) {
            $this->createJournalEntriesAndStockMovementForProductionQC($qualityControl);
        }
    }

    /**
     * Create journal entries and inventory stock for QC passed items
     */
    public function createJournalEntriesAndInventoryForQC(QualityControl $qualityControl): void
    {
        // Load necessary relationships based on model type
        if ($qualityControl->from_model_type === 'App\Models\PurchaseOrderItem') {
            $qualityControl->loadMissing([
                'fromModel.purchaseOrder.purchaseOrderCurrency',
                'product.inventoryCoa',
                'product.temporaryProcurementCoa',
                'product.unbilledPurchaseCoa'
            ]);
        } elseif ($qualityControl->from_model_type === 'App\Models\PurchaseReceiptItem') {
            $qualityControl->loadMissing([
                'fromModel.purchaseOrderItem.purchaseOrder.purchaseOrderCurrency',
                'product.inventoryCoa',
                'product.temporaryProcurementCoa',
                'product.unbilledPurchaseCoa'
            ]);
        }

        $fromModel = $qualityControl->fromModel;
        $product = $qualityControl->product;
        $passedQuantity = $qualityControl->passed_quantity;

        // Get unit price in IDR based on model type
        if ($qualityControl->from_model_type === 'App\Models\PurchaseOrderItem') {
            $purchaseOrderCurrency = $fromModel?->purchaseOrder?->purchaseOrderCurrency?->firstWhere('currency_id', $fromModel?->currency_id);
            $resolved = $this->resolveQcJournalUnitPriceIdr(
                $fromModel?->unit_price ?? 0,
                is_numeric($fromModel?->currency_id ?? null) ? (int) $fromModel->currency_id : null,
                is_numeric($purchaseOrderCurrency?->nominal ?? null)
                    ? (float) $purchaseOrderCurrency->nominal
                    : null
            );
            $unitPrice = $resolved['amount_idr'] ?? 0;
        } elseif ($qualityControl->from_model_type === 'App\Models\PurchaseReceiptItem') {
            $resolved = JournalCurrencyAmountResolver::resolvePurchaseReceiptItemUnitCost($fromModel);
            $unitPrice = $resolved['unit_price_idr'] ?? 0;
        } else {
            $unitPrice = 0;
        }

        if ($passedQuantity <= 0 || $unitPrice <= 0) {
            return;
        }

        $amount = round($passedQuantity * $unitPrice, 2);

        // Get COA accounts.  Note that the product relation uses withDefault(),
        // which will return an empty ChartOfAccount instance with no id when the
        // foreign key is null.  We only treat the relation as valid when an id is
        // present; otherwise fall back to the hardcoded codes.
        $inventoryCoa = $product->resolveInventoryCoaOrDefault();
        $temporaryProcurementCoa = $product->resolveTemporaryProcurementCoaOrDefault();
        $unbilledPurchaseCoa = $product->resolveUnbilledPurchaseCoaOrDefault();

        // The relation may still produce a ChartOfAccount with an empty id, or the
        // fallback resolver may return null if none of the codes exist. Fail hard so
        // stock cannot move without the matching journal entry.
        if (!$inventoryCoa || !$inventoryCoa->id || !$temporaryProcurementCoa || !$temporaryProcurementCoa->id || !$unbilledPurchaseCoa || !$unbilledPurchaseCoa->id) {
            Log::error('QC journal posting blocked due to missing COA', [
                'qc_number' => $qualityControl->qc_number,
                'inventory_coa' => $inventoryCoa?->id,
                'temporary_procurement_coa' => $temporaryProcurementCoa?->id,
                'unbilled_purchase_coa' => $unbilledPurchaseCoa?->id,
            ]);

            throw new \RuntimeException(
                'QC journal posting gagal karena COA persediaan, temporary procurement, atau unbilled purchase belum dikonfigurasi lengkap.'
            );
        }

        $date = now();
        $reference = $qualityControl->qc_number;

        DB::transaction(function () use (
            $amount,
            $date,
            $fromModel,
            $inventoryCoa,
            $passedQuantity,
            $product,
            $qualityControl,
            $reference,
            $temporaryProcurementCoa,
            $unitPrice
        ) {
            $alreadyPosted = JournalEntry::where('source_type', QualityControl::class)
                ->where('source_id', $qualityControl->id)
                ->where('journal_type', 'inventory')
                ->lockForUpdate()
                ->exists();

            if ($alreadyPosted) {
                return;
            }

            JournalEntry::create([
                'coa_id' => $inventoryCoa->id,
                'date' => $date,
                'reference' => $reference,
                'description' => 'QC Inventory - Debit inventory for QC passed items: ' . $qualityControl->qc_number,
                'debit' => $amount,
                'credit' => 0,
                'journal_type' => 'inventory',
                'source_type' => QualityControl::class,
                'source_id' => $qualityControl->id,
            ]);

            JournalEntry::create([
                'coa_id' => $temporaryProcurementCoa->id,
                'date' => $date,
                'reference' => $reference,
                'description' => 'QC Inventory - Credit temporary procurement for QC passed items: ' . $qualityControl->qc_number,
                'debit' => 0,
                'credit' => $amount,
                'journal_type' => 'inventory',
                'source_type' => QualityControl::class,
                'source_id' => $qualityControl->id,
            ]);

            $existingMovement = StockMovement::where('from_model_type', QualityControl::class)
                ->where('from_model_id', $qualityControl->id)
                ->lockForUpdate()
                ->first();

            if ($existingMovement) {
                return;
            }

            $meta = [
                'source' => 'quality_control',
                'qc_id' => $qualityControl->id,
                'qc_number' => $qualityControl->qc_number,
                'unit_cost' => $unitPrice,
                'currency' => $qualityControl->from_model_type === 'App\Models\PurchaseOrderItem'
                    ? optional($fromModel->purchaseOrder->purchaseOrderCurrency->first())->currency_code
                    : optional($fromModel->purchaseReceipt->currency)->code,
                'purchase_order_item_id' => $qualityControl->from_model_type === 'App\Models\PurchaseOrderItem'
                    ? $fromModel?->id
                    : $fromModel?->purchase_order_item_id,
                'passed_quantity' => $passedQuantity,
                'rejected_quantity' => $qualityControl->rejected_quantity,
            ];

            StockMovement::create([
                'product_id' => $product->id,
                'warehouse_id' => $qualityControl->warehouse_id,
                'quantity' => $passedQuantity,
                'value' => $amount,
                'type' => 'purchase_in',
                'date' => $date,
                'notes' => 'Stock inbound from QC completion: ' . $qualityControl->qc_number,
                'meta' => $meta,
                'rak_id' => $qualityControl->rak_id,
                'from_model_type' => QualityControl::class,
                'from_model_id' => $qualityControl->id,
            ]);
        });
    }

    protected function resolveCoaByCodes(array $codes): ?\App\Models\ChartOfAccount
    {
        foreach ($codes as $code) {
            if (!$code) {
                continue;
            }

            if (!array_key_exists($code, self::$coaCache)) {
                self::$coaCache[$code] = ChartOfAccount::where('code', $code)->first();
            }

            if (self::$coaCache[$code]) {
                return self::$coaCache[$code];
            }
        }

        return null;
    }

    public function checkPenerimaanBarang($qualityControl)
    {
        Log::info('checkPenerimaanBarang: CALLED for QC ' . $qualityControl->id . ' from_model_type=' . $qualityControl->from_model_type);
        
        // Find the purchase order that this quality control belongs to
        $purchaseOrder = null;

        if ($qualityControl->from_model_type === 'App\Models\PurchaseReceiptItem') {
            $purchaseReceiptItem = $qualityControl->fromModel;
            if ($purchaseReceiptItem && $purchaseReceiptItem->purchaseOrderItem) {
                $purchaseOrder = $purchaseReceiptItem->purchaseOrderItem->purchaseOrder;
            }
        } elseif ($qualityControl->from_model_type === 'App\Models\PurchaseOrderItem') {
            // QC from PO item should NOT complete the PO - it only validates quality before receipt creation
            // PO completion happens when receipts are posted, not when QC from PO item is completed
            Log::info('checkPenerimaanBarang: SKIPPING for QC from PurchaseOrderItem - PO completion happens via receipt posting');
            return;
        }

        if (!$purchaseOrder) {
            Log::info('checkPenerimaanBarang: No purchase order found for QC ' . $qualityControl->id);
            return;
        }

        // Load relationships
        $purchaseOrder->load(['purchaseOrderItem.purchaseReceiptItem']);

        Log::info('checkPenerimaanBarang: Loaded PO relationships for PO ' . $purchaseOrder->id);
        Log::info('checkPenerimaanBarang: PO has ' . $purchaseOrder->purchaseOrderItem->count() . ' items');

        $totalQuantityDibutuhkan = 0;
        $totalQuantityYangDiterima = 0;

        foreach ($purchaseOrder->purchaseOrderItem as $purchaseOrderItem) {
            $totalQuantityDibutuhkan += $purchaseOrderItem->quantity;

            // Only count posted receipts as "received" - QC from PO items don't count toward PO completion
            foreach ($purchaseOrderItem->purchaseReceiptItem as $purchaseReceiptItem) {
                if ($purchaseReceiptItem->purchaseReceipt && $purchaseReceiptItem->purchaseReceipt->status === 'completed') {
                    $totalQuantityYangDiterima += $purchaseReceiptItem->qty_accepted;
                }
            }
        }

        Log::info('checkPenerimaanBarang: PO ' . $purchaseOrder->id . ' - Dibutuhkan: ' . $totalQuantityDibutuhkan . ', Diterima: ' . $totalQuantityYangDiterima);

        $previousStatus = $purchaseOrder->status;
        $purchaseOrder->syncReceiptFulfillmentStatus(Auth::id());
        $purchaseOrder->refresh();

        if ($previousStatus !== 'completed' && $purchaseOrder->status === 'completed') {
            Log::info('checkPenerimaanBarang: Completing PO ' . $purchaseOrder->id);
            HelperController::sendNotification(isSuccess: true, message: 'Purchase Order Completed', title: 'Information');
        } else {
            Log::info('checkPenerimaanBarang: PO ' . $purchaseOrder->id . ' not completed yet');
        }
    }

    /**
     * Handle Purchase Receipt and Purchase Order completion based on QC results
     * When QC is completed for a PurchaseReceiptItem, check if all items in the Purchase Order are fully received
     */
    public function handlePurchaseReceiptCompletion($qualityControl)
    {
        $purchaseReceiptItem = $qualityControl->fromModel;
        if (!$purchaseReceiptItem) {
            return;
        }

        // Update qty_accepted on the PurchaseReceiptItem based on QC result
        $purchaseReceiptItem->update([
            'qty_accepted' => $qualityControl->passed_quantity
        ]);

        // Get the purchase receipt and purchase order
        $purchaseReceipt = $purchaseReceiptItem->purchaseReceipt;
        $purchaseOrder = $purchaseReceipt->purchaseOrder;

        if (!$purchaseOrder) {
            return;
        }

        // Load all purchase order items and their receipts
        $purchaseOrder->load(['purchaseOrderItem.purchaseReceiptItem' => function ($query) {
            $query->with(['qualityControl']);
        }]);

        $allItemsComplete = true;
        $totalOrdered = 0;
        $totalReceived = 0;

        foreach ($purchaseOrder->purchaseOrderItem as $poItem) {
            $totalOrdered += $poItem->quantity;

            foreach ($poItem->purchaseReceiptItem as $receiptItem) {
                // Only count items that have completed QC
                if ($receiptItem->qualityControl && $receiptItem->qualityControl->status == 1) {
                    $totalReceived += $receiptItem->qty_accepted;
                } else {
                    // If any item doesn't have completed QC, order is not complete
                    $allItemsComplete = false;
                }
            }
        }

        // Check if all items in THIS receipt have completed QC
        $receiptItemsComplete = true;
        foreach ($purchaseReceipt->purchaseReceiptItem as $receiptItem) {
            if (!$receiptItem->qualityControl || $receiptItem->qualityControl->status != 1) {
                $receiptItemsComplete = false;
                break;
            }
        }

        Log::info("QC Completion Check - Receipt {$purchaseReceipt->id}: ItemsComplete={$receiptItemsComplete}");

        if ($receiptItemsComplete) {
            // Complete the purchase receipt
            $purchaseReceipt->update([
                'status' => 'completed',
            ]);

            // Post the receipt so journals and inventory are posted via PurchaseReceiptService
            try {
                app(\App\Services\PurchaseReceiptService::class)->postPurchaseReceipt($purchaseReceipt);
            } catch (\Exception $e) {
                Log::error('postPurchaseReceipt failed during handlePurchaseReceiptCompletion for receipt ' . $purchaseReceipt->id . ': ' . $e->getMessage(), ['receipt' => $purchaseReceipt->id]);
            }

            Log::info("Completed Purchase Receipt {$purchaseReceipt->id} due to QC completion");
        }

        // Check if the entire purchase order is complete using the same receipt status shown in the PO list.
        $previousStatus = $purchaseOrder->status;
        $purchaseOrder->syncReceiptFulfillmentStatus(Auth::id());
        $purchaseOrder->refresh();

        if ($previousStatus !== 'completed' && $purchaseOrder->status === 'completed') {
            Log::info("Completed Purchase Order {$purchaseOrder->id} due to QC completion");

            HelperController::sendNotification(
                isSuccess: true,
                title: "Purchase Order Completed",
                message: "Purchase Order {$purchaseOrder->po_number} has been completed. All items have been received and quality controlled."
            );
        } elseif ($allItemsComplete) {
            // Complete the purchase receipt if all its items have been QC'd (even if not all ordered qty received)
            $purchaseReceipt->update([
                'status' => 'completed',
                'completed_by' => Auth::id(),
                'completed_at' => Carbon::now()
            ]);

            // Note: Receipt posting is already done in completeQualityControl via createJournalEntriesAndInventoryForQC
        } elseif ($purchaseOrder->status === 'partially_received') {
            Log::info("Set Purchase Order {$purchaseOrder->id} to partially_received");
        }
    }

    /**
     * Calculate total BDP cost for a Manufacturing Order
     */
    protected function calculateManufacturingOrderBDPTotal(\App\Models\ManufacturingOrder $mo): float
    {
        // Primary: read WIP (1-201) debit balance from manufacturing_wip journal entries
        $wipCoa = $this->resolveCoaByCodes(['1-201']);
        if ($wipCoa) {
            $productionIds = \App\Models\Production::where('manufacturing_order_id', $mo->id)->pluck('id');
            if ($productionIds->isNotEmpty()) {
                $wipBalance = \App\Models\JournalEntry::where('coa_id', $wipCoa->id)
                    ->where('journal_type', 'manufacturing_wip')
                    ->where('source_type', \App\Models\Production::class)
                    ->whereIn('source_id', $productionIds)
                    ->sum('debit');
                if ($wipBalance > 0) {
                    return (float) $wipBalance;
                }
            }
        }

        // Fallback: actual material issues for this MO (older flow, no in-progress WIP journal)
        $issuesTotal = $this->sumCompletedMaterialIssueTotalForManufacturingOrder($mo, 'issue');
        $returnsTotal = $this->sumCompletedMaterialIssueTotalForManufacturingOrder($mo, 'return');
        $actualMaterialCost = (float)$issuesTotal - (float)$returnsTotal;

        if ($actualMaterialCost <= 0 && $mo->production_plan_id) {
            // Also check legacy plan-only issues that were not yet linked to a manufacturing order.
            $issuesTotal = \App\Models\MaterialIssue::whereNull('manufacturing_order_id')
                ->where('production_plan_id', $mo->production_plan_id)
                ->where('status', 'completed')
                ->where('type', 'issue')
                ->sum('total_cost');
            $returnsTotal = \App\Models\MaterialIssue::whereNull('manufacturing_order_id')
                ->where('production_plan_id', $mo->production_plan_id)
                ->where('status', 'completed')
                ->where('type', 'return')
                ->sum('total_cost');
            $actualMaterialCost = (float)$issuesTotal - (float)$returnsTotal;
        }

        if ($actualMaterialCost > 0) {
            return $actualMaterialCost;
        }

        // Ultimate fallback: standard BOM cost
        $bom = $mo->productionPlan?->billOfMaterial;
        if (!$bom || !$bom->is_active) {
            return 0;
        }
        $bom->loadMissing('items.product');
        $materialCost  = $bom->items->sum(fn ($i) => (float)$i->quantity * (float)($i->product->cost_price ?? 0));
        $laborCost     = (float)($bom->labor_cost ?? 0);
        $overheadCost  = (float)($bom->overhead_cost ?? 0);
        return max(0, ($materialCost + $laborCost + $overheadCost) * (float)$mo->productionPlan->quantity);
    }

    protected function sumCompletedMaterialIssueTotalForManufacturingOrder(
        \App\Models\ManufacturingOrder $mo,
        string $type
    ): float {
        $issuesTotal = \App\Models\MaterialIssue::where('manufacturing_order_id', $mo->id)
            ->where('status', 'completed')
            ->where('type', $type)
            ->sum('total_cost');

        if ($issuesTotal > 0 || ! $mo->production_plan_id) {
            return (float) $issuesTotal;
        }

        return (float) \App\Models\MaterialIssue::whereNull('manufacturing_order_id')
            ->where('production_plan_id', $mo->production_plan_id)
            ->where('status', 'completed')
            ->where('type', $type)
            ->sum('total_cost');
    }

    /**
     * Create journal entries and stock movement for production QC completion
     */
    public function createJournalEntriesAndStockMovementForProductionQC(QualityControl $qualityControl): void
    {
        $production = $qualityControl->fromModel;
        $manufacturingOrder = $production->manufacturingOrder;
        $productionPlan = $manufacturingOrder->productionPlan;
        $product = $qualityControl->product;
        $passedQuantity = $qualityControl->passed_quantity;

        // Labor/overhead are already captured in the manufacturing_wip journal
        // (posted by ProductionObserver::created via generateJournalForProductionInProgress).
        // No separate syncLaborAndOverheadAllocations call is needed here.

        // Calculate total cost from WIP or fallback sources
        $totalCost = $this->calculateManufacturingOrderBDPTotal($manufacturingOrder);

        if ($totalCost <= 0 || $passedQuantity <= 0) {
            return;
        }

        // Determine credit COA: prefer 1-201 WIP Inventory if in-progress WIP journal exists,
        // otherwise fall back to 1400.04 Pos Sementara Produksi (old flow)
        $wipInventoryCoa = $this->resolveCoaByCodes(['1-201']);
        $posSementaraCoa = $this->resolveCoaByCodes(['1400.04', '1150', '1140']);

        $creditCoa = null;
        if ($wipInventoryCoa) {
            $productionIds = \App\Models\Production::where('manufacturing_order_id', $manufacturingOrder->id)->pluck('id');
            if ($productionIds->isNotEmpty()) {
                $wipBalance = \App\Models\JournalEntry::where('coa_id', $wipInventoryCoa->id)
                    ->where('journal_type', 'manufacturing_wip')
                    ->where('source_type', \App\Models\Production::class)
                    ->whereIn('source_id', $productionIds)
                    ->sum('debit');
                if ($wipBalance > 0) {
                    $creditCoa = $wipInventoryCoa;
                }
            }
        }
        if (!$creditCoa) {
            $creditCoa = $posSementaraCoa;
        }

        $barangJadiCoa = $product->resolveInventoryCoaOrDefault()
            ?? $this->resolveCoaByCodes(['1140.02']);

        if (!$creditCoa || !$barangJadiCoa) {
            return;
        }

        $branchResolver = app(\App\Services\JournalBranchResolver::class);
        $branchId = $branchResolver->resolve($qualityControl) ?? $branchResolver->resolve($production) ?? $branchResolver->resolve($manufacturingOrder);
        $departmentId = $branchResolver->resolveDepartment($qualityControl) ?? $branchResolver->resolveDepartment($production) ?? $branchResolver->resolveDepartment($manufacturingOrder);
        $projectId = $branchResolver->resolveProject($qualityControl) ?? $branchResolver->resolveProject($production) ?? $branchResolver->resolveProject($manufacturingOrder);

        $date = $qualityControl->date_send_stock ?? now();
        $reference = $production->production_number ?: $qualityControl->qc_number;

        // Prevent duplicate posting
        if (\App\Models\JournalEntry::where('source_type', QualityControl::class)
            ->where('source_id', $qualityControl->id)
            ->where('description', 'like', '%Penyelesaian produksi%')
            ->exists()) {
            return;
        }

        // Calculate cost per unit
        $costPerUnit = $totalCost / $productionPlan->quantity;
        $amount = round($costPerUnit * $passedQuantity, 2);

        // Create journal entries
        \App\Models\JournalEntry::create([
            'coa_id' => $barangJadiCoa->id,
            'date' => $date,
            'reference' => $reference,
            'description' => 'Penyelesaian produksi - ' . $manufacturingOrder->mo_number . ' (' . $product->name . ')',
            'debit' => $amount,
            'credit' => 0,
            'journal_type' => 'manufacturing_completion',
            'cabang_id' => $branchId,
            'department_id' => $departmentId,
            'project_id' => $projectId,
            'source_type' => QualityControl::class,
            'source_id' => $qualityControl->id,
        ]);

        \App\Models\JournalEntry::create([
            'coa_id' => $creditCoa->id,
            'date' => $date,
            'reference' => $reference,
            'description' => 'Penyelesaian produksi - ' . $manufacturingOrder->mo_number . ' (' . $product->name . ')',
            'debit' => 0,
            'credit' => $amount,
            'journal_type' => 'manufacturing_completion',
            'cabang_id' => $branchId,
            'department_id' => $departmentId,
            'project_id' => $projectId,
            'source_type' => QualityControl::class,
            'source_id' => $qualityControl->id,
        ]);

        // Create stock movement
        $existingMovement = \App\Models\StockMovement::where('from_model_type', QualityControl::class)
            ->where('from_model_id', $qualityControl->id)
            ->first();

        if (!$existingMovement) {
            $meta = [
                'source' => 'quality_control_manufacture',
                'qc_id' => $qualityControl->id,
                'qc_number' => $qualityControl->qc_number,
                'production_id' => $production->id,
                'production_number' => $production->production_number,
                'manufacturing_order_id' => $manufacturingOrder->id,
                'mo_number' => $manufacturingOrder->mo_number,
                'passed_quantity' => $passedQuantity,
                'rejected_quantity' => $qualityControl->rejected_quantity,
                'cost_per_unit' => $costPerUnit,
            ];

            \App\Models\StockMovement::create([
                'product_id' => $product->id,
                'warehouse_id' => $qualityControl->warehouse_id,
                'quantity' => $passedQuantity,
                'value' => $amount,
                'type' => 'manufacture_in',
                'date' => $date,
                'notes' => 'Stock masuk dari penyelesaian produksi: ' . $qualityControl->qc_number,
                'meta' => $meta,
                'rak_id' => $qualityControl->rak_id,
                'from_model_type' => QualityControl::class,
                'from_model_id' => $qualityControl->id,
            ]);
        }

        // Update production status to finished only if all quantity passed QC
        $inspectedQuantity = (float) $qualityControl->passed_quantity + (float) $qualityControl->rejected_quantity;
        $targetQuantity = (float) ($production->quantity_produced ?? $productionPlan->quantity ?? 0);

        if ($targetQuantity > 0 && $inspectedQuantity >= $targetQuantity) {
            $production->status = 'finished';
            $production->save();
        }
    }

    /**
     * Auto-create Purchase Receipt from Quality Control
     * NEW FLOW: QC creates receipt automatically after QC pass
     */
    protected function autoCreatePurchaseReceiptFromQC($qualityControl, $data)
    {
        $purchaseOrderItem = $qualityControl->fromModel;
        if (!$purchaseOrderItem) {
            return;
        }

        $purchaseOrder = \App\Models\PurchaseOrder::withoutGlobalScopes()
            ->with('purchaseOrderCurrency')
            ->find($purchaseOrderItem->purchase_order_id);

        if (!$purchaseOrder || !$purchaseOrder->id) {
            return;
        }

        // Check if receipt already exists for this QC
        $existingReceipt = \App\Models\PurchaseReceiptItem::where('purchase_order_item_id', $purchaseOrderItem->id)
            ->whereHas('purchaseReceipt', function ($query) use ($qualityControl) {
                $query->where('notes', 'like', '%' . $qualityControl->qc_number . '%');
            })
            ->first();

        if ($existingReceipt) {
            Log::info('Purchase Receipt already exists for QC ' . $qualityControl->qc_number);
            return;
        }

        $limit = OrderRequestQuantityLock::purchaseOrderItemReceiptLimit((int) $purchaseOrderItem->id);
        $qtyReceived = (float) $qualityControl->passed_quantity + (float) $qualityControl->rejected_quantity;
        $qtyAccepted = (float) $qualityControl->passed_quantity;

        if ($qtyReceived > $limit['remaining_received']) {
            throw new \Exception("Quantity Received dari QC tidak boleh melebihi sisa PO/Order Request ({$limit['remaining_received']}).");
        }

        if ($qtyAccepted > $limit['remaining_accepted']) {
            throw new \Exception("Quantity Accepted dari QC tidak boleh melebihi sisa PO/Order Request ({$limit['remaining_accepted']}).");
        }

        // Generate receipt number
        $receiptNumber = $this->generateReceiptNumber();

        // Create Purchase Receipt
        $purchaseReceipt = \App\Models\PurchaseReceipt::create([
            'receipt_number' => $receiptNumber,
            'purchase_order_id' => $purchaseOrder->id,
            'receipt_date' => now(),
            'received_by' => Auth::id() ?? $data['received_by'] ?? 1,
            'notes' => 'Auto-created from QC: ' . $qualityControl->qc_number,
            'currency_id' => $purchaseOrder->purchaseOrderCurrency->first()?->currency_id ?? 1,
            'status' => 'completed',
            'cabang_id' => $purchaseOrder->cabang_id,
        ]);

        // Create Purchase Receipt Item
        $receiptItem = \App\Models\PurchaseReceiptItem::create([
            'purchase_receipt_id'    => $purchaseReceipt->id,
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'product_id'             => $qualityControl->product_id,
            'qty_received'           => $qtyReceived,
            'qty_accepted'           => $qtyAccepted,
            'qty_rejected'           => $qualityControl->rejected_quantity,
            'reason_rejected'        => $qualityControl->rejected_quantity > 0 ? 'Failed QC inspection' : null,
            'warehouse_id'           => $qualityControl->warehouse_id,
            'rak_id'                 => $qualityControl->rak_id,
            'status'                 => 'completed', // QC already done
        ]);

        app(\App\Services\PurchaseReceiptService::class)
            ->copyBiayaFromPurchaseOrderToReceipt($purchaseOrder, $purchaseReceipt);

        Log::info('Auto-created Purchase Receipt from QC', [
            'qc_number' => $qualityControl->qc_number,
            'receipt_number' => $receiptNumber,
            'receipt_id' => $purchaseReceipt->id,
            'receipt_item_id' => $receiptItem->id,
        ]);

        return $purchaseReceipt;
    }

    /**
     * Generate receipt number
     */
    protected function generateReceiptNumber()
    {
        $date = now()->format('Ymd');
        $prefix = 'PR-' . $date . '-';

        do {
            $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = $prefix . $random;
            $exists = \App\Models\PurchaseReceipt::where('receipt_number', $candidate)->exists();
        } while ($exists);

        return $candidate;
    }

    /**
     * Check and auto-complete Purchase Order if all items are fully received
     * NEW: Auto-complete PO when all items have receipts
     */
    protected function checkAndCompletePurchaseOrder($qualityControl)
    {
        $purchaseOrderItem = $qualityControl->fromModel;
        if (!$purchaseOrderItem) {
            return;
        }

        $purchaseOrder = $purchaseOrderItem->purchaseOrder;
        if (!$purchaseOrder) {
            return;
        }

        // Don't auto-complete if already closed or paid
        if (in_array($purchaseOrder->status, ['closed', 'paid'])) {
            return;
        }

        // Refresh PO to get latest data including newly created receipt items
        $purchaseOrder->refresh();

        // Load all items with their receipts
        $purchaseOrder->load(['purchaseOrderItem.purchaseReceiptItem']);

        $previousStatus = $purchaseOrder->status;
        $summary = $purchaseOrder->receiptFulfillmentSummary();
        $purchaseOrder->syncReceiptFulfillmentStatus(Auth::id() ?? 1);
        $purchaseOrder->refresh();

        if ($previousStatus !== 'completed' && $purchaseOrder->status === 'completed') {
            Log::info('Auto-completed Purchase Order', [
                'po_id' => $purchaseOrder->id,
                'po_number' => $purchaseOrder->po_number,
                'trigger' => 'QC completion',
            ]);

            HelperController::sendNotification(
                isSuccess: true,
                title: 'Purchase Order Completed',
                message: 'PO ' . $purchaseOrder->po_number . ' has been automatically completed.'
            );
        } elseif ($summary['status_label'] !== 'Semua Diterima') {
            Log::info('PO item not fully accepted', [
                'po_id' => $purchaseOrder->id,
                'po_number' => $purchaseOrder->po_number,
                'ordered_qty' => $summary['total_ordered'],
                'received_qty' => $summary['total_received'],
                'accepted_qty' => $summary['total_accepted'],
            ]);
        }
    }
}
