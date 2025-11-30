<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillOfMaterialItem extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'bill_of_material_items';
    protected $fillable = [
        'bill_of_material_id',
        'product_id',
        'quantity',
        'uom_id',
        'unit_price',
        'subtotal',
        'note'
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'string',
        'subtotal' => 'string',
    ];

    public function billOfMaterial()
    {
        return $this->belongsTo(BillOfMaterial::class, 'bill_of_material_id')->withDefault();
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }

    public function uom()
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id')->withDefault();
    }
}
