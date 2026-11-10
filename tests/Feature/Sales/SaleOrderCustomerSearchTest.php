<?php

/**
 * Cek ulang 09 Nov 2026 butir 2: halaman Buat SO tidak lagi menempelkan seluruh master customer (±18 ribu, dulu dikirim 2x);
 * customer dicari lewat AJAX seperti produk.
 */

use App\Models\Customer;
use App\Models\Quotation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('halaman Buat SO tidak memuat master customer di payload', function () {
    $ctx = stkContext();
    foreach (range(1, 40) as $i) {
        Customer::factory()->create(['name' => "Pelanggan Massal Uji {$i}", 'code' => sprintf('CMASS-%04d', $i)]);
    }

    $html = $this->actingAs($ctx['user'])->get(route('penjualan.so.create'))->assertOk()->getContent();

    expect($html)->not->toContain('Pelanggan Massal Uji')
        ->and($html)->not->toContain('CMASS-0001')
        ->and($html)->toContain(str_replace('/', '\/', route('penjualan.so.customers.search')));
});

it('customer dari quotation dan input lama tetap ter-prefill', function () {
    $ctx = stkContext();
    $ctx['customer']->forceFill(['name' => 'Toko Prefill Quotation'])->save();
    Customer::factory()->create(['name' => 'Customer Tak Terkait']);
    $quotation = Quotation::factory()->create(['customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id]);

    $html = $this->actingAs($ctx['user'])->get(route('penjualan.so.create', ['quotation_id' => $quotation->id]))->assertOk()->getContent();
    expect($html)->toContain('Toko Prefill Quotation')->and($html)->not->toContain('Customer Tak Terkait');

    $old = Customer::factory()->create(['name' => 'Customer Input Lama']);
    $html = $this->withSession(['_old_input' => ['customer_id' => $old->id]])
        ->get(route('penjualan.so.create'))->assertOk()->getContent();
    expect($html)->toContain('Customer Input Lama');
});

it('pencarian customer: cocok nama/kode/perusahaan, kode persis didahulukan, membawa data limit & deposit, maksimal 25', function () {
    $ctx = stkContext();
    Customer::factory()->create(['name' => 'Toko Cadangan', 'code' => 'ZZ-CUS-001-X']);
    $exact = Customer::factory()->create([
        'name' => 'Toko Sumber Jaya', 'code' => 'CUS-001', 'kredit_limit' => 5000000,
        'tipe_pembayaran' => 'Kredit', 'tempo_kredit' => 30, 'address' => 'Jl. Uji 1', 'phone' => '', 'telephone' => '0811',
    ]);
    Customer::factory()->create(['name' => 'Bengkel Abadi', 'perusahaan' => 'PT Perusahaan Unik']);
    foreach (range(1, 30) as $i) {
        Customer::factory()->create(['name' => "Toko Seri {$i}"]);
    }

    $json = $this->actingAs($ctx['user'])->getJson(route('penjualan.so.customers.search', ['q' => 'CUS-001']))->assertOk()->json();

    expect($json['success'])->toBeTrue()
        ->and($json['options'][0]['id'])->toBe((string) $exact->id)
        ->and($json['options'][0]['label'])->toBe('Toko Sumber Jaya (CUS-001)')
        ->and($json['options'][0]['meta'])->toMatchArray([
            'tipe' => 'Kredit', 'limit' => 5000000.0, 'tempo' => 30, 'address' => 'Jl. Uji 1', 'phone' => '0811', 'deposit' => 0.0,
        ]);

    expect($this->getJson(route('penjualan.so.customers.search', ['q' => 'toko']))->json('options'))->toHaveCount(25);

    $byCompany = collect($this->getJson(route('penjualan.so.customers.search', ['q' => 'perusahaan unik']))->json('options'))->pluck('label')->all();
    expect($byCompany)->toHaveCount(1)->and($byCompany[0])->toStartWith('Bengkel Abadi');
});

it('pencarian customer: kata kosong ditolak, karakter LIKE tidak jadi wildcard, wajib login', function () {
    $this->getJson(route('penjualan.so.customers.search', ['q' => 'a']))->assertUnauthorized();

    $ctx = stkContext();
    Customer::factory()->create(['name' => 'Toko 50% Diskon']);
    Customer::factory()->create(['name' => 'Toko 5000 Diskon']);

    $this->actingAs($ctx['user'])->getJson(route('penjualan.so.customers.search'))->assertStatus(422);
    $labels = collect($this->getJson(route('penjualan.so.customers.search', ['q' => '50%']))->json('options'))->pluck('label')->all();
    expect($labels)->toHaveCount(1)->and($labels[0])->toStartWith('Toko 50% Diskon');
});
