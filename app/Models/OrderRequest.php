<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderRequest extends Model
{
    use SoftDeletes, HasFactory;
    protected $table = 'order_requests';
    protected $fillable = [
        'request_number',
        'warehouse_id',
        'request_date',
        'status', // draft, approved, rejected
        'note',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id')->withDefault();
    }

    public function orderRequestItem()
    {
        return $this->hasMany(OrderRequestItem::class, 'order_request_id');
    }

    protected static function booted()
    {
        static::deleting(function ($orderRequest) {
            if ($orderRequest->isForceDeleting()) {
                $orderRequest->orderRequestItem()->forceDelete();
            } else {
                $orderRequest->orderRequestItem()->delete();
            }
        });

        static::restoring(function ($orderRequest) {
            $orderRequest->orderRequestItem()->withTrashed()->restore();
        });
    }
}
