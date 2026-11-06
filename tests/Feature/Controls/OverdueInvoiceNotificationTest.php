<?php

/**
 * Isu 8 — Status jatuh tempo otomatis: `invoices:check-overdue` (terjadwal harian, lihat routes/console.php) mengubah
 * status invoice yang lewat jatuh tempo dan MASIH bersisa menjadi Terlambat. Pengingat untuk finance tampil di
 * Dashboard Eksekutif (kartu Piutang lewat jatuh tempo dan Hutang jatuh tempo <= 7 hari), bukan notifikasi database.
 */

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Cabang;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\SaleOrder;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/** Invoice pembelian (hutang) $days hari lewat jatuh tempo, masih bersisa $remaining dari $total. */
function overdueApInvoice(string $number, int $days, float $total = 1000000, ?float $remaining = null): Invoice
{
    $remaining ??= $total;
    $cabang = Cabang::factory()->create();
    $supplier = Supplier::factory()->create();
    $po = PurchaseOrder::factory()->create(['supplier_id' => $supplier->id, 'cabang_id' => $cabang->id]);
    // withoutEvents: InvoiceObserver::created() otomatis membuat AccountPayable/AccountReceivable + jurnal untuk invoice
    // non-draft — di sini nilai AP dikendalikan manual agar sisa/terbayar sesuai skenario tes.
    $invoice = Invoice::withoutEvents(fn () => Invoice::withoutGlobalScopes()->create([
        'invoice_number' => $number, 'from_model_type' => PurchaseOrder::class, 'from_model_id' => $po->id, 'cabang_id' => $cabang->id,
        'currency_id' => Currency::firstOrCreate(['code' => 'IDR'], ['name' => 'Rupiah', 'symbol' => 'Rp'])->id,
        'status' => Invoice::STATUS_SENT, 'invoice_date' => Carbon::now()->subDays($days + 30), 'due_date' => Carbon::now()->subDays($days),
        'subtotal' => $total, 'total' => $total,
    ]));
    AccountPayable::factory()->create(['invoice_id' => $invoice->id, 'supplier_id' => $supplier->id, 'total' => $total, 'paid' => $total - $remaining, 'remaining' => $remaining, 'status' => 'Belum Lunas']);

    return $invoice->fresh();
}

/** Invoice penjualan (piutang) $days hari lewat jatuh tempo, masih bersisa $remaining dari $total. */
function overdueArInvoice(string $number, int $days, float $total = 2000000, ?float $remaining = null): Invoice
{
    $remaining ??= $total;
    $cabang = Cabang::factory()->create();
    $customer = \App\Models\Customer::factory()->create();
    $so = SaleOrder::factory()->create(['customer_id' => $customer->id, 'cabang_id' => $cabang->id]);
    $invoice = Invoice::withoutEvents(fn () => Invoice::withoutGlobalScopes()->create([
        'invoice_number' => $number, 'from_model_type' => SaleOrder::class, 'from_model_id' => $so->id, 'cabang_id' => $cabang->id,
        'status' => Invoice::STATUS_SENT, 'invoice_date' => Carbon::now()->subDays($days + 30), 'due_date' => Carbon::now()->subDays($days),
        'subtotal' => $total, 'total' => $total,
    ]));
    AccountReceivable::factory()->create(['invoice_id' => $invoice->id, 'customer_id' => $customer->id, 'total' => $total, 'paid' => $total - $remaining, 'remaining' => $remaining, 'status' => 'Belum Lunas']);

    return $invoice->fresh();
}

it('migrasi notifications: dapat dibangun dari kosong dan idempoten bila tabel sudah ada', function () {
    expect(Schema::hasTable('notifications'))->toBeTrue();
    Artisan::call('migrate', ['--force' => true]);   // jalan ulang: tidak boleh error walau tabel sudah ada
    expect(Schema::hasTable('notifications'))->toBeTrue();
});

it('invoice hutang dan piutang lewat jatuh tempo berubah menjadi Terlambat', function () {
    $ap = overdueApInvoice('PINV-OD-1', days: 5, total: 1000000);
    $ar = overdueArInvoice('INV-OD-1', days: 22, total: 2000000);

    Artisan::call('invoices:check-overdue');

    expect($ap->fresh()->status)->toBe(Invoice::STATUS_OVERDUE)
        ->and($ar->fresh()->status)->toBe(Invoice::STATUS_OVERDUE);
});

it('--dry-run: status TIDAK berubah', function () {
    $invoice = overdueApInvoice('PINV-OD-DRY', days: 5);

    Artisan::call('invoices:check-overdue', ['--dry-run' => true]);

    expect($invoice->fresh()->status)->not->toBe(Invoice::STATUS_OVERDUE);
});

it('menjalankan dua kali tidak error dan status tetap Terlambat', function () {
    $invoice = overdueApInvoice('PINV-OD-TWICE', days: 5);

    Artisan::call('invoices:check-overdue');
    $exitCode = Artisan::call('invoices:check-overdue');

    expect($exitCode)->toBe(0)->and($invoice->fresh()->status)->toBe(Invoice::STATUS_OVERDUE);
});

function overdueExecutiveDashboard(): \Illuminate\Testing\TestResponse
{
    Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
    $user = User::factory()->create(['cabang_id' => null, 'manage_type' => 'all']);
    $user->assignRole('Super Admin');

    return test()->actingAs($user)->get(route('dashboard', ['refresh' => 1]));
}

it('dashboard: tanpa piutang/hutang jatuh tempo, kartu menampilkan nol, bukan angka karangan', function () {
    overdueExecutiveDashboard()
        ->assertOk()
        ->assertSee('Tidak ada piutang terlambat')
        ->assertSee('Tidak ada hutang jatuh tempo');
});

it('dashboard: piutang dan hutang yang lewat jatuh tempo muncul di kartu dengan jumlah nyata', function () {
    overdueArInvoice('INV-DASH-1', days: 10, total: 2000000);
    overdueApInvoice('PINV-DASH-1', days: 3, total: 1000000);

    overdueExecutiveDashboard()
        ->assertOk()
        ->assertSee('1 customer')
        ->assertSee('1 supplier')
        ->assertDontSee('Tidak ada piutang terlambat')
        ->assertDontSee('Tidak ada hutang jatuh tempo');
});
