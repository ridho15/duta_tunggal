<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\CashBankTransaction;
use App\Models\ChartOfAccount;
use App\Models\ExpenseRequest;
use App\Models\JournalEntry;
use App\Models\User;
use App\Support\Access;
use App\Support\AccessDefaults;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QaEvidenceUpdateBadbad52Test extends TestCase
{
    use DatabaseTransactions;

    protected Cabang $cabang;
    protected User $owner;
    protected User $staff;
    protected User $manager;
    protected User $finance;
    protected User $kasir;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        AccessDefaults::ensurePermissions();
        AccessDefaults::installAll();
        Access::flush();

        $this->cabang = Cabang::firstOrFail();

        $make = function (string $roleName) {
            $u = User::factory()->create([
                'cabang_id' => $this->cabang->id,
                'manage_type' => ['all'],
            ]);
            $u->assignRole(Role::findByName($roleName));

            return $u;
        };

        $this->owner = $make('Owner');
        $this->staff = $make('Sales');
        $this->manager = $make('Sales Manager');
        $this->finance = $make('Finance Manager');
        $this->kasir = $make('Kasir');
    }

    public function test_pusat_laporan_routes_and_role_filtering(): void
    {
        // Owner melihat semua laporan di Pusat Laporan
        $this->actingAs($this->owner)
            ->get(route('laporan.pusat.index'))
            ->assertOk()
            ->assertSee('Detail Penjualan')
            ->assertSee('Stok Barang Terjual')
            ->assertSee('Cetak Banyak Produk');

        // Detail Penjualan, Stok Terjual, Cetak Produk accessible for Owner + PDF
        $this->get(route('laporan.penjualan-detail.index'))->assertOk();
        $this->get(route('laporan.stok-terjual.index'))->assertOk();
        $this->get(route('laporan.cetak-produk.index'))->assertOk();
        $this->get(route('laporan.penjualan-detail.pdf'))->assertOk()->assertHeader('content-type', 'application/pdf');

        // Sales melihat Detail Penjualan tapi tidak Cetak Banyak Produk, dan ditolak Export Excel
        $this->actingAs($this->staff)
            ->get(route('laporan.pusat.index'))
            ->assertOk()
            ->assertSee('Detail Penjualan')
            ->assertDontSee('Cetak Banyak Produk');

        $this->get(route('laporan.penjualan-detail.excel'))->assertForbidden();
    }

    public function test_tanda_tangan_digital_dan_permintaan_bayar_non_barang_end_to_end(): void
    {
        $kas = ChartOfAccount::firstOrCreate(
            ['code' => '1111.01'],
            ['name' => 'Kas Kecil', 'type' => 'Asset', 'is_active' => true]
        );
        $atk = ChartOfAccount::firstOrCreate(
            ['code' => '6200.07'],
            ['name' => 'Biaya ATK', 'type' => 'Expense', 'is_active' => true]
        );

        // 1. Staff simpan tanda tangan digital via kanvas
        $img = UploadedFile::fake()->image('ttd-qa.png', 300, 100);
        $pngDataUrl = 'data:image/png;base64,' . base64_encode(file_get_contents($img->getPathname()));

        $this->actingAs($this->staff)
            ->post(route('signature.update'), ['signature_data' => $pngDataUrl])
            ->assertRedirect(route('signature.edit'));

        $this->assertNotNull($this->staff->fresh()->signature);

        // 2. Staff buat & ajukan Permintaan Bayar Non-Barang (Rp 450.000)
        $this->post(route('keuangan.payment-requests.store'), [
            'request_date' => now()->toDateString(),
            'payee' => 'Toko ATK QA',
            'description' => 'Pembelian ATK QA 28 Nov',
            'submit_now' => 1,
            'lines' => [
                ['coa_id' => $atk->id, 'description' => 'Kertas & Tinta', 'amount' => 450000],
            ],
        ])->assertRedirect();

        $req = ExpenseRequest::where('payee', 'Toko ATK QA')->latest('id')->firstOrFail();
        $this->assertEquals(450000.0, (float) $req->amount);
        $this->assertEquals('submitted', $req->status);
        $this->assertNotNull($req->signatureFor('requested')?->signature_path);

        // 3. Negative control: Pembuat sendiri & Kasir DILARANG menyetujui
        $this->post(route('keuangan.payment-requests.approve', $req))->assertSessionHas('error');
        $this->actingAs($this->kasir)->post(route('keuangan.payment-requests.approve', $req))->assertSessionHas('error');
        $this->assertEquals('submitted', $req->fresh()->status);

        // 4. Sales Manager menyetujui (<= 5 jt)
        $this->actingAs($this->manager)
            ->post(route('keuangan.payment-requests.approve', $req), ['approval_notes' => 'Disetujui QA'])
            ->assertRedirect();
        $this->assertEquals('approved', $req->fresh()->status);

        // 5. Negative control: Manager bukan pembayar; Kasir membayar
        $this->post(route('keuangan.payment-requests.pay', $req), [
            'payment_coa_id' => $kas->id,
            'paid_date' => now()->toDateString(),
        ])->assertSessionHas('error');

        $this->actingAs($this->kasir)
            ->post(route('keuangan.payment-requests.pay', $req), [
                'payment_coa_id' => $kas->id,
                'paid_date' => now()->toDateString(),
            ])->assertRedirect();

        $req->refresh();
        $this->assertEquals('paid', $req->status);
        $this->assertNotNull($req->cash_bank_transaction_id);

        // 6. Verifikasi Jurnal Akuntansi (Dr Biaya ATK 450.000 / Cr Kas Kecil 450.000)
        $lines = JournalEntry::where('source_type', CashBankTransaction::class)
            ->where('source_id', $req->cash_bank_transaction_id)
            ->get();
        $this->assertEquals(450000.0, (float) $lines->where('coa_id', $atk->id)->sum('debit'));
        $this->assertEquals(450000.0, (float) $lines->where('coa_id', $kas->id)->sum('credit'));

        // 7. Cetak Voucher PDF berhasil
        $this->get(route('keuangan.payment-requests.pdf', $req))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
