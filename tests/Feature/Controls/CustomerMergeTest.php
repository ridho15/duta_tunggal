<?php

/**
 * T4.3 — customers:merge & CustomerMerger (D14/D33): semua referensi pindah dalam satu transaksi, bukti sebelum/sesudah,
 * customer yang digabung di-soft-delete dengan merged_into, dry-run identik dengan apply, cadangan CSV.
 */

use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Deposit;
use App\Services\CustomerMerger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/** Dua customer ganda (A survivor, B digabung): A punya 1 SO + piutang 3 jt; B punya 2 SO + Quotation + piutang 5 jt + deposit 2 jt. */
function mrgScenario(bool $depositOnBoth = false): array
{
    $ctx = stkContext();
    $a = $ctx['customer'];
    $b = Customer::factory()->create(['cabang_id' => $ctx['cabang']->id, 'name' => 'PT Uji Stok (dup)', 'tempo_kredit' => 30, 'tipe_pembayaran' => 'Bebas']);
    $ctxA = $ctx;
    $ctxB = array_merge($ctx, ['customer' => $b]);

    [$soA] = stkSaleOrder($ctxA, 1, ['status' => 'completed', 'total_amount' => 3000000]);
    ctlInvoiceWithAr($ctxA, $soA, 3000000);
    [$soB1] = stkSaleOrder($ctxB, 1, ['status' => 'completed', 'total_amount' => 5000000]);
    stkSaleOrder($ctxB, 1, ['status' => 'approved', 'total_amount' => 1000000]);
    ctlInvoiceWithAr($ctxB, $soB1, 5000000);
    ctlQuotation($ctxB, 1000000);

    $coa = \App\Models\ChartOfAccount::firstOrCreate(['code' => '2160.04'], ['name' => 'Deposit Pelanggan', 'type' => 'Liability', 'is_active' => true, 'opening_balance' => 0]);
    $deposit = fn (Customer $c, float $amount) => Deposit::create([
        'from_model_type' => Customer::class, 'from_model_id' => $c->id, 'amount' => $amount, 'used_amount' => 0, 'remaining_amount' => $amount,
        'coa_id' => $coa->id, 'status' => 'active', 'created_by' => $ctx['user']->id, 'deposit_number' => 'DEP-'.uniqid(),
    ]);
    $deposit($b, 2000000);
    if ($depositOnBoth) {
        $deposit($a, 500000);
    }

    return [$ctx, $a->fresh(), $b->fresh()];
}

it('penggabungan memindahkan SEMUA referensi ke survivor; customer yang digabung di-soft-delete dengan merged_into; angka terbukti sama', function () {
    [$ctx, $a, $b] = mrgScenario();
    $merger = app(CustomerMerger::class);
    $before = ['a' => $merger->snapshot($a), 'b' => $merger->snapshot($b)];

    $result = $merger->merge($a, $b);

    $after = $merger->snapshot($a->fresh());
    expect($after['receivables'])->toBe($before['a']['receivables'] + $before['b']['receivables'])      // 3 jt + 5 jt
        ->and($after['receivables'])->toBe(8000000.0)
        ->and($after['deposit'])->toBe(2000000.0)
        ->and($after['documents']['sale_orders'])->toBe(3)                                                 // 1 + 2
        ->and($after['documents']['quotations'])->toBe(1)
        ->and($after['documents']['account_receivables'])->toBe(2)
        ->and($merger->snapshot($b)['documents'])->toBe(array_fill_keys(CustomerMerger::TABLES, 0))         // tidak ada yang tertinggal di B
        ->and($result['moved']['sale_orders'])->toHaveCount(2);

    $trashed = Customer::withTrashed()->find($b->id);
    expect($trashed->trashed())->toBeTrue()->and($trashed->merged_into)->toBe($a->id)
        ->and(Customer::find($b->id))->toBeNull()
        ->and(AccountReceivable::where('customer_id', $b->id)->count())->toBe(0);
});

it('penolak: sama, sudah digabung, survivor sudah digabung, dan deposit di kedua sisi', function () {
    [$ctx, $a, $b] = mrgScenario(depositOnBoth: true);
    $merger = app(CustomerMerger::class);

    expect(fn () => $merger->merge($a, $a))->toThrow(ValidationException::class, 'tidak boleh sama');
    expect(fn () => $merger->merge($a, $b))->toThrow(ValidationException::class, 'sama-sama punya deposit');
    expect($b->fresh()->trashed())->toBeFalse()->and(AccountReceivable::where('customer_id', $b->id)->count())->toBe(1);   // tidak ada yang berubah

    $b->forceFill(['merged_into' => $a->id])->save();
    expect(fn () => $merger->merge($a, $b->fresh()))->toThrow(ValidationException::class, 'sudah digabung');
    expect(fn () => $merger->merge($b->fresh(), $a))->toThrow(ValidationException::class, 'sudah digabung');
});

it('verifikasi sebelum/sesudah membatalkan penggabungan bila ada tabel yang tidak ikut dipindah', function () {
    [$ctx, $a, $b] = mrgScenario();
    // Simulasikan dokumen yang "tidak terpindah": tambahkan tabel ke daftar via pemantau yang mengembalikan customer_id ke B di tengah jalan
    $fired = false;
    $stragglerId = (int) \Illuminate\Support\Facades\DB::table('sale_orders')->where('customer_id', $b->id)->value('id');   // SO milik B pada skenario ini (bukan baris sisa tes lain)
    expect($stragglerId)->toBeGreaterThan(0);
    \Illuminate\Support\Facades\DB::listen(function ($query) use ($b, $stragglerId, &$fired) {
        if (! $fired && str_contains($query->sql, 'update `sale_orders` set `customer_id`')) {
            $fired = true;   // hanya sekali (pemantau memicu kueri lagi)
            \Illuminate\Support\Facades\DB::table('sale_orders')->where('id', $stragglerId)->update(['customer_id' => $b->id]);   // satu SO "tercecer" kembali ke B
        }
    });

    expect(fn () => app(CustomerMerger::class)->merge($a, $b))->toThrow(RuntimeException::class, 'Verifikasi penggabungan');

    expect(Customer::withTrashed()->find($b->id)->trashed())->toBeFalse()   // seluruhnya dibatalkan
        ->and(AccountReceivable::where('customer_id', $b->id)->count())->toBe(1);
});

it('customers:merge: dry-run tidak mengubah apa pun; --apply memindahkan dan menulis CSV cadangan; pasangan tak valid dilaporkan', function () {
    [$ctx, $a, $b] = mrgScenario();
    $dir = sys_get_temp_dir().'/merge-'.uniqid();
    mkdir($dir);
    $csv = $dir.'/gabung.csv';
    file_put_contents($csv, "survivor_id,merged_id\n{$a->id},{$b->id}\n{$a->id},999999\n");

    $this->artisan('customers:merge', ['csv' => $csv, '--out-dir' => $dir])->expectsOutputToContain('DRY-RUN')->assertFailed();   // gagal karena satu pasangan tak valid
    expect(Customer::find($b->id))->not->toBeNull()->and(AccountReceivable::where('customer_id', $b->id)->count())->toBe(1);

    $this->artisan('customers:merge', ['csv' => $csv, '--apply' => true, '--out-dir' => $dir])->assertFailed();
    expect(Customer::withTrashed()->find($b->id)->merged_into)->toBe($a->id)
        ->and(AccountReceivable::where('customer_id', $a->id)->count())->toBe(2)
        ->and(glob($dir.'/gabung-customer-cadangan-*.csv'))->not->toBeEmpty();
});

it('customers:merge: CSV tanpa kolom yang benar ditolak', function () {
    $csv = sys_get_temp_dir().'/salah-'.uniqid().'.csv';
    file_put_contents($csv, "a,b\n1,2\n");

    $this->artisan('customers:merge', ['csv' => $csv])->expectsOutputToContain('survivor_id dan merged_id')->assertFailed();
    $this->artisan('customers:merge', ['csv' => '/tmp/tidak-ada-'.uniqid().'.csv'])->expectsOutputToContain('tidak ditemukan')->assertFailed();
});
