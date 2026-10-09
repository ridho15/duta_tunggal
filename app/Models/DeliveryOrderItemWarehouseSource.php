<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryOrderItemWarehouseSource extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;

    protected $table = 'delivery_order_item_warehouse_sources';

    protected $fillable = [
        'delivery_order_item_id',
        'warehouse_id',
        'rak_id',
        'quantity',
    ];

    protected static function booted()
    {
        static::created(function ($source) {
            $item = $source->deliveryOrderItem;
            $do = $item?->deliveryOrder;
            if ($do && ! in_array($do->status, ['closed', 'reject'], true)) {
                app(\App\Observers\DeliveryOrderObserver::class)->createStockMovementsForDeliveryOrder($do);
            }
        });

        static::deleted(function ($source) {
            $item = $source->deliveryOrderItem;
            $do = $item?->deliveryOrder;
            if ($do && ! in_array($do->status, ['closed', 'reject'], true)) {
                $net = \App\Services\DeliveryShipments::netShipped($item, (int) $source->warehouse_id, $source->rak_id);
                if ($net > 0 && $item->product) {
                    app(\App\Services\ProductService::class)->createStockMovement(
                        product_id: $item->product_id,
                        warehouse_id: $source->warehouse_id,
                        quantity: $net,
                        type: 'adjustment_in',
                        date: now()->toDateString(),
                        notes: "Hapus alokasi gudang DO {$do->do_number}: stok dikembalikan",
                        rak_id: $source->rak_id,
                        fromModel: $item,
                        value: $item->product->cost_price * $net,
                        meta: ['delivery_reversal' => true, 'source' => 'warehouse_source_deleted']
                    );
                }
            }
        });
    }

    public function deliveryOrderItem()
    {
        return $this->belongsTo(DeliveryOrderItem::class, 'delivery_order_item_id')->withDefault();
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id')->withDefault();
    }

    public function rak()
    {
        return $this->belongsTo(Rak::class, 'rak_id')->withDefault();
    }
}
