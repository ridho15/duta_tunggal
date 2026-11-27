<?php

/**
 * Export / Import Excel-CSV semua halaman data, khusus Owner / Super Admin (Ko Handy 27 Nov 2026).
 */

use App\Models\Customer;
use App\Models\User;
use App\Support\Access;
use App\Support\DataTransfer;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->ctx = stkContext();
    $this->owner = $this->ctx['user'];
    $this->owner->syncRoles([Role::findByName('Owner')]);
    Access::flush();
});

it('semua dataset bisa diexport Excel & CSV oleh Owner', function () {
    $this->actingAs($this->owner);
    foreach (array_keys(DataTransfer::datasets()) as $page) {
        $res = $this->get(route('data-transfer.export', ['page' => $page, 'format' => 'xlsx']));
        expect($res->getStatusCode())->toBe(200, "export {$page}");
        expect($res->headers->get('content-disposition'))->toContain('.xlsx');
    }
    $this->get(route('data-transfer.export', ['page' => 'master.customers', 'format' => 'csv']))->assertOk();
});

it('selain Owner / Super Admin ditolak export & import, dan tidak melihat panel export', function () {
    $sales = User::factory()->create(['cabang_id' => $this->owner->cabang_id, 'manage_type' => ['all']]);
    $sales->assignRole('Sales');
    Access::flush();

    $this->actingAs($sales)->get(route('data-transfer.export', ['page' => 'penjualan.so']))->assertForbidden();
    $this->get(route('data-transfer.import', ['page' => 'master.customers']))->assertForbidden();
    $this->get(route('penjualan.so.index'))->assertOk()->assertDontSee('Export / Import data');

    $this->actingAs($this->owner)->get(route('penjualan.so.index'))->assertOk()->assertSee('Export / Import data');
});

it('export customer memuat kolom dan nama relasi', function () {
    $c = Customer::factory()->create(['cabang_id' => $this->owner->cabang_id]);
    expect(DataTransfer::headings('master.customers'))->toContain('code', 'cabang_id', 'cabang_nama');
    $row = DataTransfer::row('master.customers', $c->fresh(), DataTransfer::relations('master.customers'));
    expect($row)->toContain($c->code);
});

it('import customer: periksa dulu tanpa menyimpan, lalu proses membuat & memperbarui', function () {
    $existing = Customer::factory()->create(['cabang_id' => $this->owner->cabang_id, 'name' => 'Lama']);
    $cabang = $this->owner->cabang;
    $csv = "code,name,cabang_nama,address,phone,email,perusahaan,tipe,fax,nik_npwp,telephone\n"
        . "{$existing->code},Nama Baru,,,,,,,,,\n"
        . "IMP-001,Customer Import,{$cabang->nama},Jl. A,0811,a@b.test,PT A,PKP,-,123,0811\n";

    $this->actingAs($this->owner)->get(route('data-transfer.import', ['page' => 'master.customers']))->assertOk()->assertSee('Unduh template');
    $this->get(route('data-transfer.import-template', ['page' => 'master.customers']))->assertOk();

    $res = $this->post(route('data-transfer.import-check', ['page' => 'master.customers']), [
        'file' => UploadedFile::fake()->createWithContent('cust.csv', $csv),
    ])->assertOk()->assertSee('Proses import (1 baru, 1 diperbarui)');
    expect(Customer::where('code', 'IMP-001')->exists())->toBeFalse()
        ->and($existing->fresh()->name)->toBe('Lama');

    preg_match('/name="token" value="([^"]+)"/', $res->getContent(), $m);
    $this->post(route('data-transfer.import-store', ['page' => 'master.customers']), ['token' => $m[1]])
        ->assertRedirect(route('master.customers.index'));

    expect($existing->fresh()->name)->toBe('Nama Baru')
        ->and(Customer::where('code', 'IMP-001')->first()?->cabang_id)->toBe($cabang->id);
});

it('import dengan baris bermasalah tidak menyimpan apa pun dan menjelaskan solusinya', function () {
    $csv = "code,name,cabang_nama\nIMP-002,Tanpa Alamat,Cabang Tidak Ada\n,Tanpa Kode,\n";

    $this->actingAs($this->owner)->post(route('data-transfer.import-check', ['page' => 'master.customers']), [
        'file' => UploadedFile::fake()->createWithContent('bad.csv', $csv),
    ])->assertOk()
        ->assertSee('tidak ditemukan')
        ->assertSee('kolom kunci code kosong')
        ->assertDontSee('Proses import');

    expect(Customer::where('code', 'IMP-002')->exists())->toBeFalse();

    $this->post(route('data-transfer.import-store', ['page' => 'master.customers']), ['token' => '../../etc/passwd'])
        ->assertRedirect(route('data-transfer.import', ['page' => 'master.customers']));
});

it('halaman transaksi tidak bisa diimpor', function () {
    $this->actingAs($this->owner)->get(route('data-transfer.import', ['page' => 'penjualan.so']))->assertNotFound();
});
