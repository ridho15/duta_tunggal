<?php

/**
 * T3.4 — Pengaturan Akuntansi (flag sales.controls.accounting_settings): 11 kunci akun, validasi (detail/aktif/tipe), fallback
 * ke perilaku lama, jurnal berikutnya mengikuti pengaturan, pemindai tanpa kode akun hard-code di alur penjualan.
 */

use App\Models\AccountingSetting;
use App\Models\ChartOfAccount;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\SaleOrder;
use App\Observers\InvoiceObserver;
use App\Services\AccountingSettings;
use App\Services\MasterDataReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function acc(string $code, string $name, string $type, array $extra = []): ChartOfAccount
{
    // Akun 5160 (dibuat factory produk) berparent_id acak yang bisa kebetulan sama dengan id akun uji → tampak "induk". Netralkan.
    ChartOfAccount::query()->where('code', '5160')->whereNotNull('parent_id')->update(['parent_id' => null]);

    return ChartOfAccount::firstOrCreate(['code' => $code], array_merge(['name' => $name, 'type' => $type, 'is_active' => true, 'opening_balance' => 0], $extra));
}

it('ada 11 kunci akun yang dapat diatur', function () {
    expect(AccountingSettings::editableKeys())->toHaveCount(11)
        ->and(array_keys(AccountingSettings::editableKeys()))->toBe([
            'accounts_receivable', 'sales_revenue', 'sales_discount', 'sales_returns', 'sales_output_vat', 'customer_deposit',
            'goods_in_transit', 'cogs', 'inventory', 'cash_bank_default', 'sales_shipping',
        ]);
});

it('[flag mati / tanpa pengaturan] daftar kode identik dengan perilaku lama', function (string $key, array $expected) {
    config(['sales.controls.accounting_settings' => false, 'coa.inventory' => '1140.10', 'coa.accounts_receivable' => '1120', 'coa.sales_revenue' => '4000']);
    $service = app(AccountingSettings::class);
    $accountId = acc('1120', 'Piutang', 'Asset')->id;
    AccountingSetting::create(['key' => 'accounts_receivable', 'coa_id' => $accountId]);   // ada pengaturan tetapi flag mati → diabaikan

    expect($service->codes($key))->toBe($expected);
})->with([
    ['accounts_receivable', ['1120']],
    ['sales_revenue', ['4000', '4111']],
    ['inventory', ['1140.10', '1140.01']],
    ['goods_in_transit', ['1140.20', '1180.10']],
    ['cogs', ['5100.10', '5000']],
    ['return_inventory', ['1101.01', '1140.10', '1100']],
    ['return_wip', ['1101.02', '1-201', '1140.02']],
]);

it('validasi: akun induk, nonaktif, dan tipe salah ditolak; akun sah tersimpan; null menghapus pengaturan', function () {
    $service = app(AccountingSettings::class);
    $parent = acc('1100', 'Aset Lancar', 'Asset');
    $child = acc('1110', 'Kas', 'Asset', ['parent_id' => $parent->id]);
    $inactive = acc('1121', 'Piutang Lama', 'Asset', ['is_active' => false]);
    $revenue = acc('4001', 'Penjualan Lain', 'Revenue');
    $detail = acc('1122', 'Piutang Baru', 'Asset');

    expect(fn () => $service->set('accounts_receivable', $parent->id))->toThrow(ValidationException::class, 'akun induk');
    expect(fn () => $service->set('accounts_receivable', $inactive->id))->toThrow(ValidationException::class, 'tidak aktif');
    expect(fn () => $service->set('accounts_receivable', $revenue->id))->toThrow(ValidationException::class, 'bertipe Revenue');
    expect(fn () => $service->set('kunci_tidak_ada', $detail->id))->toThrow(ValidationException::class, 'tidak dikenal');
    expect(fn () => $service->set('accounts_receivable', 999999))->toThrow(ValidationException::class, 'tidak ditemukan');
    expect(AccountingSetting::count())->toBe(0);

    $service->set('accounts_receivable', $detail->id);
    expect(AccountingSetting::where('key', 'accounts_receivable')->value('coa_id'))->toBe($detail->id);

    $service->set('accounts_receivable', null);
    expect(AccountingSetting::where('key', 'accounts_receivable')->value('coa_id'))->toBeNull();
});

it('[flag hidup] akun pengaturan dipakai lebih dulu; pengaturan yang menjadi tidak valid diabaikan (fallback lama)', function () {
    config(['sales.controls.accounting_settings' => true, 'coa.accounts_receivable' => '1120']);
    $default = acc('1120', 'Piutang Dagang', 'Asset');
    $custom = acc('1122', 'Piutang Khusus', 'Asset');
    $service = app(AccountingSettings::class);
    $service->set('accounts_receivable', $custom->id);

    expect($service->codes('accounts_receivable'))->toBe(['1122', '1120'])
        ->and($service->first('accounts_receivable')->id)->toBe($custom->id)
        ->and($service->idFor('accounts_receivable'))->toBe($custom->id);

    $custom->update(['is_active' => false]);   // akun dinonaktifkan setelah diatur

    expect($service->explicit('accounts_receivable'))->toBeNull()
        ->and($service->first('accounts_receivable')->id)->toBe($default->id);
});

it('perubahan pengaturan tercatat di log aktivitas', function () {
    $before = \Spatie\Activitylog\Models\Activity::count();

    app(AccountingSettings::class)->set('sales_revenue', acc('4002', 'Penjualan Baru', 'Revenue')->id);

    expect(\Spatie\Activitylog\Models\Activity::count())->toBeGreaterThan($before);
});

// ------------------------------------------------------------------ jurnal berikutnya mengikuti pengaturan

function accInvoice(array $ctx): Invoice
{
    $invoice = Invoice::factory()->make([
        'from_model_type' => SaleOrder::class, 'from_model_id' => 1, 'subtotal' => 100000000, 'tax' => 11, 'ppn_rate' => 11,
        'total' => 111000000, 'invoice_date' => now()->toDateString(), 'cabang_id' => $ctx['cabang']->id,
    ]);
    // Akun pada invoice (ar/revenue/PPN) dikosongkan agar resolusi jatuh ke Pengaturan Akuntansi (invoice yang sudah membawa akun tetap memakainya)
    $invoice->forceFill(['ar_coa_id' => null, 'revenue_coa_id' => null, 'ppn_keluaran_coa_id' => null, 'biaya_pengiriman_coa_id' => null]);
    $invoice->saveQuietly();

    // produk tanpa akun penjualan sendiri → pendapatan jatuh ke akun default (Pengaturan Akuntansi)
    $product = stkProduct($ctx, ['cost_price' => 20000, 'sales_coa_id' => null]);
    \App\Models\InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id, 'product_id' => $product->id, 'quantity' => 2, 'price' => 50000000, 'subtotal' => 100000000, 'total' => 100000000, 'tax_rate' => 0, 'tax_amount' => 0,
    ]);

    return $invoice;
}

it('jurnal invoice berikutnya memakai akun pengaturan (Piutang, Pendapatan, PPN Keluaran); tanpa flag memakai akun bawaan', function (bool $flag) {
    config(['sales.controls.accounting_settings' => $flag]);
    $ctx = stkContext();   // menyediakan 1120, 4000, 2120.06, 5100.10, 1140.20, dst.
    $arCustom = acc('1122', 'Piutang Khusus', 'Asset');
    $revCustom = acc('4002', 'Penjualan Khusus', 'Revenue');
    $vatCustom = acc('2121', 'PPN Keluaran Khusus', 'Liability');
    $service = app(AccountingSettings::class);
    $service->set('accounts_receivable', $arCustom->id);
    $service->set('sales_revenue', $revCustom->id);
    $service->set('sales_output_vat', $vatCustom->id);
    $invoice = accInvoice($ctx);

    (new InvoiceObserver)->postSalesInvoice($invoice);

    $accounts = JournalEntry::where('source_type', Invoice::class)->where('source_id', $invoice->id)->pluck('coa_id')->all();
    $expect = fn (ChartOfAccount $custom, string $defaultCode) => $flag ? $custom->id : ChartOfAccount::where('code', $defaultCode)->value('id');

    expect(in_array($expect($arCustom, '1120'), $accounts, true))->toBeTrue('Piutang');
    expect(in_array($expect($revCustom, '4000'), $accounts, true))->toBeTrue('Pendapatan');
    expect(in_array($expect($vatCustom, '2120.06'), $accounts, true))->toBeTrue('PPN Keluaran');
    if ($flag) {
        expect($accounts)->not->toContain(ChartOfAccount::where('code', '1120')->value('id'));
    }
})->with([[true], [false]]);

// ------------------------------------------------------------------ pemindai

it('pemindai: tidak ada kode akun hard-code pada berkas alur penjualan (semua lewat AccountingSettings)', function () {
    $files = [
        'app/Observers/InvoiceObserver.php', 'app/Observers/DeliveryOrderObserver.php', 'app/Services/DeliveryOrderService.php',
        'app/Services/CustomerReturnService.php', 'app/Filament/Resources/SalesInvoiceResource.php',
    ];

    foreach ($files as $file) {
        $source = file_get_contents(base_path($file));
        preg_match_all("/'((?:1|2|4|5|6)\d{2,3}(?:[.-]\d{1,2})?|1-\d{3})'/", $source, $matches);
        expect($matches[0])->toBe([], "{$file} masih memuat kode akun literal: ".implode(', ', $matches[0]));
    }
});

// ------------------------------------------------------------------ readiness & halaman

it('master:readiness memasukkan Pengaturan Akuntansi hanya bila flag hidup; siap hanya bila semua kunci terisi sah', function () {
    stkContext();
    // Akun 5160 (dibuat factory produk) berparent_id acak; bisa kebetulan menunjuk id akun uji dan membuatnya tampak "induk" → netralkan.
    ChartOfAccount::query()->whereNotNull('parent_id')->update(['parent_id' => null]);
    expect(collect(app(MasterDataReadiness::class)->check()['checks'])->pluck('key'))->not->toContain('pengaturan_akuntansi');

    config(['sales.controls.accounting_settings' => true]);
    $check = fn () => collect(app(MasterDataReadiness::class)->check()['checks'])->firstWhere('key', 'pengaturan_akuntansi');
    expect($check()['state'])->toBe('kosong')->and($check()['ok'])->toBeFalse();

    $service = app(AccountingSettings::class);
    $service->set('accounts_receivable', acc('1120', 'Piutang Dagang', 'Asset')->id);
    expect($check()['state'])->toBe('sebagian');

    foreach (AccountingSettings::editableKeys() as $key => $definition) {
        $type = $definition['types'][0];
        $service->set($key, acc('ZZ-'.strtoupper($key), $definition['label'], $type)->id);
    }

    expect($check()['state'])->toBe('siap')->and($check()['ok'])->toBeTrue();
});

it('halaman Pengaturan Akuntansi hanya untuk Super Admin dan Finance Manager; simpan valid dan tolak akun induk', function () {
    $ctx = stkContext();
    $custom = acc('1122', 'Piutang Khusus', 'Asset');
    $parent = acc('1100', 'Aset Lancar', 'Asset');
    acc('1110', 'Kas', 'Asset', ['parent_id' => $parent->id]);

    \Illuminate\Support\Facades\Auth::login(ctlUser($ctx, 'Sales Manager'));
    expect(\App\Filament\Pages\AccountingSettingsPage::canAccess())->toBeFalse();

    $finance = ctlUser($ctx, 'Finance Manager');
    \Illuminate\Support\Facades\Auth::login($finance);
    expect(\App\Filament\Pages\AccountingSettingsPage::canAccess())->toBeTrue();

    Livewire::actingAs($finance)->test(\App\Filament\Pages\AccountingSettingsPage::class)
        ->set('accounts.accounts_receivable', $custom->id)
        ->call('save')
        ->assertHasNoErrors();
    expect(AccountingSetting::where('key', 'accounts_receivable')->value('coa_id'))->toBe($custom->id);

    Livewire::actingAs($finance)->test(\App\Filament\Pages\AccountingSettingsPage::class)
        ->set('accounts.sales_revenue', $parent->id)
        ->call('save')
        ->assertHasErrors();
    expect(AccountingSetting::where('key', 'sales_revenue')->value('coa_id'))->toBeNull();   // akun induk tidak tersimpan
});
