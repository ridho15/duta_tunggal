<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ManufacturingOrder extends Model
{
    use SoftDeletes, HasFactory,LogsGlobalActivity;
    protected $guarded = ['id'];
    protected $table = 'manufacturing_orders';
    protected $fillable = [
        'mo_number',
        'product_id',
        'quantity',
        'status', // draft, in_progress, completed
        'start_date',
        'end_date'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }

    public function manufacturingOrderMaterial()
    {
        return $this->hasMany(manufacturingOrderMaterial::class, 'manufacturing_order_id');
    }
}
