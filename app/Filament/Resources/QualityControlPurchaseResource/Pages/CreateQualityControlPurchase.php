<?php

namespace App\Filament\Resources\QualityControlPurchaseResource\Pages;

use App\Filament\Resources\QualityControlPurchaseResource;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\QualityControl;
use App\Models\QualityControlItem;
use App\Services\QualityControlService;
use App\Support\ProcurementFailureNotifier;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateQualityControlPurchase extends CreateRecord
{
    protected static string $resource = QualityControlPurchaseResource::class;

    protected function afterFill(): void
    {
        // 1. Check if purchase_order_id is passed in the query string
        $poId = request()->query('purchase_order_id');
        if ($poId) {
            $po = PurchaseOrder::with(['purchaseOrderItem.product.uom', 'warehouse'])->find($poId);
            if ($po) {
                $user = Auth::user();
                if ($user && $user->warehouse_id && $po->warehouse_id && (int) $user->warehouse_id !== (int) $po->warehouse_id && ! $user->hasRole(['super_admin', 'Super Admin', 'Owner', 'owner'])) {
                    ProcurementFailureNotifier::danger(
                        'Akses Gudang Ditolak',
                        new \Exception("Anda tidak memiliki otorisasi QC untuk gudang PO ini."),
                        "Anda hanya dapat melakukan QC untuk gudang yang ditugaskan kepada Anda."
                    );
                    return;
                }

                $items = [];
                foreach ($po->purchaseOrderItem as $poItem) {
                    $lockInfo = QualityControlPurchaseResource::draftQcLockInfo($poItem);
                    if ($lockInfo['remaining_allowed'] > 0) {
                        $items[] = [
                            'purchase_order_item_id' => $poItem->id,
                            'product_id'             => $poItem->product_id,
                            'product_name'           => ($poItem->product?->name ?? 'N/A') . ($poItem->product?->sku ? " ({$poItem->product->sku})" : ''),
                            'uom'                    => $poItem->product?->uom?->name ?? 'pcs',
                            'ordered_quantity'       => (float) $poItem->quantity,
                            'remaining_info'         => $lockInfo['message'],
                            'remaining_allowed'      => (float) $lockInfo['remaining_allowed'],
                            'quantity_received'      => (float) $lockInfo['remaining_allowed'],
                            'passed_quantity'        => (float) $lockInfo['remaining_allowed'],
                            'rejected_quantity'      => 0,
                            'failed_qc_action'       => 'wait_next_delivery',
                            'reason_reject'          => null,
                            'rak_id'                 => null,
                        ];
                    }
                }

                $this->form->fill([
                    'purchase_order_id' => $po->id,
                    'warehouse_id'      => $po->warehouse_id,
                    'cabang_id'         => $po->cabang_id ?? 1,
                    'items'             => $items,
                    'auto_process'      => true,
                ]);

                return;
            }
        }

        // 2. Legacy fallback for single-item PO item in query
        $purchaseOrderItem = QualityControlPurchaseResource::defaultPurchaseOrderItemForQuery();

        if (! $purchaseOrderItem) {
            return;
        }

        $state = $this->form->getRawState();
        $state = $state instanceof Arrayable ? $state->toArray() : $state;

        $this->form->fill(array_merge(
            $state,
            QualityControlPurchaseResource::formStateForPurchaseOrderItem($purchaseOrderItem),
        ));
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['inspected_by'] = Auth::id() ?? ($data['inspected_by'] ?? null);

        // Multi-item QC validations & authorization
        if (! empty($data['purchase_order_id'])) {
            $po = PurchaseOrder::with('warehouse')->find($data['purchase_order_id']);
            if (! $po) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => 'Purchase Order tidak ditemukan.',
                ]);
            }

            // Verify user's assigned warehouse matches PO warehouse
            $user = Auth::user();
            if ($user && $user->warehouse_id && $po->warehouse_id && (int) $user->warehouse_id !== (int) $po->warehouse_id && ! $user->hasRole(['super_admin', 'Super Admin', 'Owner', 'owner'])) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => "Anda hanya memiliki otorisasi untuk melakukan QC pada gudang asal Anda (" . ($user->warehouse?->name ?? 'Gudang #' . $user->warehouse_id) . "). PO ini dialokasikan ke gudang " . ($po->warehouse?->name ?? 'Gudang #' . $po->warehouse_id) . ".",
                ]);
            }

            // Lock destination warehouse and accounting branch to Pusat
            $defaultCabangId = \App\Models\Cabang::where('kode', 'CBG-001')->value('id') ?? \App\Models\Cabang::first()?->id;
            $targetWarehouseId = $po->warehouse_id ?: ($data['warehouse_id'] ?? null);
            if (! $targetWarehouseId) {
                $targetWarehouseId = \App\Models\Warehouse::withoutGlobalScopes()->where('status', 1)->value('id')
                    ?? \App\Models\Warehouse::withoutGlobalScopes()->value('id');
            }
            if ($targetWarehouseId && ! $po->warehouse_id) {
                $po->update(['warehouse_id' => $targetWarehouseId]);
            }
            $data['warehouse_id'] = $targetWarehouseId;
            $data['cabang_id'] = $po->cabang_id ?? $defaultCabangId;
            if (empty($data['inspected_by'])) {
                $data['inspected_by'] = Auth::id() ?? auth()->guard('web')->id() ?? \App\Models\User::first()?->id;
            }

            if (isset($data['items']) && is_array($data['items'])) {
                if (empty($data['items'])) {
                    throw ValidationException::withMessages([
                        'items' => 'Minimal 1 item harus diperiksa pada kedatangan barang ini.',
                    ]);
                }

                $totalReceived = 0;
                $totalPassed = 0;
                $totalRejected = 0;
                $messages = [];

                foreach ($data['items'] as $index => $row) {
                    $poItemId = $row['purchase_order_item_id'] ?? null;
                    $poItem = $poItemId ? PurchaseOrderItem::with('qualityControls', 'product')->find($poItemId) : null;
                    if (! $poItem) {
                        $messages["items.{$index}.purchase_order_item_id"] = "Item PO pada baris #" . ($index + 1) . " tidak valid.";
                        continue;
                    }

                    $lockInfo = QualityControlPurchaseResource::draftQcLockInfo($poItem);
                    $rem = (float) $lockInfo['remaining_allowed'];
                    $recv = (float) ($row['quantity_received'] ?? 0);
                    $passed = (float) ($row['passed_quantity'] ?? 0);
                    $rejected = (float) ($row['rejected_quantity'] ?? 0);
                    $itemName = $poItem->product?->name ?? "Item #{$poItemId}";

                    if ($recv <= 0) {
                        $messages["items.{$index}.quantity_received"] = "Qty diterima baris #" . ($index + 1) . " ({$itemName}) harus lebih dari 0.";
                    }
                    if ($recv > $rem) {
                        $messages["items.{$index}.quantity_received"] = "Item #" . ($index + 1) . " ({$itemName}): {$lockInfo['message']}";
                    }
                    if ($passed < 0 || $rejected < 0) {
                        $messages["items.{$index}.passed_quantity"] = "Qty lolos dan reject tidak boleh bernilai negatif.";
                    }
                    if (round($passed + $rejected, 4) !== round($recv, 4)) {
                        $messages["items.{$index}.quantity_received"] = "Total lolos ({$passed}) + reject ({$rejected}) harus sama dengan Qty Diterima ({$recv}) pada baris #" . ($index + 1) . " ({$itemName}).";
                    }

                    $totalReceived += $recv;
                    $totalPassed += $passed;
                    $totalRejected += $rejected;
                }

                if (! empty($messages)) {
                    throw ValidationException::withMessages($messages);
                }

                $data['quantity_received'] = $totalReceived;
                $data['passed_quantity'] = $totalPassed;
                $data['rejected_quantity'] = $totalRejected;

                $firstItem = reset($data['items']);
                $data['product_id'] = $firstItem['product_id'] ?? null;
                $data['from_model_type'] = PurchaseOrder::class;
                $data['from_model_id'] = $po->id;
            }

            return $data;
        }

        // Legacy single-item: ensure valid cabang_id
        $defaultCabangId = \App\Models\Cabang::where('kode', 'CBG-001')->value('id') ?? \App\Models\Cabang::first()?->id;
        if (empty($data['cabang_id']) || ! \App\Models\Cabang::where('id', $data['cabang_id'])->exists()) {
            $poItem = ! empty($data['from_model_id']) ? PurchaseOrderItem::with(['purchaseOrder', 'product'])->find($data['from_model_id']) : null;
            $data['cabang_id'] = $poItem?->purchaseOrder?->cabang_id
                ?? $poItem?->product?->cabang_id
                ?? Auth::user()?->cabang_id
                ?? $defaultCabangId;
        }

        return QualityControlPurchaseResource::validateQcPurchaseCreateQuantities($data);
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            if (! empty($data['purchase_order_id']) && isset($data['items']) && is_array($data['items'])) {
                return DB::transaction(function () use ($data) {
                    $itemsData = $data['items'];
                    $autoProcess = (bool) ($data['auto_process'] ?? true);

                    // Unset non-column form attributes before creating header QualityControl
                    unset($data['items'], $data['auto_process']);

                    $data['status'] = 0; // Draft initially

                    /** @var QualityControl $qc */
                    $qc = QualityControl::create($data);

                    foreach ($itemsData as $itemRow) {
                        QualityControlItem::create([
                            'quality_control_id'     => $qc->id,
                            'purchase_order_item_id' => $itemRow['purchase_order_item_id'],
                            'product_id'             => $itemRow['product_id'],
                            'quantity_received'      => $itemRow['quantity_received'],
                            'passed_quantity'        => $itemRow['passed_quantity'],
                            'rejected_quantity'      => $itemRow['rejected_quantity'] ?? 0,
                            'failed_qc_action'       => $itemRow['failed_qc_action'] ?? 'wait_next_delivery',
                            'reason_reject'          => $itemRow['reason_reject'] ?? null,
                            'rak_id'                 => $itemRow['rak_id'] ?? null,
                            'status'                 => 0,
                        ]);
                    }

                    if ($autoProcess) {
                        $qcService = app(QualityControlService::class);
                        $qcService->completeQualityControl($qc, $data);
                    }

                    return $qc;
                });
            }

            return parent::handleRecordCreation($data);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('CreateQualityControlPurchase handleRecordCreation failed', [
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'from_model_id' => $data['from_model_id'] ?? null,
                'product_id' => $data['product_id'] ?? null,
                'error' => $exception->getMessage(),
            ]);

            if ($this->isQuantityException($exception)) {
                throw ValidationException::withMessages([
                    'quantity_received' => $exception->getMessage(),
                    'passed_quantity' => $exception->getMessage(),
                ]);
            }

            ProcurementFailureNotifier::danger(
                'Gagal Membuat QC Pembelian',
                $exception,
                'QC pembelian belum berhasil dibuat. Periksa kembali quantity, gudang, dan item purchase order yang dipilih lalu coba lagi.'
            );

            throw $exception;
        }
    }

    private function isQuantityException(Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'quantity')
            || str_contains($message, 'qty received')
            || str_contains($message, 'available quantity');
    }
}
