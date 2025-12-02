<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReturnProduct extends Model
{
    use SoftDeletes, HasFactory,LogsGlobalActivity;
    protected $table = 'return_products';
    protected $fillable = [
        'return_number',
        'from_model_id',
        'from_model_type',
        'warehouse_id',
        'status', // draft / approve
        'reason',
        'return_action', // reduce_quantity_only, close_do_partial, close_so_complete
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id')->withDefault();
    }

    public function returnProductItem()
    {
        return $this->hasMany(ReturnProductItem::class, 'return_product_id');
    }

    public function fromModel()
    {
        return $this->morphTo(__FUNCTION__, 'from_model_type', 'from_model_id')->withDefault();
    }

    protected static function booted()
    {
        static::deleting(function ($returnProduct) {
            if ($returnProduct->isForceDeleting()) {
                $returnProduct->returnProductItem()->forceDelete();
            } else {
                $returnProduct->returnProductItem()->delete();
            }
        });

        static::restoring(function ($returnProduct) {
            $returnProduct->returnProductItem()->withTrashed()->restore();
        });
    }
}
