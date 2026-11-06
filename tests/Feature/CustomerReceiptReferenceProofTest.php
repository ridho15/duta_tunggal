<?php

/**
 * T1.3 — referensi & bukti pada Penerimaan Customer non-tunai:
 * referensi wajib untuk Transfer/Giro/Cheque, bukti opsional di disk PRIVAT dengan rute berotorisasi,
 * peringatan (bukan blokir) untuk kombinasi referensi+akun+nominal ganda, kolom & filter "tanpa bukti".
 */

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\Invoice;
use App\Models\SaleOrder;
use App\Models\User;
use App\Services\CustomerReceiptReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function rpContext(): array
{
    $cabang = Cabang::factory()->create(['kode' => 'RP-'.strtoupper(substr(uniqid(), -5)), 'nama' => 'Cabang RP', 'status' => 1]);

    $names = ['view any customer receipt', 'view customer receipt', 'create customer receipt', 'update customer receipt', 'view any customer', 'view any invoice'];
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach ($names as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    $user = User::factory()->create(['cabang_id' => $cabang->id, 'manage_type' => 'all']);
    $user->givePermissionTo($names);
    Auth::login($user);

    Currency::firstOrCreate(['code' => 'IDR'], ['name' => 'Rupiah', 'symbol' => 'Rp', 'to_rupiah' => 1]);

    $make = fn (string $code, string $name, string $type = 'Asset') => ChartOfAccount::firstOrCreate(['code' => $code], ['name' => $name, 'type' => $type, 'is_active' => true, 'opening_balance' => 0]);
    $make('1110', 'KAS DAN SETARA KAS');
    $make('1111', 'Kas Operasional');
    $kas = $make('1111.01', 'Kas Besar Kantor');
    $make('1112', 'Rekening Bank');
    $make('1112.01', 'Bank BCA - Operasional');
    $bank = $make('1112.01.01', 'BANK BCA - OPERASIONAL');
    $make('1120', 'Piutang Dagang');
    $make((string) config('coa.customer_deposit'), 'Hutang Titipan Konsumen', 'Liability');

    $customer = Customer::factory()->create(['cabang_id' => $cabang->id, 'name' => 'PT Bukti Transfer']);

    return compact('cabang', 'user', 'kas', 'bank', 'customer');
}

function rpInvoice(array $ctx, float $total = 100000): Invoice
{
    $order = SaleOrder::create([
        'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id, 'so_number' => 'SO-RP-'.strtoupper(substr(uniqid(), -6)), 'order_date' => now(),
        'status' => 'completed', 'tipe_pengiriman' => 'Kirim Langsung', 'exchange_rate' => 1.0, 'tempo_pembayaran' => 30, 'shipped_to' => 'Jl. Uji No. 1',
    ]);

    return Invoice::create([
        'invoice_number' => 'INV-RP-'.strtoupper(substr(uniqid(), -6)), 'from_model_type' => SaleOrder::class, 'from_model_id' => $order->id,
        'invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(), 'subtotal' => $total, 'total' => $total,
        'status' => 'sent', 'cabang_id' => $ctx['cabang']->id,
    ]);
}

/** Isian form Buat Penerimaan untuk satu invoice, dapat ditimpa. */
function rpForm(array $ctx, Invoice $invoice, array $overrides = []): array
{
    $amount = (float) $invoice->total;

    return array_merge([
        'customer_id' => $ctx['customer']->id, 'payment_date' => now()->toDateString(), 'payment_method' => 'Transfer', 'coa_id' => $ctx['bank']->id, 'status' => 'Draft',
        'selected_invoices' => json_encode([$invoice->id]), 'invoice_receipts' => json_encode([$invoice->id => $amount]), 'total_payment' => (string) $amount,
    ], $overrides);
}

function rpReceipt(array $ctx, array $attributes = []): CustomerReceipt
{
    return CustomerReceipt::factory()->create(array_merge([
        'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id, 'payment_method' => 'Transfer', 'coa_id' => $ctx['bank']->id,
        'total_payment' => 100000, 'status' => 'Draft',
    ], $attributes));
}

// ───────────────────────────── Aturan referensi ─────────────────────────────

it('metode menentukan wajib/tidaknya referensi dan label isiannya', function () {
    expect(CustomerReceiptReference::requiresReference('Transfer'))->toBeTrue()
        ->and(CustomerReceiptReference::requiresReference('Giro'))->toBeTrue()
        ->and(CustomerReceiptReference::requiresReference('Cheque'))->toBeTrue()
        ->and(CustomerReceiptReference::requiresReference('Cash'))->toBeFalse()
        ->and(CustomerReceiptReference::requiresReference('Deposit'))->toBeFalse()
        ->and(CustomerReceiptReference::requiresReference(null))->toBeFalse()
        ->and(CustomerReceiptReference::label('Transfer'))->toBe('No. Referensi Transfer')
        ->and(CustomerReceiptReference::label('Giro'))->toBe('No. Giro')
        ->and(CustomerReceiptReference::label('Cheque'))->toBe('No. Cek');
});

// ───────────────────────────── Rute bukti berotorisasi ─────────────────────────────

it('rute bukti: pengguna berizin 200, tanpa izin 403, tamu diarahkan ke login, tanpa berkas 404', function () {
    Storage::fake('local');
    $ctx = rpContext();
    Storage::disk('local')->put('customer-receipts/proofs/bukti.pdf', '%PDF-1.4 uji');
    $receipt = rpReceipt($ctx, ['payment_reference' => 'TRF-1', 'proof_path' => 'customer-receipts/proofs/bukti.pdf']);
    $noProof = rpReceipt($ctx, ['payment_reference' => 'TRF-2']);

    $this->actingAs($ctx['user'])->get(route('customer-receipts.proof', $receipt))->assertOk();
    $this->actingAs($ctx['user'])->get(route('customer-receipts.proof', $noProof))->assertNotFound();

    $outsider = User::factory()->create(['cabang_id' => $ctx['cabang']->id, 'manage_type' => 'all']);
    $this->actingAs($outsider)->get(route('customer-receipts.proof', $receipt))->assertForbidden();

    Auth::logout();
    $this->get(route('customer-receipts.proof', $receipt))->assertRedirect();
});

// ───────────────────────────── Peringatan duplikat ─────────────────────────────

it('kombinasi referensi + akun + nominal yang sama diperingatkan lewat flash, penerimaan tetap tersimpan', function () {
    $ctx = rpContext();
    $request = Illuminate\Http\Request::create('/');
    $request->setLaravelSession(app('session.store'));
    app()->instance('request', $request);

    $first = rpReceipt($ctx, ['payment_reference' => 'TRF-DUP-1']);
    $service = app(CustomerReceiptReference::class);

    $service->warnIfDuplicate($first);
    expect(session('warning'))->toBeNull();

    $second = rpReceipt($ctx, ['payment_reference' => ' trf-dup-1 ']);
    $service->warnIfDuplicate($second);

    expect(session('warning'))->toContain('Kemungkinan penerimaan ganda')
        ->and(session('warning'))->toContain('#'.$first->id)
        ->and(CustomerReceipt::count())->toBe(2);
});

it('duplicatesOf: nominal atau akun berbeda bukan duplikat', function () {
    $ctx = rpContext();
    $base = rpReceipt($ctx, ['payment_reference' => 'TRF-9']);
    rpReceipt($ctx, ['payment_reference' => 'TRF-9', 'total_payment' => 250000]);
    rpReceipt($ctx, ['payment_reference' => 'TRF-9', 'coa_id' => $ctx['kas']->id]);
    $same = rpReceipt($ctx, ['payment_reference' => 'trf-9']);

    $duplicates = app(CustomerReceiptReference::class)->duplicatesOf($base);

    expect($duplicates->pluck('id')->all())->toBe([$same->id]);
});

// ───────────────────────────── Tampilan: View, daftar, filter, Edit ─────────────────────────────

