<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderRequest extends Model
{
    use SoftDeletes, HasFactory,LogsGlobalActivity;
    protected $table = 'order_requests';
    protected $fillable = [
        'request_number',
        'warehouse_id',
        'supplier_id',
        'request_date',
        'status', // draft, approved, rejected
        'note',
        'created_by'
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id')->withDefault();
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id')->withDefault();
    }

    public function orderRequestItem()
    {
        return $this->hasMany(OrderRequestItem::class, 'order_request_id');
    }

    public function purchaseOrder()
    {
        return $this->morphOne(PurchaseOrder::class, 'refer_model')->withDefault();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')->withDefault();
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
