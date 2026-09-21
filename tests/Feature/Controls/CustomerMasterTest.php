<?php

/**
 * T4.2 — CustomerService::create tunggal (X10): dedup (flag customer_dedup), kode CUST-00001 (flag central_numbering, D32),
 * customers:assign-codes, form Customer memakai layanan.
 */

use App\Models\ApprovalOverride;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function cstData(array $overrides = []): array
{
    return array_merge([
        'name' => 'PT Maju Bersama', 'address' => 'Jl. Uji 1', 'phone' => '081234567890', 'telephone' => '0212345678', 'email' => 'maju@example.test',
        'perusahaan' => 'PT Maju Bersama', 'tipe' => 'PKP', 'fax' => '-', 'nik_npwp' => '01.234.567.8-901.000', 'tempo_kredit' => 0, 'kredit_limit' => 0,
        'tipe_pembayaran' => 'Bebas',
    ], $overrides);
}

it('[flag mati] create memakai kode yang diisi form (atau membuat kode lama bila kosong) dan tidak menolak duplikat', function () {
    $ctx = stkContext();
    $service = app(CustomerService::class);

    $first = $service->create(cstData(['code' => 'KODE-MANUAL-1', 'cabang_id' => $ctx['cabang']->id]));
    $twin = $service->create(cstData(['code' => 'KODE-MANUAL-2', 'cabang_id' => $ctx['cabang']->id]));   // NIK/NPWP sama → tetap lolos (flag mati)
    $auto = $service->create(cstData(['name' => 'PT Lain', 'nik_npwp' => '99.999.999.9-999.000', 'phone' => '081900000001', 'cabang_id' => $ctx['cabang']->id]));

    expect($first->code)->toBe('KODE-MANUAL-1')->and($twin->exists)->toBeTrue()->and($auto->code)->toStartWith('CUS-'.now()->format('Ymd'));
});

it('[central_numbering] kode dibuat server CUST-00001 berurutan, global, mengabaikan kode isian form', function () {
    config(['sales.controls.central_numbering' => true]);
    $ctx = stkContext();
    $service = app(CustomerService::class);

    $a = $service->create(cstData(['code' => 'ABAIKAN', 'cabang_id' => $ctx['cabang']->id, 'nik_npwp' => '1111111111', 'phone' => '0811111111']));
    $b = $service->create(cstData(['code' => null, 'cabang_id' => $ctx['cabang']->id, 'nik_npwp' => '2222222222', 'phone' => '0822222222', 'name' => 'PT Dua']));

    expect($a->code)->toBe('CUST-00001')->and($b->code)->toBe('CUST-00002');
});

it('[customer_dedup] NIK/NPWP sama ditolak (format berbeda dinormalisasi); pesan menyebut customer yang sudah ada', function () {
    config(['sales.controls.customer_dedup' => true]);
    $ctx = stkContext();
    $service = app(CustomerService::class);
    $existing = $service->create(cstData(['code' => 'C-1', 'cabang_id' => $ctx['cabang']->id]));

    try {
        $service->create(cstData(['code' => 'C-2', 'name' => 'Nama Lain Sama Sekali', 'phone' => '081999999999', 'telephone' => '0219999999', 'nik_npwp' => '012345678901000', 'cabang_id' => $ctx['cabang']->id]));
        $this->fail('seharusnya ditolak');
    } catch (ValidationException $e) {
        expect($e->errors()['customer'][0])->toContain('C-1')->and($e->errors()['customer'][0])->toContain('NIK/NPWP sama');
    }

    expect(Customer::count())->toBeGreaterThanOrEqual(1)->and(Customer::where('code', 'C-2')->exists())->toBeFalse();
});

it('[customer_dedup] nama (badan usaha diabaikan) dan telepon (awalan 0/62/+62 diabaikan) sama ditolak; hanya nama sama atau hanya telepon sama lolos', function () {
    config(['sales.controls.customer_dedup' => true]);
    $ctx = stkContext();
    $service = app(CustomerService::class);
    $service->create(cstData(['code' => 'C-1', 'name' => 'PT Daya Teknik', 'nik_npwp' => '3333333333', 'phone' => '081234567890', 'telephone' => '-', 'cabang_id' => $ctx['cabang']->id]));

    expect(fn () => $service->create(cstData(['code' => 'C-2', 'name' => 'Daya Teknik, PT', 'nik_npwp' => '4444444444', 'phone' => '+6281234567890', 'telephone' => '-', 'cabang_id' => $ctx['cabang']->id])))
        ->toThrow(ValidationException::class, 'nama dan telepon sama');

    $onlyName = $service->create(cstData(['code' => 'C-3', 'name' => 'PT Daya Teknik', 'nik_npwp' => '5555555555', 'phone' => '087700000000', 'telephone' => '-', 'cabang_id' => $ctx['cabang']->id]));
    $onlyPhone = $service->create(cstData(['code' => 'C-4', 'name' => 'CV Berbeda Total', 'nik_npwp' => '6666666666', 'phone' => '081234567890', 'telephone' => '-', 'cabang_id' => $ctx['cabang']->id]));

    expect($onlyName->exists)->toBeTrue()->and($onlyPhone->exists)->toBeTrue();
});

it('[customer_dedup] override duplikat: hanya Owner/Super Admin/Admin dengan alasan ≥ 10 karakter; tercatat', function () {
    config(['sales.controls.customer_dedup' => true]);
    $ctx = stkContext();
    $service = app(CustomerService::class);
    $service->create(cstData(['code' => 'C-1', 'cabang_id' => $ctx['cabang']->id]));
    $dup = cstData(['code' => 'C-2', 'cabang_id' => $ctx['cabang']->id]);
    $reason = 'Cabang berbeda dengan badan hukum sama, disetujui direksi';

    Auth::login(ctlUser($ctx, 'Sales'));
    expect(fn () => $service->create($dup, ['allow_duplicate_reason' => $reason]))->toThrow(ValidationException::class);

    Auth::login(ctlUser($ctx, 'Owner'));
    expect(fn () => $service->create($dup, ['allow_duplicate_reason' => 'singkat']))->toThrow(ValidationException::class);
    expect(ApprovalOverride::count())->toBe(0);

    $created = $service->create($dup, ['allow_duplicate_reason' => $reason]);

    $override = ApprovalOverride::firstOrFail();
    expect($created->exists)->toBeTrue()->and($override->document_type)->toBe('customer')->and($override->document_id)->toBe($created->id)
        ->and($override->context['kind'])->toBe('duplicate_customer')->and($override->reason)->toBe($reason);
});

it('customers:assign-codes: dry-run tidak mengubah; --apply memberi CUST-##### menurut id dan menyimpan kode lama; aman diulang; yang digabung dilewati', function () {
    $ctx = stkContext();
    $a = Customer::factory()->create(['cabang_id' => $ctx['cabang']->id, 'code' => 'IMPOR-77']);
    $b = Customer::factory()->create(['cabang_id' => $ctx['cabang']->id, 'code' => 'LAMA-2']);
    $merged = Customer::factory()->create(['cabang_id' => $ctx['cabang']->id, 'code' => 'GABUNG-9', 'merged_into' => $a->id]);
    $dir = sys_get_temp_dir().'/cust-codes-'.uniqid();

    $this->artisan('customers:assign-codes', ['--out-dir' => $dir])->expectsOutputToContain('DRY-RUN')->assertSuccessful();
    expect($a->fresh()->code)->toBe('IMPOR-77')->and(\Illuminate\Support\Facades\DB::table('document_sequences')->where('type', 'customer')->count())->toBe(0);

    $this->artisan('customers:assign-codes', ['--apply' => true, '--out-dir' => $dir])->assertSuccessful();
    $codes = Customer::query()->whereNull('merged_into')->orderBy('id')->pluck('code', 'id');
    expect($a->fresh()->legacy_code)->toBe('IMPOR-77')->and($b->fresh()->legacy_code)->toBe('LAMA-2')
        ->and($merged->fresh()->code)->toBe('GABUNG-9')->and($merged->fresh()->legacy_code)->toBeNull()
        ->and($codes->every(fn ($code) => preg_match('/^CUST-\d{5}$/', $code)))->toBeTrue()
        ->and($codes->values()->all())->toBe($codes->values()->sort()->values()->all())     // berurutan menurut id
        ->and(glob($dir.'/kode-customer-apply-*.csv'))->not->toBeEmpty();

    $before = $codes->all();
    $this->artisan('customers:assign-codes', ['--apply' => true, '--no-csv' => true])->assertSuccessful();
    expect(Customer::query()->whereNull('merged_into')->orderBy('id')->pluck('code', 'id')->all())->toBe($before);   // tidak berubah
});

it('form Customer membuat customer lewat CustomerService (dedup berlaku) dan menampilkan alasannya', function () {
    config(['sales.controls.customer_dedup' => true]);
    $ctx = stkContext();
    $user = ctlUser($ctx, 'Super Admin', ['create customer', 'view any customer', 'view customer']);
    app(CustomerService::class)->create(cstData(['code' => 'C-1', 'cabang_id' => $ctx['cabang']->id]));

    \Livewire\Livewire::actingAs($user)->test(\App\Filament\Resources\CustomerResource\Pages\CreateCustomer::class)
        ->fillForm(cstData(['code' => 'C-2', 'cabang_id' => $ctx['cabang']->id]))
        ->call('create');

    expect(Customer::where('code', 'C-2')->exists())->toBeFalse();   // ditolak (halt) — tidak tersimpan
});
