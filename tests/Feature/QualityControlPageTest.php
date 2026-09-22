<?php

use App\Filament\Resources\QualityControlPurchaseResource\Pages\ListQualityControlPurchases;
use App\Models\Cabang;
use App\Models\Currency;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\QualityControl;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class QualityControlPageTest extends \Tests\TestCase
{
    use RefreshDatabase;

    private function createUserWithQualityControlPermissions(): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create([
            'manage_type' => 'all',
        ]);

        foreach ([
            'view any quality control',
            'view quality control',
            'view any quality control purchase',
            'view quality control purchase',
        ] as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $user->givePermissionTo([
            'view any quality control',
            'view quality control',
            'view any quality control purchase',
            'view quality control purchase',
        ]);

        return $user;
    }

    private function createQualityControlPurchaseRecord(array $overrides = []): QualityControl
    {
        $cabang = Cabang::factory()->create();
        UnitOfMeasure::factory()->create();
        $currency = Currency::factory()->create(['code' => 'IDR', 'to_rupiah' => 1]);
        $supplier = Supplier::factory()->create([
            'perusahaan' => $overrides['supplier_name'] ?? 'SupTest',
            'cabang_id' => $cabang->id,
        ]);
        $warehouse = Warehouse::factory()->create([
            'cabang_id' => $cabang->id,
            'status' => 1,
        ]);
        $product = Product::factory()->forCabang($cabang)->create([
            'supplier_id' => $supplier->id,
        ]);
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'po_number' => $overrides['po_number'] ?? 'PO-123',
            'status' => 'approved',
            'cabang_id' => $cabang->id,
        ]);
        $purchaseOrderItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 1000,
            'currency_id' => $currency->id,
        ]);

        return QualityControl::factory()->create([
            'from_model_type' => PurchaseOrderItem::class,
            'from_model_id' => $purchaseOrderItem->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity_received' => 2,
            'passed_quantity' => 2,
            'rejected_quantity' => 0,
            'status' => 0,
            'inspected_by' => $this->createUserWithQualityControlPermissions()->id,
            'cabang_id' => $cabang->id,
        ]);
    }

    public function test_quality_control_page_loads_without_errors()
    {
        $user = $this->createUserWithQualityControlPermissions();
        
        $response = $this->actingAs($user)
                         ->get('/admin/quality-control-purchases');
        
        $response->assertStatus(200);
    }

    public function test_quality_control_view_page_loads_without_errors()
    {
        $user = $this->createUserWithQualityControlPermissions();
        $qc = $this->createQualityControlPurchaseRecord();
        
        $response = $this->actingAs($user)
                         ->get('/admin/quality-control-purchases/' . $qc->id);
        
        $response->assertStatus(200);
    }

    public function test_index_shows_supplier_and_po_and_filters_exist()
    {
        $user = $this->createUserWithQualityControlPermissions();
        $qualityControl = $this->createQualityControlPurchaseRecord([
            'supplier_name' => 'SupTest',
            'po_number' => 'PO-123',
        ]);

        Livewire::actingAs($user)
            ->test(ListQualityControlPurchases::class)
            ->assertCanSeeTableRecords([$qualityControl]);
    }
}
