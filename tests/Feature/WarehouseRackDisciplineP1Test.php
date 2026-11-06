<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\QualityControl;
use App\Models\Rak;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WarehouseRackDisciplineP1Test extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang;
    protected User $user;
    protected Warehouse $warehouseWithRacks;
    protected Warehouse $warehouseWithoutRacks;
    protected Rak $rak1;
    protected Rak $rak2;
    protected Supplier $supplier;
    protected Product $product;
    protected Currency $currency;
    protected UnitOfMeasure $uom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uom = UnitOfMeasure::factory()->create();

        $this->cabang = Cabang::create([
            'kode' => 'CB-JKT',
            'nama' => 'Cabang Jakarta',
            'alamat' => 'Jl. Sudirman',
            'telepon' => '021123456',
        ]);

        $this->user = User::factory()->create([
            'cabang_id' => $this->cabang->id,
            'manage_type' => ['all'],
        ]);

        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $permissions = [
            'view_any_quality::control::purchase',
            'view_quality::control::purchase',
            'create_quality::control::purchase',
            'update_quality::control::purchase',
        ];
        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        $role->givePermissionTo(Permission::all());
        $this->user->assignRole($role);

        $this->warehouseWithRacks = Warehouse::factory()->create([
            'kode' => 'GD-RACK',
            'name' => 'Gudang Utama Ber-Rak',
            'cabang_id' => $this->cabang->id,
            'status' => 1,
            'location' => 'Jakarta Barat',
        ]);

        $this->rak1 = Rak::create([
            'code' => 'R-01',
            'name' => 'Rak A1',
            'warehouse_id' => $this->warehouseWithRacks->id,
        ]);

        $this->rak2 = Rak::create([
            'code' => 'R-02',
            'name' => 'Rak A2',
            'warehouse_id' => $this->warehouseWithRacks->id,
        ]);

        $this->warehouseWithoutRacks = Warehouse::factory()->create([
            'kode' => 'GD-FLAT',
            'name' => 'Gudang Transit Tanpa Rak',
            'cabang_id' => $this->cabang->id,
            'status' => 1,
            'location' => 'Jakarta Timur',
        ]);

        $this->supplier = Supplier::factory()->create([
            'cabang_id' => $this->cabang->id,
            'perusahaan' => 'Supplier Mitra Utama',
        ]);

        $this->currency = Currency::firstOrCreate(
            ['code' => 'IDR'],
            [
                'name' => 'Rupiah',
                'symbol' => 'Rp',
                'exchange_rate' => 1,
                'is_default' => 1,
            ]
        );

        $this->product = Product::factory()->create([
            'name' => 'Baut Baja 10mm',
            'sku' => 'BB-10',
            'cost_price' => 10000,
            'sell_price' => 15000,
            'uom_id' => $this->uom->id,
            'cabang_id' => $this->cabang->id,
        ]);
    }

    public function test_delivery_order_source_rack_field_is_conditionally_required(): void
    {
        $withRacksWhId = $this->warehouseWithRacks->id;
        $withoutRacksWhId = $this->warehouseWithoutRacks->id;

        $rule = fn ($warehouseId) => filled($warehouseId) && Rak::where('warehouse_id', $warehouseId)->exists();

        $this->assertTrue($rule($withRacksWhId));
        $this->assertFalse($rule($withoutRacksWhId));
        $this->assertFalse($rule(null));
    }

    public function test_warehouse_confirmation_rack_field_is_conditionally_required(): void
    {
        $withRacksWhId = $this->warehouseWithRacks->id;
        $withoutRacksWhId = $this->warehouseWithoutRacks->id;

        $rule = fn ($warehouseId) => filled($warehouseId) && Rak::where('warehouse_id', $warehouseId)->exists();

        $this->assertTrue($rule($withRacksWhId));
        $this->assertFalse($rule($withoutRacksWhId));
        $this->assertFalse($rule(null));
    }

    public function test_qc_manufacture_rack_field_is_conditionally_required(): void
    {
        $withRacksWhId = $this->warehouseWithRacks->id;
        $withoutRacksWhId = $this->warehouseWithoutRacks->id;

        $rule = fn ($warehouseId) => filled($warehouseId) && Rak::where('warehouse_id', $warehouseId)->exists();

        $this->assertTrue($rule($withRacksWhId));
        $this->assertFalse($rule($withoutRacksWhId));
        $this->assertFalse($rule(null));
    }
}
