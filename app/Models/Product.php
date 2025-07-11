<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'products';
    protected $fillable = [
        'name', // Nama Product
        'sku', // Kode
        'cabang_id',
        'product_category_id',
        'cost_price', // Harga Beli Asli (Rp.)
        'sell_price', // Harga jual (Rp.)
        'biaya',
        'harga_batas',
        'tipe_pajak',
        'pajak',
        'jumlah_kelipatan_gudang_besar',
        'jumlah_jual_kategori_banyak',
        'kode_merk',
        'description',
        'uom_id', // Satuan
        'is_asset',
        'is_manufacture',
    ];

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id')->withDefault();
    }

    public function unitConversions()
    {
        return $this->hasMany(ProductUnitConversion::class, 'product_id');
    }

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

    public function billOfMaterial()
    {
        return $this->hasMany(BillOfMaterial::class, 'product_id');
    }

    public function billOfMaterialItem()
    {
        return $this->hasMany(BillOfMaterialItem::class, 'product_id');
    }

    protected static function booted()
    {
        static::deleting(function ($product) {
            if ($product->isForceDeleting()) {
                $product->purchaseOrderItem()->forceDelete();
                $product->purchaseReceiptItem()->forceDelete();
                $product->inventoryStock()->forceDelete();
                $product->stockMovement()->forceDelete();
            } else {
                $product->purchaseOrderItem()->delete();
                $product->purchaseReceiptItem()->delete();
                $product->inventoryStock()->delete();
                $product->stockMovement()->delete();
            }
        });

        static::restoring(function ($product) {
            $product->purchaseOrderItem()->restore();
            $product->purchaseReceiptItem()->restore();
            $product->inventoryStock()->restore();
            $product->stockMovement()->restore();
        });
    }
}
