<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleOrder extends Model
{
    use SoftDeletes;
    protected $table = 'sale_orders';
    protected $fillable = [
        'customer_id',
        'so_number',
        'order_date',
        'status',
        'delivery_date',
        'total_amount'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id')->withDefault();
    }

    public function saleOrderItem()
    {
        return $this->hasMany(SaleOrderItem::class, 'sale_order_id');
    }
}
