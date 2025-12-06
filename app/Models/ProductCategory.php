<?php

namespace App\Models;

use App\Models\Scopes\CabangScope;
use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductCategory extends Model
{
    use SoftDeletes, HasFactory,LogsGlobalActivity;
    protected $table = 'product_categories';
    protected $fillable = [
        'name',
        'kode',
        'cabang_id',
        'kenaikan_harga',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new CabangScope);
    }

    public function product()
    {
        return $this->hasMany(Product::class, 'product_category_id');
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id')->withDefault();
    }
}
