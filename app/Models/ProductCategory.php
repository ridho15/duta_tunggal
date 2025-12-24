<?php

namespace App\Models;

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
        'kenaikan_harga',
    ];

    public function product()
    {
        return $this->hasMany(Product::class, 'product_category_id');
    }
}
