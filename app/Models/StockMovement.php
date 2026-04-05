<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockMovement extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'stock_movements';
    protected $fillable = [
        'product_id',
        'warehouse_id',
        'quantity',
        'value',
        'type',
        'reference_id',
        'date',
        'notes',
        'meta',
        'rak_id',
        'from_model_type',
        'from_model_id',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'meta' => 'array',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id')->withDefault();
    }

    public function rak()
    {
        return $this->belongsTo(Rak::class, 'rak_id')->withDefault();
    }

    public function fromModel()
    {
        return $this->morphTo(__FUNCTION__, 'from_model_type', 'from_model_id')->withDefault();
    }

    public function getSourceTypeLabelAttribute(): string
    {
        return match ($this->from_model_type) {
            SaleOrder::class => 'Sales Order',
            PurchaseOrder::class => 'Purchase Order',
            DeliveryOrder::class, DeliveryOrderItem::class => 'Delivery Order',
            PurchaseReceipt::class, PurchaseReceiptItem::class => 'Purchase Receipt',
            StockTransfer::class, StockTransferItem::class => 'Stock Transfer',
            ManufacturingOrder::class => 'Manufacturing Order',
            MaterialIssue::class => 'Material Issue',
            StockAdjustment::class => 'Stock Adjustment',
            QualityControl::class => 'Quality Control',
            PurchaseReturn::class => 'Purchase Return',
            default => 'Unknown',
        };
    }

    public function getSourceNumberAttribute(): string
    {
        $source = $this->resolveSourceModel();

        if (! $source) {
            return 'N/A';
        }

        return match ($this->from_model_type) {
            SaleOrder::class => $source->so_number ?? 'N/A',
            PurchaseOrder::class => $source->po_number ?? 'N/A',
            DeliveryOrder::class => $source->do_number ?? 'N/A',
            DeliveryOrderItem::class => $source->deliveryOrder?->do_number ?? 'N/A',
            PurchaseReceipt::class => $source->receipt_number ?? 'N/A',
            PurchaseReceiptItem::class => $source->purchaseReceipt?->receipt_number ?? 'N/A',
            StockTransfer::class => $source->transfer_number ?? 'N/A',
            StockTransferItem::class => $source->stockTransfer?->transfer_number ?? 'N/A',
            ManufacturingOrder::class => $source->mo_number ?? 'N/A',
            MaterialIssue::class => $source->issue_number ?? 'N/A',
            StockAdjustment::class => $source->adjustment_number ?? 'N/A',
            QualityControl::class => $source->qc_number ?? 'N/A',
            PurchaseReturn::class => $source->nota_retur ?? 'N/A',
            default => 'N/A',
        };
    }

    public function getSourceDisplayAttribute(): string
    {
        $source = $this->resolveSourceModel();

        if (! $source) {
            return '-';
        }

        return $this->source_type_label . ' - ' . $this->source_number;
    }

    public function getSourceResourceUrlAttribute(): ?string
    {
        $source = $this->resolveSourceModel();

        if (! $source) {
            return null;
        }

        return match ($this->from_model_type) {
            SaleOrder::class => route('filament.admin.resources.sale-orders.view', $source->id),
            PurchaseOrder::class => route('filament.admin.resources.purchase-orders.view', $source->id),
            DeliveryOrder::class => route('filament.admin.resources.delivery-orders.view', $source->id),
            DeliveryOrderItem::class => $source->deliveryOrder
                ? route('filament.admin.resources.delivery-orders.view', $source->deliveryOrder->id)
                : null,
            PurchaseReceipt::class => route('filament.admin.resources.purchase-receipts.view', $source->id),
            PurchaseReceiptItem::class => $source->purchaseReceipt
                ? route('filament.admin.resources.purchase-receipts.view', $source->purchaseReceipt->id)
                : null,
            StockTransfer::class => route('filament.admin.resources.stock-transfers.view', $source->id),
            StockTransferItem::class => $source->stockTransfer
                ? route('filament.admin.resources.stock-transfers.view', $source->stockTransfer->id)
                : null,
            ManufacturingOrder::class => route('filament.admin.resources.manufacturing-orders.view', $source->id),
            MaterialIssue::class => route('filament.admin.resources.material-issues.view', $source->id),
            StockAdjustment::class => route('filament.admin.resources.stock-adjustments.view', $source->id),
            QualityControl::class => route('filament.admin.resources.quality-control-manufactures.view', $source->id),
            default => null,
        };
    }

    protected function resolveSourceModel(): ?EloquentModel
    {
        if (! $this->from_model_type || ! $this->from_model_id) {
            return null;
        }

        $source = $this->fromModel;

        if (! $source instanceof EloquentModel || ! $source->exists) {
            return null;
        }

        return $source;
    }
}
