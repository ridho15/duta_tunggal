<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Centang checker per barang DO (Tahap 3, layar Siapkan & Kirim di HP). */
class DeliveryOrderItemCheck extends Model
{
    protected $fillable = ['delivery_order_item_id', 'checked_by', 'checked_at'];

    protected $casts = ['checked_at' => 'datetime'];

    public function deliveryOrderItem()
    {
        return $this->belongsTo(DeliveryOrderItem::class);
    }

    public function checker()
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
