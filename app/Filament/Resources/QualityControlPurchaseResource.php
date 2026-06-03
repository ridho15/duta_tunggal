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
        return Auth::user()?->hasRole(['Super Admin', 'Owner']) === true;
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
            ->when($purchaseOrderId, fn(Builder $query) => $query->where('purchase_order_id', $purchaseOrderId))
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
        $purchaseOrderId = static::queryPurchaseOrderId();

        if (! $purchaseOrderId) {
            return null;
        }

        $items = static::eligiblePurchaseOrderItems($purchaseOrderId);

        return $items->count() === 1 ? $items->first() : null;
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

        return "PO: {$poNumber} - {$supplierName} - {$productName}"
            . " (Status QC: {$statusLabel} | Ordered: {$ordered} | Accepted: {$accepted} | Sisa: {$remaining})";
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
        return PurchaseOrder::with(['supplier', 'purchaseOrderItem.qualityControls'])
            ->whereIn('status', static::getQcPurchaseEligiblePurchaseOrderStatuses())
            ->get()
            ->filter(function (PurchaseOrder $purchaseOrder) {
                return $purchaseOrder->purchaseOrderItem->contains(function (PurchaseOrderItem $item) {
                    return static::purchaseOrderItemQcRemaining($item)['remaining'] > 0;
                });
            })
            ->mapWithKeys(function (PurchaseOrder $purchaseOrder) {
                $supplier = $purchaseOrder->supplier->perusahaan ?? 'N/A';
                $progress = static::purchaseOrderQcProgressSummary($purchaseOrder);

                return [$purchaseOrder->id => "PO: {$purchaseOrder->po_number} | {$supplier} | Status QC: {$progress['status_label']}"];
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

        return [
            'ordered' => $orderedQuantity,
            'accepted' => $acceptedQuantity,
            'remaining' => $remainingQuantity,
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

    protected static function resolvedAcceptedQuantity(float $processedPassedQuantity, float $limitAcceptedQuantity): float
    {
        // Use the greater value so mixed pending/processed QC rows never undercount
        // already accepted quantity, but pending rows still do not count as accepted.
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

        $remainingQty = static::purchaseOrderItemQcRemaining($item, $currentQualityControl)['remaining'];
        $numericValue = (float) ($value ?? 0);

        if ($numericValue > $remainingQty) {
            $label = match ($field) {
                'quantity_received' => 'Quantity Received',
                'passed_quantity' => 'Passed quantity',
                default => 'Quantity',
            };

            $fail("{$label} ({$numericValue}) melebihi sisa qty yang perlu diinspeksi ({$remainingQty}).");
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

        $remainingQty = static::purchaseOrderItemQcRemaining($item)['remaining'];
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
                Fieldset::make('Form Quality Control Purchase')
                    ->schema([
                        Section::make('From Purchase Order Item')
                            ->description('Quality Control dibuat dari Purchase Order Item. Alur: PO → QC → Purchase Receipt (dibuat otomatis).')
                            ->columns(2)
                            ->columnSpanFull()
                            ->schema([
                                Select::make('from_model_id')
                                    ->label('Purchase Order Item')
                                    ->options(function ($context, $get) {
                                        if ($context === 'create') {
                                            return static::eligiblePurchaseOrderItems(static::queryPurchaseOrderId())
                                                ->mapWithKeys(fn(PurchaseOrderItem $item) => [$item->id => static::purchaseOrderItemOptionLabel($item)])
                                                ->all();
                                        }

                                        return PurchaseOrderItem::with(['purchaseOrder.supplier', 'product', 'qualityControls'])
                                            ->get()
                                            ->filter(fn(PurchaseOrderItem $item) => $item->purchaseOrder && $item->purchaseOrder->supplier && $item->product)
                                            ->mapWithKeys(fn(PurchaseOrderItem $item) => [$item->id => static::purchaseOrderItemOptionLabel($item)])
                                            ->all();
                                    })
                                    ->searchable()
                                    ->reactive()
                                    ->disabled(fn($context) => $context === 'edit') // Disable saat edit
                                    ->dehydrated(fn($context) => $context !== 'edit') // Jangan kirim data saat edit
                                    ->afterStateUpdated(function ($set, $get, $state, $context) {
                                        // Skip afterStateUpdated in edit mode since field is disabled
                                        if ($context === 'edit') {
                                            return;
                                        }

                                        $purchaseOrderItemId = $get('from_model_id');
                                        if ($purchaseOrderItemId) {
                                            $item = PurchaseOrderItem::with([
                                                'product.uom',
                                                'qualityControls',
                                                'referItemModel',
                                                'purchaseOrder.supplier',
                                                'purchaseOrder.referModel',
                                            ])->find($purchaseOrderItemId);
                                            if ($item) {
                                                static::applyPurchaseOrderItemStateToForm($item, $set);
                                            }
                                        } else {
                                            $set('total_inspected', 0);
                                        }
                                    })
                                    ->required(fn($context) => $context !== 'edit') // Required hanya saat create
                                    ->validationMessages([
                                        'required' => 'Purchase Order Item harus dipilih'
                                    ]),
                                \Filament\Forms\Components\Hidden::make('from_model_type')
                                    ->default('App\Models\PurchaseOrderItem')
                                    ->dehydrated(true),
                                Select::make('cabang_id')
                                    ->label('Cabang')
                                    ->options(\App\Models\Cabang::pluck('nama', 'id'))
                                    ->disabled()
                                    ->dehydrated(true),
                                TextInput::make('qc_number')
                                    ->label('QC Number')
                                    ->default(function () {
                                        return HelperController::generateUniqueCode('quality_controls', 'qc_number', 'QC-P-' . date('Ymd') . '-', 4);
                                    })
                                    ->required(fn($context) => $context !== 'edit') // Required hanya saat create
                                    ->disabled(fn($context) => $context === 'edit')
                                    ->dehydrated(fn($context) => $context !== 'edit')
                                    ->rules(function ($context) {
                                        // Tidak ada validasi apapun saat edit
                                        if ($context === 'edit') {
                                            return [];
                                        }
                                        // Validasi normal saat create
                                        return ['required', 'unique:quality_controls,qc_number'];
                                    })
                                    ->validationMessages([
                                        'required' => 'QC Number wajib diisi',
                                        'unique' => 'QC Number sudah digunakan'
                                    ])
                                    ->suffixAction(
                                        ActionsAction::make('generateQcNumber')
                                            ->label('Generate')
                                            ->icon('heroicon-o-arrow-path')
                                            ->action(function ($set) {
                                                $set('qc_number', HelperController::generateUniqueCode('quality_controls', 'qc_number', 'QC-P-' . date('Ymd') . '-', 4));
                                            })
                                            ->hidden(fn($context) => $context === 'edit')
                                    ),
                            ]),
                        Section::make('Product Information')
                            ->columns(2)
                            ->schema([
                                TextInput::make('product_name')
                                    ->label('Product')
                                    ->formatStateUsing(function ($state, $get) {
                                        return $state;
                                    })
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('sku')
                                    ->label('SKU')
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('uom')
                                    ->label('Unit of Measure')
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('quantity_received')
                                    ->label('Quantity Received')
                                    ->numeric()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function (callable $set, callable $get) {
                                        static::syncQcQuantityAgainstReceived($set, $get, 'quantity_received');
                                    })
                                    ->helperText('Jumlah barang yang datang/diterima dari supplier')
                                    ->rules([
                                        function ($get) {
                                            return function (string $attribute, $value, \Closure $fail) use ($get): void {
                                                static::validateQcQuantityAgainstPurchaseOrderItem($get, $fail, $value, 'quantity_received');
                                            };
                                        },
                                    ])
                                    ->validationMessages([
                                        'required' => 'Quantity Received wajib diisi',
                                        'numeric'  => 'Quantity Received harus berupa angka',
                                    ])
                                    ->dehydrated(true),
                                \Filament\Forms\Components\Hidden::make('product_id')
                                    ->dehydrated(true),
                                Select::make('warehouse_id')
                                    ->label('Gudang')
                                    ->options(function ($get) {
                                        $cabangId = $get('cabang_id')
                                            ?: static::resolveQcPurchaseCabangIdFromPurchaseOrderItemId(
                                                is_numeric($get('from_model_id')) ? (int) $get('from_model_id') : null
                                            );

                                        return static::getQcPurchaseWarehouseOptions($cabangId ? (int) $cabangId : null);
                                    })
                                    ->searchable(['kode', 'name'])
                                    ->required()
                                    ->reactive()
                                    ->rules([
                                        function ($get) {
                                            return function (string $attribute, $value, \Closure $fail) use ($get): void {
                                                $cabangId = $get('cabang_id')
                                                    ?: static::resolveQcPurchaseCabangIdFromPurchaseOrderItemId(
                                                        is_numeric($get('from_model_id')) ? (int) $get('from_model_id') : null
                                                    );

                                                if (! static::warehouseMatchesQcPurchaseCabang(is_numeric($value) ? (int) $value : null, $cabangId ? (int) $cabangId : null)) {
                                                    $fail('Gudang harus sesuai dengan cabang Permintaan Pembelian/Purchase Order.');
                                                }
                                            };
                                        },
                                    ])
                                    ->validationMessages([
                                        'required' => 'Warehouse harus dipilih'
                                    ]),
                                Select::make('rak_id')
                                    ->label('Rak')
                                    ->options(function ($get) {
                                        $warehouseId = $get('warehouse_id');
                                        if ($warehouseId) {
                                            return Rak::where('warehouse_id', $warehouseId)
                                                ->get()
                                                ->mapWithKeys(function ($rak) {
                                                    return [$rak->id => "({$rak->code}) {$rak->name}"];
                                                });
                                        }
                                        return [];
                                    })
                                    ->searchable(['code', 'name'])
                                    ->preload(),
                            ]),
                        Section::make('Quality Control Result')
                            ->columns(3)
                            ->schema([
                                TextInput::make('passed_quantity')
                                    ->label('Passed Quantity')
                                    ->numeric()
                                    ->required()
                                    ->reactive()
                                    ->rules([
                                        function ($get, $livewire) {
                                            return function (string $attribute, $value, \Closure $fail) use ($get, $livewire) {
                                                $purchaseOrderItemId = $get('from_model_id');
                                                if ($purchaseOrderItemId) {
                                                    $currentQualityControl = $livewire->record instanceof QualityControl
                                                        ? $livewire->record
                                                        : null;

                                                    static::validateQcQuantityAgainstPurchaseOrderItem($get, $fail, $value, 'passed_quantity', $currentQualityControl);
                                                }

                                                static::validateQcQuantityAgainstReceived($get, $fail, $value);
                                            };
                                        }
                                    ])
                                    ->validationMessages([
                                        'required' => 'Passed Quantity wajib diisi',
                                        'numeric' => 'Passed Quantity harus berupa angka'
                                    ])
                                    ->afterStateUpdated(function ($set, $get) {
                                        static::syncQcQuantityAgainstReceived($set, $get, 'passed_quantity');
                                    }),
                                TextInput::make('rejected_quantity')
                                    ->label('Rejected Quantity')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(true),
                                TextInput::make('total_inspected')
                                    ->label('Total Inspected')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->reactive(),
                            ]),
                        Section::make('Additional Information')
                            ->columns(2)
                            ->schema([
                                Placeholder::make('qc_status_notice')
                                    ->label('QC Status')
                                    ->content(function (?QualityControl $record): string {
                                        if (! $record) {
                                            return 'Status QC akan ditampilkan setelah record dibuka.';
                                        }

                                        if ((int) $record->status === 1) {
                                            return 'Sudah diproses. Perubahan nilai hasil QC akan mengikuti proses QC yang sudah selesai.';
                                        }

                                        return 'Belum diproses. Passed Quantity yang terlihat masih bersifat draft dan belum dihitung sebagai QC selesai sampai tombol Process QC dijalankan.';
                                    })
                                    ->columnSpanFull(),
                                Select::make('inspected_by')
                                    ->label('Inspected By')
                                    ->options(\App\Models\User::pluck('name', 'id'))
                                    ->default(fn(?QualityControl $record) => $record?->inspected_by ?? Auth::id())
                                    ->disabled(fn() => !static::canChooseInspector())
                                    ->dehydrated(true)
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Inspected By harus dipilih'
                                    ]),
                                DatePicker::make('date_send_stock')
                                    ->default(\Carbon\Carbon::now())
                                    ->label('Date Send to Stock'),
                                Textarea::make('notes')
                                    ->label('Notes')
                                    ->rows(3),
                                Textarea::make('reason_reject')
                                    ->label('Reason Reject')
                                    ->rows(3),
                            ]),
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
                                    ->afterStateUpdated(function ($set) {
                                        // Reset selected items when PO changes
                                        $set('selected_po_item_ids', []);
                                        $set('warehouse_id', null);
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
                                    ->afterStateUpdated(function ($set) {
                                        $set('warehouse_id', null);
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
                                                ->mapWithKeys(fn($rak) => [$rak->id => "({$rak->code}) {$rak->name}"]);
                                        }
                                        return [];
                                    })
                                    ->searchable(),
                                Select::make('inspected_by')
                                    ->label('Inspected By')
                                    ->options(\App\Models\User::pluck('name', 'id'))
                                    ->default(Auth::id())
                                    ->disabled(fn() => !static::canChooseInspector())
                                    ->dehydrated(true)
                                    ->required()
                                    ->validationMessages(['required' => 'Inspected By harus dipilih']),
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
                        $inspectedBy = static::canChooseInspector() ? ($data['inspected_by'] ?? Auth::id()) : Auth::id();
                        $batchCabangId = static::resolveBatchQcPurchaseCabangId(
                            is_numeric($data['purchase_order_id'] ?? null) ? (int) $data['purchase_order_id'] : null,
                            (array) $selectedItemIds
                        );

                        if (! $batchCabangId) {
                            throw ValidationException::withMessages([
                                'selected_po_item_ids' => 'Produk yang dipilih berasal dari cabang berbeda atau cabangnya tidak ditemukan. Buat QC per cabang.',
                            ]);
                        }

                        if (! static::warehouseMatchesQcPurchaseCabang(is_numeric($data['warehouse_id'] ?? null) ? (int) $data['warehouse_id'] : null, $batchCabangId)) {
                            throw ValidationException::withMessages([
                                'warehouse_id' => 'Gudang harus sesuai dengan cabang produk PO yang dipilih.',
                            ]);
                        }

                        foreach ($selectedItemIds as $poItemId) {
                            $poItem = PurchaseOrderItem::with([
                                'product',
                                'qualityControls',
                                'referItemModel',
                                'purchaseOrder.supplier',
                                'purchaseOrder.referModel',
                            ])->find($poItemId);
                            if (!$poItem) continue;
                            $itemCabangId = static::resolveQcPurchaseCabangId($poItem);

                            if ((int) $itemCabangId !== (int) $batchCabangId) {
                                throw ValidationException::withMessages([
                                    'selected_po_item_ids' => 'Produk yang dipilih berasal dari cabang berbeda. Buat QC per cabang.',
                                ]);
                            }

                            // Check remaining qty (partial QC support)
                            $remainingQty = static::purchaseOrderItemQcRemaining($poItem)['remaining'];
                            if ($remainingQty <= 0) continue; // no more qty to inspect

                            $qcNumber = HelperController::generateUniqueCode(
                                'quality_controls',
                                'qc_number',
                                'QC-P-' . date('Ymd') . '-',
                                4
                            );

                            QualityControl::create([
                                'from_model_type'   => \App\Models\PurchaseOrderItem::class,
                                'from_model_id'     => $poItemId,
                                'qc_number'         => $qcNumber,
                                'product_id'        => $poItem->product_id,
                                'warehouse_id'      => $data['warehouse_id'],
                                'rak_id'            => $data['rak_id'] ?? null,
                                'passed_quantity'   => $remainingQty,
                                'rejected_quantity' => 0,
                                'quantity_received' => $remainingQty,
                                'status'            => 0,
                                'inspected_by'      => $inspectedBy,
                                'notes'             => $data['notes'] ?? null,
                                'date_send_stock'   => $data['inspection_date'] ?? now(),
                                'cabang_id'         => $itemCabangId,
                            ]);
                            $created++;
                        }

                        HelperController::sendNotification(
                            isSuccess: true,
                            title: 'Batch QC Berhasil',
                            message: "{$created} Quality Control Purchase berhasil dibuat."
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
                        \Filament\Forms\Components\Select::make('supplier_id')
                            ->label('Supplier')
                            ->searchable()
                            ->preload()
                            ->options(function () {
                                return \App\Models\Supplier::all()->mapWithKeys(function ($supplier) {
                                    return [$supplier->id => "({$supplier->code}) " . ($supplier->perusahaan ?? '')];
                                });
                            }),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['supplier_id'])) {
                            return $query;
                        }
                        return $query->whereHas('fromModel.purchaseOrder', function (Builder $query) use ($data) {
                            $query->where('supplier_id', $data['supplier_id']);
                        });
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (empty($data['supplier_id'])) return null;
                        $supplier = \App\Models\Supplier::find($data['supplier_id']);
                        return $supplier ? 'Supplier: ' . ($supplier->perusahaan ?? '') : null;
                    }),
                Filter::make('po_number_filter')
                    ->label('PO Number')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('po_number')
                            ->label('PO Number')
                            ->placeholder('Cari PO Number...'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['po_number'])) {
                            return $query;
                        }
                        return $query->whereHas('fromModel.purchaseOrder', function (Builder $query) use ($data) {
                            $query->where('po_number', 'LIKE', '%' . $data['po_number'] . '%');
                        });
                    })
                    ->indicateUsing(function (array $data): ?string {
                        return !empty($data['po_number']) ? 'PO: ' . $data['po_number'] : null;
                    }),
                Filter::make('created_at')
                    ->label('Tanggal QC')
                    ->form([
                        DatePicker::make('created_from')->label('Dari Tanggal'),
                        DatePicker::make('created_until')->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    Action::make('process_qc')
                        ->label('Process QC')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(function ($record) {
                            // Sembunyikan action jika passed_quantity = 0 atau sudah diproses
                            return !$record->status && $record->passed_quantity > 0;
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Konfirmasi Process QC')
                        ->modalDescription(fn($record) => "Passed: {$record->passed_quantity}, Rejected: {$record->rejected_quantity}. Apakah Anda yakin ingin memproses QC ini?")
                        ->modalSubmitActionLabel('Proses QC')
                        ->action(function ($record, array $data) {
                            try {
                                $qcService     = new QualityControlService();

                                $qcService->completeQualityControl($record, []);
                                HelperController::sendNotification(isSuccess: true, title: "Information", message: "Quality Control Purchase Completed. Proses selanjutnya: Tim Gudang perlu memperbarui stok penerimaan barang dan memastikan Purchase Order ditandai sebagai selesai.");
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
            ->where('from_model_type', 'App\Models\PurchaseOrderItem')
            ->with([
                'product.uom',
                'fromModel.purchaseOrder.supplier',
                'inspectedBy',
                'warehouse.cabang',
                'rak'
            ]);

        $user = Auth::user();
        if ($user && !in_array('all', $user->manage_type ?? [])) {
            $query->whereHas('warehouse', function ($q) use ($user) {
                $q->where('cabang_id', $user->cabang_id);
            });
        }

        return $query;
    }
}
