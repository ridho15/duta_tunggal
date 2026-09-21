<?php

/**
 * T6.1 — DocumentPrintBuilder + kop per Cabang (D12/D41): urutan Cabang → pengaturan global → data lama; kolom kosong dilewati
 * (tidak pernah alamat/telepon contoh); rekening bank; watermark; terbilang; partial Blade.
 */

use App\Models\AppSetting;
use App\Models\Cabang;
use App\Models\CreditNote;
use App\Services\CreditNoteService;
use App\Services\DocumentPrintBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function dpCabang(array $attributes = []): Cabang
{
    return Cabang::factory()->create(array_merge([
        'kode' => 'DP'.strtoupper(substr(uniqid(), -4)), 'nama' => 'Cabang Padang', 'alamat' => 'Jl. Sudirman No. 10, Padang', 'telepon' => '0751-123456',
        'nama_legal' => null, 'npwp' => null, 'alamat_pajak' => null, 'rekening' => null,
    ], $attributes));
}

it('kop: data Cabang dipakai lebih dulu; alamat pajak untuk dokumen berpajak, alamat cabang untuk dokumen non-pajak', function () {
    $cabang = dpCabang([
        'nama_legal' => 'PT Duta Tunggal Sumatera', 'npwp' => '01.234.567.8-201.000', 'alamat_pajak' => 'Jl. Pajak No. 1, Padang',
        'rekening' => [['bank' => 'BCA', 'number' => '1234567890', 'holder' => 'PT Duta Tunggal', 'branch' => 'KCP Padang']],
    ]);
    $builder = new DocumentPrintBuilder;

    $tax = $builder->company($cabang, true);
    $plain = $builder->company($cabang, false);

    expect($tax['name'])->toBe('PT Duta Tunggal Sumatera')->and($tax['address'])->toBe('Jl. Pajak No. 1, Padang')->and($tax['npwp'])->toBe('01.234.567.8-201.000')
        ->and($tax['branch'])->toBe('Cabang Padang')->and($tax['phone'])->toBe('0751-123456')
        ->and($tax['banks'])->toBe([['bank' => 'BCA', 'number' => '1234567890', 'holder' => 'PT Duta Tunggal', 'branch' => 'KCP Padang']])
        ->and($tax['lines'])->toContain('Jl. Pajak No. 1, Padang')->toContain('Telp: 0751-123456')->toContain('NPWP: 01.234.567.8-201.000')
        ->and($plain['address'])->toBe('Jl. Sudirman No. 10, Padang');
});

it('kop: kolom Cabang kosong → pengaturan global; global kosong → data lama; tidak pernah alamat/telepon contoh', function () {
    $cabang = dpCabang(['alamat' => '', 'telepon' => '']);
    $builder = new DocumentPrintBuilder;

    // tanpa apa pun: hanya nama bawaan + email bawaan; tidak ada alamat/telepon/NPWP
    $empty = $builder->company($cabang, true);
    expect($empty['name'])->toBe(DocumentPrintBuilder::DEFAULT_COMPANY_NAME)->and($empty['address'])->toBeNull()->and($empty['phone'])->toBeNull()->and($empty['npwp'])->toBeNull()
        ->and($empty['banks'])->toBe([])
        ->and(implode(' ', $empty['lines']))->not->toContain('Contoh')->not->toContain('12345678')->not->toContain('xxx')->not->toContain('08xx');

    AppSetting::set('company_legal_name', 'PT Global Legal');
    AppSetting::set('company_npwp', '99.999.999.9-999.000');
    AppSetting::set('company_address', 'Jl. Global No. 5');
    AppSetting::set('company_phone', '021-999');
    AppSetting::set('company_email', 'kontak@global.test');
    AppSetting::set('company_bank_accounts', json_encode([['bank' => 'Mandiri', 'number' => '555', 'holder' => 'PT Global']]));

    $global = (new DocumentPrintBuilder)->company($cabang, true);
    expect($global['name'])->toBe('PT Global Legal')->and($global['npwp'])->toBe('99.999.999.9-999.000')->and($global['address'])->toBe('Jl. Global No. 5')
        ->and($global['phone'])->toBe('021-999')->and($global['email'])->toBe('kontak@global.test')
        ->and($global['banks'])->toBe([['bank' => 'Mandiri', 'number' => '555', 'holder' => 'PT Global', 'branch' => null]]);

    // Cabang menang atas global (nama legal + rekening), kolom lain yang kosong tetap jatuh ke global
    $cabang->update(['nama_legal' => 'PT Cabang Legal', 'rekening' => [['bank' => 'BNI', 'number' => '777']]]);
    $mixed = (new DocumentPrintBuilder)->company($cabang->fresh(), true);
    expect($mixed['name'])->toBe('PT Cabang Legal')->and($mixed['banks'][0]['bank'])->toBe('BNI')->and($mixed['npwp'])->toBe('99.999.999.9-999.000');
});

it('rekening: baris tanpa bank atau nomor dibuang; bukan array → kosong', function () {
    $cabang = dpCabang(['rekening' => [['bank' => 'BCA', 'number' => ''], ['bank' => '', 'number' => '123'], ['bank' => 'BRI', 'number' => '888', 'holder' => '  ']]]);

    expect((new DocumentPrintBuilder)->company($cabang)['banks'])->toBe([['bank' => 'BRI', 'number' => '888', 'holder' => null, 'branch' => null]]);
    expect((new DocumentPrintBuilder)->company(dpCabang())['banks'])->toBe([]);
});

it('watermark: draft → DRAFT; cancelled/canceled → DIBATALKAN; status lain → tanpa watermark', function () {
    $builder = new DocumentPrintBuilder;

    expect($builder->watermark('draft'))->toBe('DRAFT')->and($builder->watermark('Draft'))->toBe('DRAFT')
        ->and($builder->watermark('cancelled'))->toBe('DIBATALKAN')->and($builder->watermark('canceled'))->toBe('DIBATALKAN')
        ->and($builder->watermark('paid'))->toBeNull()->and($builder->watermark('unpaid'))->toBeNull()->and($builder->watermark(null))->toBeNull();
});

it('terbilang rupiah untuk kwitansi', function () {
    expect(DocumentPrintBuilder::terbilang(0))->toBe('Nol rupiah')
        ->and(DocumentPrintBuilder::terbilang(1000))->toBe('Seribu rupiah')
        ->and(DocumentPrintBuilder::terbilang(2000))->toBe('Dua ribu rupiah')
        ->and(DocumentPrintBuilder::terbilang(111000))->toBe('Seratus sebelas ribu rupiah')
        ->and(DocumentPrintBuilder::terbilang(200000))->toBe('Dua ratus ribu rupiah')
        ->and(DocumentPrintBuilder::terbilang(1250000))->toBe('Satu juta dua ratus lima puluh ribu rupiah')
        ->and(DocumentPrintBuilder::terbilang(12345.5))->toBe('Dua belas ribu tiga ratus empat puluh lima rupiah lima puluh sen')
        ->and(DocumentPrintBuilder::terbilang(2000000000))->toBe('Dua miliar rupiah');
});

it('partial: kop menampilkan nama legal, NPWP, alamat; kolom kosong tidak dicetak; rekening & tanda tangan & watermark', function () {
    $cabang = dpCabang(['nama_legal' => 'PT Duta Tunggal Sumatera', 'npwp' => '01.234.567.8-201.000', 'telepon' => '', 'rekening' => [['bank' => 'BCA', 'number' => '1234567890', 'holder' => 'PT DT']]]);
    $builder = new DocumentPrintBuilder;
    $doc = ['company' => $builder->company($cabang), 'title' => 'INVOICE', 'number' => 'INV-001', 'watermark' => 'DRAFT', 'meta' => [['label' => 'Tanggal', 'value' => '1 Januari 2026']],
        'signatures' => [['role' => 'Hormat kami', 'name' => 'PT Duta Tunggal Sumatera', 'caption' => 'Nama jelas'], ['role' => 'Diterima oleh', 'name' => null, 'caption' => 'Cap']]];

    $header = view('pdf.partials.company-header', ['doc' => $doc])->render();
    expect($header)->toContain('PT Duta Tunggal Sumatera')->toContain('NPWP: 01.234.567.8-201.000')->toContain('Jl. Sudirman No. 10, Padang')->toContain('INVOICE')->toContain('INV-001')
        ->not->toContain('Telp:');

    expect(view('pdf.partials.doc-meta', ['doc' => $doc])->render())->toContain('Tanggal')->toContain('1 Januari 2026');
    expect(view('pdf.partials.signature-block', ['doc' => $doc])->render())->toContain('Hormat kami')->toContain('Diterima oleh')->toContain('Cap');
    expect(view('pdf.partials.bank-accounts', ['doc' => $doc])->render())->toContain('1234567890')->toContain('a.n. PT DT');
    expect(view('pdf.partials.watermark', ['doc' => $doc])->render())->toContain('DRAFT');

    $doc['company']['banks'] = [];
    $doc['watermark'] = null;
    expect(trim(view('pdf.partials.bank-accounts', ['doc' => $doc])->render()))->toBe('')->and(trim(view('pdf.partials.watermark', ['doc' => $doc])->render()))->toBe('');
});

it('bingkai invoice: meta, pelanggan dari SO, watermark status, Nota Kredit terbit terdaftar, syarat tanpa "tidak dapat dikembalikan"', function () {
    config(['sales.controls.credit_notes' => true]);
    $ctx = stkContext();
    $user = ctlUser($ctx, 'Finance Manager', ['approve credit note', 'create credit note']);
    Auth::login($user);
    [$invoice, $item] = ctlCreditInvoice($ctx);
    $ctx['customer']->update(['address' => 'Jl. Pelanggan No. 9, Bukittinggi', 'nik_npwp' => '12.345.678.9-012.000']);
    $builder = new DocumentPrintBuilder;

    $doc = $builder->invoice($invoice->fresh());
    $labels = collect($doc['meta'])->pluck('label')->all();
    expect($doc['title'])->toBe('INVOICE')->and($doc['number'])->toBe($invoice->invoice_number)->and($doc['watermark'])->toBeNull()
        ->and($labels)->toContain('No. Invoice')->toContain('Tanggal')->toContain('Jatuh Tempo')->toContain('Status')->not->toContain('No. Faktur Pajak')
        ->and($doc['party']['name'])->toBe($ctx['customer']->name)->and($doc['party']['address'])->toBe('Jl. Pelanggan No. 9, Bukittinggi')->and($doc['party']['npwp'])->toBe('12.345.678.9-012.000')
        ->and($doc['credit_notes'])->toBe([])->and($doc['credit_total'])->toBe(0.0)
        ->and(implode(' ', $doc['terms']))->not->toContain('tidak dapat dikembalikan')->and($doc['signatures'])->toHaveCount(2);

    $invoice->forceFill(['status' => 'draft', 'tax_invoice_number' => '010.000-26.00000001'])->saveQuietly();
    $draft = $builder->invoice($invoice->fresh());
    expect($draft['watermark'])->toBe('DRAFT')->and(collect($draft['meta'])->pluck('label')->all())->toContain('No. Faktur Pajak');

    $invoice->forceFill(['status' => 'unpaid'])->saveQuietly();
    $service = app(CreditNoteService::class);
    $service->draft($invoice->fresh(), CreditNote::TYPE_RETURN, [$item->id => 1], 'Draf yang belum diterbitkan tidak tampil', actor: $user);   // draf tidak dihitung
    $service->issue($service->draft($invoice->fresh(), CreditNote::TYPE_RETURN, [$item->id => 3], 'Retur tiga unit dari invoice', actor: $user), $user);

    $withCredit = $builder->invoice($invoice->fresh());
    expect($withCredit['credit_notes'])->toHaveCount(1)->and($withCredit['credit_total'])->toBe(333000.0)->and($withCredit['credit_notes'][0]['total'])->toBe(333000.0);

    $invoice->forceFill(['status' => 'cancelled'])->saveQuietly();
    expect($builder->invoice($invoice->fresh())['watermark'])->toBe('DIBATALKAN');
});

it('Pengaturan Aplikasi: kop global tersimpan (kolom kosong dibersihkan, rekening tidak lengkap dibuang)', function () {
    $ctx = stkContext();
    $admin = ctlUser($ctx, 'Super Admin', []);
    Auth::login($admin);

    Livewire::actingAs($admin)->test(\App\Filament\Pages\AppSettingsPage::class)
        ->set('company_legal_name', 'PT Global Legal')
        ->set('company_npwp', '99.999.999.9-999.000')
        ->set('company_address', 'Jl. Global No. 5')
        ->set('company_bank_accounts', [['bank' => 'BCA', 'number' => '111', 'holder' => 'PT G', 'branch' => null], ['bank' => 'BRI', 'number' => '', 'holder' => null, 'branch' => null]])
        ->call('save')
        ->assertHasNoErrors();

    expect(AppSetting::get('company_legal_name'))->toBe('PT Global Legal')->and(AppSetting::get('company_npwp'))->toBe('99.999.999.9-999.000')
        ->and(json_decode((string) AppSetting::get('company_bank_accounts'), true))->toBe([['bank' => 'BCA', 'number' => '111', 'holder' => 'PT G', 'branch' => null]]);

    $company = (new DocumentPrintBuilder)->company(dpCabang(['alamat' => '', 'telepon' => '']));
    expect($company['name'])->toBe('PT Global Legal')->and($company['address'])->toBe('Jl. Global No. 5');
});
