<?php

/**
 * Fase 5A audit 10 bug sedang penjualan (docs/AUDIT-10-BUG-SEDANG-PENJUALAN.md), Isu 7:
 *  - cabang penerimaan = cabang invoice (satu cabang per penerimaan), bukan cabang customer
 *  - akun kas/bank penerima uang (is_cash_bank): akun induk, DEPOSITO, INVESTASI tidak muncul; default hanya bila satu
 *  - kelebihan bayar tidak dipotong senyap: ditolak, atau dicatat sebagai Deposit Customer
 *  - "Penyesuaian Sisa" tidak lagi ditampilkan; daftar invoice disaring dari AR dan cakupan cabang
 */

use App\Filament\Resources\CustomerReceiptResource;
use App\Filament\Resources\CustomerReceiptResource\Pages\CreateCustomerReceipt;
use App\Filament\Resources\CustomerReceiptResource\Pages\EditCustomerReceipt;
use App\Models\AccountReceivable;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\Deposit;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\SaleOrder;
use App\Models\User;
use App\Services\CustomerReceiptAllocator;
use App\Services\MasterDataReadiness;
use App\Support\CustomerReceiptAccounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function phase5Permissions(User $user): void
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $names = ['view any customer receipt', 'view customer receipt', 'create customer receipt', 'update customer receipt', 'view any customer', 'view any invoice'];
    foreach ($names as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    $user->givePermissionTo($names);
}

/** Struktur COA seperti data nyata: induk, akun detail, DEPOSITO, INVESTASI, dan akun sistem. */
function phase5Coa(): array
{
    $make = fn (string $code, string $name, string $type = 'Asset') => ChartOfAccount::firstOrCreate(
        ['code' => $code],
        ['name' => $name, 'type' => $type, 'is_active' => true, 'opening_balance' => 0]
    );

    return [
        'parent_kas_bank' => $make('1110', 'KAS DAN SETARA KAS'),
        'parent_kas' => $make('1111', 'Kas Operasional'),
        'kas' => $make('1111.01', 'Kas Besar Kantor'),
        'parent_bank' => $make('1112', 'Rekening Bank'),
        'parent_bca' => $make('1112.01', 'Bank BCA - Operasional'),
        'bca' => $make('1112.01.01', 'BANK BCA - OPERASIONAL'),
        'bca_deposito' => $make('1112.01.02', 'BANK BCA - DEPOSITO'),
        'bca_investasi' => $make('1112.01.03', 'BANK BCA - INVESTASI'),
        'mandiri' => $make('1112.02.01', 'BANK MANDIRI - OPERASIONAL'),
        'piutang' => $make('1120', 'Piutang Dagang'),
        'deposit_pelanggan' => $make((string) config('coa.customer_deposit'), 'Hutang Titipan Konsumen', 'Liability'),
    ];
}

function phase5Context(bool $allBranches = true): array
{
    $cabangA = Cabang::factory()->create(['kode' => 'P5A-' . strtoupper(substr(uniqid(), -5)), 'nama' => 'Cabang A', 'status' => 1]);
    $cabangB = Cabang::factory()->create(['kode' => 'P5B-' . strtoupper(substr(uniqid(), -5)), 'nama' => 'Cabang B', 'status' => 1]);

    $user = User::factory()->create(['cabang_id' => $cabangA->id, 'manage_type' => $allBranches ? 'all' : 'cabang']);
    phase5Permissions($user);
    Auth::login($user);

    Currency::firstOrCreate(['code' => 'IDR'], ['name' => 'Rupiah', 'symbol' => 'Rp', 'to_rupiah' => 1]);

    // Customer terdaftar di cabang B, tetapi invoice-nya diterbitkan cabang A
    $customer = Customer::factory()->create(['cabang_id' => $cabangB->id]);

    return ['a' => $cabangA, 'b' => $cabangB, 'user' => $user, 'customer' => $customer, 'coa' => phase5Coa()];
}

/** Invoice penjualan (AR otomatis dibuat oleh observer) untuk customer pada cabang tertentu. */
function phase5Invoice(array $ctx, Cabang $cabang, float $total = 100000, ?Customer $customer = null, string $number = null): Invoice
{
    $customer ??= $ctx['customer'];
    $order = SaleOrder::create([
        'customer_id' => $customer->id, 'cabang_id' => $cabang->id, 'so_number' => 'SO-P5-' . strtoupper(substr(uniqid(), -6)),
        'order_date' => now(), 'status' => 'completed', 'tipe_pengiriman' => 'Kirim Langsung', 'exchange_rate' => 1.0,
        'tempo_pembayaran' => 30, 'shipped_to' => 'Jl. Uji No. 1',
    ]);

    return Invoice::create([
        'invoice_number' => $number ?? ('INV-P5-' . strtoupper(substr(uniqid(), -6))),
        'from_model_type' => SaleOrder::class, 'from_model_id' => $order->id,
        'invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => $total, 'total' => $total, 'status' => 'sent', 'cabang_id' => $cabang->id,
    ]);
}

function phase5Form(array $ctx, array $overrides = []): array
{
    return array_merge([
        'customer_id' => $ctx['customer']->id,
        'payment_date' => now()->toDateString(),
        'payment_method' => 'Transfer',
        'payment_reference' => 'TRF-' . strtoupper(uniqid()),   // T1.3: referensi wajib untuk non-tunai
        'coa_id' => $ctx['coa']['bca']->id,
        'status' => 'Draft',
    ], $overrides);
}

function phase5Fill(array $invoiceAmounts, array $extra = []): array
{
    return array_merge([
        'selected_invoices' => json_encode(array_map('intval', array_keys($invoiceAmounts))),
        'invoice_receipts' => json_encode($invoiceAmounts),
        'total_payment' => (string) array_sum($invoiceAmounts),
    ], $extra);
}

// ───────────────────────────── 7.2 Akun kas/bank ─────────────────────────────

it('Isu 7: daftar akun tidak memuat akun induk, DEPOSITO, maupun INVESTASI (jembatan sebelum ada penanda)', function () {
    $coa = phase5Coa();

    $cash = CustomerReceiptAccounts::options('Cash');
    $bank = CustomerReceiptAccounts::options('Transfer');

    expect(array_keys($cash))->toBe([$coa['kas']->id])
        ->and(array_keys($bank))->toContain($coa['bca']->id, $coa['mandiri']->id)
        ->and(array_keys($bank))->not->toContain(
            $coa['parent_kas_bank']->id, $coa['parent_kas']->id, $coa['parent_bank']->id, $coa['parent_bca']->id,
            $coa['bca_deposito']->id, $coa['bca_investasi']->id
        );
});

it('Isu 7: default akun hanya bila kandidatnya tepat satu; bila lebih, dikosongkan (tidak jatuh ke akun induk)', function () {
    $coa = phase5Coa();

    expect(CustomerReceiptAccounts::defaultId('Cash'))->toBe($coa['kas']->id)          // satu kandidat
        ->and(CustomerReceiptAccounts::defaultId('Transfer'))->toBeNull()               // BCA + Mandiri
        ->and(CustomerReceiptResource::getDefaultCoaIdByPaymentMethod('Transfer'))->toBeNull();
});

it('Isu 7: setelah ada akun bertanda is_cash_bank, HANYA akun bertanda yang dapat dipilih', function () {
    $coa = phase5Coa();
    $coa['bca']->update(['is_cash_bank' => true]);

    $bank = CustomerReceiptAccounts::options('Transfer');
    expect(array_keys($bank))->toBe([$coa['bca']->id])
        ->and(CustomerReceiptAccounts::isAllowed($coa['mandiri']->id, 'Transfer'))->toBeFalse()
        ->and(CustomerReceiptAccounts::isAllowed($coa['bca']->id, 'Transfer'))->toBeTrue()
        ->and(CustomerReceiptAccounts::defaultId('Transfer'))->toBe($coa['bca']->id)
        ->and(CustomerReceiptAccounts::options('Cash'))->toBe([]);   // kas belum ditandai
});

it('Isu 7: coa:flag-cash-bank — dry-run tidak mengubah; --apply menandai kandidat + CSV; --codes membatasi; kode asing ditolak', function () {
    $coa = phase5Coa();

    Artisan::call('coa:flag-cash-bank');
    expect(Artisan::output())->toContain('DRY-RUN')->toContain('1112.01.01')->toContain('1111.01')
        ->and(ChartOfAccount::where('is_cash_bank', true)->count())->toBe(0);

    // kode induk / deposito bukan kandidat
    expect(Artisan::call('coa:flag-cash-bank', ['--apply' => true, '--codes' => '1112.01.02']))->toBe(1)
        ->and(ChartOfAccount::where('is_cash_bank', true)->count())->toBe(0);

    Artisan::call('coa:flag-cash-bank', ['--apply' => true, '--codes' => '1112.01.01']);
    expect(ChartOfAccount::where('is_cash_bank', true)->pluck('code')->all())->toBe(['1112.01.01']);

    // --apply penuh menandai seluruh kandidat (kas + kedua bank), tanpa induk/deposito/investasi
    Artisan::call('coa:flag-cash-bank', ['--apply' => true, '--reset' => true]);
    $flagged = ChartOfAccount::where('is_cash_bank', true)->pluck('code')->sort()->values()->all();
    expect($flagged)->toBe(['1111.01', '1112.01.01', '1112.02.01']);

    $csv = glob(storage_path('app/backfill/coa-cash-bank-*.csv'));
    expect($csv)->not->toBeEmpty();
    array_map('unlink', $csv);
});

// ───────────────────────────── 7.5 & 7.1 Daftar invoice ─────────────────────────────

it('Isu 7: daftar invoice disaring dari AR (sisa > 0) — invoice lunas hilang, invoice SO parsial tetap muncul', function () {
    $ctx = phase5Context();
    $open = phase5Invoice($ctx, $ctx['a'], 100000, number: 'INV-P5-TERBUKA');
    $paid = phase5Invoice($ctx, $ctx['a'], 50000, number: 'INV-P5-LUNAS');
    AccountReceivable::where('invoice_id', $paid->id)->update(['paid' => 50000, 'remaining' => 0]);
    $other = phase5Invoice($ctx, $ctx['a'], 70000, Customer::factory()->create(), 'INV-P5-LAIN');

    $numbers = CustomerReceiptResource::invoiceableInvoicesQuery($ctx['customer']->id)->pluck('invoices.invoice_number')->all();
    expect($numbers)->toContain('INV-P5-TERBUKA')->not->toContain('INV-P5-LUNAS')->not->toContain('INV-P5-LAIN');

    // mode ubah: invoice yang sudah dipilih tetap tampil walau kini lunas
    $withIncluded = CustomerReceiptResource::invoiceableInvoicesQuery($ctx['customer']->id, [$paid->id])->pluck('invoices.invoice_number')->all();
    expect($withIncluded)->toContain('INV-P5-LUNAS');
});

it('Isu 7: pengguna non-"all" tidak melihat invoice cabang lain milik customer yang sama', function () {
    $ctx = phase5Context(allBranches: false);
    Auth::logout();   // data dibuat tanpa cakupan cabang; baru kemudian masuk sebagai pengguna cabang A
    phase5Invoice($ctx, $ctx['a'], 100000, number: 'INV-P5-CAB-A');
    phase5Invoice($ctx, $ctx['b'], 100000, number: 'INV-P5-CAB-B');
    Auth::login($ctx['user']);

    $numbers = CustomerReceiptResource::invoiceableInvoicesQuery($ctx['customer']->id)->pluck('invoices.invoice_number')->all();

    expect($numbers)->toContain('INV-P5-CAB-A')->not->toContain('INV-P5-CAB-B');
});

// ───────────────────────────── Allocator (aturan tunggal) ─────────────────────────────

it('Isu 7: allocator — kelebihan bayar ditolak dengan pesan jelas (nominal, sisa, kelebihan) dan TIDAK dipotong senyap', function () {
    $ctx = phase5Context();
    $invoice = phase5Invoice($ctx, $ctx['a'], 100000, number: 'INV-P5-KLB');

    $message = null;
    try {
        app(CustomerReceiptAllocator::class)->plan($ctx['customer']->id, [$invoice->id => 130000], 'Transfer');
    } catch (ValidationException $e) {
        $message = $e->errors()['total_payment'][0] ?? null;
    }

    expect($message)->toContain('Nominal Rp 130.000,00 melebihi sisa tagihan Rp 100.000,00')
        ->toContain('INV-P5-KLB')->toContain('kelebihan Rp 30.000,00');
});

it('Isu 7: allocator — dengan opsi deposit, nominal diterapkan sebesar sisa dan kelebihan dilaporkan', function () {
    $ctx = phase5Context();
    $invoice = phase5Invoice($ctx, $ctx['a'], 100000);

    $plan = app(CustomerReceiptAllocator::class)->plan($ctx['customer']->id, [$invoice->id => 130000], 'Transfer', true);

    expect($plan['applied'])->toBe([$invoice->id => 100000.0])
        ->and($plan['overpayment'])->toBe(30000.0)
        ->and($plan['cabang_id'])->toBe($ctx['a']->id);
});

it('Isu 7: allocator — invoice bukan milik customer, sudah lunas, tanpa pilihan, dan metode Deposit yang melebihi ditolak', function () {
    $ctx = phase5Context();
    $foreign = phase5Invoice($ctx, $ctx['a'], 100000, Customer::factory()->create());
    $paid = phase5Invoice($ctx, $ctx['a'], 100000);
    AccountReceivable::where('invoice_id', $paid->id)->update(['paid' => 100000, 'remaining' => 0]);
    $ok = phase5Invoice($ctx, $ctx['a'], 100000);
    $allocator = app(CustomerReceiptAllocator::class);

    $errors = function (array $receipts, string $method = 'Transfer', bool $deposit = false) use ($allocator, $ctx) {
        try {
            $allocator->plan($ctx['customer']->id, $receipts, $method, $deposit);
        } catch (ValidationException $e) {
            return array_keys($e->errors());
        }

        return [];
    };

    expect($errors([$foreign->id => 1000]))->toBe(['customer_id'])
        ->and($errors([$paid->id => 1000]))->toBe(['total_payment'])
        ->and($errors([]))->toBe(['selected_invoices'])
        ->and($errors([$ok->id => 0]))->toBe(['selected_invoices'])
        ->and($errors([$ok->id => 150000], 'Deposit', true))->toBe(['total_payment']);
});

it('Isu 7: allocator — semua invoice satu cabang (D9-A); pengguna non-"all" hanya cabangnya', function () {
    $ctx = phase5Context();
    $a = phase5Invoice($ctx, $ctx['a'], 100000, number: 'INV-P5-A');
    $b = phase5Invoice($ctx, $ctx['b'], 100000, number: 'INV-P5-B');
    $allocator = app(CustomerReceiptAllocator::class);

    try {
        $allocator->plan($ctx['customer']->id, [$a->id => 1000, $b->id => 1000], 'Transfer');
        $message = null;
    } catch (ValidationException $e) {
        $message = $e->errors()['cabang_id'][0] ?? null;
    }
    expect($message)->toContain('lebih dari satu cabang')->toContain('INV-P5-A')->toContain('INV-P5-B');

    // pengguna cabang A mencoba menerima invoice cabang B
    $userA = User::factory()->create(['cabang_id' => $ctx['a']->id, 'manage_type' => 'cabang']);
    try {
        $allocator->plan($ctx['customer']->id, [$b->id => 1000], 'Transfer', false, null, $userA);
        $blocked = false;
    } catch (ValidationException $e) {
        $blocked = isset($e->errors()['cabang_id']);
    }
    expect($blocked)->toBeTrue();

    // cabangnya sendiri lolos
    expect($allocator->plan($ctx['customer']->id, [$a->id => 1000], 'Transfer', false, null, $userA)['cabang_id'])->toBe($ctx['a']->id);
});

it('Isu 7: allocator — pada mode ubah, nominal penerimaan sendiri dikembalikan ke sisa tagihan', function () {
    $ctx = phase5Context();
    $invoice = phase5Invoice($ctx, $ctx['a'], 100000);
    $receipt = CustomerReceipt::factory()->create(['customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['a']->id]);
    \App\Models\CustomerReceiptItem::factory()->create(['customer_receipt_id' => $receipt->id, 'invoice_id' => $invoice->id, 'amount' => 60000]);
    AccountReceivable::where('invoice_id', $invoice->id)->update(['paid' => 60000, 'remaining' => 40000]);

    $allocator = app(CustomerReceiptAllocator::class);

    // Tanpa mode ubah, sisa hanya 40.000 sehingga 100.000 melebihi; pada mode ubah 60.000 miliknya dikembalikan
    expect(fn () => $allocator->plan($ctx['customer']->id, [$invoice->id => 100000], 'Transfer'))->toThrow(ValidationException::class);
    expect($allocator->plan($ctx['customer']->id, [$invoice->id => 100000], 'Transfer', false, $receipt)['applied'])->toBe([$invoice->id => 100000.0]);
});

it('Isu 7: akun penerima harus akun yang sah untuk metode pembayaran', function () {
    $ctx = phase5Context();
    $allocator = app(CustomerReceiptAllocator::class);
    $check = function (?int $coaId, string $method) use ($allocator) {
        try {
            $allocator->assertAccountAllowed($coaId, $method);

            return true;
        } catch (ValidationException) {
            return false;
        }
    };

    expect($check($ctx['coa']['bca']->id, 'Transfer'))->toBeTrue()
        ->and($check($ctx['coa']['kas']->id, 'Cash'))->toBeTrue()
        ->and($check($ctx['coa']['parent_kas_bank']->id, 'Cash'))->toBeFalse()      // 1110 induk
        ->and($check($ctx['coa']['parent_bank']->id, 'Transfer'))->toBeFalse()      // 1112 induk
        ->and($check($ctx['coa']['bca_deposito']->id, 'Transfer'))->toBeFalse()
        ->and($check($ctx['coa']['bca_investasi']->id, 'Transfer'))->toBeFalse()
        ->and($check($ctx['coa']['kas']->id, 'Transfer'))->toBeFalse()              // kas bukan akun bank
        ->and($check(null, 'Cash'))->toBeFalse();
});

// ───────────────────────────── Halaman Buat ─────────────────────────────

it('Isu 7: penerimaan pas — cabang = cabang invoice (bukan cabang customer), AR lunas, tanpa deposit', function () {
    $ctx = phase5Context();
    $invoice = phase5Invoice($ctx, $ctx['a'], 100000);

    Livewire::actingAs($ctx['user'])
        ->test(CreateCustomerReceipt::class)
        ->fillForm(phase5Form($ctx, phase5Fill([$invoice->id => 100000])))
        ->call('create')
        ->assertHasNoFormErrors();

    $receipt = CustomerReceipt::withoutGlobalScopes()->latest('id')->firstOrFail();
    expect($receipt->cabang_id)->toBe($ctx['a']->id)                 // cabang invoice, bukan cabang customer (B)
        ->and($ctx['customer']->cabang_id)->toBe($ctx['b']->id)
        ->and((float) $receipt->total_payment)->toBe(100000.0)
        ->and((float) $receipt->overpayment_amount)->toBe(0.0)
        ->and($receipt->deposit_id)->toBeNull()
        ->and((float) AccountReceivable::where('invoice_id', $invoice->id)->value('remaining'))->toBe(0.0)
        ->and(Deposit::count())->toBe(0);
});

it('Isu 7: kelebihan bayar tanpa opsi deposit DITOLAK — tidak ada receipt, AR tidak berubah, pesan pada field total', function () {
    $ctx = phase5Context();
    $invoice = phase5Invoice($ctx, $ctx['a'], 100000, number: 'INV-P5-LEBIH');

    Livewire::actingAs($ctx['user'])
        ->test(CreateCustomerReceipt::class)
        ->fillForm(phase5Form($ctx, phase5Fill([$invoice->id => 130000])))
        ->call('create')
        ->assertHasFormErrors(['total_payment']);

    expect(CustomerReceipt::withoutGlobalScopes()->count())->toBe(0)
        ->and(Deposit::count())->toBe(0)
        ->and((float) AccountReceivable::where('invoice_id', $invoice->id)->value('remaining'))->toBe(100000.0);
});

it('Isu 7: kelebihan bayar dengan opsi deposit → Deposit Customer + jurnal Kas/Bank debit, Deposit Pelanggan kredit (cabang invoice)', function () {
    $ctx = phase5Context();
    $invoice = phase5Invoice($ctx, $ctx['a'], 100000);

    Livewire::actingAs($ctx['user'])
        ->test(CreateCustomerReceipt::class)
        ->fillForm(phase5Form($ctx, phase5Fill([$invoice->id => 130000], ['overpayment_as_deposit' => true])))
        ->call('create')
        ->assertHasNoFormErrors();

    $receipt = CustomerReceipt::withoutGlobalScopes()->latest('id')->firstOrFail();
    $deposit = Deposit::firstOrFail();

    expect((float) $receipt->total_payment)->toBe(100000.0)              // hanya yang teralokasi ke invoice
        ->and((float) $receipt->overpayment_amount)->toBe(30000.0)
        ->and($receipt->deposit_id)->toBe($deposit->id)
        ->and((float) $deposit->amount)->toBe(30000.0)
        ->and((float) $deposit->remaining_amount)->toBe(30000.0)
        ->and($deposit->from_model_type)->toBe(Customer::class)
        ->and($deposit->from_model_id)->toBe($ctx['customer']->id)
        ->and($deposit->status)->toBe('active')
        ->and((float) AccountReceivable::where('invoice_id', $invoice->id)->value('remaining'))->toBe(0.0);

    $entries = JournalEntry::where('source_type', Deposit::class)->where('source_id', $deposit->id)->get();
    expect($entries)->toHaveCount(2)
        ->and((float) $entries->firstWhere('coa_id', $ctx['coa']['bca']->id)->debit)->toBe(30000.0)
        ->and((float) $entries->firstWhere('coa_id', $ctx['coa']['deposit_pelanggan']->id)->credit)->toBe(30000.0)
        ->and($entries->pluck('cabang_id')->unique()->all())->toBe([$ctx['a']->id]);
});

it('Isu 7: kelebihan bayar dengan opsi deposit ditolak bila akun Deposit Pelanggan belum ada (tidak ada uang tanpa jurnal)', function () {
    $ctx = phase5Context();
    $invoice = phase5Invoice($ctx, $ctx['a'], 100000);
    $ctx['coa']['deposit_pelanggan']->forceDelete();

    Livewire::actingAs($ctx['user'])
        ->test(CreateCustomerReceipt::class)
        ->fillForm(phase5Form($ctx, phase5Fill([$invoice->id => 120000], ['overpayment_as_deposit' => true])))
        ->call('create')
        ->assertHasFormErrors(['total_payment']);

    expect(CustomerReceipt::withoutGlobalScopes()->count())->toBe(0)->and(Deposit::count())->toBe(0);
});

it('Isu 7: invoice dari dua cabang dalam satu penerimaan ditolak dengan pesan pada field Cabang', function () {
    $ctx = phase5Context();
    $a = phase5Invoice($ctx, $ctx['a'], 100000);
    $b = phase5Invoice($ctx, $ctx['b'], 100000);

    Livewire::actingAs($ctx['user'])
        ->test(CreateCustomerReceipt::class)
        ->fillForm(phase5Form($ctx, phase5Fill([$a->id => 100000, $b->id => 100000])))
        ->call('create')
        ->assertHasFormErrors(['cabang_id']);

    expect(CustomerReceipt::withoutGlobalScopes()->count())->toBe(0);
});

it('Isu 7: akun induk (1110) atau DEPOSITO tidak dapat dipakai sebagai akun penerima', function (string $key) {
    $ctx = phase5Context();
    $invoice = phase5Invoice($ctx, $ctx['a'], 100000);

    Livewire::actingAs($ctx['user'])
        ->test(CreateCustomerReceipt::class)
        ->fillForm(phase5Form($ctx, phase5Fill([$invoice->id => 100000], ['coa_id' => $ctx['coa'][$key]->id])))
        ->call('create')
        ->assertHasFormErrors(['coa_id']);

    expect(CustomerReceipt::withoutGlobalScopes()->count())->toBe(0);
})->with(['parent_kas_bank', 'parent_bank', 'bca_deposito', 'bca_investasi']);

it('Isu 7: tanpa invoice terpilih ditolak — tidak ada lagi alokasi senyap ke invoice pertama', function () {
    $ctx = phase5Context();
    $invoice = phase5Invoice($ctx, $ctx['a'], 100000);

    Livewire::actingAs($ctx['user'])
        ->test(CreateCustomerReceipt::class)
        ->fillForm(phase5Form($ctx, ['selected_invoices' => '[]', 'invoice_receipts' => '{}', 'total_payment' => '100000']))
        ->call('create');

    expect(CustomerReceipt::withoutGlobalScopes()->count())->toBe(0)
        ->and((float) AccountReceivable::where('invoice_id', $invoice->id)->value('remaining'))->toBe(100000.0);
});

it('Isu 7: form Buat — cabang tidak diisi dari customer, kolom cabang tidak dapat diubah manual, dan opsi deposit tersedia', function () {
    $ctx = phase5Context();

    Livewire::actingAs($ctx['user'])
        ->test(CreateCustomerReceipt::class)
        ->fillForm(['customer_id' => $ctx['customer']->id, 'payment_method' => 'Transfer'])
        ->assertFormFieldIsDisabled('cabang_id')
        ->assertFormFieldExists('overpayment_as_deposit')
        ->assertFormFieldIsVisible('overpayment_as_deposit')
        ->assertSet('data.cabang_id', null);   // customer terdaftar di cabang B, tetapi cabang mengikuti invoice
});

it('Isu 7: metode Deposit menyembunyikan opsi kelebihan-sebagai-deposit', function () {
    $ctx = phase5Context();

    Livewire::actingAs($ctx['user'])
        ->test(CreateCustomerReceipt::class)
        ->fillForm(['payment_method' => 'Deposit'])
        ->assertFormFieldIsHidden('overpayment_as_deposit');
});

it('Isu 7: pilihan COA pada form mengikuti daftar sah (tanpa induk/deposito/investasi) dan default kosong bila ambigu', function () {
    $ctx = phase5Context();

    $component = Livewire::actingAs($ctx['user'])
        ->test(CreateCustomerReceipt::class)
        ->fillForm(['payment_method' => 'Transfer'])
        ->assertSet('data.coa_id', null);   // BCA + Mandiri → user memilih sendiri

    $options = CustomerReceiptResource::getCoaOptionsByPaymentMethod('Transfer');
    expect(array_keys($options))->not->toContain($ctx['coa']['parent_bank']->id, $ctx['coa']['bca_deposito']->id);
});

// ───────────────────────────── Halaman Ubah ─────────────────────────────

it('Isu 7: halaman Ubah menolak nominal melebihi sisa (tanpa pemotongan senyap) dan tetap memakai akun sah', function () {
    $ctx = phase5Context();
    $invoice = phase5Invoice($ctx, $ctx['a'], 100000);

    Livewire::actingAs($ctx['user'])
        ->test(CreateCustomerReceipt::class)
        ->fillForm(phase5Form($ctx, phase5Fill([$invoice->id => 40000])))
        ->call('create')
        ->assertHasNoFormErrors();
    $receipt = CustomerReceipt::withoutGlobalScopes()->latest('id')->firstOrFail();

    Livewire::actingAs($ctx['user'])
        ->test(EditCustomerReceipt::class, ['record' => $receipt->getKey()])
        ->fillForm(phase5Fill([$invoice->id => 120000], ['coa_id' => $ctx['coa']['bca']->id]))
        ->call('save')
        ->assertHasFormErrors(['total_payment']);

    expect((float) $receipt->fresh()->total_payment)->toBe(40000.0)
        ->and((float) AccountReceivable::where('invoice_id', $invoice->id)->value('remaining'))->toBe(60000.0);

    // 100.000 = 40.000 miliknya + sisa 60.000 → sah pada mode ubah
    Livewire::actingAs($ctx['user'])
        ->test(EditCustomerReceipt::class, ['record' => $receipt->getKey()])
        ->fillForm(phase5Fill([$invoice->id => 100000], ['coa_id' => $ctx['coa']['bca']->id]))
        ->call('save')
        ->assertHasNoFormErrors();
});

// ───────────────────────────── 7.4 & 7.6 Tampilan ─────────────────────────────

it('Isu 7: tabel invoice tidak lagi memuat kolom Penyesuaian Sisa maupun Select2 CDN; kelebihan tidak dipotong dengan alert()', function () {
    $html = view('components.customer-receipt-invoice-table', [
        'invoices' => [[
            'id' => 1, 'invoice_number' => 'INV-X', 'customer_name' => 'PT X', 'cabang_id' => 1, 'total' => 100000,
            'remaining' => 100000, 'receipt' => '', 'balance' => '', 'payment_balance' => '',
        ]],
        'selectedInvoices' => [],
        'message' => '',
    ])->render();

    expect($html)->not->toContain('Penyesuaian')
        ->not->toContain('adjustment-select')
        ->not->toContain('select2')
        ->not->toContain('alert(')
        ->toContain('receipt-overpay-note');

    $init = file_get_contents(resource_path('views/components/customer-receipt-javascript-init.blade.php'));
    expect($init)->not->toContain('alert(`Pembayaran tidak boleh melebihi')->toContain('receipt-overpay-note');
});

it('Isu 7: teks bantuan menjelaskan perilaku sebenarnya (kelebihan ditolak / deposit)', function () {
    $source = file_get_contents(app_path('Filament/Resources/CustomerReceiptResource.php'));

    expect($source)->not->toContain('Overpayment akan dicatat sebagai customer deposit.')
        ->toContain('tidak dipotong senyap');
});

it('Isu 7: halaman Lihat menampilkan cabang dan kelebihan yang dicatat sebagai deposit', function () {
    $ctx = phase5Context();
    $invoice = phase5Invoice($ctx, $ctx['a'], 100000);

    Livewire::actingAs($ctx['user'])
        ->test(CreateCustomerReceipt::class)
        ->fillForm(phase5Form($ctx, phase5Fill([$invoice->id => 125000], ['overpayment_as_deposit' => true])))
        ->call('create');

    $receipt = CustomerReceipt::withoutGlobalScopes()->latest('id')->firstOrFail();
    $deposit = Deposit::firstOrFail();

    Livewire::actingAs($ctx['user'])
        ->test(\App\Filament\Resources\CustomerReceiptResource\Pages\ViewCustomerReceipt::class, ['record' => $receipt->getKey()])
        ->assertSee('Kelebihan Bayar')
        ->assertSee($deposit->deposit_number)
        ->assertSee('Cabang A');
});

// ───────────────────────────── Checklist kesiapan ─────────────────────────────

it('Isu 7: master:readiness — akun kas/bank "sebagian" sampai ada yang ditandai, lalu "siap"', function () {
    phase5Coa();

    $check = fn () => collect(app(MasterDataReadiness::class)->check()['checks'])->keyBy('key')['coa_kas_bank'];

    expect($check()['state'])->toBe('sebagian')->and($check()['ok'])->toBeTrue()->and($check()['hint'])->toContain('coa:flag-cash-bank');

    Artisan::call('coa:flag-cash-bank', ['--apply' => true]);
    expect($check()['state'])->toBe('siap');
    array_map('unlink', glob(storage_path('app/backfill/coa-cash-bank-*.csv')) ?: []);
});

it('Isu 7: coa:flag-cash-bank memberi pesan jelas (bukan galat SQL) bila migrasi is_cash_bank belum dijalankan', function () {
    // DDL = implicit commit di MySQL dan bertahan lintas tes → kolom WAJIB dikembalikan di finally,
    // dan tidak ada data yang dibuat sebelum DDL (agar tidak ikut ter-commit).
    \Illuminate\Support\Facades\Schema::table('chart_of_accounts', fn ($table) => $table->dropColumn('is_cash_bank'));

    try {
        $exit = Artisan::call('coa:flag-cash-bank', ['--apply' => true]);

        expect($exit)->toBe(1)->and(Artisan::output())->toContain('php artisan migrate');

        // Pemilihan akun tetap aman tanpa kolom (dianggap belum ada penanda)
        expect(ChartOfAccount::hasCashBankFlags())->toBeFalse();
    } finally {
        \Illuminate\Support\Facades\Schema::table('chart_of_accounts', function ($table) {
            $table->boolean('is_cash_bank')->default(false)->after('is_active')->index();
        });
    }

    expect(\Illuminate\Support\Facades\Schema::hasColumn('chart_of_accounts', 'is_cash_bank'))->toBeTrue();
});

it('Isu 7: penanda "AR sudah diperbarui" milik ID lama tidak menular ke penerimaan baru ber-ID sama', function () {
    $ctx = phase5Context();
    $staleId = 987654;
    \App\Observers\CustomerReceiptObserver::markArUpdatedInCreate($staleId);

    CustomerReceipt::factory()->create([
        'id' => $staleId, 'customer_id' => $ctx['customer']->id,
        'total_payment' => 1000, 'payment_method' => 'cash', 'status' => 'Draft',
    ]);

    $flags = (new \ReflectionClass(\App\Observers\CustomerReceiptObserver::class))->getProperty('arUpdatedInCreate')->getValue();

    expect($flags)->not->toHaveKey($staleId);
});
