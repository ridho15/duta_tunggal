<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SaleOrder;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CustomerReturnService;
use App\Services\StockAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QuotationProductPriceSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Cabang $cabang;
    private Customer $customer;
    private Currency $currency;
    private Product $product1;
    private Product $product2;
    private Warehouse $warehouse;
    private ChartOfAccount $inventoryCoa;
    private ChartOfAccount $salesCoa;
    private ChartOfAccount $adjustmentGainCoa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::firstOrCreate(
            ['kode' => 'CBG-QSYNC'],
            ['nama' => 'Cabang QSync Test', 'alamat' => 'Jl. QSync', 'status' => 1]
        );

        $this->currency = Currency::firstOrCreate(
            ['code' => 'IDR'],
            ['name' => 'Rupiah', 'symbol' => 'Rp', 'exchange_rate' => 1.0, 'status' => 1, 'is_default' => true]
        );

        $role = Role::findOrCreate('Super Admin', 'web');

        $this->user = User::factory()->create([
            'username' => 'qsync_admin_' . uniqid(),
            'email' => 'qsync_admin_' . uniqid() . '@example.com',
            'kode_user' => 'QS' . strtoupper(substr(uniqid(), -4)),
            'cabang_id' => $this->cabang->id,
            'manage_type' => 'all',
        ]);
        $this->user->assignRole($role);

        $this->customer = Customer::factory()->create([
            'cabang_id' => $this->cabang->id,
        ]);

        foreach ([
            ['code' => '1101.01', 'name' => 'Persediaan Barang', 'type' => 'Asset'],
            ['code' => '1120', 'name' => 'Piutang Dagang', 'type' => 'Asset'],
            ['code' => '1140.20', 'name' => 'Barang Terkirim', 'type' => 'Asset'],
            ['code' => '2120.06', 'name' => 'PPN Keluaran', 'type' => 'Liability'],
            ['code' => '4000', 'name' => 'Penjualan', 'type' => 'Revenue'],
            ['code' => '4120.10', 'name' => 'Retur Penjualan', 'type' => 'Revenue'],
            ['code' => '5100.10', 'name' => 'HPP / COGS', 'type' => 'Expense'],
            ['code' => '7000.04', 'name' => 'Selisih Persediaan', 'type' => 'Revenue'],
        ] as $coa) {
            ChartOfAccount::firstOrCreate(
                ['code' => $coa['code']],
                ['name' => $coa['name'], 'type' => $coa['type'], 'is_active' => true, 'status' => 1]
            );
        }

        $this->inventoryCoa = ChartOfAccount::where('code', '1101.01')->first();
        $this->salesCoa = ChartOfAccount::where('code', '4000')->first();
        $cogsCoa = ChartOfAccount::where('code', '5100.10')->first();

        $this->warehouse = Warehouse::create([
            'cabang_id' => $this->cabang->id,
            'kode' => 'WH-QS-' . strtoupper(substr(uniqid(), -4)),
            'name' => 'Gudang QSync',
            'location' => 'Area QS',
            'status' => 1,
        ]);

        $this->product1 = Product::factory()->create([
            'cabang_id' => $this->cabang->id,
            'inventory_coa_id' => $this->inventoryCoa->id,
            'sales_coa_id' => $this->salesCoa->id,
            'cogs_coa_id' => $cogsCoa->id,
            'cost_price' => 50000,
            'sell_price' => 100000,
        ]);

        $this->product2 = Product::factory()->create([
            'cabang_id' => $this->cabang->id,
            'inventory_coa_id' => $this->inventoryCoa->id,
            'sales_coa_id' => $this->salesCoa->id,
            'cogs_coa_id' => $cogsCoa->id,
            'cost_price' => 150000,
            'sell_price' => 250000,
        ]);
    }

    public function test_quotation_api_auto_fills_product_sell_price_when_unit_price_is_zero(): void
    {
        $this->actingAs($this->user);

        $payload = [
            'header' => [
                'quotation_number' => 'QUO-TEST-' . strtoupper(substr(uniqid(), -5)),
                'customer_id' => $this->customer->id,
                'cabang_id' => $this->cabang->id,
                'date' => now()->toDateString(),
                'currency_id' => $this->currency->id,
                'tempo_pembayaran' => 30,
            ],
            'items' => [
                [
                    'product_id' => $this->product2->id,
                    'quantity' => 2,
                    'unit_price' => 0, // Zero price should fallback to product2->sell_price (250,000)
                    'discount' => 0,
                    'tax_type' => 'None',
                    'tax' => 0,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/quotations', $payload);
        $response->assertStatus(200);

        $quotation = Quotation::where('quotation_number', $payload['header']['quotation_number'])->first();
        $this->assertNotNull($quotation);
        $this->assertCount(1, $quotation->quotationItem);

        $item = $quotation->quotationItem->first();
        $this->assertEquals(250000.0, (float) $item->unit_price, 'Harga satuan harus otomatis menggunakan harga jual produk.');
        $this->assertEquals(500000.0, (float) $item->total_price);
        $this->assertEquals(500000.0, (float) $quotation->total_amount);
    }

    public function test_customer_return_stock_movement_is_idempotent_and_prevents_duplicate_movements(): void
    {
        $so = SaleOrder::create([
            'so_number' => 'SO-IDEM-' . uniqid(),
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'tipe_pengiriman' => 'Ambil Sendiri',
            'total_amount' => 200000,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-IDEM-' . uniqid(),
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $so->id,
            'cabang_id' => $this->cabang->id,
            'customer_name' => $this->customer->name,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'status' => 'unpaid',
            'subtotal' => 200000,
            'total' => 200000,
        ]);

        $invItem = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product1->id,
            'quantity' => 2,
            'price' => 100000,
            'subtotal' => 200000,
            'total' => 200000,
        ]);

        $customerReturn = CustomerReturn::create([
            'return_number' => CustomerReturn::generateReturnNumber(),
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'return_date' => now(),
            'reason' => 'Barang cacat',
            'status' => CustomerReturn::STATUS_APPROVED,
            'received_by' => $this->user->id,
            'qc_inspected_by' => $this->user->id,
            'approved_by' => $this->user->id,
        ]);

        $retItem = CustomerReturnItem::create([
            'customer_return_id' => $customerReturn->id,
            'product_id' => $this->product1->id,
            'invoice_item_id' => $invItem->id,
            'quantity' => 1,
            'problem_description' => 'Cacat pabrik',
            'qc_result' => CustomerReturnItem::QC_RESULT_PASS,
            'decision' => CustomerReturnItem::DECISION_REPLACE,
        ]);

        $service = app(CustomerReturnService::class);

        // First completion creates the stock movement
        $service->processCompletion($customerReturn);

        $movementCountFirst = StockMovement::where('from_model_type', CustomerReturn::class)
            ->where('from_model_id', $customerReturn->id)
            ->count();
        $this->assertEquals(1, $movementCountFirst);

        // Reset status to approved and stock_restored_at to null to simulate retry / second completion pass
        $customerReturn->update([
            'status' => CustomerReturn::STATUS_APPROVED,
            'stock_restored_at' => null,
        ]);
        $service->processCompletion($customerReturn);

        // Second pass MUST NOT create another stock movement
        $movementCountSecond = StockMovement::where('from_model_type', CustomerReturn::class)
            ->where('from_model_id', $customerReturn->id)
            ->count();
        $this->assertEquals(1, $movementCountSecond, 'StockMovement tidak boleh diduplikasi saat completion diproses ulang.');
    }

    public function test_stock_adjustment_approval_fails_with_validation_exception_if_product_lacks_inventory_coa(): void
    {
        // Create product without inventory COA and without default fallback COAs
        $orphanProduct = Product::factory()->create([
            'cabang_id' => $this->cabang->id,
            'inventory_coa_id' => null,
            'cost_price' => 50000,
        ]);

        // Temporarily delete fallback COAs to ensure null inventory COA trigger
        ChartOfAccount::whereIn('code', ['1140.10', '1140.01', '1140', '1100'])->delete();

        $adjustment = StockAdjustment::create([
            'adjustment_number' => StockAdjustment::generateAdjustmentNumber(),
            'warehouse_id' => $this->warehouse->id,
            'adjustment_date' => now()->toDateString(),
            'adjustment_type' => 'increase',
            'reason' => 'Penyesuaian Tanpa COA',
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        StockAdjustmentItem::create([
            'stock_adjustment_id' => $adjustment->id,
            'product_id' => $orphanProduct->id,
            'quantity_system' => 0,
            'quantity_actual' => 5,
            'difference_qty' => 5,
            'unit_cost' => 50000,
            'difference_value' => 250000,
        ]);

        $service = app(StockAdjustmentService::class);

        // Must throw ValidationException because inventory COA is missing (no silent unjournaled approvals!)
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Akun persediaan (COA)');

        $service->approveStockAdjustment($adjustment, $this->user->id);
    }
}
