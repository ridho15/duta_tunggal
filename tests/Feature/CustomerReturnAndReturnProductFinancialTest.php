<?php

namespace Tests\Feature;

use App\Filament\Resources\CustomerReturnResource\Pages\ViewCustomerReturn;
use App\Filament\Resources\ReturnProductResource\Pages\EditReturnProduct;
use App\Models\AccountReceivable;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ReturnProduct;
use App\Models\ReturnProductItem;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\TaxSetting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CustomerReturnService;
use App\Services\ReturnProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerReturnAndReturnProductFinancialTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Cabang $cabang;
    private Customer $customer;
    private Product $product;
    private Warehouse $warehouse;
    private ChartOfAccount $arCoa;
    private ChartOfAccount $salesCoa;
    private ChartOfAccount $salesReturnCoa;
    private ChartOfAccount $ppnCoa;
    private ChartOfAccount $inventoryCoa;
    private ChartOfAccount $cogsCoa;
    private ChartOfAccount $goodsDeliveryCoa;

    protected function setUp(): void
    {
        parent::setUp();

        TaxSetting::updateOrCreate(
            ['type' => 'PPN', 'status' => true],
            [
                'name' => 'PPN 11%',
                'rate' => 11,
                'status' => true,
                'effective_date' => now()->subYear()->toDateString(),
            ]
        );

        $this->cabang = Cabang::firstOrCreate(
            ['kode' => 'CBG-CR-TEST'],
            ['nama' => 'Cabang CR Test', 'alamat' => 'Jl. CR Test', 'status' => 1]
        );

        $role = Role::findOrCreate('Super Admin', 'web');

        $this->user = User::factory()->create([
            'username' => 'cr_admin_' . uniqid(),
            'email' => 'cr_admin_' . uniqid() . '@example.com',
            'kode_user' => 'CR' . strtoupper(substr(uniqid(), -4)),
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
        ] as $coa) {
            ChartOfAccount::firstOrCreate(
                ['code' => $coa['code']],
                ['name' => $coa['name'], 'type' => $coa['type'], 'is_active' => true]
            );
        }

        $this->inventoryCoa = ChartOfAccount::where('code', '1101.01')->first();
        $this->arCoa = ChartOfAccount::where('code', '1120')->first();
        $this->goodsDeliveryCoa = ChartOfAccount::where('code', '1140.20')->first();
        $this->ppnCoa = ChartOfAccount::where('code', '2120.06')->first();
        $this->salesCoa = ChartOfAccount::where('code', '4000')->first();
        $this->salesReturnCoa = ChartOfAccount::where('code', '4120.10')->first();
        $this->cogsCoa = ChartOfAccount::where('code', '5100.10')->first();

        $this->warehouse = Warehouse::create([
            'cabang_id' => $this->cabang->id,
            'kode' => 'WH-CR-' . strtoupper(substr(uniqid(), -4)),
            'name' => 'Gudang CR Test',
            'location' => 'Area CR',
            'status' => 1,
        ]);

        $this->product = Product::factory()->create([
            'cabang_id' => $this->cabang->id,
            'sales_coa_id' => $this->salesCoa->id,
            'inventory_coa_id' => $this->inventoryCoa->id,
            'cogs_coa_id' => $this->cogsCoa->id,
            'goods_delivery_coa_id' => $this->goodsDeliveryCoa->id,
            'cost_price' => 50000,
            'sell_price' => 100000,
        ]);
    }

    public function test_customer_return_with_replace_decision_creates_financial_journal_and_adjusts_ar(): void
    {
        $so = SaleOrder::create([
            'so_number' => 'SO-CR-' . uniqid(),
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'tipe_pengiriman' => 'Ambil Sendiri',
            'total_amount' => 200000,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-CR-' . uniqid(),
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $so->id,
            'cabang_id' => $this->cabang->id,
            'customer_name' => $this->customer->name,
            'customer_phone' => $this->customer->phone,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'status' => 'unpaid',
            'subtotal' => 200000,
            'dpp' => 200000,
            'total' => 200000,
            'ppn_rate' => 0,
            'tipe_pajak' => 'None',
        ]);

        $invItem = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 100000,
            'subtotal' => 200000,
            'total' => 200000,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'coa_id' => $this->salesCoa->id,
        ]);

        // Initial stock = 10 pcs
        InventoryStock::updateOrCreate(
            [
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
            ],
            [
                'qty_available' => 10,
                'qty_reserved' => 0,
                'qty_min' => 0,
            ]
        );

        // Customer Return with 1 pc returned, decision 'replace', qc_result 'pass'
        $customerReturn = CustomerReturn::create([
            'return_number' => CustomerReturn::generateReturnNumber(),
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'return_date' => now(),
            'reason' => 'Barang rusak kemasan',
            'status' => CustomerReturn::STATUS_APPROVED,
            'received_by' => $this->user->id,
            'qc_inspected_by' => $this->user->id,
            'approved_by' => $this->user->id,
        ]);

        CustomerReturnItem::create([
            'customer_return_id' => $customerReturn->id,
            'product_id' => $this->product->id,
            'invoice_item_id' => $invItem->id,
            'quantity' => 1,
            'problem_description' => 'Kemasan penyok',
            'qc_result' => CustomerReturnItem::QC_RESULT_PASS,
            'decision' => CustomerReturnItem::DECISION_REPLACE,
        ]);

        app(CustomerReturnService::class)->processCompletion($customerReturn);

        // 1. Assert CustomerReturn is completed and stock restored (10 + 1 = 11)
        $customerReturn->refresh();
        $this->assertEquals(CustomerReturn::STATUS_COMPLETED, $customerReturn->status);
        $stock = InventoryStock::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse->id)->first();
        $this->assertEquals(11.0, (float) $stock->qty_available);

        // 2. Assert Financial Journal Entries were created:
        // Debit Retur Penjualan (4120.10) 100,000; Credit Piutang Dagang (1120) 100,000
        $finJournals = JournalEntry::where('source_type', CustomerReturn::class)
            ->where('source_id', $customerReturn->id)
            ->where('description', 'like', '%Sales Return%')
            ->get();

        $this->assertNotEmpty($finJournals, 'Jurnal finansial retur penjualan harus terbentuk.');
        $salesReturnDebit = $finJournals->where('coa_id', $this->salesReturnCoa->id)->first();
        $this->assertNotNull($salesReturnDebit, 'Akun Retur Penjualan harus didebit.');
        $this->assertEquals(100000.0, (float) $salesReturnDebit->debit);

        $arCredit = JournalEntry::where('source_type', CustomerReturn::class)
            ->where('source_id', $customerReturn->id)
            ->where('coa_id', $this->arCoa->id)
            ->first();
        $this->assertNotNull($arCredit, 'Akun Piutang Dagang harus dikredit.');
        $this->assertEquals(100000.0, (float) $arCredit->credit);

        // 3. Assert AccountReceivable is reduced by 100,000 (from 200,000 to 100,000)
        $ar = AccountReceivable::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($ar);
        $this->assertEquals(100000.0, (float) $ar->remaining);
        $this->assertEquals(100000.0, (float) $ar->total);
    }

    public function test_customer_return_approval_blocked_if_any_item_lacks_qc_result(): void
    {
        $so = SaleOrder::create([
            'so_number' => 'SO-QC-' . uniqid(),
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'total_amount' => 100000,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-QC-' . uniqid(),
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $so->id,
            'cabang_id' => $this->cabang->id,
            'customer_name' => $this->customer->name,
            'customer_phone' => $this->customer->phone,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'status' => 'unpaid',
            'subtotal' => 100000,
            'total' => 100000,
        ]);

        $invItem = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => 100000,
            'subtotal' => 100000,
            'total' => 100000,
        ]);

        $customerReturn = CustomerReturn::create([
            'return_number' => CustomerReturn::generateReturnNumber(),
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'return_date' => now(),
            'reason' => 'Barang rusak',
            'status' => CustomerReturn::STATUS_QC_INSPECTION,
        ]);

        // Item created without qc_result
        CustomerReturnItem::create([
            'customer_return_id' => $customerReturn->id,
            'product_id' => $this->product->id,
            'invoice_item_id' => $invItem->id,
            'quantity' => 1,
            'problem_description' => 'Rusak',
            'qc_result' => null,
            'decision' => CustomerReturnItem::DECISION_REPLACE,
        ]);

        $this->actingAs($this->user);

        // Attempting to approve via ViewCustomerReturn page must be rejected
        Livewire::test(ViewCustomerReturn::class, ['record' => $customerReturn->getKey()])
            ->callAction('approve');

        $customerReturn->refresh();
        $this->assertEquals(CustomerReturn::STATUS_QC_INSPECTION, $customerReturn->status, 'Status tidak boleh berubah jika hasil QC kosong.');

        // Attempting processCompletion directly must also throw
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('belum memiliki hasil pemeriksaan QC');
        app(CustomerReturnService::class)->processCompletion($customerReturn);
    }

    public function test_return_product_for_uninvoiced_do_credits_goods_delivery_account(): void
    {
        $so = SaleOrder::create([
            'so_number' => 'SO-DO-UNINV-' . uniqid(),
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'total_amount' => 100000,
        ]);

        $do = DeliveryOrder::create([
            'do_number' => 'DO-UNINV-' . uniqid(),
            'sale_order_id' => $so->id,
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'confirmed',
            'delivery_date' => now(),
        ]);

        $doItem = DeliveryOrderItem::create([
            'delivery_order_id' => $do->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        // Uninvoiced DO: No invoice created!
        $returnProduct = ReturnProduct::create([
            'return_number' => 'RET-UNINV-' . strtoupper(substr(uniqid(), -4)),
            'from_model_type' => DeliveryOrder::class,
            'from_model_id' => $do->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
            'reason' => 'Barang retur sebelum diinvoice',
        ]);

        ReturnProductItem::create([
            'return_product_id' => $returnProduct->id,
            'from_item_model_type' => DeliveryOrderItem::class,
            'from_item_model_id' => $doItem->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'condition' => 'good',
        ]);

        app(ReturnProductService::class)->updateQuantityFromModel($returnProduct);

        $returnProduct->refresh();
        $this->assertEquals('approved', $returnProduct->status);

        // Journals for ReturnProduct: Debit Persediaan 50,000, Credit Barang Terkirim 50,000
        $journals = JournalEntry::where('source_type', ReturnProduct::class)
            ->where('source_id', $returnProduct->id)
            ->get();

        $this->assertNotEmpty($journals);
        $debitInventory = $journals->where('coa_id', $this->inventoryCoa->id)->first();
        $creditDelivery = $journals->where('coa_id', $this->goodsDeliveryCoa->id)->first();

        $this->assertNotNull($debitInventory, 'Akun Persediaan harus didebit.');
        $this->assertEquals(50000.0, (float) $debitInventory->debit);
        $this->assertNotNull($creditDelivery, 'Akun Barang Terkirim harus dikredit untuk DO yang belum di-invoice.');
        $this->assertEquals(50000.0, (float) $creditDelivery->credit);
    }

    public function test_return_product_for_invoiced_do_credits_cogs_account_and_updates_sales_invoice(): void
    {
        $so = SaleOrder::create([
            'so_number' => 'SO-DO-INV-' . uniqid(),
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'total_amount' => 200000,
        ]);

        SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_price' => 100000,
            'discount' => 0,
            'total_price' => 200000,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $do = DeliveryOrder::create([
            'do_number' => 'DO-INV-' . uniqid(),
            'sale_order_id' => $so->id,
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'confirmed',
            'delivery_date' => now(),
        ]);

        $doItem = DeliveryOrderItem::create([
            'delivery_order_id' => $do->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        // Invoice is issued for this DO!
        $invoice = Invoice::create([
            'invoice_number' => 'INV-DO-' . uniqid(),
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $so->id,
            'cabang_id' => $this->cabang->id,
            'customer_name' => $this->customer->name,
            'customer_phone' => $this->customer->phone,
            'delivery_orders' => [$do->id],
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'status' => 'unpaid',
            'subtotal' => 200000,
            'dpp' => 200000,
            'total' => 200000,
            'ppn_rate' => 0,
            'tipe_pajak' => 'None',
        ]);

        $invItem = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 100000,
            'subtotal' => 200000,
            'total' => 200000,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'coa_id' => $this->salesCoa->id,
        ]);

        $returnProduct = ReturnProduct::create([
            'return_number' => 'RET-INV-' . strtoupper(substr(uniqid(), -4)),
            'from_model_type' => DeliveryOrder::class,
            'from_model_id' => $do->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
            'reason' => 'Barang retur setelah invoice terbit',
        ]);

        ReturnProductItem::create([
            'return_product_id' => $returnProduct->id,
            'from_item_model_type' => DeliveryOrderItem::class,
            'from_item_model_id' => $doItem->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'condition' => 'good',
        ]);

        app(ReturnProductService::class)->updateQuantityFromModel($returnProduct);

        // 1. Assert ReturnProduct journal credits HPP/COGS (NOT Barang Terkirim)
        $journals = JournalEntry::where('source_type', ReturnProduct::class)
            ->where('source_id', $returnProduct->id)
            ->get();

        $this->assertNotEmpty($journals);
        $debitInventory = $journals->where('coa_id', $this->inventoryCoa->id)->first();
        $creditCogs = $journals->where('coa_id', $this->cogsCoa->id)->first();
        $creditDelivery = $journals->where('coa_id', $this->goodsDeliveryCoa->id)->first();

        $this->assertNotNull($debitInventory, 'Akun Persediaan harus didebit.');
        $this->assertEquals(50000.0, (float) $debitInventory->debit);
        $this->assertNotNull($creditCogs, 'Akun HPP/COGS harus dikredit untuk DO yang sudah di-invoice.');
        $this->assertEquals(50000.0, (float) $creditCogs->credit);
        $this->assertNull($creditDelivery, 'Akun Barang Terkirim TIDAK boleh dikredit saat DO sudah di-invoice.');

        // 2. Audit Protection: Assert linked posted Invoice item quantity is NOT modified in-place
        // (Perbaikan UAT Poin 3: Invoice yang sudah diposting/unpaid tidak boleh diubah in-place demi integritas jurnal historis)
        $invItem->refresh();
        $this->assertEquals(2.0, (float) $invItem->quantity, 'Invoice non-draft harus dilindungi dari perubahan in-place.');
        $this->assertEquals(200000.0, (float) $invItem->total);

        $invoice->refresh();
        $this->assertEquals(200000.0, (float) $invoice->total, 'Header invoice non-draft tidak boleh diubah in-place.');

        // 3. Verifikasi bahwa Invoice DRAFT dapat disesuaikan secara in-place
        $draftInvoice = Invoice::create([
            'invoice_number' => 'INV-DRAFT-' . uniqid(),
            'from_model_type' => DeliveryOrder::class,
            'from_model_id' => $do->id,
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'customer_phone' => $this->customer->phone,
            'delivery_orders' => [$do->id],
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'status' => Invoice::STATUS_DRAFT,
            'subtotal' => 200000,
            'dpp' => 200000,
            'total' => 200000,
            'ppn_rate' => 0,
            'tipe_pajak' => 'None',
        ]);

        $draftInvItem = InvoiceItem::create([
            'invoice_id' => $draftInvoice->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 100000,
            'subtotal' => 200000,
            'total' => 200000,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'coa_id' => $this->salesCoa->id,
        ]);

        app(ReturnProductService::class)->adjustLinkedSalesInvoice($returnProduct, $do);

        $draftInvItem->refresh();
        $draftInvoice->refresh();
        $this->assertEquals(1.0, (float) $draftInvItem->quantity, 'Invoice DRAFT harus berkurang kuantitasnya saat retur.');
        $this->assertEquals(100000.0, (float) $draftInvoice->total, 'Invoice DRAFT harus berkurang totalnya saat retur.');
    }

    public function test_edit_return_product_loads_max_quantity_and_blocks_over_return(): void
    {
        $so = SaleOrder::create([
            'so_number' => 'SO-EDIT-' . uniqid(),
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'total_amount' => 500000,
        ]);

        $do = DeliveryOrder::create([
            'do_number' => 'DO-EDIT-' . uniqid(),
            'sale_order_id' => $so->id,
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'confirmed',
            'delivery_date' => now(),
        ]);

        $doItem = DeliveryOrderItem::create([
            'delivery_order_id' => $do->id,
            'product_id' => $this->product->id,
            'quantity' => 5, // Source quantity is 5
        ]);

        $returnProduct = ReturnProduct::create([
            'return_number' => 'RET-EDIT-' . strtoupper(substr(uniqid(), -4)),
            'from_model_type' => DeliveryOrder::class,
            'from_model_id' => $do->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
            'reason' => 'Draft retur',
        ]);

        $retItem = ReturnProductItem::create([
            'return_product_id' => $returnProduct->id,
            'from_item_model_type' => DeliveryOrderItem::class,
            'from_item_model_id' => $doItem->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'condition' => 'good',
        ]);

        $this->actingAs($this->user);

        // Edit page must populate max_quantity as 5
        $livewire = Livewire::test(EditReturnProduct::class, ['record' => $returnProduct->getKey()]);
        $formData = $livewire->get('data');
        $this->assertNotEmpty($formData['returnProductItem']);
        $itemKey = array_key_first($formData['returnProductItem']);
        $this->assertEquals(5.0, (float) $formData['returnProductItem'][$itemKey]['max_quantity']);

        // Attempting to set quantity to 10 (exceeding 5)
        $formData['returnProductItem'][$itemKey]['quantity'] = 10;
        $livewire->fillForm($formData);
        
        // Either afterStateUpdated auto-clamps it to 5, or save fails with form error
        $savedQty = (float) ($livewire->get('data')['returnProductItem'][$itemKey]['quantity'] ?? 0);
        if ($savedQty > 5) {
            $livewire->call('save')->assertHasFormErrors();
        } else {
            // Reactive afterStateUpdated clamped it to max_quantity (5.0)
            $this->assertEquals(5.0, $savedQty);
        }
    }
}
