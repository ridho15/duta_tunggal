<?php

namespace Tests\Feature;

use App\Filament\Resources\SalesInvoiceResource\Pages\EditSalesInvoice;
use App\Models\AccountReceivable;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\TaxSetting;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SalesInvoicePostingLockTest extends TestCase
{
    private User $superAdmin;
    private User $staffUser;
    private Cabang $cabang;
    private Customer $customer;
    private Product $product;
    private Warehouse $warehouse;
    private ChartOfAccount $arCoa;
    private ChartOfAccount $salesCoa;
    private ChartOfAccount $ppnCoa;

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
            ['kode' => 'CBG-LOCK-TEST'],
            ['nama' => 'Cabang Lock Test', 'alamat' => 'Jl. Lock Test', 'status' => 1]
        );

        $superAdminRole = Role::findOrCreate('Super Admin', 'web');
        $staffRole = Role::findOrCreate('Staff Finance', 'web');

        $this->superAdmin = User::factory()->create([
            'username' => 'sa_' . uniqid(),
            'email' => 'sa_' . uniqid() . '@example.com',
            'kode_user' => 'SA' . strtoupper(substr(uniqid(), -4)),
            'cabang_id' => $this->cabang->id,
            'manage_type' => 'all',
        ]);
        $this->superAdmin->assignRole($superAdminRole);

        $this->staffUser = User::factory()->create([
            'username' => 'staff_' . uniqid(),
            'email' => 'staff_' . uniqid() . '@example.com',
            'kode_user' => 'ST' . strtoupper(substr(uniqid(), -4)),
            'cabang_id' => $this->cabang->id,
            'manage_type' => 'all',
        ]);
        $this->staffUser->assignRole($staffRole);

        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->staffUser->givePermissionTo('view any invoice');
        $this->staffUser->givePermissionTo('view invoice');
        $this->staffUser->givePermissionTo('update invoice');

        $this->customer = Customer::factory()->create([
            'cabang_id' => $this->cabang->id,
        ]);

        $this->arCoa = ChartOfAccount::firstOrCreate(
            ['code' => '1120'],
            ['name' => 'Piutang Dagang', 'type' => 'Asset', 'is_active' => true]
        );

        $this->salesCoa = ChartOfAccount::firstOrCreate(
            ['code' => '4000'],
            ['name' => 'Penjualan', 'type' => 'Revenue', 'is_active' => true]
        );

        $this->ppnCoa = ChartOfAccount::firstOrCreate(
            ['code' => '2120.06'],
            ['name' => 'PPN Keluaran', 'type' => 'Liability', 'is_active' => true]
        );

        $cogsCoa = ChartOfAccount::firstOrCreate(
            ['code' => '5100.10'],
            ['name' => 'Harga Pokok Penjualan', 'type' => 'Expense', 'is_active' => true]
        );

        $goodsDeliveryCoa = ChartOfAccount::firstOrCreate(
            ['code' => '1140.20'],
            ['name' => 'Barang Terkirim (In Transit) Lock Test', 'type' => 'Asset', 'is_active' => true]
        );

        $this->warehouse = Warehouse::create([
            'cabang_id' => $this->cabang->id,
            'kode' => 'WH-LOCK-' . strtoupper(substr(uniqid(), -4)),
            'name' => 'Gudang Lock Test',
            'location' => 'Area Test',
            'status' => 1,
        ]);

        $this->product = Product::factory()->create([
            'cabang_id' => $this->cabang->id,
            'sales_coa_id' => $this->salesCoa->id,
            'cogs_coa_id' => $cogsCoa->id,
            'goods_delivery_coa_id' => $goodsDeliveryCoa->id,
            'cost_price' => 50000,
            'sell_price' => 100000,
        ]);
    }

    /**
     * InvoiceObserver::createSalesInvoiceAr() resolves customer_id from the linked SaleOrder
     * (Invoice itself has no customer_id column) — a fake from_model_id leaves it null and
     * violates the account_receivables.customer_id NOT NULL constraint on invoice creation.
     */
    private function createLinkedSaleOrder(): SaleOrder
    {
        return SaleOrder::create([
            'so_number' => 'SO-LOCK-' . uniqid(),
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'total_amount' => 111000,
        ]);
    }

    public function test_non_super_admin_cannot_access_edit_page_for_unpaid_sales_invoice(): void
    {
        $so = $this->createLinkedSaleOrder();

        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-UNPAID-' . uniqid(),
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $so->id,
            'cabang_id' => $this->cabang->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'status' => 'unpaid',
            'subtotal' => 100000,
            'total' => 111000,
            'ppn_rate' => 11,
            'tipe_pajak' => 'Eksklusif',
        ]);

        $this->actingAs($this->staffUser);

        // Edit page must redirect away with an error notification for non-draft invoices
        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->getKey()])
            ->assertRedirect();
    }

    public function test_non_super_admin_can_access_edit_page_for_draft_sales_invoice(): void
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-DRAFT-' . uniqid(),
            'from_model_type' => SaleOrder::class,
            'from_model_id' => 9999,
            'cabang_id' => $this->cabang->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'status' => 'draft',
            'subtotal' => 100000,
            'total' => 100000,
            'ppn_rate' => 0,
            'tipe_pajak' => 'None',
        ]);

        $this->actingAs($this->staffUser);

        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->getKey()])
            ->assertSuccessful();
    }

    public function test_super_admin_can_access_edit_page_for_unpaid_sales_invoice(): void
    {
        $so = $this->createLinkedSaleOrder();

        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-UNPAID-SA-' . uniqid(),
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $so->id,
            'cabang_id' => $this->cabang->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'status' => 'unpaid',
            'subtotal' => 100000,
            'total' => 111000,
            'ppn_rate' => 11,
            'tipe_pajak' => 'Eksklusif',
        ]);

        $this->actingAs($this->superAdmin);

        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->getKey()])
            ->assertSuccessful();
    }

    public function test_super_admin_emergency_override_updates_ar_reposts_journals_and_logs_activity(): void
    {
        // 1. Create Sale Order with 1 item
        $so = SaleOrder::create([
            'so_number' => 'SO-EMERGENCY-' . uniqid(),
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'total_amount' => 100000,
        ]);

        $soItem = SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_price' => 100000,
            'discount' => 0,
            'total_price' => 200000,
            'warehouse_id' => $this->warehouse->id,
        ]);

        // 2. Create Invoice in posted/unpaid status
        $invoice = Invoice::create([
            'invoice_number' => 'INV-OVERRIDE-' . uniqid(),
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $so->id,
            'cabang_id' => $this->cabang->id,
            'customer_name' => $this->customer->name,
            'customer_phone' => $this->customer->phone,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'status' => 'unpaid',
            'tipe_pajak' => 'None',
            'ppn_rate' => 0,
            'subtotal' => 200000,
            'dpp' => 200000,
            'total' => 200000,
        ]);

        InvoiceItem::create([
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

        // Initial AR is created automatically by InvoiceObserver::created() (from_model_id
        // points to a real SaleOrder above, so customer_id resolves) — no manual insert needed.

        // Post initial journal
        JournalEntry::create([
            'coa_id' => $this->arCoa->id,
            'date' => now(),
            'reference' => $invoice->invoice_number,
            'description' => 'Initial AR',
            'debit' => 200000,
            'credit' => 0,
            'source_type' => Invoice::class,
            'source_id' => $invoice->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $this->actingAs($this->superAdmin);

        // Super Admin edits the invoice: change PPN to 11% Eksklusif
        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->getKey()])
            ->fillForm([
                'tipe_pajak' => 'Eksklusif',
                'ppn_rate' => 11,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $invoice->refresh();

        // Total should now be 200,000 + 11% (22,000) = 222,000
        $this->assertEquals(222000.0, (float) $invoice->total);
        $this->assertEquals('Eksklusif', $invoice->tipe_pajak);

        // Account Receivable should be updated to 222,000
        $ar = AccountReceivable::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($ar);
        $this->assertEquals(222000.0, (float) $ar->total);
        $this->assertEquals(222000.0, (float) $ar->remaining);

        // Journal Entries should be reposted and balanced. postSalesInvoice() posts two legs:
        // AR/Revenue/PPN (222,000) and a separate COGS/goods-delivery-release leg (2 x 50,000
        // cost_price = 100,000) — so the grand total is 322,000, not the invoice total alone.
        $journals = JournalEntry::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->get();

        $this->assertNotEmpty($journals);
        $totalDebit = (float) $journals->sum('debit');
        $totalCredit = (float) $journals->sum('credit');
        $this->assertEquals($totalDebit, $totalCredit, 'Jurnal hasil repost harus seimbang');

        // The AR leg specifically must reflect the new invoice total (222,000), proving the
        // repost picked up the new tipe_pajak/ppn_rate rather than stale item amounts.
        $arDebit = (float) $journals->where('coa_id', $this->arCoa->id)->sum('debit');
        $this->assertEquals(222000.0, $arDebit, 'Debit akun Piutang Dagang harus sesuai total baru invoice');

        // Activity log for emergency override must exist
        $overrideActivity = Activity::where('log_name', 'emergency_invoice_override')
            ->where('subject_type', Invoice::class)
            ->where('subject_id', $invoice->id)
            ->first();

        $this->assertNotNull($overrideActivity, 'Aktivitas emergency_invoice_override harus tercatat');
        $this->assertEquals($this->superAdmin->id, $overrideActivity->causer_id);
    }
}
