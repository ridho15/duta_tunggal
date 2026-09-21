<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Riwayat append-only perubahan reservasi stok (T2.1). Tidak pernah diubah/dihapus oleh aplikasi.
 */
class StockReservationEvent extends Model
{
    public const RESERVED = 'reserved';

    public const ADJUSTED = 'adjusted';

    public const CONSUMED = 'consumed';

    public const RELEASED = 'released';

    public const RECONCILED = 'reconciled';

    public const UPDATED_AT = null;

    protected $fillable = [
        'stock_reservation_id', 'sale_order_id', 'sale_order_item_id', 'delivery_order_id', 'product_id', 'warehouse_id',
        'quantity', 'event', 'reason', 'actor_id',
    ];

    protected $casts = ['quantity' => 'float'];
}
