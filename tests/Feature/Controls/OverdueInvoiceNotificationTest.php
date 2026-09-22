<?php

/**
 * Isu 8 — Status jatuh tempo otomatis + notifikasi ke finance: `invoices:check-overdue` (terjadwal harian, lihat
 * routes/console.php) mengubah status invoice yang lewat jatuh tempo dan MASIH bersisa menjadi Terlambat, lalu
 * mengirim notifikasi database Filament ke pengguna berizin `view account payable` (hutang) / `view account
 * receivable` (piutang). Migrasi `notifications` (dulu ada di DB tetapi tidak pernah tercatat sebagai migrasi)
 * dibuktikan idempoten dan dapat dibangun dari kosong.
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
use App\Services\OverdueInvoiceNotifier;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function overdueUser(array $permissions = []): User
{
    $user = User::factory()->create();
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        $user->givePermissionTo($permission);
    }

    return $user;
}

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

it('invoice hutang lewat jatuh tempo: status jadi Terlambat + notifikasi ke pengguna berizin "view account payable" saja', function () {
    $finance = overdueUser(['view account payable']);
    $sales = overdueUser(['view account receivable']);   // berizin piutang, BUKAN hutang — tidak boleh dapat notifikasi ini
    $invoice = overdueApInvoice('PINV-OD-1', days: 5, total: 1000000, remaining: 1000000);

    Artisan::call('invoices:check-overdue');

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_OVERDUE)
        ->and($finance->fresh()->unreadNotifications()->count())->toBe(1)
        ->and($sales->fresh()->unreadNotifications()->count())->toBe(0);

    $data = $finance->fresh()->unreadNotifications()->first()->data;
    expect($data['title'])->toContain('Hutang')->toContain('1')->and($data['body'])->toContain('PINV-OD-1')->toContain('Rp 1.000.000');
});

it('invoice piutang lewat jatuh tempo: status jadi Terlambat + notifikasi ke pengguna berizin "view account receivable" saja', function () {
    $sales = overdueUser(['view account receivable']);
    $finance = overdueUser(['view account payable']);
    $invoice = overdueArInvoice('INV-OD-1', days: 22, total: 2000000, remaining: 2000000);

    Artisan::call('invoices:check-overdue');

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_OVERDUE)
        ->and($sales->fresh()->unreadNotifications()->count())->toBe(1)
        ->and($finance->fresh()->unreadNotifications()->count())->toBe(0);

    $data = $sales->fresh()->unreadNotifications()->first()->data;
    expect($data['title'])->toContain('Piutang')->and($data['body'])->toContain('INV-OD-1')->toContain('Rp 2.000.000');
});

it('hutang dan piutang jatuh tempo BERSAMAAN dalam satu jalankan: masing-masing hanya masuk kelompok notifikasi miliknya', function () {
    $finance = overdueUser(['view account payable']);
    $sales = overdueUser(['view account receivable']);
    overdueApInvoice('PURCHASE-MIX-001', days: 5, total: 1000000);
    overdueArInvoice('SALES-MIX-001', days: 22, total: 2000000);

    Artisan::call('invoices:check-overdue');

    $financeBody = $finance->fresh()->unreadNotifications()->first()->data['body'];
    $salesBody = $sales->fresh()->unreadNotifications()->first()->data['body'];

    expect($finance->fresh()->unreadNotifications()->count())->toBe(1)->and($financeBody)->toContain('PURCHASE-MIX-001')->not->toContain('SALES-MIX-001')
        ->and($sales->fresh()->unreadNotifications()->count())->toBe(1)->and($salesBody)->toContain('SALES-MIX-001')->not->toContain('PURCHASE-MIX-001');
});

it('--dry-run: status TIDAK berubah dan TIDAK ada notifikasi terkirim', function () {
    $finance = overdueUser(['view account payable']);
    $invoice = overdueApInvoice('PINV-OD-DRY', days: 5);

    Artisan::call('invoices:check-overdue', ['--dry-run' => true]);

    expect($invoice->fresh()->status)->not->toBe(Invoice::STATUS_OVERDUE)
        ->and($finance->fresh()->notifications()->count())->toBe(0);
});

it('idempoten: menjalankan dua kali hanya mengirim notifikasi SEKALI (bukan setiap hari selama masih Terlambat)', function () {
    $finance = overdueUser(['view account payable']);
    overdueApInvoice('PINV-OD-TWICE', days: 5);

    Artisan::call('invoices:check-overdue');
    Artisan::call('invoices:check-overdue');

    expect($finance->fresh()->notifications()->count())->toBe(1);
});

it('tidak ada pengguna berizin: tidak mengirim notifikasi dan tidak error', function () {
    overdueApInvoice('PINV-OD-NOONE', days: 5);

    $exitCode = Artisan::call('invoices:check-overdue');

    expect($exitCode)->toBe(0);
});

it('lebih dari 5 invoice: nomor dipotong dan diberi keterangan "dan N lainnya"; total sisa dijumlah benar', function () {
    $finance = overdueUser(['view account payable']);
    foreach (range(1, 7) as $i) {
        overdueApInvoice("PINV-OD-MANY-{$i}", days: 3, total: 100000, remaining: 100000);
    }

    Artisan::call('invoices:check-overdue');

    $data = $finance->fresh()->unreadNotifications()->first()->data;
    expect($data['title'])->toContain('7 invoice')->and($data['body'])->toContain('dan 2 lainnya')->toContain('Rp 700.000');
});

it('OverdueInvoiceNotifier::notify: koleksi kosong tidak melempar dan tidak mengirim apa pun', function () {
    overdueUser(['view account payable']);
    app(OverdueInvoiceNotifier::class)->notify(collect());
    expect(User::whereHas('notifications')->count())->toBe(0);
});
