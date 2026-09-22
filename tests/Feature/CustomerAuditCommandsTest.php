<?php

/**
 * T1.7 — audit data customer (READ-ONLY): customers:audit-duplicates dan customers:audit-credit-limit.
 * Jaminan utama: tidak ada data yang berubah (baris, updated_at, saldo), hanya CSV yang ditulis.
 */

use App\Enums\PaymentStatus;
use App\Models\AccountReceivable;
use App\Models\Cabang;
use App\Models\Customer;
use App\Models\Quotation;
use App\Models\SaleOrder;
use App\Services\CreditValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

function auditDir(): string
{
    $dir = sys_get_temp_dir().'/audit-'.uniqid();
    mkdir($dir);

    return $dir;
}

/** Sidik jari data: jumlah baris + checksum kolom penting seluruh tabel yang mungkin disentuh audit. */
function auditFingerprint(): array
{
    $fingerprint = [];
    foreach (['customers', 'quotations', 'sale_orders', 'account_receivables', 'deposits', 'customer_receipts', 'cabangs'] as $table) {
        $fingerprint[$table] = [
            'rows' => DB::table($table)->count(),
            'sum' => md5(json_encode(DB::table($table)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all())),
        ];
    }

    return $fingerprint;
}

function auditCsv(string $dir): array
{
    $files = File::glob($dir.'/*.csv');
    if ($files === []) {
        return [];
    }

    $rows = array_map('str_getcsv', file($files[0], FILE_IGNORE_NEW_LINES));
    $rows[0][0] = ltrim($rows[0][0], "\xEF\xBB\xBF");   // BOM
    $header = array_shift($rows);

    return array_map(fn ($row) => array_combine($header, $row), $rows);
}

function auditCustomer(array $attributes = []): Customer
{
    return Customer::factory()->create(array_merge(['cabang_id' => Cabang::factory()->create()->id], $attributes));
}

// ───────────────────────────── customers:audit-duplicates ─────────────────────────────

it('menemukan grup "Daya Teknik Medika" (varian nama + NPWP + telepon sama + kode menyerupai NIK), survivor = transaksi terbanyak', function () {
    $tax = '3171234567890001';
    $a = auditCustomer(['code' => '3171234567890001', 'name' => 'DAYA TEKNIK MEDIKA', 'nik_npwp' => $tax, 'phone' => '0812-1111-2222']);
    $b = auditCustomer(['code' => 'CUST-0002', 'name' => 'PT Daya Teknik Medika', 'nik_npwp' => '', 'phone' => '0812-9999-0000']);
    $c = auditCustomer(['code' => 'CUST-0003', 'name' => 'Daya Teknik Medika, CV', 'nik_npwp' => $tax, 'phone' => '+62 812 1111 2222']);
    $other = auditCustomer(['code' => 'CUST-0004', 'name' => 'Daya Sentosa', 'nik_npwp' => '', 'phone' => '0813-5555-6666']);

    // $b punya transaksi terbanyak → saran survivor
    foreach ([1, 2, 3] as $i) {
        Quotation::create(['quotation_number' => "QO-AUD-{$i}", 'customer_id' => $b->id, 'cabang_id' => $b->cabang_id, 'date' => now(), 'status' => 'draft', 'total_amount' => 1000]);
    }
    SaleOrder::create(['customer_id' => $c->id, 'cabang_id' => $c->cabang_id, 'so_number' => 'SO-AUD-1', 'order_date' => now(), 'status' => 'draft', 'tipe_pengiriman' => 'Kirim Langsung', 'exchange_rate' => 1.0, 'tempo_pembayaran' => 30, 'shipped_to' => 'x']);

    $dir = auditDir();
    expect(Artisan::call('customers:audit-duplicates', ['--out-dir' => $dir]))->toBe(0);
    $output = Artisan::output();

    expect($output)->toContain('Grup kandidat duplikat: 1')->toContain('Kode menyerupai NIK: 1')->toContain('tidak ada data yang diubah');

    $rows = auditCsv($dir);
    $byId = collect($rows)->keyBy('id');

    expect($rows)->toHaveCount(3)
        ->and($byId->keys()->map(fn ($k) => (int) $k)->sort()->values()->all())->toBe([$a->id, $b->id, $c->id])
        ->and($byId->has($other->id))->toBeFalse()
        ->and($byId[$b->id]['saran_survivor'])->toBe('YA')
        ->and($byId[$a->id]['saran_survivor'])->toBe('')
        ->and($byId[$b->id]['quotation'])->toBe('3')
        ->and($byId[$c->id]['sale_order'])->toBe('1')
        ->and($byId[$b->id]['total_transaksi'])->toBe('3')
        ->and($byId[$a->id]['kode_menyerupai_nik'])->toBe('YA')
        ->and($byId[$b->id]['kode_menyerupai_nik'])->toBe('')
        ->and($rows[0]['alasan'])->toContain('nama sama')->toContain('NPWP/NIK sama')->toContain('telepon sama');

    File::deleteDirectory($dir);
});

it('READ-ONLY: tidak ada baris atau nilai yang berubah pada customer maupun tabel transaksi', function () {
    auditCustomer(['name' => 'PT Sama Persis', 'nik_npwp' => '3171234567890001']);
    auditCustomer(['name' => 'Sama Persis PT', 'nik_npwp' => '3171234567890001']);
    $before = auditFingerprint();

    $dir = auditDir();
    Artisan::call('customers:audit-duplicates', ['--out-dir' => $dir]);
    Artisan::call('customers:audit-credit-limit', ['--out-dir' => $dir]);

    expect(auditFingerprint())->toBe($before);

    File::deleteDirectory($dir);
});

it('--no-csv tidak menulis berkas; tanpa duplikat melaporkan 0 grup dan tidak membuat CSV', function () {
    auditCustomer(['name' => 'Alpha Unik Pertama', 'nik_npwp' => '', 'phone' => '0811-0000-0001']);
    auditCustomer(['name' => 'Bravo Unik Kedua', 'nik_npwp' => '', 'phone' => '0811-0000-0002']);

    $dir = auditDir();
    Artisan::call('customers:audit-duplicates', ['--out-dir' => $dir]);
    expect(Artisan::output())->toContain('Grup kandidat duplikat: 0')
        ->and(File::glob($dir.'/*.csv'))->toBe([]);

    auditCustomer(['name' => 'Alpha Unik Pertama']);
    Artisan::call('customers:audit-duplicates', ['--out-dir' => $dir, '--no-csv' => true]);
    expect(Artisan::output())->toContain('Grup kandidat duplikat: 1')
        ->and(File::glob($dir.'/*.csv'))->toBe([]);

    File::deleteDirectory($dir);
});

it('customer terhapus (soft delete) tidak ikut diaudit', function () {
    $a = auditCustomer(['name' => 'PT Sudah Dihapus']);
    $b = auditCustomer(['name' => 'Sudah Dihapus PT']);
    $b->delete();

    Artisan::call('customers:audit-duplicates', ['--no-csv' => true]);

    expect(Artisan::output())->toContain('Grup kandidat duplikat: 0');
});

// ───────────────────────────── customers:audit-credit-limit ─────────────────────────────

it('audit limit kredit menandai limit 0, limit sangat tinggi, tempo 0, dan piutang melebihi limit; yang wajar tidak muncul', function () {
    $zero = auditCustomer(['name' => 'Limit Nol', 'tipe_pembayaran' => 'Kredit', 'kredit_limit' => 0, 'tempo_kredit' => 30]);
    $huge = auditCustomer(['name' => 'Limit Raksasa', 'tipe_pembayaran' => 'Kredit', 'kredit_limit' => 999999999999, 'tempo_kredit' => 30]);
    $tempo0 = auditCustomer(['name' => 'Tempo Nol', 'tipe_pembayaran' => 'Kredit', 'kredit_limit' => 50000000, 'tempo_kredit' => 0]);
    $over = auditCustomer(['name' => 'Melebihi Limit', 'tipe_pembayaran' => 'Kredit', 'kredit_limit' => 5000000, 'tempo_kredit' => 30]);
    $fine = auditCustomer(['name' => 'Wajar Saja', 'tipe_pembayaran' => 'Kredit', 'kredit_limit' => 50000000, 'tempo_kredit' => 30]);
    auditCustomer(['name' => 'Bebas Limit', 'tipe_pembayaran' => 'Bebas', 'kredit_limit' => 5000000]);
    auditCustomer(['name' => 'COD Nol', 'tipe_pembayaran' => 'COD (Bayar Lunas)', 'kredit_limit' => 0]);

    AccountReceivable::factory()->create(['customer_id' => $over->id, 'total' => 6000000, 'paid' => 0, 'remaining' => 6000000, 'status' => PaymentStatus::UNPAID->value]);

    $dir = auditDir();
    Artisan::call('customers:audit-credit-limit', ['--out-dir' => $dir]);
    $output = Artisan::output();

    expect($output)->toContain('Customer bertipe Kredit: 5')->toContain('perlu ditinjau: 4')->toContain('non-Kredit dengan limit > 0 (tidak berlaku): 1');

    $rows = collect(auditCsv($dir))->keyBy('id');

    expect($rows->keys()->map(fn ($k) => (int) $k)->sort()->values()->all())->toBe([$zero->id, $huge->id, $tempo0->id, $over->id])
        ->and($rows[$zero->id]['catatan'])->toContain('LIMIT 0')
        ->and($rows[$huge->id]['catatan'])->toContain('LIMIT SANGAT TINGGI')
        ->and($rows[$tempo0->id]['catatan'])->toContain('TEMPO 0 HARI')
        ->and($rows[$over->id]['catatan'])->toContain('PIUTANG MELEBIHI LIMIT')
        ->and((float) $rows[$over->id]['piutang_berjalan'])->toBe(6000000.0)
        ->and((float) $rows[$over->id]['persen_terpakai'])->toBe(120.0)
        ->and($rows->has($fine->id))->toBeFalse();

    File::deleteDirectory($dir);
});

it('ambang --threshold dapat diubah dan piutang berjalan sama dengan CreditValidationService', function () {
    $customer = auditCustomer(['name' => 'Ambang Rendah', 'tipe_pembayaran' => 'Kredit', 'kredit_limit' => 20000000, 'tempo_kredit' => 30]);
    AccountReceivable::factory()->create(['customer_id' => $customer->id, 'total' => 3000000, 'paid' => 0, 'remaining' => 3000000, 'status' => PaymentStatus::UNPAID->value]);
    AccountReceivable::factory()->create(['customer_id' => $customer->id, 'total' => 9000000, 'paid' => 9000000, 'remaining' => 0, 'status' => PaymentStatus::PAID->value]);

    $dir = auditDir();
    Artisan::call('customers:audit-credit-limit', ['--out-dir' => $dir, '--threshold' => 10000000]);
    $rows = collect(auditCsv($dir))->keyBy('id');

    expect($rows->has($customer->id))->toBeTrue()
        ->and($rows[$customer->id]['catatan'])->toContain('LIMIT SANGAT TINGGI')
        ->and((float) $rows[$customer->id]['piutang_berjalan'])->toBe(app(CreditValidationService::class)->getCurrentCreditUsage($customer));

    File::deleteDirectory($dir);
});
