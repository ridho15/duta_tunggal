<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnitOfMeasure extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'unit_of_measures';
    protected $fillable = [
        'name',
        'abbreviation'
    ];

    public function product()
    {
        return $this->hasMany(Product::class, 'uom_id');
    }

    public function productUnitConversion()
    {
        return $this->hasMany(ProductUnitConversion::class, 'uom_id');
    }
}
