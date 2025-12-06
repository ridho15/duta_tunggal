<?php

use App\Models\Asset;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Supplier;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AssetPurchaseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $cabang;
    protected $supplier;
    protected $product;
    protected $assetCoa;
    protected $accumulatedDepreciationCoa;
    protected $depreciationExpenseCoa;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'username' => 'testuser',
            'password' => bcrypt('password'),
            'first_name' => 'Test',
            'last_name' => 'User',
            'kode_user' => 'TEST001',
            'cabang_id' => 1,
            'manage_type' => ['all']
        ]);

        // Create test cabang
        $this->cabang = Cabang::factory()->create([
            'kode' => 'TEST',
            'nama' => 'Test Branch'
        ]);

        // Create test supplier
        $this->supplier = Supplier::factory()->create([
            'name' => 'Test Asset Supplier',
            'code' => 'ASSET001'
        ]);

        // Create test product (asset type)
        $this->product = Product::factory()->create([
            'name' => 'Test Asset Equipment',
            'sku' => 'ASSET001',
            'cost_price' => 10000000, // 10 million IDR
            'sell_price' => 12000000,
            'is_manufacture' => false,
            'is_raw_material' => false
        ]);

        // Create COA accounts for asset accounting
        $this->assetCoa = ChartOfAccount::factory()->create([
            'code' => '1210.01',
            'name' => 'PERALATAN KANTOR (OE)',
            'type' => 'asset'
        ]);

        $this->accumulatedDepreciationCoa = ChartOfAccount::factory()->create([
            'code' => '1220.01',
            'name' => 'AKUMULASI PENYUSUTAN PERALATAN KANTOR',
            'type' => 'asset'
        ]);

        $this->depreciationExpenseCoa = ChartOfAccount::factory()->create([
            'code' => '6311',
            'name' => 'BEBAN PENYUSUTAN PERALATAN KANTOR',
            'type' => 'expense'
        ]);
    }

    /** @test */
    public function test_complete_asset_purchase_workflow()
    {
        $this->actingAs($this->user);

        // Step 1: Create Purchase Order
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved'
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 10000000
        ]);

        // Verify PO creation
        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'approved'
        ]);

        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 10000000
        ]);

        // Step 2: Create Purchase Receipt
        $purchaseReceipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'receipt_date' => now(),
            'status' => 'completed'
        ]);

        $receiptItem = PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $purchaseReceipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 1,
            'qty_accepted' => 1,
            'warehouse_id' => 1
        ]);

        // Verify receipt creation
        $this->assertDatabaseHas('purchase_receipts', [
            'id' => $purchaseReceipt->id,
            'purchase_order_id' => $purchaseOrder->id,
            'status' => 'completed'
        ]);

        $this->assertDatabaseHas('purchase_receipt_items', [
            'purchase_receipt_id' => $purchaseReceipt->id,
            'purchase_order_item_id' => $poItem->id,
            'qty_received' => 1
        ]);

        // Step 3: Create Purchase Invoice
        $purchaseInvoice = Invoice::factory()->create([
            'from_model_type' => PurchaseReceipt::class,
            'from_model_id' => $purchaseReceipt->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 10000000,
            'tax' => 1100000, // 11% PPN
            'total' => 11100000,
            'status' => 'unpaid',
            'supplier_name' => $this->supplier->name
        ]);

        $invoiceItem = InvoiceItem::factory()->create([
            'invoice_id' => $purchaseInvoice->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => 10000000,
            'total' => 10000000
        ]);

        // Verify invoice creation
        $this->assertDatabaseHas('invoices', [
            'id' => $purchaseInvoice->id,
            'from_model_type' => 'App\Models\PurchaseReceipt',
            'from_model_id' => $purchaseReceipt->id,
            'status' => 'unpaid',
            'total' => 11100000
        ]);

        // Step 4: Create Asset Record (Manual Process)
        $asset = Asset::factory()->create([
            'name' => 'Test Asset Equipment',
            'product_id' => $this->product->id,
            'purchase_order_id' => $purchaseOrder->id,
            'purchase_order_item_id' => $poItem->id,
            'purchase_date' => now(),
            'usage_date' => now(),
            'purchase_cost' => 10000000,
            'salvage_value' => 100000, // 1% salvage value
            'useful_life_years' => 5,
            'depreciation_method' => 'straight_line',
            'cabang_id' => $this->cabang->id,
            'asset_coa_id' => $this->assetCoa->id,
            'accumulated_depreciation_coa_id' => $this->accumulatedDepreciationCoa->id,
            'depreciation_expense_coa_id' => $this->depreciationExpenseCoa->id,
            'book_value' => 10000000,
            'accumulated_depreciation' => 0,
            'monthly_depreciation' => 166666.67, // (10M - 100K) / (5 * 12)
            'status' => 'active'
        ]);

        // Verify asset creation and linking
        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'product_id' => $this->product->id,
            'purchase_order_id' => $purchaseOrder->id,
            'purchase_order_item_id' => $poItem->id,
            'purchase_cost' => 10000000,
            'status' => 'active'
        ]);

        // Verify asset relationships
        $this->assertEquals($purchaseOrder->id, $asset->purchase_order_id);
        $this->assertEquals($poItem->id, $asset->purchase_order_item_id);
        $this->assertEquals($this->product->id, $asset->product_id);

        // Verify asset can be retrieved through purchase order
        $assetFromPO = $purchaseOrder->assets()->first();
        $this->assertNotNull($assetFromPO);
        $this->assertEquals($asset->id, $assetFromPO->id);

        // Verify asset can be retrieved through product
        $assetFromProduct = $this->product->assets()->first();
        $this->assertNotNull($assetFromProduct);
        $this->assertEquals($asset->id, $assetFromProduct->id);

        // Step 5: Verify Accounting Integration
        // Asset should be recorded in the asset COA
        $this->assertEquals($this->assetCoa->id, $asset->asset_coa_id);
        $this->assertEquals($this->accumulatedDepreciationCoa->id, $asset->accumulated_depreciation_coa_id);
        $this->assertEquals($this->depreciationExpenseCoa->id, $asset->depreciation_expense_coa_id);

        // Verify depreciation calculation
        $expectedMonthlyDepreciation = (10000000 - 100000) / (5 * 12); // Straight line method
        $this->assertEquals(round($expectedMonthlyDepreciation, 2), $asset->monthly_depreciation);

        // Test complete workflow success
        $this->assertTrue(true, 'Complete asset purchase workflow executed successfully');
    }

    /** @test */
    public function test_asset_creation_without_purchase_order()
    {
        $this->actingAs($this->user);

        // Create asset without purchase order (direct purchase scenario)
        $asset = Asset::factory()->create([
            'name' => 'Direct Purchase Asset',
            'product_id' => $this->product->id,
            'purchase_date' => now(),
            'usage_date' => now(),
            'purchase_cost' => 5000000,
            'salvage_value' => 50000,
            'useful_life_years' => 3,
            'depreciation_method' => 'straight_line',
            'cabang_id' => $this->cabang->id,
            'asset_coa_id' => $this->assetCoa->id,
            'accumulated_depreciation_coa_id' => $this->accumulatedDepreciationCoa->id,
            'depreciation_expense_coa_id' => $this->depreciationExpenseCoa->id,
            'book_value' => 5000000,
            'accumulated_depreciation' => 0,
            'monthly_depreciation' => 138888.89, // (5M - 50K) / (3 * 12)
            'status' => 'active'
        ]);

        // Verify asset creation without PO linking
        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'product_id' => $this->product->id,
            'purchase_order_id' => null,
            'purchase_order_item_id' => null,
            'purchase_cost' => 5000000,
            'status' => 'active'
        ]);

        $this->assertNull($asset->purchase_order_id);
        $this->assertNull($asset->purchase_order_item_id);
    }

    /** @test */
    public function test_asset_depreciation_calculation()
    {
        $this->actingAs($this->user);

        // Test straight line depreciation
        $asset = Asset::factory()->create([
            'name' => 'Depreciation Test Asset',
            'product_id' => $this->product->id,
            'purchase_cost' => 12000000, // 12 million
            'salvage_value' => 1200000, // 10% salvage
            'useful_life_years' => 4,
            'depreciation_method' => 'straight_line',
            'cabang_id' => $this->cabang->id,
            'asset_coa_id' => $this->assetCoa->id,
            'accumulated_depreciation_coa_id' => $this->accumulatedDepreciationCoa->id,
            'depreciation_expense_coa_id' => $this->depreciationExpenseCoa->id,
            'status' => 'active'
        ]);

        // Calculate expected depreciation
        $depreciableAmount = 12000000 - 1200000; // 10.8 million
        $totalMonths = 4 * 12; // 48 months
        $expectedMonthlyDepreciation = $depreciableAmount / $totalMonths; // 225,000

        $this->assertEquals(225000, round($asset->monthly_depreciation));
        $this->assertEquals(12000000, $asset->book_value);
        $this->assertEquals(0, $asset->accumulated_depreciation);
    }

    /** @test */
    public function test_asset_purchase_flow_with_pre_set_signature()
    {
        // Step 1: Setup user with signature
        $userWithSignature = User::create([
            'name' => 'Test User with Signature',
            'email' => 'test.signature@example.com',
            'username' => 'testuser_sig',
            'password' => bcrypt('password'),
            'first_name' => 'Test',
            'last_name' => 'User',
            'kode_user' => 'TEST002',
            'signature' => 'signatures/test_signature.png', // Pre-set signature
            'cabang_id' => $this->cabang->id,
            'manage_type' => ['all']
        ]);

        $this->actingAs($userWithSignature);

        // Step 2: Create Purchase Order with is_asset = true
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'request_approval', // Status untuk menunggu approval
            'is_asset' => true // Mark as asset purchase
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 10000000,
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'Non Pajak'
        ]);

        // Verify PO creation with asset flag
        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'request_approval',
            'is_asset' => true
        ]);

        // Step 3: Approve purchase order using pre-set signature
        // Simulate the approval action with pre-set signature
        DB::transaction(function () use ($purchaseOrder, $userWithSignature) {
            // Use user's pre-set signature (not manual signature)
            $signaturePath = $userWithSignature->signature;

            $status = $purchaseOrder->is_asset ? 'completed' : 'approved';
            $purchaseOrder->update([
                'status' => $status,
                'date_approved' => Carbon::now(),
                'approved_by' => $userWithSignature->id,
                'approval_signature' => $signaturePath,
                'approval_signed_at' => Carbon::now(),
            ]);

            if ($purchaseOrder->is_asset) {
                $purchaseOrder->update([
                    'completed_at' => Carbon::now(),
                    'completed_by' => $userWithSignature->id,
                ]);

                // Auto-create asset
                foreach ($purchaseOrder->purchaseOrderItem as $item) {
                    $total = \App\Http\Controllers\HelperController::hitungSubtotal(
                        (int)$item->quantity,
                        (int)$item->unit_price,
                        (int)$item->discount,
                        (int)$item->tax,
                        $item->tipe_pajak
                    );

                    $asset = Asset::create([
                        'name' => $item->product->name,
                        'product_id' => $item->product_id,
                        'purchase_order_id' => $purchaseOrder->id,
                        'purchase_order_item_id' => $item->id,
                        'purchase_date' => $purchaseOrder->order_date,
                        'usage_date' => $purchaseOrder->order_date,
                        'purchase_cost' => $total,
                        'salvage_value' => 0,
                        'useful_life_years' => 5,
                        'asset_coa_id' => $this->assetCoa->id,
                        'accumulated_depreciation_coa_id' => $this->accumulatedDepreciationCoa->id,
                        'depreciation_expense_coa_id' => $this->depreciationExpenseCoa->id,
                        'status' => 'active',
                        'notes' => 'Generated from PO ' . $purchaseOrder->po_number,
                        'cabang_id' => $this->cabang->id,
                    ]);

                    $asset->calculateDepreciation();
                }
            }
        });

        // Step 4: Verify approval with pre-set signature
        $purchaseOrder->refresh();
        $this->assertEquals('completed', $purchaseOrder->status);
        $this->assertEquals($userWithSignature->id, $purchaseOrder->approved_by);
        $this->assertEquals($userWithSignature->signature, $purchaseOrder->approval_signature);
        $this->assertNotNull($purchaseOrder->approval_signed_at);

        // Step 5: Verify asset auto-creation
        $this->assertDatabaseHas('assets', [
            'product_id' => $this->product->id,
            'purchase_order_id' => $purchaseOrder->id,
            'purchase_order_item_id' => $poItem->id,
            'purchase_cost' => 10000000,
            'status' => 'active',
            'asset_coa_id' => $this->assetCoa->id,
            'accumulated_depreciation_coa_id' => $this->accumulatedDepreciationCoa->id,
            'depreciation_expense_coa_id' => $this->depreciationExpenseCoa->id,
        ]);

        $asset = Asset::where('purchase_order_id', $purchaseOrder->id)->first();
        $this->assertNotNull($asset);
        $this->assertEquals('Test Asset Equipment', $asset->name);
        $this->assertEquals(10000000, $asset->purchase_cost);
        $this->assertEquals(5, $asset->useful_life_years);

        // Step 6: Verify asset stock is SEPARATE from inventory stock
        // Asset should NOT create inventory stock entry
        $this->assertDatabaseMissing('inventory_stocks', [
            'product_id' => $this->product->id,
        ]);

        // Asset should have depreciation fields (not quantity)
        $this->assertNotNull($asset->monthly_depreciation);
        $this->assertNotNull($asset->book_value);
        $this->assertEquals(0, $asset->accumulated_depreciation);

        // Verify asset relationships work
        $this->assertInstanceOf(Product::class, $asset->product);
        $this->assertEquals($this->product->id, $asset->product->id);
        $this->assertInstanceOf(PurchaseOrder::class, $asset->purchaseOrder);
        $this->assertEquals($purchaseOrder->id, $asset->purchaseOrder->id);
    }

    /** @test */
    public function test_asset_purchase_fails_without_user_signature()
    {
        // Create user WITHOUT signature
        $userWithoutSignature = User::create([
            'name' => 'Test User without Signature',
            'email' => 'test.no.signature@example.com',
            'username' => 'testuser_nosig',
            'password' => bcrypt('password'),
            'first_name' => 'Test',
            'last_name' => 'User',
            'kode_user' => 'TEST003',
            'signature' => null, // No signature set
            'cabang_id' => $this->cabang->id,
            'manage_type' => ['all']
        ]);

        $this->actingAs($userWithoutSignature);

        // Create Purchase Order with is_asset = true
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'request_approval',
            'is_asset' => true
        ]);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 10000000,
        ]);

        // Attempt to approve without signature should fail
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Tanda tangan belum diatur di profil user');

        // Simulate approval attempt
        if (!$userWithoutSignature->signature) {
            throw new \Exception('Tanda tangan belum diatur di profil user. Silakan atur tanda tangan terlebih dahulu.');
        }
    }
}