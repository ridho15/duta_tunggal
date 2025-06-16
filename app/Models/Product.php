<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes, HasFactory;
    protected $table = 'products';
    protected $fillable = [
        'name',
        'sku',
        'product_category_id',
        'cost_price',
        'sell_price',
        'description',
        'uom_id',
        'is_asset',
        'usefull_life_years',
        'residual_value',
        'purchase_date'
    ];

    public function productCategory()
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id')->withDefault();
    }

    public function uom()
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id')->withDefault();
    }

    public function stockMovement()
    {
        return $this->hasMany(StockMovement::class, 'product_id');
    }

    public function inventoryStock()
    {
        return $this->hasMany(InventoryStock::class, 'product_id');
    }

    public function purchaseReceiptItem()
    {
        return $this->hasMany(PurchaseReceiptItem::class, 'product_id');
    }

    public function purchaseOrderItem()
    {
        return $this->hasMany(PurchaseOrderItem::class, 'product_id');
    }
}
