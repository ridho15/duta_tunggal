<?php

namespace Tests\Feature\Finance;

use App\Models\Cabang;
use App\Models\CashBankAccount;
use App\Models\CashBankTransfer;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\CashBankService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CashBankTransferValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Cabang $cabang;
    protected ChartOfAccount $sourceBankCoa;
    protected ChartOfAccount $destCashCoa;
    protected ChartOfAccount $adminFeeCoa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::withoutGlobalScopes()->first() ?? Cabang::create([
            'kode' => 'CBG-TRF-TEST',
            'nama' => 'Cabang Test Transfer',
            'alamat' => 'Jl. Test No. 99',
            'status' => true,
        ]);

        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $perms = [
            'view any cash bank transaction',
            'view cash bank transaction',
            'create cash bank transfer',
            'view any cash bank transfer',
            'view cash bank transfer',
            'delete cash bank transfer',
        ];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        $role->givePermissionTo($perms);

        $this->user = User::withoutGlobalScopes()->first() ?? User::factory()->create([
            'username' => 'tester_cashbank',
            'email' => 'tester-cashbank@dutatunggal.co.id',
            'name' => 'Transfer Tester',
            'password' => bcrypt('password'),
            'cabang_id' => $this->cabang->id,
            'manage_type' => ['all'],
        ]);
        if (! $this->user->hasRole('Super Admin')) {
            $this->user->assignRole($role);
        }

        $this->sourceBankCoa = ChartOfAccount::create([
            'code' => '1102.TRF.SRC',
            'name' => 'Bank BCA Operasional',
            'type' => 'Asset',
            'is_cash_bank' => true,
            'is_active' => true,
        ]);

        $this->destCashCoa = ChartOfAccount::create([
            'code' => '1101.TRF.DST',
            'name' => 'Kas Kecil Kasir',
            'type' => 'Asset',
            'is_cash_bank' => true,
            'is_active' => true,
        ]);

        $this->adminFeeCoa = ChartOfAccount::create([
            'code' => '6999.TRF.FEE',
            'name' => 'Beban Administrasi Bank Transfer',
            'type' => 'Expense',
            'is_active' => true,
        ]);

        CashBankAccount::create([
            'code' => 'ACC-SRC-01',
            'name' => 'Bank BCA',
            'type' => 'bank',
            'account_number' => '1234567890',
            'coa_id' => $this->sourceBankCoa->id,
            'cabang_id' => $this->cabang->id,
            'is_active' => true,
        ]);

        CashBankAccount::create([
            'code' => 'ACC-DST-01',
            'name' => 'Kas Kecil',
            'type' => 'cash',
            'account_number' => 'KAS-01',
            'coa_id' => $this->destCashCoa->id,
            'cabang_id' => $this->cabang->id,
            'is_active' => true,
        ]);
    }

    /**
     * Skenario 1: Input biaya admin > 0 tanpa memilih COA biaya admin harus gagal validasi.
     */
    public function test_validation_fails_when_other_costs_is_positive_but_coa_is_missing(): void
    {
        $payload = [
            'number' => 'TRF-FAIL-01',
            'date' => now()->toDateString(),
            'from_coa_id' => $this->sourceBankCoa->id,
            'to_coa_id' => $this->destCashCoa->id,
            'amount' => 1500000,
            'other_costs' => 6500,
            'other_costs_coa_id' => '', // kosong
        ];

        $response = $this->actingAs($this->user)
            ->from(route('keuangan.cashbank.transfer.create'))
            ->post(route('keuangan.cashbank.transfer.store'), $payload);

        $response->assertStatus(302);
        $response->assertRedirect(route('keuangan.cashbank.transfer.create'));
        $response->assertSessionHasErrors(['other_costs_coa_id']);

        $errorMsg = session('errors')->first('other_costs_coa_id');
        $this->assertEquals('Akun COA Biaya Admin wajib dipilih jika ada biaya admin transfer.', $errorMsg);

        $this->assertDatabaseMissing('cash_bank_transfers', [
            'number' => 'TRF-FAIL-01',
        ]);
    }

    /**
     * Skenario 2: Input biaya admin > 0 dengan ID COA fiktif (tidak ada di chart_of_accounts) harus gagal validasi.
     */
    public function test_validation_fails_when_other_costs_coa_id_does_not_exist(): void
    {
        $payload = [
            'number' => 'TRF-FAIL-02',
            'date' => now()->toDateString(),
            'from_coa_id' => $this->sourceBankCoa->id,
            'to_coa_id' => $this->destCashCoa->id,
            'amount' => 1000000,
            'other_costs' => 2500,
            'other_costs_coa_id' => 999999, // ID tidak ada
        ];

        $response = $this->actingAs($this->user)
            ->from(route('keuangan.cashbank.transfer.create'))
            ->post(route('keuangan.cashbank.transfer.store'), $payload);

        $response->assertStatus(302);
        $response->assertRedirect(route('keuangan.cashbank.transfer.create'));
        $response->assertSessionHasErrors(['other_costs_coa_id']);
    }

    /**
     * Skenario 3: Transfer tanpa biaya admin (0 atau null) tidak mewajibkan COA biaya admin dan sukses membukukan 2 jurnal seimbang.
     */
    public function test_transfer_without_admin_fee_succeeds_with_null_coa(): void
    {
        $payload = [
            'number' => 'TRF-PASS-NOFEE',
            'date' => now()->toDateString(),
            'from_coa_id' => $this->sourceBankCoa->id,
            'to_coa_id' => $this->destCashCoa->id,
            'amount' => 500000,
            'other_costs' => 0,
            'other_costs_coa_id' => '',
            'description' => 'Transfer tanpa biaya admin',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('keuangan.cashbank.transfer.store'), $payload);

        $transfer = CashBankTransfer::where('number', 'TRF-PASS-NOFEE')->first();
        $this->assertNotNull($transfer);
        $this->assertEquals(0, (float) $transfer->other_costs);
        $this->assertNull($transfer->other_costs_coa_id);

        $response->assertRedirect(route('keuangan.cashbank.transfer.show', $transfer));

        // Jurnal harus tepat 2 baris (Debit to, Credit from)
        $entries = JournalEntry::where('source_type', CashBankTransfer::class)
            ->where('source_id', $transfer->id)
            ->get();

        $this->assertCount(2, $entries);
        $this->assertEquals(500000, (float) $entries->sum('debit'));
        $this->assertEquals(500000, (float) $entries->sum('credit'));
    }

    /**
     * Skenario 4: Transfer dengan biaya admin dan COA valid sukses membukukan 3 jurnal seimbang.
     */
    public function test_transfer_with_admin_fee_and_valid_coa_succeeds_with_balanced_journal(): void
    {
        $payload = [
            'number' => 'TRF-PASS-FEE',
            'date' => now()->toDateString(),
            'from_coa_id' => $this->sourceBankCoa->id,
            'to_coa_id' => $this->destCashCoa->id,
            'amount' => 1000000,
            'other_costs' => 5000,
            'other_costs_coa_id' => $this->adminFeeCoa->id,
            'description' => 'Transfer dengan biaya admin',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('keuangan.cashbank.transfer.store'), $payload);

        $transfer = CashBankTransfer::where('number', 'TRF-PASS-FEE')->first();
        $this->assertNotNull($transfer);
        $this->assertEquals(5000, (float) $transfer->other_costs);
        $this->assertEquals($this->adminFeeCoa->id, $transfer->other_costs_coa_id);

        $response->assertRedirect(route('keuangan.cashbank.transfer.show', $transfer));

        // Jurnal harus tepat 3 baris
        $entries = JournalEntry::where('source_type', CashBankTransfer::class)
            ->where('source_id', $transfer->id)
            ->get();

        $this->assertCount(3, $entries);

        $creditSource = $entries->firstWhere('coa_id', $this->sourceBankCoa->id);
        $debitDest = $entries->firstWhere('coa_id', $this->destCashCoa->id);
        $debitFee = $entries->firstWhere('coa_id', $this->adminFeeCoa->id);

        $this->assertEquals(1005000, (float) $creditSource->credit);
        $this->assertEquals(1000000, (float) $debitDest->debit);
        $this->assertEquals(5000, (float) $debitFee->debit);

        // Keseimbangan total debit = credit
        $this->assertEquals(1005000, (float) $entries->sum('debit'));
        $this->assertEquals(1005000, (float) $entries->sum('credit'));
    }

    /**
     * Skenario 5: CashBankService melemparkan InvalidArgumentException jika biaya admin > 0 tapi tidak ada COA maupun fallback.
     */
    public function test_service_throws_exception_when_posting_transfer_with_fee_but_no_available_coa(): void
    {
        // Pastikan akun fallback 8000.01 tidak ada
        ChartOfAccount::where('code', '8000.01')->delete();

        $transfer = CashBankTransfer::create([
            'number' => 'TRF-NO-FALLBACK',
            'date' => now()->toDateString(),
            'from_coa_id' => $this->sourceBankCoa->id,
            'to_coa_id' => $this->destCashCoa->id,
            'amount' => 200000,
            'other_costs' => 2500,
            'other_costs_coa_id' => null,
            'status' => 'draft',
            'cabang_id' => $this->cabang->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Akun biaya admin transfer wajib ditentukan');

        app(CashBankService::class)->postTransfer($transfer);
    }
}
