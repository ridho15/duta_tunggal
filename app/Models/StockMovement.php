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

    /**
     * Movement types that increase InventoryStock.qty_available.
     * Single source of truth for StockMovementObserver and every stock
     * recompute/reconciliation path — keep these in sync or recomputes
     * will silently erase movements the observer already applied.
     */
    public const IN_TYPES = ['purchase_in', 'transfer_in', 'manufacture_in', 'adjustment_in', 'customer_return', 'do_return', 'return_in', 'sales_return_in'];

    /**
     * Movement types that decrease InventoryStock.qty_available.
     */
    public const OUT_TYPES = ['sales', 'pos_sale', 'transfer_out', 'manufacture_out', 'adjustment_out', 'purchase_return', 'return_out', 'purchase_return_out'];

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
            CustomerReturn::class => 'Customer Return',
            ReturnProduct::class, ReturnProductItem::class => 'Return Product',
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
            CustomerReturn::class => $source->return_number ?? 'N/A',
            ReturnProduct::class => $source->return_number ?? 'N/A',
            ReturnProductItem::class => $source->returnProduct?->return_number ?? 'N/A',
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

    protected static function booted()
    {
        static::creating(function ($movement) {
            if (! empty($movement->date)) {
                $parsed = \Carbon\Carbon::parse($movement->date);
                if ($parsed->format('H:i:s') === '00:00:00') {
                    $now = \Carbon\Carbon::now();
                    $movement->date = $parsed->setTime($now->hour, $now->minute, $now->second)->format('Y-m-d H:i:s');
                }
            } else {
                $movement->date = \Carbon\Carbon::now()->format('Y-m-d H:i:s');
            }
        });
    }
}
