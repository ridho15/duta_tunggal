<?php

/**
 * Permintaan Bayar non-barang (Ko Handy 28 Nov 2026): buat → ajukan → setujui (aturan nominal, bukan diri sendiri)
 * → dibayar kasir/finance (Kas/Bank keluar + jurnal) → voucher bertanda tangan digital.
 */

use App\Models\ChartOfAccount;
use App\Models\ExpenseRequest;
use App\Models\JournalEntry;
use App\Models\User;
use App\Support\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function prUser(array $ctx, string $role): User
{
    $u = User::factory()->create(['cabang_id' => $ctx['cabang']->id, 'manage_type' => ['all']]);
    $u->assignRole(Role::findByName($role));
    Access::flush();

    return $u;
}

beforeEach(function () {
    Storage::fake('public');
    $this->ctx = stkContext();
    $this->staff = prUser($this->ctx, 'Sales');
    $this->manager = prUser($this->ctx, 'Sales Manager');
    $this->finance = prUser($this->ctx, 'Finance Manager');
    $this->kasir = prUser($this->ctx, 'Kasir');
    $this->kas = ChartOfAccount::firstOrCreate(['code' => '1111.01'], ['name' => 'Kas Kecil', 'type' => 'Asset', 'is_active' => true]);
    $this->atk = ChartOfAccount::firstOrCreate(['code' => '6200.07'], ['name' => 'Biaya ATK', 'type' => 'Expense', 'is_active' => true]);
    $this->servis = ChartOfAccount::firstOrCreate(['code' => '6200.09'], ['name' => 'Biaya Perbaikan Kendaraan', 'type' => 'Expense', 'is_active' => true]);
});

function prPayload(array $extra = []): array
{
    return array_merge([
        'request_date' => now()->toDateString(), 'payee' => 'Toko ATK Sinar', 'description' => 'Beli kertas & tinta',
        'lines' => [
            ['coa_id' => test()->atk->id, 'description' => 'Kertas A4', 'amount' => 150000],
            ['coa_id' => test()->servis->id, 'description' => 'Ganti oli', 'amount' => 350000],
        ],
    ], $extra);
}

it('alur lengkap: staf ajukan, manager setujui, kasir bayar, jurnal & voucher bertanda tangan', function () {
    // tanda tangan staf (gambar kanvas)
    $img = UploadedFile::fake()->image('ttd.png', 300, 100);
    $png = 'data:image/png;base64,' . base64_encode(file_get_contents($img->getPathname()));
    $this->actingAs($this->staff)->post(route('signature.update'), ['signature_data' => $png])->assertRedirect(route('signature.edit'));
    expect($this->staff->fresh()->signature)->not->toBeNull();

    $this->post(route('keuangan.payment-requests.store'), prPayload(['submit_now' => 1]))->assertRedirect();
    $req = ExpenseRequest::sole();
    expect((float) $req->amount)->toBe(500000.0)->and($req->status)->toBe('submitted')
        ->and($req->signatureFor('requested')?->signature_path)->not->toBeNull();

    // pembuat tidak boleh menyetujui sendiri; kasir bukan penyetuju
    $this->post(route('keuangan.payment-requests.approve', $req))->assertSessionHas('error');
    $this->actingAs($this->kasir)->post(route('keuangan.payment-requests.approve', $req))->assertSessionHas('error');
    expect($req->fresh()->status)->toBe('submitted');

    // <= 5 jt: Sales Manager boleh
    $this->actingAs($this->manager)->post(route('keuangan.payment-requests.approve', $req), ['approval_notes' => 'OK'])->assertRedirect();
    expect($req->fresh()->status)->toBe('approved')->and($req->fresh()->signatureFor('approved')->signer_name)->toBe($this->manager->name);

    // manager bukan pembayar; kasir membayar
    $this->post(route('keuangan.payment-requests.pay', $req), ['payment_coa_id' => $this->kas->id, 'paid_date' => now()->toDateString()])->assertSessionHas('error');
    $this->actingAs($this->kasir)->post(route('keuangan.payment-requests.pay', $req), ['payment_coa_id' => $this->kas->id, 'paid_date' => now()->toDateString()])
        ->assertRedirect();
    $req->refresh();
    expect($req->status)->toBe('paid')->and($req->cash_bank_transaction_id)->not->toBeNull();

    $lines = JournalEntry::where('source_type', \App\Models\CashBankTransaction::class)->where('source_id', $req->cash_bank_transaction_id)->get();
    expect((float) $lines->where('coa_id', $this->atk->id)->sum('debit'))->toBe(150000.0)
        ->and((float) $lines->where('coa_id', $this->servis->id)->sum('debit'))->toBe(350000.0)
        ->and((float) $lines->where('coa_id', $this->kas->id)->sum('credit'))->toBe(500000.0);

    $this->get(route('keuangan.payment-requests.pdf', $req))->assertOk()->assertHeader('content-type', 'application/pdf');
    $html = view('keuangan.payment-requests.pdf', ['req' => $req->load(['lines.coa', 'requester', 'approver', 'payer', 'cabang', 'paymentCoa', 'cashBankTransaction', 'signatures']),
        'company' => app(\App\Services\DocumentPrintBuilder::class)->company(null, false)])->render();
    expect($html)->toContain('BUKTI PENGELUARAN KAS / BANK')->toContain($this->manager->name)->toContain($this->kasir->name)->toContain('Lima ratus ribu');
});

it('nominal di atas 5 juta: Sales Manager tidak boleh, Finance Manager boleh; ditolak bisa diperbaiki & diajukan ulang', function () {
    $this->actingAs($this->staff)->post(route('keuangan.payment-requests.store'), prPayload([
        'submit_now' => 1, 'lines' => [['coa_id' => $this->servis->id, 'amount' => 7500000]],
    ]));
    $req = ExpenseRequest::sole();

    $this->actingAs($this->manager)->post(route('keuangan.payment-requests.approve', $req))->assertSessionHas('error');
    $this->actingAs($this->finance)->post(route('keuangan.payment-requests.reject', $req), ['approval_notes' => 'Lampirkan nota bengkel'])->assertRedirect();
    expect($req->fresh()->status)->toBe('rejected');

    $this->actingAs($this->staff)->put(route('keuangan.payment-requests.update', $req), prPayload([
        'submit_now' => 1, 'lines' => [['coa_id' => $this->servis->id, 'amount' => 7500000]],
    ]))->assertRedirect();
    expect($req->fresh()->status)->toBe('submitted');
    $this->actingAs($this->finance)->post(route('keuangan.payment-requests.approve', $req))->assertRedirect();
    expect($req->fresh()->status)->toBe('approved');
});

it('validasi & privasi: akun kas tidak boleh jadi biaya, staf lain tidak bisa melihat', function () {
    $this->actingAs($this->staff)->post(route('keuangan.payment-requests.store'), prPayload([
        'lines' => [['coa_id' => $this->kas->id, 'amount' => 1000]],
    ]))->assertSessionHasErrors('lines');

    $this->post(route('keuangan.payment-requests.store'), prPayload());
    $req = ExpenseRequest::sole();
    $other = prUser($this->ctx, 'Sales');
    $this->actingAs($other)->get(route('keuangan.payment-requests.show', $req))->assertForbidden();
    $this->actingAs($other)->get(route('keuangan.payment-requests.index', ['tab' => 'semua']))->assertOk()->assertDontSee($req->number);
    $this->actingAs($this->finance)->get(route('keuangan.payment-requests.index', ['tab' => 'semua']))->assertOk()->assertSee($req->number);
    $this->get(route('keuangan.payment-requests.pdf', $req))->assertRedirect();   // belum dibayar
});

it('akun piutang / persediaan tidak boleh dipakai sebagai biaya', function () {
    $ar = ChartOfAccount::firstOrCreate(['code' => '1120.01'], ['name' => 'Piutang Usaha', 'type' => 'Asset', 'is_active' => true]);
    $this->actingAs($this->staff)->post(route('keuangan.payment-requests.store'), prPayload([
        'lines' => [['coa_id' => $ar->id, 'amount' => 1000]],
    ]))->assertSessionHasErrors('lines');
    expect(\App\Services\ExpenseRequestService::expenseAccountQuery()->pluck('id')->all())->toContain($this->atk->id)->not->toContain($ar->id);
});
