<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QualityControlPurchaseResource\Pages;
use App\Http\Controllers\HelperController;
use App\Models\OrderRequestItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReturn;
use App\Models\QualityControl;
use App\Models\Rak;
use App\Models\Warehouse;
use App\Services\PurchaseReturnService;
use App\Services\QualityControlService;
use App\Support\CurrencyConversionResolver;
use App\Support\JournalCurrencyAmountResolver;
use App\Support\OrderRequestQuantityLock;
use App\Support\ProcurementFailureNotifier;
use Filament\Forms\Components\Actions\Action as ActionsAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Illuminate\Support\Facades\Log;
use Throwable;

class QualityControlPurchaseResource extends Resource
{
    protected static ?string $model = QualityControl::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box-arrow-down';

    protected static ?string $navigationGroup = 'Pembelian';

    protected static ?string $navigationLabel = 'Kontrol Kualitas Pembelian';

    protected static ?string $modelLabel = 'Kontrol Kualitas Pembelian';

    protected static ?string $pluralModelLabel = 'Kontrol Kualitas Pembelian';

    protected static ?int $navigationSort = 3;

    public static function canChooseInspector(): bool
    {
        return false;
    }

    public static function formatQcPurchaseOriginalMoney(mixed $amount, ?int $currencyId): string
    {
        return CurrencyConversionResolver::formatAmount($currencyId, (float) $amount, 2);
    }

    public static function qcPurchaseMoneySummary(?QualityControl $qualityControl): array
    {
        $purchaseOrderItem = $qualityControl?->fromModel instanceof PurchaseOrderItem
            ? $qualityControl->fromModel
            : null;

        if (! $purchaseOrderItem) {
            return [
                'unit_price' => '-',
                'currency' => '-',
                'exchange_rate' => '-',
                'unit_price_idr' => '-',
                'accepted_value' => '-',
                'accepted_value_idr' => '-',
            ];
        }

        $purchaseOrderItem->loadMissing([
            'currency',
            'purchaseOrder.purchaseOrderCurrency.currency',
        ]);

        $currencyId = is_numeric($purchaseOrderItem->currency_id ?? null)
            ? (int) $purchaseOrderItem->currency_id
            : null;
        $poCurrency = $currencyId
            ? $purchaseOrderItem->purchaseOrder?->purchaseOrderCurrency?->firstWhere('currency_id', $currencyId)
            : null;
        $resolved = JournalCurrencyAmountResolver::resolve(
            $purchaseOrderItem->unit_price ?? 0,
            $currencyId,
            is_numeric($poCurrency?->nominal ?? null) ? (float) $poCurrency->nominal : null
        );

        $unitOriginal = (float) ($purchaseOrderItem->unit_price ?? 0);
        $acceptedQty = (float) ($qualityControl?->passed_quantity ?? 0);
        $acceptedOriginal = $unitOriginal * $acceptedQty;
        $exchangeRate = (float) ($resolved['exchange_rate'] ?? 1);

        return [
            'unit_price' => static::formatQcPurchaseOriginalMoney($unitOriginal, $currencyId),
            'currency' => $purchaseOrderItem->currency?->code ?? '-',
            'exchange_rate' => \App\Helpers\MoneyHelper::rupiah($exchangeRate),
            'unit_price_idr' => \App\Helpers\MoneyHelper::rupiah($resolved['amount_idr'] ?? 0),
            'accepted_value' => sprintf(
                '%s x %s = %s',
                rtrim(rtrim(number_format($acceptedQty, 2, ',', '.'), '0'), ','),
                static::formatQcPurchaseOriginalMoney($unitOriginal, $currencyId),
                static::formatQcPurchaseOriginalMoney($acceptedOriginal, $currencyId)
            ),
            'accepted_value_idr' => \App\Helpers\MoneyHelper::rupiah($acceptedOriginal * $exchangeRate),
        ];
    }

    public static function resolveQcPurchaseCabangId(?PurchaseOrderItem $purchaseOrderItem = null, ?PurchaseOrder $purchaseOrder = null): ?int
    {
        $purchaseOrderItem?->loadMissing([
            'referItemModel',
            'purchaseOrder.supplier',
            'purchaseOrder.referModel',
        ]);

        $referItem = $purchaseOrderItem?->referItemModel;
        if ($referItem instanceof OrderRequestItem && filled($referItem->cabang_id)) {
            return (int) $referItem->cabang_id;
        }

        $purchaseOrder = $purchaseOrder ?? $purchaseOrderItem?->purchaseOrder;
        $purchaseOrder?->loadMissing(['supplier', 'referModel']);

        $rawPoCabangId = $purchaseOrder?->getRawOriginal('cabang_id');
        if (filled($rawPoCabangId)) {
            return (int) $rawPoCabangId;
        }

        $referModel = $purchaseOrder?->referModel;
        if ($referModel && filled($referModel->cabang_id)) {
            return (int) $referModel->cabang_id;
        }

        if (filled($purchaseOrder?->supplier?->cabang_id)) {
            return (int) $purchaseOrder->supplier->cabang_id;
        }

        return null;
    }

    public static function resolveQcPurchaseCabangIdFromPurchaseOrderItemId(?int $purchaseOrderItemId): ?int
    {
        if (! $purchaseOrderItemId) {
            return null;
        }

        $purchaseOrderItem = PurchaseOrderItem::with([
            'referItemModel',
            'purchaseOrder.supplier',
            'purchaseOrder.referModel',
        ])->find($purchaseOrderItemId);

        return static::resolveQcPurchaseCabangId($purchaseOrderItem);
    }

    public static function getQcPurchaseWarehouseOptions(?int $cabangId): array
    {
        if (! $cabangId) {
            return [];
        }

        return Warehouse::withoutGlobalScopes()
            ->where('status', 1)
            ->where('cabang_id', $cabangId)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn($warehouse) => [$warehouse->id => "({$warehouse->kode}) {$warehouse->name}"])
            ->all();
    }

    public static function getQcPurchaseEligiblePurchaseOrderStatuses(): array
    {
        return ['approved', 'partially_received'];
    }

    public static function queryPurchaseOrderId(): ?int
    {
        $purchaseOrderId = request()->query('purchase_order_id');

        return is_numeric($purchaseOrderId) ? (int) $purchaseOrderId : null;
    }

    public static function eligiblePurchaseOrderItems(?int $purchaseOrderId = null): Collection
    {
        return PurchaseOrderItem::with([
            'purchaseOrder.supplier',
            'purchaseOrder.referModel',
            'product.uom',
            'qualityControls',
            'referItemModel',
        ])
            ->when(
                $purchaseOrderId,
                fn(Builder $query) => $query->where('purchase_order_id', $purchaseOrderId)->orderBy('id', 'asc'),
                fn(Builder $query) => $query->latest('id')
            )
            ->whereHas('purchaseOrder', function (Builder $query): void {
                $query->whereIn('status', static::getQcPurchaseEligiblePurchaseOrderStatuses());
            })
            ->get()
            ->filter(function (PurchaseOrderItem $item): bool {
                if (! $item->purchaseOrder || ! $item->purchaseOrder->supplier || ! $item->product) {
                    return false;
                }

                return static::purchaseOrderItemQcRemaining($item)['remaining'] > 0;
            })
            ->values();
    }

    public static function defaultPurchaseOrderItemForQuery(): ?PurchaseOrderItem
    {
        $itemId = request()->query('purchase_order_item_id');
        if (is_numeric($itemId)) {
            $item = PurchaseOrderItem::with([
                'purchaseOrder.supplier',
                'purchaseOrder.referModel',
                'product.uom',
                'qualityControls',
                'referItemModel',
            ])->find((int) $itemId);
            if ($item) {
                return $item;
            }
        }

        $purchaseOrderId = static::queryPurchaseOrderId();

        if (! $purchaseOrderId) {
            return null;
        }

        $items = static::eligiblePurchaseOrderItems($purchaseOrderId);

        return $items->first();
    }

    public static function purchaseOrderItemOptionLabel(PurchaseOrderItem $item): string
    {
        $po = $item->purchaseOrder;
        $supplier = $po?->supplier;
        $product = $item->product;
        $poNumber = $po?->po_number ?? 'N/A';
        $supplierName = $supplier?->perusahaan ?? 'N/A';
        $productName = $product?->name ?? 'N/A';
        $ordered = $item->quantity ?? 0;
        $progress = static::purchaseOrderItemQcProgressSummary($item);
        $qcRemaining = static::purchaseOrderItemQcRemaining($item);
        $accepted = $qcRemaining['accepted'];
        $remaining = $qcRemaining['remaining'];
        $statusLabel = $progress['status_label'];
        $draftQty = static::draftQcPendingQuantity($item);

        $label = "PO: {$poNumber} - {$supplierName} - {$productName}"
            . " (Status QC: {$statusLabel} | Ordered: {$ordered} | Accepted: {$accepted} | Sisa: {$remaining})";

        if ($draftQty > 0) {
            $label .= " ⚠️ {$draftQty} pcs dikunci oleh draft QC lain";
        }

        return $label;
    }

    public static function formStateForPurchaseOrderItem(PurchaseOrderItem $item): array
    {
        $item->loadMissing([
            'product.uom',
            'qualityControls',
            'referItemModel',
            'purchaseOrder.supplier',
            'purchaseOrder.referModel',
        ]);

        $purchaseOrder = $item->purchaseOrder;
        $cabangId = static::resolveQcPurchaseCabangId($item, $purchaseOrder);
        $warehouseId = $purchaseOrder?->warehouse_id;
        $remainingQty = static::purchaseOrderItemQcRemaining($item)['remaining'];

        return [
            'from_model_id' => $item->id,
            'from_model_type' => PurchaseOrderItem::class,
            'product_name' => $item->product?->name ?? '',
            'sku' => $item->product?->sku ?? '',
            'uom' => $item->product?->uom?->name ?? '',
            'product_id' => $item->product_id,
            'cabang_id' => $cabangId,
            'warehouse_id' => static::warehouseMatchesQcPurchaseCabang($warehouseId ? (int) $warehouseId : null, $cabangId)
                ? (int) $warehouseId
                : null,
            'rak_id' => null,
            'quantity_received' => $remainingQty,
            'passed_quantity' => $remainingQty,
            'rejected_quantity' => 0,
            'total_inspected' => $remainingQty,
        ];
    }

    public static function applyPurchaseOrderItemStateToForm(PurchaseOrderItem $item, callable $set): void
    {
        foreach (static::formStateForPurchaseOrderItem($item) as $field => $value) {
            $set($field, $value);
        }
    }

    public static function getQcPurchasePurchaseOrderOptions(): array
    {
        $user = Auth::user();
        $query = PurchaseOrder::with(['supplier', 'warehouse', 'purchaseOrderItem.qualityControls', 'purchaseOrderItem.qualityControlItems']);

        if ($user && filled($user->warehouse_id) && ! $user->hasRole(['super_admin', 'Super Admin', 'Owner'])) {
            $query->where('warehouse_id', $user->warehouse_id);
        }

        return $query->whereIn('status', static::getQcPurchaseEligiblePurchaseOrderStatuses())
            ->get()
            ->filter(function (PurchaseOrder $purchaseOrder) {
                return $purchaseOrder->purchaseOrderItem->contains(function (PurchaseOrderItem $item) {
                    return static::purchaseOrderItemQcRemaining($item)['remaining'] > 0;
                });
            })
            ->mapWithKeys(function (PurchaseOrder $purchaseOrder) {
                $supplier = $purchaseOrder->supplier->perusahaan ?? 'N/A';
                $warehouse = $purchaseOrder->warehouse?->name ?? 'Gudang';
                $progress = static::purchaseOrderQcProgressSummary($purchaseOrder);

                return [$purchaseOrder->id => "PO: {$purchaseOrder->po_number} | {$supplier} | Gudang: {$warehouse} | Status QC: {$progress['status_label']}"];
            })
            ->all();
    }

    public static function purchaseOrderQcProgressSummary(PurchaseOrder $purchaseOrder): array
    {
        $purchaseOrder->loadMissing(['purchaseOrderItem.qualityControls']);

        $itemSummaries = $purchaseOrder->purchaseOrderItem->map(fn(PurchaseOrderItem $item) => static::purchaseOrderItemQcProgressSummary($item));

        $pendingCount = $itemSummaries->sum('pending_count');
        $processedCount = $itemSummaries->sum('processed_count');
        $remainingQuantity = (float) $itemSummaries->sum('remaining');

        if ($pendingCount === 0 && $processedCount === 0) {
            $statusLabel = 'Belum ada QC';
        } elseif ($remainingQuantity <= 0 && $processedCount > 0) {
            $statusLabel = 'QC Selesai';
        } elseif ($processedCount > 0) {
            $statusLabel = 'QC Partial';
        } else {
            $statusLabel = 'QC Pending';
        }

        return [
            'processed_count' => (int) $processedCount,
            'pending_count' => (int) $pendingCount,
            'remaining' => $remainingQuantity,
            'status_label' => $statusLabel,
        ];
    }

    public static function warehouseMatchesQcPurchaseCabang(?int $warehouseId, ?int $cabangId): bool
    {
        if (! $warehouseId || ! $cabangId) {
            return false;
        }

        return Warehouse::withoutGlobalScopes()
            ->whereKey($warehouseId)
            ->where('status', 1)
            ->where('cabang_id', $cabangId)
            ->exists();
    }

    public static function resolveBatchQcPurchaseCabangId(?int $purchaseOrderId, array $purchaseOrderItemIds = []): ?int
    {
        $normalizedItemIds = collect($purchaseOrderItemIds)
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();

        if ($normalizedItemIds->isNotEmpty()) {
            $cabangIds = PurchaseOrderItem::with([
                'referItemModel',
                'purchaseOrder.supplier',
                'purchaseOrder.referModel',
            ])
                ->whereIn('id', $normalizedItemIds)
                ->get()
                ->map(fn(PurchaseOrderItem $item) => static::resolveQcPurchaseCabangId($item))
                ->filter()
                ->unique()
                ->values();

            return $cabangIds->count() === 1 ? (int) $cabangIds->first() : null;
        }

        return null;
    }

    public static function syncQcQuantityAgainstReceived(callable $set, callable $get, ?string $changedField = null): void
    {
        $received = max(0, (float) ($get('quantity_received') ?? 0));
        $passed = max(0, (float) ($get('passed_quantity') ?? 0));

        // ensure passed quantity does not exceed received quantity
        if ($passed > $received) {
            $passed = $received;
        }

        $rejected = max(0, $received - $passed);

        $set('passed_quantity', $passed);
        $set('rejected_quantity', $rejected);
        $set('total_inspected', $received);
    }

    public static function draftQcLockInfo(PurchaseOrderItem $item, ?int $excludeQcId = null): array
    {
        $item->loadMissing([
            'qualityControls',
            'purchaseReceiptItem.purchaseReceipt',
            'product',
        ]);

        $ordered = (float) ($item->quantity ?? 0);

        $accepted = (float) $item->purchaseReceiptItem
            ->filter(fn($pri) => $pri->purchaseReceipt && in_array($pri->purchaseReceipt->status, ['completed', 'posted'], true))
            ->sum('qty_accepted');

        $processedPassed = (float) $item->qualityControls
            ->filter(fn(QualityControl $qc) => (int) ($qc->status ?? 0) === 1)
            ->sum('passed_quantity');

        $accepted = max($accepted, $processedPassed);

        $legacyDrafts = $item->qualityControls
            ->filter(fn(QualityControl $qc) =>
                (int) ($qc->status ?? 0) === 0
                && ($excludeQcId === null || (int) $qc->id !== $excludeQcId)
            );

        $multiDraftItems = \App\Models\QualityControlItem::with('qualityControl')
            ->where('purchase_order_item_id', $item->id)
            ->where('status', 0)
            ->whereHas('qualityControl', fn($q) => $q->where('status', 0)->when($excludeQcId, fn($sq) => $sq->where('id', '!=', $excludeQcId)))
            ->get();

        $lockedQty = 0.0;
        $draftQcNumbers = [];

        foreach ($legacyDrafts as $ld) {
            $lockedQty += (float) ($ld->quantity_received ?? $ld->passed_quantity ?? 0);
            if (filled($ld->qc_number)) {
                $draftQcNumbers[] = $ld->qc_number;
            }
        }

        foreach ($multiDraftItems as $mdi) {
            $lockedQty += (float) ($mdi->quantity_received ?? 0);
            if ($mdi->qualityControl && filled($mdi->qualityControl->qc_number)) {
                $draftQcNumbers[] = $mdi->qualityControl->qc_number;
            }
        }

        $draftQcNumbers = array_values(array_unique($draftQcNumbers));
        $remainingAllowed = max(0, $ordered - $accepted - $lockedQty);

        $message = "Sisa yang bisa di-QC {$remainingAllowed} pcs";
        if ($lockedQty > 0 && !empty($draftQcNumbers)) {
            $qcList = implode(', ', $draftQcNumbers);
            $message .= " ({$lockedQty} pcs sedang di {$qcList})";
        }

        return [
            'ordered'           => $ordered,
            'accepted'          => $accepted,
            'locked_qty'        => $lockedQty,
            'draft_qc_numbers'  => $draftQcNumbers,
            'remaining_allowed' => $remainingAllowed,
            'message'           => $message,
        ];
    }

    public static function purchaseOrderItemQcRemaining(PurchaseOrderItem $purchaseOrderItem, ?QualityControl $currentQualityControl = null): array
    {
        $purchaseOrderItem->loadMissing('qualityControls');

        $limit = OrderRequestQuantityLock::purchaseOrderItemReceiptLimit((int) $purchaseOrderItem->id);
        $orderedQuantity = (float) ($purchaseOrderItem->quantity ?? 0);
        $processedPassedQuantity = static::processedQcPassedQuantity($purchaseOrderItem);
        $acceptedQuantity = static::resolvedAcceptedQuantity(
            $processedPassedQuantity,
            (float) ($limit['accepted_quantity'] ?? 0)
        );

        $limitRemainingAccepted = (float) ($limit['remaining_accepted'] ?? 0);

        if (
            $currentQualityControl
            && $currentQualityControl->from_model_type === PurchaseOrderItem::class
            && (int) $currentQualityControl->from_model_id === (int) $purchaseOrderItem->id
        ) {
            $currentPassedQuantity = max(0, (float) ($currentQualityControl->passed_quantity ?? 0));
            $acceptedQuantity = max(0, $acceptedQuantity - $currentPassedQuantity);
            $limitRemainingAccepted = min($orderedQuantity, $limitRemainingAccepted + $currentPassedQuantity);
        }

        $remainingQuantity = static::resolvedRemainingQuantity(
            $orderedQuantity,
            $acceptedQuantity,
            $limitRemainingAccepted
        );

        $lockInfo = static::draftQcLockInfo($purchaseOrderItem, $currentQualityControl?->id);

        return [
            'ordered'           => $orderedQuantity,
            'accepted'          => $acceptedQuantity,
            'remaining'         => $remainingQuantity,
            'remaining_allowed' => $lockInfo['remaining_allowed'],
            'draft_locked'      => $lockInfo['locked_qty'],
            'message'           => $lockInfo['message'],
        ];
    }

    public static function purchaseOrderItemQcProgressSummary(PurchaseOrderItem $purchaseOrderItem): array
    {
        $purchaseOrderItem->loadMissing('qualityControls');

        $pendingQualityControls = $purchaseOrderItem->qualityControls
            ->filter(fn(QualityControl $qualityControl) => (int) ($qualityControl->status ?? 0) !== 1);
        $processedQualityControls = $purchaseOrderItem->qualityControls
            ->filter(fn(QualityControl $qualityControl) => (int) ($qualityControl->status ?? 0) === 1);

        $remaining = static::purchaseOrderItemQcRemaining($purchaseOrderItem)['remaining'];
        $processedCount = $processedQualityControls->count();
        $pendingCount = $pendingQualityControls->count();

        if ($processedCount === 0 && $pendingCount === 0) {
            $statusLabel = 'Belum ada QC';
        } elseif ($remaining <= 0 && $processedCount > 0) {
            $statusLabel = 'QC Selesai';
        } elseif ($processedCount > 0) {
            $statusLabel = 'QC Partial';
        } else {
            $statusLabel = 'QC Pending';
        }

        return [
            'processed_count' => $processedCount,
            'pending_count' => $pendingCount,
            'remaining' => $remaining,
            'status_label' => $statusLabel,
        ];
    }

    protected static function processedQcPassedQuantity(PurchaseOrderItem $purchaseOrderItem): float
    {
        return (float) $purchaseOrderItem->qualityControls
            ->filter(fn(QualityControl $qualityControl) => (int) ($qualityControl->status ?? 0) === 1)
            ->sum('passed_quantity');
    }

    /**
     * Hitung qty yang dikunci oleh draft QC lain (status = 0) yang belum diselesaikan.
     * Draft QC yang sedang diedit dikecualikan lewat parameter $excludeQcId.
     */
    protected static function draftQcPendingQuantity(
        PurchaseOrderItem $purchaseOrderItem,
        ?int $excludeQcId = null
    ): float {
        return static::draftQcLockInfo($purchaseOrderItem, $excludeQcId)['locked_qty'];
    }

    protected static function resolvedAcceptedQuantity(float $processedPassedQuantity, float $limitAcceptedQuantity): float
    {
        return max($processedPassedQuantity, $limitAcceptedQuantity);
    }

    protected static function resolvedRemainingQuantity(float $orderedQuantity, float $acceptedQuantity, float $limitRemainingQuantity): float
    {
        $remainingFromOrder = max(0, $orderedQuantity - $acceptedQuantity);

        return min($remainingFromOrder, max(0, $limitRemainingQuantity));
    }

    public static function validateQcQuantityAgainstReceived(callable $get, \Closure $fail, mixed $passedValue = null, mixed $rejectedValue = null): void
    {
        $received = (float) ($get('quantity_received') ?? 0);
        $passed = (float) ($passedValue ?? $get('passed_quantity') ?? 0);
        $rejected = (float) ($rejectedValue ?? $get('rejected_quantity') ?? 0);

        if ($passed > $received) {
            $fail("Passed quantity ({$passed}) tidak boleh melebihi Qty Received ({$received}).");
        }

        if (($passed + $rejected) > $received) {
            $fail("Total passed dan rejected ({$passed} + {$rejected}) tidak boleh melebihi Qty Received ({$received}).");
        }
    }

    public static function validateQcQuantityAgainstPurchaseOrderItem(callable $get, \Closure $fail, mixed $value, string $field, ?QualityControl $currentQualityControl = null): void
    {
        $purchaseOrderItemId = $get('from_model_id');

        if (! $purchaseOrderItemId) {
            return;
        }

        $item = PurchaseOrderItem::with('qualityControls')->find($purchaseOrderItemId);

        if (! $item) {
            return;
        }

        $qcData = static::purchaseOrderItemQcRemaining($item, $currentQualityControl);
        $remainingQty = $qcData['remaining_allowed'] ?? $qcData['remaining'];
        $numericValue = (float) ($value ?? 0);

        if ($numericValue > $remainingQty) {
            $label = match ($field) {
                'quantity_received' => 'Qty Diterima',
                'passed_quantity'   => 'Qty Passed',
                default             => 'Kuantitas',
            };

            $fail("{$label}: {$qcData['message']}");
        }
    }

    public static function validateQcPurchaseCreateQuantities(array $data): array
    {
        $purchaseOrderItemId = $data['from_model_id'] ?? null;

        if (! is_numeric($purchaseOrderItemId)) {
            return $data;
        }

        $item = PurchaseOrderItem::with('qualityControls')->find((int) $purchaseOrderItemId);

        if (! $item) {
            return $data;
        }

        $qcData = static::purchaseOrderItemQcRemaining($item);
        $remainingQty = $qcData['remaining_allowed'] ?? $qcData['remaining'];
        $quantityReceived = (float) ($data['quantity_received'] ?? 0);
        $passedQuantity = (float) ($data['passed_quantity'] ?? 0);
        $rejectedQuantity = (float) ($data['rejected_quantity'] ?? 0);
        $totalInspected = $passedQuantity + $rejectedQuantity;
        $messages = [];

        if ($quantityReceived > $remainingQty) {
            $messages['quantity_received'] = "Quantity Received ({$quantityReceived}) melebihi sisa qty yang perlu diinspeksi ({$remainingQty}).";
        }

        if ($passedQuantity > $quantityReceived) {
            $messages['passed_quantity'] = "Passed quantity ({$passedQuantity}) tidak boleh melebihi Qty Received ({$quantityReceived}).";
        }

        if ($passedQuantity > $remainingQty) {
            $messages['passed_quantity'] = "Passed quantity ({$passedQuantity}) melebihi sisa qty yang perlu diinspeksi ({$remainingQty}).";
        }

        if ($totalInspected > $quantityReceived) {
            $messages['rejected_quantity'] = "Total passed dan rejected ({$totalInspected}) tidak boleh melebihi Qty Received ({$quantityReceived}).";
        }

        if ($totalInspected > $remainingQty) {
            $messages['passed_quantity'] = "Total inspected ({$totalInspected}) melebihi sisa qty yang perlu diinspeksi ({$remainingQty}).";
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }

        return $data;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Informasi Purchase Order & Gudang')
                    ->description('Pilih Purchase Order yang datang. Gudang penerimaan dan cabang akuntansi akan dikunci secara otomatis.')
                    ->columns(3)
                    ->schema([
                        Select::make('purchase_order_id')
                            ->label('Purchase Order')
                            ->options(fn() => static::getQcPurchasePurchaseOrderOptions())
                            ->searchable()
                            ->reactive()
                            ->live()
                            ->required(fn(\Filament\Forms\Get $get, $context, ?QualityControl $record) => ! $get('from_model_id') && ($context === 'create' || ($record && $record->items()->exists())))
                            ->disabled(fn($context) => $context === 'edit')
                            ->dehydrated(true)
                            ->afterStateUpdated(function ($set, $state) {
                                $po = $state ? PurchaseOrder::with(['purchaseOrderItem.product.uom', 'warehouse'])->find($state) : null;
                                if ($po) {
                                    $set('warehouse_id', $po->warehouse_id);
                                    $set('cabang_id', $po->cabang_id ?? 1);
                                    $items = [];
                                    foreach ($po->purchaseOrderItem as $poItem) {
                                        $lockInfo = static::draftQcLockInfo($poItem);
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
                                    $set('items', $items);
                                } else {
                                    $set('items', []);
                                }
                            })
                            ->columnSpan(2)
                            ->validationMessages(['required' => 'Purchase Order harus dipilih']),

                        TextInput::make('qc_number')
                            ->label('QC Number')
                            ->default(function () {
                                return HelperController::generateUniqueCode('quality_controls', 'qc_number', 'QC-' . date('Ymd') . '-', 4);
                            })
                            ->required()
                            ->disabled(fn($context) => $context === 'edit')
                            ->dehydrated(true)
                            ->columnSpan(1),

                        Select::make('warehouse_id')
                            ->label('Gudang Penerimaan (Terkunci ke PO)')
                            ->options(\App\Models\Warehouse::pluck('name', 'id'))
                            ->disabled()
                            ->dehydrated(true)
                            ->required()
                            ->helperText('Gudang penerimaan barang terkunci mengikuti PO tujuan.')
                            ->columnSpan(1),

                        Select::make('cabang_id')
                            ->label('Cabang Akuntansi (Pusat)')
                            ->options(\App\Models\Cabang::pluck('nama', 'id'))
                            ->disabled()
                            ->dehydrated(true)
                            ->default(fn() => \App\Models\Cabang::where('kode', 'CBG-001')->value('id') ?? \App\Models\Cabang::first()?->id)
                            ->helperText('Beban akuntansi pembelian terpusat di Kantor Pusat.')
                            ->columnSpan(1),

                        Select::make('inspected_by')
                            ->label('Petugas QC (Inspected By)')
                            ->options(\App\Models\User::pluck('name', 'id'))
                            ->default(fn(?QualityControl $record) => $record?->inspected_by ?? Auth::id())
                            ->disabled()
                            ->dehydrated(true)
                            ->helperText('Terisi otomatis dengan akun login.')
                            ->required()
                            ->columnSpan(1),

                        DatePicker::make('date_send_stock')
                            ->default(\Carbon\Carbon::now())
                            ->label('Tanggal Kedatangan Barang')
                            ->columnSpan(1),

                        Textarea::make('notes')
                            ->label('Catatan QC / No. Surat Jalan Supplier')
                            ->rows(2)
                            ->columnSpan(2),

                        \Filament\Forms\Components\Toggle::make('auto_process')
                            ->label('Langsung Terbitkan Penerimaan Barang (GRN)')
                            ->helperText('Jika aktif, saat simpan akan langsung menghasilkan 1 nomor QC dan 1 nomor GRN (stok bertambah). Jika nonaktif, disimpan sebagai Draft QC (mengunci kuantitas).')
                            ->default(true)
                            ->visible(fn($context) => $context === 'create')
                            ->columnSpanFull(),
                    ]),

                Section::make('Tabel Kedatangan Barang (1 QC untuk Banyak Item)')
                    ->description('Tabel seluruh item dari PO yang dipilih. Isi kuantitas diterima, lolos, dan ditolak per baris.')
                    ->visible(fn(\Filament\Forms\Get $get, $context, ?QualityControl $record) => filled($get('purchase_order_id')) || ($record && $record->items()->exists()))
                    ->columnSpanFull()
                    ->schema([
                        \Filament\Forms\Components\Repeater::make('items')
                            ->label('Item Kedatangan')
                            ->defaultItems(0)
                            ->columns(12)
                            ->addable(false)
                            ->deletable(true)
                            ->reorderable(false)
                            ->columnSpanFull()
                            ->schema([
                                Hidden::make('purchase_order_item_id'),
                                Hidden::make('product_id'),
                                Hidden::make('remaining_allowed'),

                                TextInput::make('product_name')
                                    ->label('Produk')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(4),

                                TextInput::make('remaining_info')
                                    ->label('Info Sisa QC')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(2),

                                TextInput::make('quantity_received')
                                    ->label('Diterima')
                                    ->numeric()
                                    ->required(fn(\Filament\Forms\Get $get) => filled($get('../../purchase_order_id')))
                                    ->reactive()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $recv = max(0, (float) ($state ?? 0));
                                        $passed = min($recv, (float) ($get('passed_quantity') ?? $recv));
                                        $set('passed_quantity', $passed);
                                        $set('rejected_quantity', max(0, $recv - $passed));
                                    })
                                    ->columnSpan(2),

                                TextInput::make('passed_quantity')
                                    ->label('Lolos')
                                    ->numeric()
                                    ->required(fn(\Filament\Forms\Get $get) => filled($get('../../purchase_order_id')))
                                    ->reactive()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $recv = max(0, (float) ($get('quantity_received') ?? 0));
                                        $passed = max(0, (float) ($state ?? 0));
                                        if ($passed > $recv) {
                                            $passed = $recv;
                                            $set('passed_quantity', $passed);
                                        }
                                        $set('rejected_quantity', max(0, $recv - $passed));
                                    })
                                    ->columnSpan(2),

                                TextInput::make('rejected_quantity')
                                    ->label('Reject')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->columnSpan(2),

                                Select::make('failed_qc_action')
                                    ->label('Tindak Lanjut Barang Reject')
                                    ->options([
                                        \App\Models\PurchaseReturn::QC_ACTION_WAIT_NEXT_DELIVERY => 'Tunggu Pengganti (PO Tetap Terbuka)',
                                        \App\Models\PurchaseReturn::QC_ACTION_RETURN_SUPPLIER    => 'Retur ke Supplier (Buat Dokumen Retur)',
                                        \App\Models\PurchaseReturn::QC_ACTION_REDUCE_STOCK       => 'Batalkan Sisa PO (Kurangi Qty PO & Selesaikan)',
                                    ])
                                    ->default(\App\Models\PurchaseReturn::QC_ACTION_WAIT_NEXT_DELIVERY)
                                    ->required(fn(\Filament\Forms\Get $get) => filled($get('../../purchase_order_id')))
                                    ->visible(fn($get) => (float)($get('rejected_quantity') ?? 0) > 0)
                                    ->columnSpan(6),

                                TextInput::make('reason_reject')
                                    ->label('Alasan Reject')
                                    ->placeholder('Misal: Cacat fisik, kadaluarsa, dsb.')
                                    ->visible(fn($get) => (float)($get('rejected_quantity') ?? 0) > 0)
                                    ->columnSpan(6),

                                Select::make('rak_id')
                                    ->label('Rak Penyimpanan')
                                    ->options(function ($get) {
                                        $whId = $get('../../warehouse_id');
                                        if ($whId) {
                                            return Rak::where('warehouse_id', $whId)->get()->mapWithKeys(fn($r) => [$r->id => filled($r->code) ? "({$r->code}) {$r->name}" : ($r->name ?? '-')]);
                                        }
                                        return [];
                                    })
                                    ->searchable()
                                    ->columnSpan(6),
                            ]),
                    ]),

                // Backwards-compatible section for legacy single-item QC edit
                Section::make('Single Item QC (Legacy)')
                    ->visible(fn($context, ?QualityControl $record) => $context === 'edit' && $record && !$record->items()->exists() && empty($record->purchase_order_id))
                    ->columns(2)
                    ->schema([
                        TextInput::make('passed_quantity')->label('Passed Quantity')->numeric(),
                        TextInput::make('rejected_quantity')->label('Rejected Quantity')->numeric(),
                        Textarea::make('reason_reject')->label('Reason Reject'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('qc_number')
                    ->label('QC Number')
                    ->searchable(),
                TextColumn::make('supplier_name')
                    ->label('Supplier')
                    ->getStateUsing(function ($record) {
                        $supplier = $record->fromModel?->purchaseOrder?->supplier;
                        if ($supplier) {
                            return "({$supplier->code}) " . ($supplier->perusahaan ?? 'N/A');
                        }
                        return 'N/A';
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        return $query->whereHas('fromModel.purchaseOrder.supplier', function ($query) use ($search) {
                            return $query->where('perusahaan', 'LIKE', '%' . $search . '%')
                                ->orWhere('code', 'LIKE', '%' . $search . '%');
                        });
                    }),
                TextColumn::make('po_number')
                    ->label('PO Number')
                    ->getStateUsing(function ($record) {
                        return $record->fromModel?->purchaseOrder?->po_number
                            ?? $record->fromModel?->purchaseReceipt?->purchaseOrder?->po_number
                            ?? 'N/A';
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        return $query->whereHas('fromModel.purchaseOrder', function ($query) use ($search) {
                            return $query->where('po_number', 'LIKE', '%' . $search . '%');
                        });
                    }),
                TextColumn::make('product.name')
                    ->label('Product')
                    ->getStateUsing(function ($record) {
                        return $record->product?->name ?? 'N/A';
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        return $query->whereHas('product', function ($query) use ($search) {
                            return $query->where('name', 'LIKE', '%' . $search . '%')
                                ->orWhere('sku', 'LIKE', '%' . $search . '%');
                        });
                    }),
                TextColumn::make('inspectedBy.name')
                    ->label('Inspected By')
                    ->getStateUsing(function ($record) {
                        return $record->inspectedBy?->name ?? 'N/A';
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        return $query->whereHas('inspectedBy', function ($query) use ($search) {
                            return $query->where('name', 'LIKE', '%' . $search . '%');
                        });
                    }),
                TextColumn::make('passed_quantity')
                    ->label('Passed')
                    ->numeric(),
                TextColumn::make('rejected_quantity')
                    ->label('Rejected')
                    ->numeric(),
                TextColumn::make('status_formatted')
                    ->label('Status')
                    ->badge()
                    ->color(function (string $state): string {
                        return $state === 'Sudah diproses' ? 'success' : 'gray';
                    })
                    ->tooltip(function (TextColumn $column): string {
                        $state = (string) $column->getState();

                        if ($state === 'Sudah diproses') {
                            return 'QC sudah diproses. Hasil Passed/Rejected sudah final.';
                        }

                        return 'QC belum diproses. Passed Quantity masih draft sampai Process QC dijalankan.';
                    }),
            ])
            ->description(new \Illuminate\Support\HtmlString(
                '<details class="mb-4">' .
                    '<summary class="cursor-pointer font-semibold">Panduan Quality Control Purchase (QC Pembelian)</summary>' .
                    '<div class="mt-2 text-sm">' .
                    '<ul class="list-disc pl-5">' .
                    '<li><strong>Apa ini:</strong> Quality Control Purchase adalah proses inspeksi kualitas barang yang diterima dari supplier melalui Purchase Receipt.</li>' .
                    '<li><strong>Sumber:</strong> Dibuat otomatis dari <em>Purchase Receipt Item</em> saat barang diterima. Setiap item dalam receipt akan memiliki QC terpisah.</li>' .
                    '<li><strong>Komponen Utama:</strong> <em>QC Number</em> (nomor QC unik), <em>Purchase Receipt</em> (referensi penerimaan), <em>Product</em> (produk yang diinspeksi), <em>Inspected By</em> (petugas QC).</li>' .
                    '<li><strong>Quantity Control:</strong> <em>Passed Quantity</em> (jumlah lulus QC), <em>Rejected Quantity</em> (jumlah ditolak), <em>Total Quantity</em> (dari purchase receipt).</li>' .
                    '<li><strong>Status Flow:</strong> <em>Belum diproses</em> (menunggu inspeksi) → <em>Sudah diproses</em> (QC selesai, stock updated).</li>' .
                    '<li><strong>Validasi:</strong> <em>Quantity Check</em> - total passed + rejected harus sama dengan quantity receipt. <em>Stock Validation</em> - memastikan stock tersedia untuk update.</li>' .
                    '<li><strong>Integration:</strong> Terintegrasi dengan <em>Purchase Receipt</em> (sumber), <em>Purchase Order</em> (referensi PO), <em>Inventory</em> (update stock), dan <em>Return Product</em> (untuk rejected items).</li>' .
                    '<li><strong>Actions:</strong> <em>Process QC</em> (proses inspeksi - hanya untuk status belum diproses), <em>View/Edit</em> (lihat detail QC), <em>Delete</em> (hapus QC record).</li>' .
                    '<li><strong>Permissions:</strong> <em>view any quality control purchase</em>, <em>create quality control purchase</em>, <em>update quality control purchase</em>, <em>delete quality control purchase</em>, <em>restore quality control purchase</em>, <em>force-delete quality control purchase</em>.</li>' .
                    '<li><strong>Stock Impact:</strong> <em>Passed items</em> → stock bertambah di inventory. <em>Rejected items</em> → otomatis membuat Return Product untuk dikembalikan ke supplier.</li>' .
                    '<li><strong>Reporting:</strong> Menyediakan data untuk quality metrics, supplier performance, dan inventory accuracy tracking.</li>' .
                    '</ul>' .
                    '</div>' .
                    '</details>'
            ))
            ->headerActions([
                Action::make('batch_create_qc')
                    ->label('Batch Buat QC')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->modalWidth('5xl')
                    ->modalHeading('Batch Pembuatan Quality Control Purchase')
                    ->modalDescription('Pilih Purchase Order terlebih dahulu, lalu centang produk yang akan di-QC.')
                    ->form([
                        Section::make('Langkah 1 — Pilih Purchase Order')
                            ->columns(2)
                            ->schema([
                                Select::make('purchase_order_id')
                                    ->label('Purchase Order')
                                    ->options(fn() => static::getQcPurchasePurchaseOrderOptions())
                                    ->searchable()
                                    ->reactive()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($set, $state) {
                                        // Reset selected items when PO changes
                                        $set('selected_po_item_ids', []);
                                        $po = $state ? PurchaseOrder::find($state) : null;
                                        $set('warehouse_id', $po?->warehouse_id ?: null);
                                        $set('rak_id', null);
                                    })
                                    ->validationMessages(['required' => 'Purchase Order harus dipilih'])
                                    ->columnSpanFull(),
                            ]),
                        Section::make('Langkah 2 — Pilih Produk yang di-QC')
                            ->description('Centang produk dari PO yang dipilih untuk membuat QC. Hanya produk yang masih memiliki sisa qty yang ditampilkan.')
                            ->schema([
                                \Filament\Forms\Components\CheckboxList::make('selected_po_item_ids')
                                    ->label('Produk yang akan di-QC')
                                    ->options(function ($get) {
                                        $poId = $get('purchase_order_id');
                                        if (!$poId) {
                                            return [];
                                        }
                                        return PurchaseOrderItem::with(['product', 'qualityControls'])
                                            ->where('purchase_order_id', $poId)
                                            ->get()
                                            ->filter(function ($item) {
                                                if (!$item->product) return false;
                                                return static::purchaseOrderItemQcRemaining($item)['remaining'] > 0;
                                            })
                                            ->mapWithKeys(function ($item) {
                                                $product   = $item->product->name ?? 'N/A';
                                                $sku       = $item->product->sku ?? '';
                                                $progress  = static::purchaseOrderItemQcProgressSummary($item);
                                                $qcRemaining = static::purchaseOrderItemQcRemaining($item);
                                                $label     = "{$product}" . ($sku ? " ({$sku})" : '') . " — Status QC: {$progress['status_label']} | Dipesan: {$item->quantity} | Accepted: {$qcRemaining['accepted']} | Sisa QC: {$qcRemaining['remaining']}";
                                                return [$item->id => $label];
                                            });
                                    })
                                    ->columns(1)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($set, $get) {
                                        $poId = $get('purchase_order_id');
                                        $po = $poId ? PurchaseOrder::find($poId) : null;
                                        if (empty($po?->warehouse_id)) {
                                            $set('warehouse_id', null);
                                        }
                                        $set('rak_id', null);
                                    })
                                    ->validationMessages(['required' => 'Minimal satu produk harus dipilih'])
                                    ->columnSpanFull(),
                            ]),
                        Section::make('Langkah 3 — Pengaturan QC')
                            ->description('Pengaturan ini berlaku untuk semua produk yang di-QC dalam batch ini.')
                            ->columns(2)
                            ->schema([
                                Select::make('warehouse_id')
                                    ->label('Gudang')
                                    ->options(function ($get) {
                                        $cabangId = static::resolveBatchQcPurchaseCabangId(
                                            is_numeric($get('purchase_order_id')) ? (int) $get('purchase_order_id') : null,
                                            (array) ($get('selected_po_item_ids') ?? [])
                                        );

                                        return static::getQcPurchaseWarehouseOptions($cabangId);
                                    })
                                    ->searchable()
                                    ->required()
                                    ->reactive()
                                    ->disabled(function ($get) {
                                        $poId = is_numeric($get('purchase_order_id')) ? (int) $get('purchase_order_id') : null;
                                        if (!$poId) return false;
                                        $po = PurchaseOrder::find($poId);
                                        return !empty($po?->warehouse_id);
                                    })
                                    ->dehydrated(true)
                                    ->helperText(function ($get) {
                                        $poId = is_numeric($get('purchase_order_id')) ? (int) $get('purchase_order_id') : null;
                                        if (!$poId) return null;
                                        $po = PurchaseOrder::with('warehouse')->find($poId);
                                        if (!empty($po?->warehouse_id)) {
                                            $warehouseName = $po->warehouse->name ?? '-';
                                            return "Dikunci oleh PO: Gudang tujuan sudah ditetapkan ke \"{$warehouseName}\".";
                                        }
                                        return null;
                                    })
                                    ->rules([
                                        function ($get) {
                                            return function (string $attribute, $value, \Closure $fail) use ($get): void {
                                                $cabangId = static::resolveBatchQcPurchaseCabangId(
                                                    is_numeric($get('purchase_order_id')) ? (int) $get('purchase_order_id') : null,
                                                    (array) ($get('selected_po_item_ids') ?? [])
                                                );

                                                if (! static::warehouseMatchesQcPurchaseCabang(is_numeric($value) ? (int) $value : null, $cabangId)) {
                                                    $fail('Gudang harus sesuai dengan cabang produk PO yang dipilih.');
                                                }
                                            };
                                        },
                                    ])
                                    ->validationMessages(['required' => 'Gudang harus dipilih']),
                                Select::make('rak_id')
                                    ->label('Rak')
                                    ->options(function ($get) {
                                        $warehouseId = $get('warehouse_id');
                                        if ($warehouseId) {
                                            return Rak::where('warehouse_id', $warehouseId)
                                                ->get()
                                                ->mapWithKeys(fn($rak) => [$rak->id => filled($rak->code) ? "({$rak->code}) {$rak->name}" : ($rak->name ?? '-')]);
                                        }
                                        return [];
                                    })
                                    ->searchable(),
                                Select::make('inspected_by')
                                    ->label('Petugas QC (Inspected By)')
                                    ->options(\App\Models\User::pluck('name', 'id'))
                                    ->default(Auth::id())
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->helperText('Terisi otomatis dengan akun pengguna yang login dan tidak dapat diubah.')
                                    ->required()
                                    ->validationMessages(['required' => 'Petugas QC harus terisi']),
                                \Filament\Forms\Components\DatePicker::make('inspection_date')
                                    ->label('Tanggal Inspeksi')
                                    ->default(now())
                                    ->required(),
                                Textarea::make('notes')
                                    ->label('Catatan')
                                    ->nullable()
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->action(function (array $data) {
                        $created = 0;
                        $selectedItemIds = $data['selected_po_item_ids'] ?? [];
                        $inspectedBy = Auth::id();
                        $batchCabangId = static::resolveBatchQcPurchaseCabangId(
                            is_numeric($data['purchase_order_id'] ?? null) ? (int) $data['purchase_order_id'] : null,
                            (array) $selectedItemIds
                        );

                        if (! $batchCabangId) {
                            throw ValidationException::withMessages([
                                'selected_po_item_ids' => 'Produk yang dipilih berasal dari cabang berbeda atau cabangnya tidak ditemukan. Buat QC per cabang.',
                            ]);
                        }

                        $po = PurchaseOrder::find($data['purchase_order_id'] ?? null);
                        if (!empty($po?->warehouse_id)) {
                            $data['warehouse_id'] = $po->warehouse_id;
                        }

                        if (! static::warehouseMatchesQcPurchaseCabang(is_numeric($data['warehouse_id'] ?? null) ? (int) $data['warehouse_id'] : null, $batchCabangId)) {
                            throw ValidationException::withMessages([
                                'warehouse_id' => 'Gudang harus sesuai dengan cabang produk PO yang dipilih.',
                            ]);
                        }

                        $firstPoItem = PurchaseOrderItem::find(reset($selectedItemIds));
                        if (!$firstPoItem) return;

                        $qcNumber = HelperController::generateUniqueCode(
                            'quality_controls',
                            'qc_number',
                            'QC-P-' . date('Ymd') . '-',
                            4
                        );

                        $qc = QualityControl::create([
                            'from_model_type'   => \App\Models\PurchaseOrder::class,
                            'from_model_id'     => $po->id,
                            'purchase_order_id' => $po->id,
                            'qc_number'         => $qcNumber,
                            'product_id'        => $firstPoItem->product_id,
                            'warehouse_id'      => $data['warehouse_id'],
                            'rak_id'            => $data['rak_id'] ?? null,
                            'passed_quantity'   => 0,
                            'rejected_quantity' => 0,
                            'quantity_received' => 0,
                            'status'            => 0,
                            'inspected_by'      => $inspectedBy,
                            'notes'             => $data['notes'] ?? null,
                            'date_send_stock'   => $data['inspection_date'] ?? now(),
                            'cabang_id'         => $batchCabangId,
                        ]);

                        $totalReceived = 0;
                        $totalPassed = 0;

                        foreach ($selectedItemIds as $poItemId) {
                            $poItem = PurchaseOrderItem::find($poItemId);
                            if (!$poItem) continue;

                            $remainingQty = static::purchaseOrderItemQcRemaining($poItem)['remaining'];
                            if ($remainingQty <= 0) continue;

                            \App\Models\QualityControlItem::create([
                                'quality_control_id'     => $qc->id,
                                'purchase_order_item_id' => $poItem->id,
                                'product_id'             => $poItem->product_id,
                                'quantity_received'      => $remainingQty,
                                'passed_quantity'        => $remainingQty,
                                'rejected_quantity'      => 0,
                                'failed_qc_action'       => 'wait_next_delivery',
                                'rak_id'                 => $data['rak_id'] ?? null,
                                'status'                 => 0,
                            ]);

                            $totalReceived += $remainingQty;
                            $totalPassed += $remainingQty;
                        }

                        $qc->update([
                            'quantity_received' => $totalReceived,
                            'passed_quantity'   => $totalPassed,
                        ]);

                        HelperController::sendNotification(
                            isSuccess: true,
                            title: 'Batch QC Berhasil',
                            message: "1 Quality Control Purchase ({$qcNumber}) untuk " . count($selectedItemIds) . " item berhasil dibuat."
                        );
                    })
                    ->visible(fn() => Auth::user()?->can('create quality control purchase')),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        0 => 'Belum diproses',
                        1 => 'Sudah diproses',
                    ]),
                SelectFilter::make('warehouse_id')
                    ->label('Gudang')
                    ->options(function () {
                        $user = Auth::user();
                        $manageType = $user?->manage_type ?? [];
                        $query = Warehouse::where('status', 1);

                        if (!$user || !is_array($manageType) || !in_array('all', $manageType)) {
                            $query->where('cabang_id', $user?->cabang_id);
                        }

                        return $query->orderBy('name')
                            ->get()
                            ->mapWithKeys(function ($warehouse) {
                                return [$warehouse->id => "({$warehouse->kode}) {$warehouse->name}"];
                            });
                    }),
                Filter::make('supplier')
                    ->label('Supplier')
                    ->form([
                        Select::make('supplier_id')
                            ->label('Supplier')
                            ->options(function () {
                                return \App\Models\Supplier::where('status', 1)
                                    ->get()
                                    ->mapWithKeys(fn($supplier) => [$supplier->id => "({$supplier->code}) {$supplier->perusahaan}"]);
                            })
                            ->searchable(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['supplier_id'],
                            function ($query, $supplierId) {
                                return $query->where(function ($sub) use ($supplierId) {
                                    $sub->whereHas('fromModel.purchaseOrder', fn($q) => $q->where('supplier_id', $supplierId))
                                        ->orWhereHas('purchaseOrder', fn($q) => $q->where('supplier_id', $supplierId));
                                });
                            }
                        );
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()
                        ->visible(fn(QualityControl $record): bool => ! $record->status),
                    Action::make('process_qc')
                        ->label('Process QC')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(function ($record) {
                            return ! $record->status && ($record->passed_quantity > 0 || $record->rejected_quantity > 0 || $record->items()->exists());
                        })
                        ->modalHeading('Proses Quality Control')
                        ->modalDescription(function ($record) {
                            $passed = number_format((float) ($record->passed_quantity ?? 0), 0, ',', '.');
                            $rejected = number_format((float) ($record->rejected_quantity ?? 0), 0, ',', '.');
                            $prodName = optional($record->product)->name ?? 'Produk';
                            return "Item: {$prodName} | Qty Lulus: {$passed} | Qty Ditolak: {$rejected}.";
                        })
                        ->form(function ($record) {
                            if ((float) ($record->rejected_quantity ?? 0) <= 0 || $record->items()->exists()) {
                                return [];
                            }

                            return [
                                \Filament\Forms\Components\Radio::make('failed_qc_action')
                                    ->label('Tindak Lanjut Barang Ditolak (Rejected)')
                                    ->options([
                                        \App\Models\PurchaseReturn::QC_ACTION_WAIT_NEXT_DELIVERY => 'Tunggu Pengganti (PO tetap terbuka untuk pengiriman ulang supplier)',
                                        \App\Models\PurchaseReturn::QC_ACTION_RETURN_SUPPLIER    => 'Retur ke Supplier (Buat dokumen nota retur ke supplier)',
                                        \App\Models\PurchaseReturn::QC_ACTION_REDUCE_STOCK       => 'Batalkan Sisa PO (Kurangi kuantitas PO & tutup sesuai jumlah yang diterima)',
                                    ])
                                    ->default(\App\Models\PurchaseReturn::QC_ACTION_WAIT_NEXT_DELIVERY)
                                    ->required()
                                    ->helperText('Tentukan tindakan untuk barang yang tidak lolos QC agar PO tidak menggantung selamanya.'),
                            ];
                        })
                        ->modalSubmitActionLabel('Proses QC')
                        ->action(function ($record, array $data) {
                            try {
                                $qcService = new QualityControlService();
                                $purchaseReturnService = app(\App\Services\PurchaseReturnService::class);

                                if ($record->items()->exists()) {
                                    $qcService->completeQualityControl($record, $data);
                                } else {
                                    if ((float) ($record->rejected_quantity ?? 0) > 0 && ! empty($data['failed_qc_action'])) {
                                        $action = $data['failed_qc_action'];

                                        // Create PurchaseReturn record
                                        $purchaseReturn = $purchaseReturnService->createFromQualityControl($record, $action);

                                        // If reduce_stock selected, execute resolution to reduce PO qty immediately
                                        if ($action === \App\Models\PurchaseReturn::QC_ACTION_REDUCE_STOCK) {
                                            $purchaseReturnService->executeQcResolution($purchaseReturn);
                                        }
                                    }

                                    $qcService->completeQualityControl($record, $data);
                                }

                                $msg = "Quality Control Purchase Completed. 1 GRN Penerimaan telah berhasil diterbitkan.";
                                if ((float) ($record->rejected_quantity ?? 0) > 0) {
                                    $labels = [
                                        \App\Models\PurchaseReturn::QC_ACTION_WAIT_NEXT_DELIVERY => 'PO tetap terbuka menunggu pengganti supplier.',
                                        \App\Models\PurchaseReturn::QC_ACTION_RETURN_SUPPLIER    => 'Dokumen retur telah dibuat untuk pengembalian ke supplier.',
                                        \App\Models\PurchaseReturn::QC_ACTION_REDUCE_STOCK       => 'Kuantitas PO telah disesuaikan dengan jumlah diterima.',
                                    ];
                                    $actionLabel = $labels[$data['failed_qc_action'] ?? ''] ?? '';
                                    if ($actionLabel) {
                                        $msg .= " Tindak lanjut reject: {$actionLabel}";
                                    }
                                }

                                HelperController::sendNotification(isSuccess: true, title: "QC Berhasil Diproses", message: $msg);
                            } catch (Throwable $exception) {
                                Log::error('QualityControlPurchaseResource process_qc failed', [
                                    'quality_control_id' => $record->id,
                                    'error' => $exception->getMessage(),
                                ]);

                                ProcurementFailureNotifier::danger(
                                    'Gagal Memproses QC Pembelian',
                                    $exception,
                                    'QC pembelian belum berhasil diproses. Periksa hasil QC, gudang, dan data retur terkait lalu coba lagi.'
                                );
                            }
                        }),
                    DeleteAction::make(),
                ])
                    ->icon('heroicon-m-ellipsis-horizontal'),
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make('Quality Control Details')
                    ->schema([
                        TextEntry::make('qc_number')->label('QC Number'),
                        TextEntry::make('created_at')->date()->label('QC Date'),
                        TextEntry::make('product.name')->label('Product'),
                        TextEntry::make('product.sku')->label('SKU'),
                        TextEntry::make('warehouse.name')->label('Warehouse'),
                        TextEntry::make('warehouse.cabang.nama')->label('Cabang'),
                        TextEntry::make('rak.name')->label('Rack'),
                        TextEntry::make('status_formatted')->label('Status')->badge(),
                        TextEntry::make('status_notice')
                            ->label('Status Note')
                            ->getStateUsing(function (?QualityControl $record): string {
                                if (! $record) {
                                    return '-';
                                }

                                if ((int) $record->status === 1) {
                                    return 'Sudah diproses. Hasil QC sudah final dan siap digunakan oleh proses lanjutan.';
                                }

                                return 'Belum diproses. Passed Quantity masih draft sampai QC dijalankan melalui aksi Process QC.';
                            })
                            ->columnSpanFull(),
                        TextEntry::make('inspectedBy.name')->label('Inspected By'),
                        TextEntry::make('notes'),
                    ])->columns(2),
                InfolistSection::make('Purchase Information')
                    ->schema([
                        // QC Purchase is created from a PurchaseOrderItem, not a receipt item.
                        TextEntry::make('fromModel.purchaseOrder.po_number')->label('PO Number'),
                        TextEntry::make('fromModel.purchaseOrder.supplier.perusahaan')->label('Supplier'),
                        TextEntry::make('fromModel.quantity')->label('Ordered Quantity'),
                        TextEntry::make('qc_purchase_unit_price')
                            ->label('Unit Price')
                            ->getStateUsing(fn(QualityControl $record) => static::qcPurchaseMoneySummary($record)['unit_price']),
                        TextEntry::make('qc_purchase_currency')
                            ->label('Currency')
                            ->getStateUsing(fn(QualityControl $record) => static::qcPurchaseMoneySummary($record)['currency']),
                        TextEntry::make('qc_purchase_exchange_rate')
                            ->label('Exchange Rate')
                            ->getStateUsing(fn(QualityControl $record) => static::qcPurchaseMoneySummary($record)['exchange_rate']),
                        TextEntry::make('qc_purchase_unit_price_idr')
                            ->label('Unit Price (IDR)')
                            ->getStateUsing(fn(QualityControl $record) => static::qcPurchaseMoneySummary($record)['unit_price_idr']),
                        TextEntry::make('qc_purchase_accepted_value')
                            ->label('QC Accepted Value')
                            ->getStateUsing(fn(QualityControl $record) => static::qcPurchaseMoneySummary($record)['accepted_value']),
                        TextEntry::make('qc_purchase_accepted_value_idr')
                            ->label('QC Accepted Value (IDR)')
                            ->getStateUsing(fn(QualityControl $record) => static::qcPurchaseMoneySummary($record)['accepted_value_idr']),
                    ])->columns(2),
                InfolistSection::make('Quality Control Results')
                    ->schema([
                        TextEntry::make('fromModel.quantity')->label('Qty Order'),
                        TextEntry::make('quantity_received')->label('Qty Received'),
                        TextEntry::make('passed_quantity')->label('Qty Accepted')->color('success'),
                        TextEntry::make('rejected_quantity')->label('Qty Rejected')->color('danger'),
                        TextEntry::make('reason_reject')->label('Rejection Reason'),
                        TextEntry::make('date_send_stock')->date()->label('Date Send to Stock'),
                    ])->columns(3),
                InfolistSection::make('Item Hasil Pemeriksaan (Multi-Item QC)')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                TextEntry::make('product.name')->label('Produk'),
                                TextEntry::make('product.sku')->label('SKU'),
                                TextEntry::make('quantity_received')->label('Diterima')->numeric(),
                                TextEntry::make('passed_quantity')->label('Lolos')->numeric()->color('success'),
                                TextEntry::make('rejected_quantity')->label('Reject')->numeric()->color('danger'),
                                TextEntry::make('failed_qc_action')
                                    ->label('Tindak Lanjut')
                                    ->badge()
                                    ->formatStateUsing(fn($state) => match($state) {
                                        'reduce_stock' => 'Kurangi Qty PO',
                                        'return_supplier' => 'Retur ke Supplier',
                                        default => 'Tunggu Pengganti',
                                    }),
                                TextEntry::make('reason_reject')->label('Alasan Reject'),
                            ])->columns(7),
                    ])
                    ->visible(fn(?QualityControl $record) => $record && $record->items()->exists()),
                InfolistSection::make('Journal Entries')
                    ->headerActions([
                        \Filament\Infolists\Components\Actions\Action::make('view_journal_entries')
                            ->label('View All Journal Entries')
                            ->icon('heroicon-o-document-text')
                            ->color('primary')
                            ->url(function ($record) {
                                // Redirect to JournalEntryResource with filter for this quality control
                                $sourceType = urlencode(\App\Models\QualityControl::class);
                                $sourceId = $record->id;

                                return "/admin/journal-entries?tableFilters[source_type][value]={$sourceType}&tableFilters[source_id][value]={$sourceId}";
                            })
                            ->openUrlInNewTab()
                            ->visible(function ($record) {
                                return $record->journalEntries()->exists();
                            }),
                    ])
                    ->schema([
                        RepeatableEntry::make('journalEntries')
                            ->label('')
                            ->schema([
                                TextEntry::make('date')->date()->label('Date'),
                                TextEntry::make('coa.code')->label('COA'),
                                TextEntry::make('coa.name')->label('Account Name'),
                                TextEntry::make('debit')->rupiah()->label('Debit')->color('success'),
                                TextEntry::make('credit')->rupiah()->label('Credit')->color('danger'),
                                TextEntry::make('description')->label('Description'),
                                TextEntry::make('journal_type')->badge()->label('Type'),
                            ])->columns(4),
                    ])
                    ->columns(1)
                    ->visible(function ($record) {
                        return $record->journalEntries()->exists();
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQualityControlPurchases::route('/'),
            'create' => Pages\CreateQualityControlPurchase::route('/create'),
            'view' => Pages\ViewQualityControlPurchase::route('/{record}'),
            'edit' => Pages\EditQualityControlPurchase::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->where(function (Builder $q) {
                $q->where('from_model_type', 'App\Models\PurchaseOrderItem')
                    ->orWhere('from_model_type', 'App\Models\PurchaseOrder')
                    ->orWhereNotNull('purchase_order_id')
                    ->orWhereHas('items');
            })
            ->with([
                'product.uom',
                'fromModel.purchaseOrder.supplier',
                'purchaseOrder.supplier',
                'items.product',
                'inspectedBy',
                'warehouse.cabang',
                'rak'
            ]);

        $user = Auth::user();
        if ($user && filled($user->warehouse_id) && ! $user->hasRole(['super_admin', 'Super Admin', 'Owner'])) {
            $query->where('warehouse_id', $user->warehouse_id);
        } elseif ($user && !in_array('all', $user->manage_type ?? [])) {
            $query->whereHas('warehouse', function ($q) use ($user) {
                $q->where('cabang_id', $user->cabang_id);
            });
        }

        return $query;
    }
}
