<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransferShipmentItem extends Model
{
    protected $fillable = ['stock_transfer_shipment_id', 'stock_transfer_item_id', 'quantity'];

    protected $casts = ['quantity' => 'float'];

    public function shipment()
    {
        return $this->belongsTo(StockTransferShipment::class, 'stock_transfer_shipment_id');
    }

    public function transferItem()
    {
        return $this->belongsTo(StockTransferItem::class, 'stock_transfer_item_id')->withTrashed();
    }
}
