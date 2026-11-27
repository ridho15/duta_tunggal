<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\Deposit;
use App\Models\JournalEntry;
use App\Models\User;
use App\Support\Access;
use App\Support\AccessDefaults;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QaEvidenceUpdate77fee5fcTest extends TestCase
{
    use DatabaseTransactions;

    protected Cabang $cabang;
    protected User $owner;
    protected User $sales;
    protected User $kasir;
    protected User $kasirLain;
    protected User $finance;

    protected function setUp(): void
    {
        parent::setUp();

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
        $this->sales = $make('Sales');
        $this->kasir = $make('Kasir');
        $this->kasirLain = $make('Kasir');
        $this->finance = $make('Finance Manager');
    }

    public function test_titip_dana_customer_dan_voucher_penerimaan_sesuai_hak_akses(): void
    {
        $customer = Customer::first() ?? Customer::factory()->create(['cabang_id' => $this->cabang->id]);
        $kas = ChartOfAccount::firstOrCreate(
            ['code' => '1110.01'],
            ['name' => 'Kas Operasional', 'type' => 'Asset', 'is_active' => true, 'is_cash_bank' => true]
        );
        $depAcc = ChartOfAccount::firstOrCreate(
            ['code' => config('coa.customer_deposit', '2150.01')],
            ['name' => 'Deposit Pelanggan', 'type' => 'Liability', 'is_active' => true]
        );

        // 1. Kasir mencatat Titip Dana Customer
        $this->actingAs($this->kasir)
            ->post(route('penjualan.receipts.store'), [
                'customer_id' => $customer->id,
                'payment_date' => '2026-11-27',
                'payment_method' => 'Cash',
                'payment_reference' => 'QA-TRF-77FEE5',
                'coa_id' => $kas->id,
                'deposit_amount' => 250000,
                'notes' => 'QA Titip Dana 77fee5fc',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('penjualan.receipts.index', ['tab' => 'titip']));

        $deposit = Deposit::where('payment_reference', 'QA-TRF-77FEE5')->firstOrFail();
        $this->assertEquals(250000.0, (float) $deposit->amount);
        $this->assertEquals($this->kasir->id, (int) $deposit->created_by);

        // 2. Jurnal Dr Kas / Cr Deposit Pelanggan terbentuk
        $lines = JournalEntry::where('source_type', Deposit::class)->where('source_id', $deposit->id)->get();
        $this->assertEquals(250000.0, (float) $lines->firstWhere('coa_id', $kas->id)?->debit);
        $this->assertEquals(250000.0, (float) $lines->firstWhere('coa_id', $depAcc->id)?->credit);

        // 3. Tab Titip Dana bisa dibuka & Kasir pencatat bisa cetak voucher PDF
        $this->get(route('penjualan.receipts.index', ['tab' => 'titip']))->assertOk()->assertSee($deposit->deposit_number);
        $this->get(route('penjualan.receipts.deposit-voucher', $deposit))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        // 4. Kasir lain DITOLAK cetak voucher (403), Finance Manager & Owner DIPERBOLEHKAN (200)
        $this->actingAs($this->kasirLain)->get(route('penjualan.receipts.deposit-voucher', $deposit))->assertForbidden();
        $this->actingAs($this->finance)->get(route('penjualan.receipts.deposit-voucher', $deposit))->assertOk();
        $this->actingAs($this->owner)->get(route('penjualan.receipts.deposit-voucher', $deposit))->assertOk();
    }

    public function test_export_import_khusus_owner_dan_super_admin(): void
    {
        $this->assertTrue(Access::canExportImport($this->owner));
        $this->assertFalse(Access::canExportImport($this->finance));
        $this->assertFalse(Access::canExportImport($this->sales));
        $this->assertFalse(Access::canExportImport($this->kasir));
    }
}
