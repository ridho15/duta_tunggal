<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Product extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'products';
    protected $fillable = [
        'name', // Nama Product
        'sku', // Kode
        'product_category_id',
        'cabang_id',
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
        'is_manufacture',
        'is_raw_material',
        'inventory_coa_id',
        'sales_coa_id',
        'sales_return_coa_id',
        'sales_discount_coa_id',
        'goods_delivery_coa_id',
        'cogs_coa_id',
        'purchase_return_coa_id',
        'unbilled_purchase_coa_id',
        'temporary_procurement_coa_id',
        'manufacturing_labor_coa_id',
        'manufacturing_overhead_coa_id',
        'is_active',
    ];

    protected $casts = [
        'is_manufacture' => 'boolean',
        'is_raw_material' => 'boolean',
        'is_active' => 'boolean',
        'cost_price' => 'decimal:2',
        'sell_price' => 'decimal:2',
        'biaya' => 'decimal:2',
    ];

    public static function productCoaFields(): array
    {
        return [
            'inventory_coa_id',
            'sales_coa_id',
            'sales_return_coa_id',
            'sales_discount_coa_id',
            'goods_delivery_coa_id',
            'cogs_coa_id',
            'purchase_return_coa_id',
            'unbilled_purchase_coa_id',
            'temporary_procurement_coa_id',
            'manufacturing_labor_coa_id',
            'manufacturing_overhead_coa_id',
        ];
    }

    public static function resolveDefaultProductCoaCodes(string $field, bool $isManufacture = false, bool $isRawMaterial = false): array
    {
        $productDefaults = config('coa.product', []);

        if ($field === 'inventory_coa_id') {
            if ($isRawMaterial) {
                return $productDefaults['inventory_coa_id']['raw_material'] ?? [];
            }

            if ($isManufacture) {
                return $productDefaults['inventory_coa_id']['manufacture'] ?? [];
            }

            return $productDefaults['inventory_coa_id']['standard'] ?? [];
        }

        return $productDefaults[$field] ?? [];
    }

    public static function resolveDefaultProductCoaId(string $field, bool $isManufacture = false, bool $isRawMaterial = false): ?int
    {
        foreach (self::resolveDefaultProductCoaCodes($field, $isManufacture, $isRawMaterial) as $code) {
            $coaId = ChartOfAccount::where('code', $code)->value('id');

            if ($coaId) {
                return $coaId;
            }
        }

        return null;
    }

    public static function resolveDefaultProductCoaMap(bool $isManufacture = false, bool $isRawMaterial = false): array
    {
        $defaults = [];

        foreach (self::productCoaFields() as $field) {
            $defaults[$field] = self::resolveDefaultProductCoaId($field, $isManufacture, $isRawMaterial);
        }

        return $defaults;
    }

    // Scopes for active/inactive products
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeForCabang(Builder $query, ?int $cabangId): Builder
    {
        if (! $cabangId) {
            return $query;
        }

        return $query->where(function (Builder $branchQuery) use ($cabangId) {
            $branchQuery->where('cabang_id', $cabangId)
                ->orWhereNull('cabang_id');
        });
    }

    public function suppliers()
    {
        return $this->belongsToMany(Supplier::class, 'product_supplier')
            ->withPivot('supplier_price')
            ->withTimestamps()
            ->withCasts([
                'supplier_price' => 'decimal:2'
            ]);
    }

    public function unitConversions()
    {
        return $this->hasMany(ProductUnitConversion::class, 'product_id');
    }

    public function productCategory()
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id')->withDefault();
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id')->withDefault();
    }

    public function uom()
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id')->withDefault();
    }

    public function inventoryCoa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'inventory_coa_id')->withDefault();
    }

    public function salesCoa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'sales_coa_id')->withDefault();
    }

    public function salesReturnCoa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'sales_return_coa_id')->withDefault();
    }

    public function salesDiscountCoa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'sales_discount_coa_id')->withDefault();
    }

    public function goodsDeliveryCoa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'goods_delivery_coa_id')->withDefault();
    }

    public function cogsCoa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'cogs_coa_id')->withDefault();
    }

    public function purchaseReturnCoa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'purchase_return_coa_id')->withDefault();
    }

    public function unbilledPurchaseCoa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'unbilled_purchase_coa_id')->withDefault();
    }

    public function temporaryProcurementCoa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'temporary_procurement_coa_id')->withDefault();
    }

    public function manufacturingLaborCoa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'manufacturing_labor_coa_id')->withDefault();
    }

    public function manufacturingOverheadCoa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'manufacturing_overhead_coa_id')->withDefault();
    }

    public function resolveInventoryCoaOrDefault(): ?ChartOfAccount
    {
        return $this->resolveCoaRelationOrDefault('inventoryCoa', self::resolveDefaultProductCoaCodes('inventory_coa_id', (bool) $this->is_manufacture, (bool) $this->is_raw_material));
    }

    public function resolveUnbilledPurchaseCoaOrDefault(): ?ChartOfAccount
    {
        return $this->resolveCoaRelationOrDefault('unbilledPurchaseCoa', self::resolveDefaultProductCoaCodes('unbilled_purchase_coa_id'));
    }

    public function resolveTemporaryProcurementCoaOrDefault(): ?ChartOfAccount
    {
        return $this->resolveCoaRelationOrDefault('temporaryProcurementCoa', self::resolveDefaultProductCoaCodes('temporary_procurement_coa_id'));
    }

    public function resolveCogsCoaOrDefault(): ?ChartOfAccount
    {
        return $this->resolveCoaRelationOrDefault('cogsCoa', self::resolveDefaultProductCoaCodes('cogs_coa_id'));
    }

    public function resolveGoodsDeliveryCoaOrDefault(): ?ChartOfAccount
    {
        return $this->resolveCoaRelationOrDefault('goodsDeliveryCoa', self::resolveDefaultProductCoaCodes('goods_delivery_coa_id'));
    }

    public function resolvePurchaseReturnCoaOrDefault(): ?ChartOfAccount
    {
        return $this->resolveCoaRelationOrDefault('purchaseReturnCoa', self::resolveDefaultProductCoaCodes('purchase_return_coa_id'));
    }

    public function resolveManufacturingLaborCoaOrDefault(): ?ChartOfAccount
    {
        return $this->resolveCoaRelationOrDefault('manufacturingLaborCoa', self::resolveDefaultProductCoaCodes('manufacturing_labor_coa_id'));
    }

    public function resolveManufacturingOverheadCoaOrDefault(): ?ChartOfAccount
    {
        return $this->resolveCoaRelationOrDefault('manufacturingOverheadCoa', self::resolveDefaultProductCoaCodes('manufacturing_overhead_coa_id'));
    }

    protected function resolveCoaRelationOrDefault(string $relation, array $fallbackCodes): ?ChartOfAccount
    {
        $coa = $this->{$relation};

        if ($coa && $coa->exists && $coa->id) {
            return $coa;
        }

        foreach ($fallbackCodes as $code) {
            $fallback = ChartOfAccount::where('code', $code)->first();

            if ($fallback?->id) {
                return $fallback;
            }
        }

        return null;
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

    public function assets()
    {
        return $this->hasMany(Asset::class, 'product_id');
    }

    public static function resolveDefaultInventoryCoaId(bool $isManufacture = false, bool $isRawMaterial = false): ?int
    {
        return self::resolveDefaultProductCoaId('inventory_coa_id', $isManufacture, $isRawMaterial);
    }

    protected static function booted()
    {
        static::addGlobalScope('product_cabang', function (Builder $builder) {
            $user = Auth::user();

            if (! $user || ! $user->cabang_id) {
                return;
            }

            $manageType = $user->manage_type ?? [];
            if (is_array($manageType) && in_array('all', $manageType)) {
                return;
            }

            $builder->forCabang((int) $user->cabang_id);
        });

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

        static::created(function ($product) {
            $warehouses = \App\Models\Warehouse::all();
            foreach ($warehouses as $warehouse) {
                \App\Models\InventoryStock::create([
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                    'qty_available' => 0,
                    'qty_reserved' => 0,
                    'qty_min' => 0,
                    'rak_id' => null,
                ]);
            }
        });
    }
}
