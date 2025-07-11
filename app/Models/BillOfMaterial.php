<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillOfMaterial extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'bill_of_materials';
    protected $fillable = [
        'cabang_id',
        'product_id',
        'quantity',
        'code',
        'nama_bom',
        'note',
        'is_active',
        'uom_id',
    ];

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id')->withDefault();
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }

    public function uom()
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id')->withDefault();
    }

    public function items()
    {
        return $this->hasMany(BillOfMaterialItem::class, 'bill_of_material_id');
    }
}
