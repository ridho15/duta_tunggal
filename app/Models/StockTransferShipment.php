<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Satu pengiriman mutasi dari gudang asal (qty yang benar-benar naik kendaraan) + tanda terima gudang tujuan. */
class StockTransferShipment extends Model
{
    protected $fillable = [
        'stock_transfer_id', 'shipment_number', 'transit_warehouse_id',
        'shipped_at', 'shipped_by', 'received_at', 'received_by', 'notes',
    ];

    protected $casts = ['shipped_at' => 'datetime', 'received_at' => 'datetime'];

    public function stockTransfer()
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function items()
    {
        return $this->hasMany(StockTransferShipmentItem::class, 'stock_transfer_shipment_id');
    }

    public function transitWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'transit_warehouse_id');
    }

    public function shipper()
    {
        return $this->belongsTo(User::class, 'shipped_by');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function isReceived(): bool
    {
        return $this->received_at !== null;
    }
}
