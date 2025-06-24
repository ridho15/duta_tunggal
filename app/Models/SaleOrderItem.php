<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleOrderItem extends Model
{
    use SoftDeletes, HasFactory,LogsGlobalActivity;
    protected $table = 'sale_order_items';
    protected $fillable = [
        'sale_order_id',
        'product_id',
        'quantity',
        'unit_price',
        'discount',
        'tax'
    ];

    public function saleOrder()
    {
        return $this->belongsTo(SaleOrder::class, 'sale_order_id')->withDefault();
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }

    public function purchaseOrderItem()
    {
        return $this->morphMany(PurchaseOrderItem::class, 'refer_item_model');
    }
}
