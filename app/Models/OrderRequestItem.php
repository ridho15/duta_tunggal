<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderRequestItem extends Model
{
    use SoftDeletes, HasFactory;
    protected $table = 'order_request_items';
    protected $fillable = [
        'order_request_id',
        'product_id',
        'quantity',
        'note'
    ];

    public function orderRequest()
    {
        return $this->belongsTo(OrderRequest::class, 'order_request_id')->withDefault();
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }
}
