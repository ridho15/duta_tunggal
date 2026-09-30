<?php

namespace Tests\Feature;

use App\Filament\Resources\SalesInvoiceResource\Pages\EditSalesInvoice;
use App\Models\AccountReceivable;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
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
    use RefreshDatabase;

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
            'manage_type' => (string) $this->cabang->id,
        ]);
        $this->staffUser->assignRole($staffRole);

        $this->staffUser->givePermissionTo('view any invoice');
        $this->staffUser->givePermissionTo('view invoice');
        $this->staffUser->givePermissionTo('update invoice');

        $this->customer = Customer::factory()->create([
            'cabang_id' => $this->cabang->id,
        ]);

        foreach ([
            ['code' => '1120', 'name' => 'Piutang Dagang', 'type' => 'Asset'],
            ['code' => '4000', 'name' => 'Penjualan', 'type' => 'Revenue'],
            ['code' => '2120.06', 'name' => 'PPN Keluaran', 'type' => 'Liability'],
            ['code' => '1140.20', 'name' => 'Barang Terkirim', 'type' => 'Asset'],
            ['code' => '5100.10', 'name' => 'HPP Barang', 'type' => 'Expense'],
            ['code' => '6100.02', 'name' => 'Biaya Pengiriman', 'type' => 'Expense'],
            ['code' => '4100.01', 'name' => 'Diskon Penjualan', 'type' => 'Expense'],
        ] as $coa) {
            ChartOfAccount::firstOrCreate(
                ['code' => $coa['code']],
                ['name' => $coa['name'], 'type' => $coa['type'], 'is_active' => true]
            );
        }

        $this->arCoa = ChartOfAccount::where('code', '1120')->first();
        $this->salesCoa = ChartOfAccount::where('code', '4000')->first();
        $this->ppnCoa = ChartOfAccount::where('code', '2120.06')->first();

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
            'cost_price' => 50000,
            'sell_price' => 100000,
        ]);
    }

    private function createTestSaleOrder(int $quantity = 1, float $price = 100000): SaleOrder
    {
        $so = SaleOrder::create([
            'so_number' => 'SO-' . uniqid(),
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'tipe_pengiriman' => 'Ambil Sendiri',
            'total_amount' => $quantity * $price,
        ]);

        SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id' => $this->product->id,
            'quantity' => $quantity,
            'unit_price' => $price,
            'discount' => 0,
            'total_price' => $quantity * $price,
            'warehouse_id' => $this->warehouse->id,
        ]);

        return $so;
    }

    private function createTestInvoice(
        SaleOrder $so,
        string $status = 'unpaid',
        float $subtotal = 100000,
        float $total = 100000,
        string $taxType = 'None',
        float $taxRate = 0
    ): Invoice {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-' . uniqid(),
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $so->id,
            'cabang_id' => $this->cabang->id,
            'customer_name' => $this->customer->name,
            'customer_phone' => $this->customer->phone,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'status' => $status,
            'subtotal' => $subtotal,
            'dpp' => $subtotal,
            'total' => $total,
            'ppn_rate' => $taxRate,
            'tipe_pajak' => $taxType,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => $subtotal,
            'subtotal' => $subtotal,
            'total' => $total,
            'tax_rate' => $taxRate,
            'tax_amount' => $total - $subtotal,
            'coa_id' => $this->salesCoa->id,
        ]);

        return $invoice;
    }

    public function test_non_super_admin_cannot_access_edit_page_for_unpaid_sales_invoice(): void
    {
        $so = $this->createTestSaleOrder(1, 100000);
        $invoice = $this->createTestInvoice($so, 'unpaid', 100000, 111000, 'Eksklusif', 11);

        $this->actingAs($this->staffUser);

        // Edit page must redirect away for non-draft invoices when accessed by non-Super Admin
        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->getKey()])
            ->assertRedirect();
    }

    public function test_non_super_admin_can_access_edit_page_for_draft_sales_invoice(): void
    {
        $so = $this->createTestSaleOrder(1, 100000);
        $invoice = $this->createTestInvoice($so, 'draft', 100000, 100000, 'None', 0);

        $this->actingAs($this->staffUser);

        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->getKey()])
            ->assertSuccessful();
    }

    public function test_super_admin_can_access_edit_page_for_unpaid_sales_invoice(): void
    {
        $so = $this->createTestSaleOrder(1, 100000);
        $invoice = $this->createTestInvoice($so, 'unpaid', 100000, 111000, 'Eksklusif', 11);

        $this->actingAs($this->superAdmin);

        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->getKey()])
            ->assertSuccessful();
    }

    public function test_super_admin_emergency_override_updates_ar_reposts_journals_and_logs_activity(): void
    {
        // 1. Create Sale Order with 2 items @ 100,000 = 200,000
        $so = $this->createTestSaleOrder(2, 100000);

        // 2. Create Invoice in posted/unpaid status with None tax (total = 200,000)
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

        // Verify initial AR was created by InvoiceObserver
        $initialAr = AccountReceivable::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($initialAr);
        $this->assertEquals(200000.0, (float) $initialAr->total);

        // Post initial journal (Debit AR 200,000, Credit Sales 200,000)
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
        JournalEntry::create([
            'coa_id' => $this->salesCoa->id,
            'date' => now(),
            'reference' => $invoice->invoice_number,
            'description' => 'Initial Sales Revenue',
            'debit' => 0,
            'credit' => 200000,
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

        // Journal Entries should be reposted with debit = credit = 222,000
        $journals = JournalEntry::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->get();

        $this->assertNotEmpty($journals);
        $totalDebit = (float) $journals->sum('debit');
        $totalCredit = (float) $journals->sum('credit');
        $this->assertEquals($totalDebit, $totalCredit, 'Jurnal hasil repost harus seimbang');
        $arJournal = $journals->firstWhere('coa_id', $this->arCoa->id);
        $this->assertNotNull($arJournal, 'Jurnal Piutang Dagang harus ada');
        $this->assertEquals(222000.0, (float) $arJournal->debit, 'Jurnal Piutang Dagang debit harus sesuai total baru invoice');

        // Activity log for emergency override must exist
        $overrideActivity = Activity::where('log_name', 'emergency_invoice_override')
            ->where('subject_type', Invoice::class)
            ->where('subject_id', $invoice->id)
            ->first();

        $this->assertNotNull($overrideActivity, 'Aktivitas emergency_invoice_override harus tercatat');
        $this->assertEquals($this->superAdmin->id, $overrideActivity->causer_id);
    }
}
