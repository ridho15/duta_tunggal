<?php

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Rak;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Auth;

/**
 * Fixture context untuk pengujian modul Penyesuaian Persediaan & Opname Fisik (Tahap 7).
 */
function opnContext(array $overrides = []): array
{
    $cabang = Cabang::factory()->create([
        'kode' => 'OPN-' . strtoupper(substr(uniqid(), -5)),
        'nama' => 'Cabang Persediaan & Gudang Utama',
        'status' => 1,
        'lihat_stok_cabang_lain' => false,
    ]);

    $user = User::factory()->create([
        'cabang_id' => $cabang->id,
        'username' => 'opn_' . uniqid(),
        'email' => 'opn_' . uniqid() . '@example.com',
        'kode_user' => 'U' . strtoupper(substr(uniqid(), -4)),
        'manage_type' => 'all',
    ]);
    Auth::login($user);

    $warehouse = Warehouse::factory()->create([
        'cabang_id' => $cabang->id,
        'kode' => 'WH1-' . strtoupper(substr(uniqid(), -4)),
        'name' => 'Gudang Pusat Distribusi',
        'status' => 1,
    ]);

    $warehouseTarget = Warehouse::factory()->create([
        'cabang_id' => $cabang->id,
        'kode' => 'WH2-' . strtoupper(substr(uniqid(), -4)),
        'name' => 'Gudang Transit Logistik',
        'status' => 1,
    ]);

    $rakSource = Rak::create([
        'name' => 'Rak A-01 Utama',
        'code' => 'RAK-A01-' . strtoupper(substr(uniqid(), -3)),
        'warehouse_id' => $warehouse->id,
    ]);

    $rakTarget = Rak::create([
        'name' => 'Rak B-01 Transit',
        'code' => 'RAK-B01-' . strtoupper(substr(uniqid(), -3)),
        'warehouse_id' => $warehouseTarget->id,
    ]);

    $coa = fn (string $code, string $name, string $type) => ChartOfAccount::firstOrCreate(
        ['code' => $code],
        ['name' => $name, 'type' => $type, 'is_active' => true, 'opening_balance' => 0]
    );

    $inventoryCoa = ChartOfAccount::whereIn('code', ['1100', config('coa.inventory', '1140.01'), '1140.10'])->first()
        ?? $coa('1100', 'Persediaan Barang Dagang', 'Asset');

    $adjustmentCoa = ChartOfAccount::whereIn('code', ['5100', '5100.10', config('coa.general_expense', '6100')])->first()
        ?? $coa('5100.10', 'Selisih Penyesuaian Persediaan (Opname)', 'Expense');

    $kasCoa = $coa('1101', 'Kas Operasional', 'Asset');

    $uom = UnitOfMeasure::firstOrCreate(['name' => 'Unit'], ['abbreviation' => 'unt']);

    $product = Product::factory()->create([
        'sku' => 'PRD-OPN-' . strtoupper(substr(uniqid(), -5)),
        'name' => 'Komoditas Uji Opname',
        'uom_id' => $uom->id,
        'cost_price' => 50000.0,
        'sell_price' => 75000.0,
        'inventory_coa_id' => $inventoryCoa->id,
    ]);

    return array_merge(compact(
        'cabang', 'user', 'warehouse', 'warehouseTarget',
        'rakSource', 'rakTarget', 'inventoryCoa', 'adjustmentCoa',
        'kasCoa', 'uom', 'product'
    ), $overrides);
}

/**
 * Buat atau update InventoryStock pada rak tertentu.
 */
function opnSetStock(Product $product, Warehouse $warehouse, Rak $rak, float $availableQty, float $reservedQty = 0.0): InventoryStock
{
    $stock = InventoryStock::withoutGlobalScopes()
        ->where('product_id', $product->id)
        ->where('warehouse_id', $warehouse->id)
        ->where('rak_id', $rak->id)
        ->first();

    if ($stock) {
        $stock->forceFill([
            'qty_available' => $availableQty,
            'qty_reserved' => $reservedQty,
        ])->save();

        return $stock->fresh();
    }

    return InventoryStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
        'qty_available' => $availableQty,
        'qty_reserved' => $reservedQty,
        'qty_min' => 0,
    ]);
}

/**
 * Buat StockOpname dengan parameter default.
 */
function opnCreate(array $ctx, array $attributes = []): StockOpname
{
    return StockOpname::create(array_merge([
        'opname_number' => 'SO-' . now()->format('Ymd') . '-' . strtoupper(substr(uniqid(), -4)),
        'opname_date' => now()->toDateString(),
        'warehouse_id' => $ctx['warehouse']->id,
        'status' => 'completed',
        'notes' => 'Stock opname audit periodik',
        'created_by' => $ctx['user']->id,
    ], $attributes));
}

/**
 * Buat StockOpnameItem.
 */
function opnItem(StockOpname $opname, array $ctx, array $attributes = []): StockOpnameItem
{
    $systemQty = $attributes['system_qty'] ?? 10.0;
    $physicalQty = $attributes['physical_qty'] ?? 10.0;
    $diffQty = $physicalQty - $systemQty;
    $unitCost = $attributes['unit_cost'] ?? 50000.0;
    $diffVal = $diffQty * $unitCost;

    return StockOpnameItem::create(array_merge([
        'stock_opname_id' => $opname->id,
        'product_id' => $ctx['product']->id,
        'rak_id' => $ctx['rakSource']->id,
        'system_qty' => $systemQty,
        'physical_qty' => $physicalQty,
        'difference_qty' => $diffQty,
        'unit_cost' => $unitCost,
        'average_cost' => $unitCost,
        'difference_value' => $diffVal,
        'total_value' => $physicalQty * $unitCost,
        'notes' => 'Item opname verifikasi fisik',
    ], $attributes));
}

/**
 * Buat StockAdjustment dengan parameter default.
 */
function adjCreate(array $ctx, array $attributes = []): StockAdjustment
{
    return StockAdjustment::create(array_merge([
        'adjustment_number' => 'SA-' . now()->format('Ymd') . '-' . strtoupper(substr(uniqid(), -4)),
        'adjustment_date' => now()->toDateString(),
        'warehouse_id' => $ctx['warehouse']->id,
        'adjustment_type' => 'increase',
        'reason' => 'Penyesuaian stok berkala',
        'notes' => 'Catatan penyesuaian',
        'status' => 'draft',
        'created_by' => $ctx['user']->id,
    ], $attributes));
}

/**
 * Buat StockAdjustmentItem.
 */
function adjItem(StockAdjustment $adjustment, array $ctx, array $attributes = []): StockAdjustmentItem
{
    $currentQty = $attributes['current_qty'] ?? 10.0;
    $diffQty = $attributes['difference_qty'] ?? ($adjustment->adjustment_type === 'increase' ? 5.0 : -5.0);
    $adjustedQty = $attributes['adjusted_qty'] ?? ($currentQty + $diffQty);
    $unitCost = $attributes['unit_cost'] ?? 50000.0;
    $diffVal = $diffQty * $unitCost;

    return StockAdjustmentItem::create(array_merge([
        'stock_adjustment_id' => $adjustment->id,
        'product_id' => $ctx['product']->id,
        'rak_id' => $ctx['rakSource']->id,
        'current_qty' => $currentQty,
        'adjusted_qty' => $adjustedQty,
        'difference_qty' => $diffQty,
        'unit_cost' => $unitCost,
        'difference_value' => $diffVal,
        'notes' => 'Detail item adjustment',
    ], $attributes));
}

/**
 * Buat StockTransfer dengan parameter default.
 */
function stfCreate(array $ctx, array $attributes = []): StockTransfer
{
    return StockTransfer::create(array_merge([
        'transfer_number' => 'ST-' . now()->format('Ymd') . '-' . strtoupper(substr(uniqid(), -4)),
        'from_warehouse_id' => $ctx['warehouse']->id,
        'to_warehouse_id' => $ctx['warehouseTarget']->id,
        'transfer_date' => now()->toDateString(),
        'status' => 'Draft',
    ], $attributes));
}

/**
 * Buat StockTransferItem.
 */
function stfItem(StockTransfer $transfer, array $ctx, array $attributes = []): StockTransferItem
{
    return StockTransferItem::create(array_merge([
        'stock_transfer_id' => $transfer->id,
        'product_id' => $ctx['product']->id,
        'quantity' => 5.0,
        'from_warehouse_id' => $transfer->from_warehouse_id,
        'from_rak_id' => $ctx['rakSource']->id,
        'to_warehouse_id' => $transfer->to_warehouse_id,
        'to_rak_id' => $ctx['rakTarget']->id,
    ], $attributes));
}
