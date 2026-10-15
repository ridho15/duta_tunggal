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

    // Dua tes lama (Super Admin boleh mengedit / koreksi darurat invoice terposting) DIPENSIUNKAN: sejak 12 Okt 2026 invoice non-draft
    // terkunci mutlak untuk semua pengguna termasuk Super Admin (B26; koreksi lewat Credit Note). Dicakup tc_uat_c04 di UATPriorityAndIntegrityFixesTest.
}
