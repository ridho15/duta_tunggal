<?php

namespace Tests\Feature\Sales;

use App\Models\Cabang;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\User;
use App\Models\Product;
use App\Models\UnitOfMeasure;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SalesReportViewCardRefactorTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Cabang $cabang;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create([
            'kode' => 'CBG-REP-TEST',
            'nama' => 'Cabang Report Test',
            'alamat' => 'Jl. Laporan No. 1',
            'telepon' => '021-123456',
            'status' => true,
        ]);

        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $perms = ['view any sale order', 'view sale order', 'view any invoice', 'view invoice'];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        $role->givePermissionTo($perms);

        $this->user = User::factory()->create([
            'cabang_id' => $this->cabang->id,
            'manage_type' => ['all'],
        ]);
        $this->user->assignRole('Super Admin');

        $this->customer = Customer::factory()->create([
            'cabang_id' => $this->cabang->id,
            'tipe_pembayaran' => 'Kredit',
        ]);
    }

    public function test_invoice_mode_renders_structured_financial_and_reconciliation_cards(): void
    {
        $response = $this->actingAs($this->user)->get(route('penjualan.laporan.index', ['mode' => 'invoice']));

        $response->assertOk();
        $response->assertViewIs('penjualan.laporan.index');
        $response->assertSee('Total Penjualan (Bruto)');
        $response->assertSee('Beban Pokok (HPP)');
        $response->assertSee('Laba Kotor &amp; Margin', false);
        $response->assertSee('Piutang Belum Tertagih');
        $response->assertSee('Jumlah Faktur Terbit');
        $response->assertSee('Dasar Pengenaan Pajak (DPP)');
        $response->assertSee('PPN Terhitung (Keluaran)');
        $response->assertSee('Rekonsiliasi Jurnal HPP');
    }

    public function test_delivery_mode_renders_symmetric_five_cards(): void
    {
        $response = $this->actingAs($this->user)->get(route('penjualan.laporan.index', ['mode' => 'delivery']));

        $response->assertOk();
        $response->assertViewIs('penjualan.laporan.index');
        $response->assertSee('Jumlah Pengiriman');
        $response->assertSee('Total Qty');
        $response->assertSee('Total DPP');
        $response->assertSee('Total HPP (Stok)');
        $response->assertSee('Margin');
    }

    public function test_order_mode_renders_symmetric_five_cards(): void
    {
        $response = $this->actingAs($this->user)->get(route('penjualan.laporan.index', ['mode' => 'order']));

        $response->assertOk();
        $response->assertViewIs('penjualan.laporan.index');
        $response->assertSee('Total Pesanan');
        $response->assertSee('Total Kuantitas');
        $response->assertSee('Total DPP');
        $response->assertSee('Total Nilai Order');
        $response->assertSee('Produk Dipesan');
    }
}

