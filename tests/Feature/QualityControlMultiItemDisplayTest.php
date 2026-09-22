<?php

namespace Tests\Feature;

use App\Filament\Resources\QualityControlPurchaseResource;
use App\Filament\Resources\QualityControlPurchaseResource\Pages\ListQualityControlPurchases;
use App\Models\Cabang;
use App\Models\Currency;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\QualityControl;
use App\Models\QualityControlItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Daftar dan halaman lihat QC pembelian harus bisa menampilkan QC "1 QC banyak item"
 * (fromModel = PurchaseOrder) berdampingan dengan QC lama per item (fromModel = PurchaseOrderItem).
 */
class QualityControlMultiItemDisplayTest extends TestCase
{
    use RefreshDatabase;

    private function viewer(): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create(['manage_type' => 'all']);

        $permissions = [
            'view any quality control',
            'view quality control',
            'view any quality control purchase',
            'view quality control purchase',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $user->givePermissionTo($permissions);

        return $user;
    }

    /**
     * @return array{purchaseOrder: PurchaseOrder, supplier: Supplier, items: array<int, PurchaseOrderItem>, warehouse: Warehouse, cabang: Cabang}
     */
    private function purchaseOrderWithItems(string $poNumber, string $supplierName, int $itemCount = 2): array
    {
        $cabang = Cabang::factory()->create();
        UnitOfMeasure::factory()->create();
        $currency = Currency::firstOrCreate(['code' => 'IDR'], ['name' => 'Rupiah', 'symbol' => 'Rp', 'to_rupiah' => 1]);
        $supplier = Supplier::factory()->create(['perusahaan' => $supplierName, 'cabang_id' => $cabang->id]);
        $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id, 'status' => 1]);

        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'po_number' => $poNumber,
            'status' => 'approved',
            'cabang_id' => $cabang->id,
            'warehouse_id' => $warehouse->id,
        ]);

        $items = [];
        for ($i = 0; $i < $itemCount; $i++) {
            $product = Product::factory()->forCabang($cabang)->create(['supplier_id' => $supplier->id]);
            $items[] = PurchaseOrderItem::factory()->create([
                'purchase_order_id' => $purchaseOrder->id,
                'product_id' => $product->id,
                'quantity' => 10,
                'unit_price' => 1000,
                'currency_id' => $currency->id,
            ]);
        }

        return compact('purchaseOrder', 'supplier', 'items', 'warehouse', 'cabang');
    }

    private function multiItemQc(array $po, User $inspector): QualityControl
    {
        $qc = QualityControl::factory()->create([
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $po['purchaseOrder']->id,
            'purchase_order_id' => $po['purchaseOrder']->id,
            'product_id' => null,
            'warehouse_id' => $po['warehouse']->id,
            'quantity_received' => 8,
            'passed_quantity' => 8,
            'rejected_quantity' => 0,
            'status' => 0,
            'inspected_by' => $inspector->id,
            'cabang_id' => $po['cabang']->id,
        ]);

        foreach ($po['items'] as $item) {
            QualityControlItem::create([
                'quality_control_id' => $qc->id,
                'purchase_order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'quantity_received' => 4,
                'passed_quantity' => 4,
                'rejected_quantity' => 0,
                'failed_qc_action' => 'wait_next_delivery',
            ]);
        }

        return $qc;
    }

    private function itemQc(array $po, User $inspector): QualityControl
    {
        $item = $po['items'][0];

        return QualityControl::factory()->create([
            'from_model_type' => PurchaseOrderItem::class,
            'from_model_id' => $item->id,
            'product_id' => $item->product_id,
            'warehouse_id' => $po['warehouse']->id,
            'quantity_received' => 2,
            'passed_quantity' => 2,
            'rejected_quantity' => 0,
            'status' => 0,
            'inspected_by' => $inspector->id,
            'cabang_id' => $po['cabang']->id,
        ]);
    }

    public function test_list_shows_multi_item_and_item_level_qc_with_po_and_supplier(): void
    {
        $user = $this->viewer();
        $multi = $this->multiItemQc($this->purchaseOrderWithItems('PO-MULTI-001', 'Supplier Multi'), $user);
        $single = $this->itemQc($this->purchaseOrderWithItems('PO-SINGLE-001', 'Supplier Single', 1), $user);

        Livewire::actingAs($user)
            ->test(ListQualityControlPurchases::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$multi, $single])
            ->assertTableColumnStateSet('po_number', 'PO-MULTI-001', record: $multi)
            ->assertTableColumnStateSet('po_number', 'PO-SINGLE-001', record: $single)
            ->assertTableColumnStateSet('product.name', 'Multi-item (2 produk)', record: $multi);

        $supplierColumn = QualityControlPurchaseResource::resolveQcPurchaseOrder($multi->fresh())->supplier;
        $this->assertSame('Supplier Multi', $supplierColumn->perusahaan);
    }

    public function test_list_search_and_supplier_filter_find_multi_item_qc(): void
    {
        $user = $this->viewer();
        $multiPo = $this->purchaseOrderWithItems('PO-MULTI-002', 'Pemasok Alfa');
        $multi = $this->multiItemQc($multiPo, $user);
        $single = $this->itemQc($this->purchaseOrderWithItems('PO-SINGLE-002', 'Pemasok Beta', 1), $user);

        Livewire::actingAs($user)
            ->test(ListQualityControlPurchases::class)
            ->searchTable('PO-MULTI-002')
            ->assertCanSeeTableRecords([$multi])
            ->assertCanNotSeeTableRecords([$single])
            ->searchTable('Pemasok Beta')
            ->assertCanSeeTableRecords([$single])
            ->assertCanNotSeeTableRecords([$multi])
            ->searchTable('')
            ->filterTable('supplier', ['supplier_id' => $multiPo['supplier']->id])
            ->assertCanSeeTableRecords([$multi])
            ->assertCanNotSeeTableRecords([$single]);
    }

    public function test_view_page_renders_multi_item_qc_with_po_supplier_and_items(): void
    {
        $user = $this->viewer();
        $multi = $this->multiItemQc($this->purchaseOrderWithItems('PO-MULTI-003', 'Supplier Lihat'), $user);

        $this->actingAs($user)
            ->get('/admin/quality-control-purchases/' . $multi->id)
            ->assertOk()
            ->assertSee('PO-MULTI-003')
            ->assertSee('Supplier Lihat')
            ->assertSee('Multi-item');
    }

    public function test_view_page_still_renders_item_level_qc_with_price_summary(): void
    {
        $user = $this->viewer();
        $single = $this->itemQc($this->purchaseOrderWithItems('PO-SINGLE-003', 'Supplier Satu', 1), $user);

        $this->actingAs($user)
            ->get('/admin/quality-control-purchases/' . $single->id)
            ->assertOk()
            ->assertSee('PO-SINGLE-003')
            ->assertSee('Unit Price');
    }

    public function test_resolver_and_ordered_quantity_cover_all_qc_shapes(): void
    {
        $user = $this->viewer();
        $po = $this->purchaseOrderWithItems('PO-MULTI-004', 'Supplier Resolver');
        $multi = $this->multiItemQc($po, $user);
        $single = $this->itemQc($po, $user);

        $this->assertSame($po['purchaseOrder']->id, QualityControlPurchaseResource::resolveQcPurchaseOrder($multi)->id);
        $this->assertSame($po['purchaseOrder']->id, QualityControlPurchaseResource::resolveQcPurchaseOrder($single)->id);
        $this->assertSame(20.0, QualityControlPurchaseResource::qcPurchaseOrderedQuantity($multi->fresh()));
        $this->assertSame(10.0, QualityControlPurchaseResource::qcPurchaseOrderedQuantity($single->fresh()));
        $this->assertNull(QualityControlPurchaseResource::resolveQcPurchaseOrder(null));
    }
}
