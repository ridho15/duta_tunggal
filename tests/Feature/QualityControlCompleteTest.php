<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\QualityControl;
use App\Models\ReturnProduct;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\QualityControlService;
use Database\Seeders\CabangSeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\SupplierSeeder;
use Database\Seeders\UnitOfMeasureSeeder;
use Database\Seeders\WarehouseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QualityControlCompleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed required data
        $this->seed(CabangSeeder::class);
        $this->seed(CurrencySeeder::class);
        $this->seed(UnitOfMeasureSeeder::class);
        $this->seed(ProductSeeder::class);
        $this->seed(WarehouseSeeder::class);
        $this->seed(SupplierSeeder::class);

        $this->user = User::factory()->create(['cabang_id' => Cabang::first()->id]);
        $this->actingAs($this->user);
    }

    /** @test */
    public function qc_complete_with_rejected_products_creates_return_product()
    {
        // Create purchase order and receipt
        $supplier = Supplier::first();
        $product = Product::first();
        $warehouse = Warehouse::first() ?? Warehouse::factory()->create();
        $currency = Currency::first();
        $cabangId = Cabang::first()->id;

        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-' . strtoupper(uniqid()),
            'order_date' => now(),
            'status' => 'completed',
            'total_amount' => 100000,
            'cabang_id' => $cabangId,
            'created_by' => $this->user->id,
            'warehouse_id' => $warehouse->id,
            'approved_by' => $this->user->id,
            'date_approved' => now(),
            'completed_by' => $this->user->id,
            'completed_at' => now(),
            'tempo_hutang' => 30,
            'is_asset' => 0,
            'close_reason' => null,
            'note' => null,
            'close_requested_by' => $this->user->id,
            'close_requested_at' => now(),
            'closed_by' => $this->user->id,
            'closed_at' => now(),
            'refer_model_type' => null,
            'refer_model_id' => null,
            'is_import' => false,
            'ppn_option' => 'standard',
        ]);

        $receipt = PurchaseReceipt::create([
            'purchase_order_id' => $po->id,
            'receipt_number' => 'RC-' . strtoupper(uniqid()),
            'receipt_date' => now(),
            'status' => 'completed',
            'total_received' => 100000,
            'cabang_id' => $cabangId,
            'created_by' => $this->user->id,
            'received_by' => $this->user->id,
            'currency_id' => $currency->id,
        ]);

        $receiptItem = PurchaseReceiptItem::create([
            'purchase_receipt_id' => $receipt->id,
            'product_id' => $product->id,
            'qty_received' => 10,
            'qty_accepted' => 10,
            'unit_price' => 10000,
            'warehouse_id' => $warehouse->id,
            'rak_id' => null,
        ]);

        // Create QC with rejected products
        $qcService = app(QualityControlService::class);
        $qc = QualityControl::create([
            'qc_number' => 'QC-P-' . date('Ymd') . '-0001',
            'passed_quantity' => 7,
            'rejected_quantity' => 3,
            'status' => 0,
            'inspected_by' => $this->user->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'from_model_type' => PurchaseReceiptItem::class,
            'from_model_id' => $receiptItem->id,
        ]);

        // Complete QC with return data
        $completeData = [
            'warehouse_id' => $warehouse->id,
            'rak_id' => null,
            'item_condition' => 'damage',
            'reason' => 'Items damaged during transport',
        ];

        $qcService->completeQualityControl($qc, $completeData);

        // Assert QC is completed
        $qc->refresh();
        $this->assertEquals(1, $qc->status);

        // Assert ReturnProduct was created
        $returnProduct = ReturnProduct::where('from_model_id', $qc->id)
            ->where('from_model_type', QualityControl::class)
            ->first();

        $this->assertNotNull($returnProduct);
        $this->assertEquals('draft', $returnProduct->status);
        $this->assertEquals($warehouse->id, $returnProduct->warehouse_id);
        $this->assertStringStartsWith('RN-', $returnProduct->return_number);

        // Assert ReturnProductItem was created
        $returnItem = $returnProduct->returnProductItem()->first();
        $this->assertNotNull($returnItem);
        $this->assertEquals(3, $returnItem->quantity);
        $this->assertEquals('damage', $returnItem->condition);
        $this->assertEquals($product->id, $returnItem->product_id);
    }
}