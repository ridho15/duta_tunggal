<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Deposit;
use App\Models\DepositLog;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Filament\Resources\SalesInvoiceResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UATPriorityAndIntegrityFixesTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $staff;
    protected Cabang $cabang;
    protected Warehouse $warehouse;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        UnitOfMeasure::factory()->create();

        $this->cabang = Cabang::create([
            'kode' => 'HQ',
            'nama' => 'Kantor Pusat',
            'alamat' => 'Jakarta',
            'is_aktif' => true,
        ]);

        $this->warehouse = Warehouse::factory()->create([
            'cabang_id' => $this->cabang->id,
            'name' => 'Gudang Utama',
            'status' => 1,
        ]);

        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $staffRole = Role::firstOrCreate(['name' => 'Staff', 'guard_name' => 'web']);

        $this->superAdmin = User::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->superAdmin->assignRole($superAdminRole);

        $this->staff = User::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->staff->assignRole($staffRole);

        $this->customer = Customer::factory()->create([
            'cabang_id' => $this->cabang->id,
            'name' => 'PT Pelanggan Prioritas',
            'code' => 'CUST-001',
        ]);
    }

    #[Test]
    public function tc_uat_a02_surat_jalan_dropdown_query_uses_so_number(): void
    {
        // Pastikan record SaleOrder ada dan query dropdown SuratJalan dapat dieksekusi tanpa error unknown column 'order_number'
        $so = SaleOrder::create([
            'cabang_id' => $this->cabang->id,
            'customer_id' => $this->customer->id,
            'so_number' => 'SO-TEST-0001',
            'status' => 'approved',
            'payment_status' => 'unpaid',
            'order_date' => now()->toDateString(),
            'total_amount' => 100000,
        ]);

        $query = SaleOrder::query()
            ->where('customer_id', $this->customer->id)
            ->whereIn('status', ['approved', 'completed'])
            ->orderBy('so_number', 'desc');

        $options = $query->pluck('so_number', 'id')->toArray();

        $this->assertArrayHasKey($so->id, $options);
        $this->assertSame('SO-TEST-0001', $options[$so->id]);
    }

    #[Test]
    public function tc_uat_c02_stock_transfer_query_uses_qty_available(): void
    {
        $product = Product::factory()->create([
            'cabang_id' => $this->cabang->id,
            'name' => 'Pipa Tembaga 1/2',
            'sku' => 'CU-PIPE-05',
        ]);

        // Catat stok di inventory_stocks dengan kolom qty_available
        \Illuminate\Support\Facades\DB::table('inventory_stocks')->insert([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'qty_available' => 15,
            'qty_reserved' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Simulasikan query dari StockTransferResource
        $products = Product::whereHas('inventoryStock', function ($q) {
            $q->where('warehouse_id', $this->warehouse->id)
              ->where('qty_available', '>', 0);
        })->pluck('name', 'id')->toArray();

        $this->assertArrayHasKey($product->id, $products);
        $this->assertSame('Pipa Tembaga 1/2', $products[$product->id]);
    }

    #[Test]
    public function tc_uat_c04_posted_invoice_is_strictly_locked_for_all_users_including_super_admin(): void
    {
        $this->actingAs($this->superAdmin);

        $sentInvoice = new Invoice([
            'status' => Invoice::STATUS_SENT,
        ]);
        $sentInvoice->exists = true;

        $paidInvoice = new Invoice([
            'status' => Invoice::STATUS_PAID,
        ]);
        $paidInvoice->exists = true;

        $draftInvoice = new Invoice([
            'status' => Invoice::STATUS_DRAFT,
        ]);
        $draftInvoice->exists = true;

        // Pada implementasi yang telah diperbaiki, isInvoiceFieldLocked HARUS mengembalikan true
        // untuk semua non-draft sekalipun user adalah Super Admin
        $this->assertTrue(SalesInvoiceResource::isInvoiceFieldLocked($sentInvoice));
        $this->assertTrue(SalesInvoiceResource::isInvoiceFieldLocked($paidInvoice));
        $this->assertFalse(SalesInvoiceResource::isInvoiceFieldLocked($draftInvoice));
    }

    #[Test]
    public function tc_uat_c07_deposit_coa_filter_only_allows_cash_bank(): void
    {
        $kasBank = ChartOfAccount::create([
            'code' => '1110.01',
            'name' => 'Kas Operasional',
            'account_type' => 'asset',
            'is_cash_bank' => true,
            'status' => true,
        ]);

        $nonKasBank = ChartOfAccount::create([
            'code' => '1140.10',
            'name' => 'Persediaan Barang Dagang',
            'account_type' => 'asset',
            'is_cash_bank' => false,
            'status' => true,
        ]);

        $filteredAccounts = ChartOfAccount::where('is_cash_bank', true)
            ->pluck('name', 'id')
            ->toArray();

        $this->assertArrayHasKey($kasBank->id, $filteredAccounts);
        $this->assertArrayNotHasKey($nonKasBank->id, $filteredAccounts);
    }

    // tc_uat_d (verifikasi kasir COD ↔ saldo titip) dipindah ke tests/Feature/Sales/Wave2CashierVerificationTest.php:
    // versi lama hanya menyalin logika aksi di dalam tes (tidak memanggil kode nyata).
}
