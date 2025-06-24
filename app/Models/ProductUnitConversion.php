<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductUnitConversion extends Model
{
    use SoftDeletes, HasFactory,LogsGlobalActivity;
    protected $table = 'product_unit_conversions';
    protected $fillable = [
        'product_id',
        'uom_id',
        'nilai_konversi',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }

    public function uom()
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id')->withDefault();
    }
}
