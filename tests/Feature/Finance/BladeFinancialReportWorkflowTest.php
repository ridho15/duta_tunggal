<?php

namespace Tests\Feature\Finance;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BladeFinancialReportWorkflowTest extends TestCase
{
    protected User $user;
    protected Cabang $cabang;
    protected ChartOfAccount $cashCoa;
    protected ChartOfAccount $salesCoa;
    protected ChartOfAccount $cogsCoa;
    protected ChartOfAccount $expenseCoa;
    protected ChartOfAccount $payableCoa;
    protected ChartOfAccount $equityCoa;

    protected function setUp(): void
    {
        parent::setUp();

        // Bersihkan data uji terdahulu agar tes bersifat idempotent
        JournalEntry::where('reference', 'like', 'JV-REP-%')->delete();

        $this->cabang = Cabang::withoutGlobalScopes()->first() ?? Cabang::create([
            'kode' => 'CBG-TEST-REP',
            'nama' => 'Cabang Test Laporan',
            'alamat' => 'Jl. Test Laporan No. 1',
            'status' => true,
        ]);

        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $perms = [
            'view any financial report',
            'view financial report',
        ];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        $role->givePermissionTo($perms);

        $this->user = User::withoutGlobalScopes()->first() ?? User::create([
            'username' => 'report_tester',
            'email' => 'report-tester@dutatunggal.co.id',
            'name' => 'Report Tester',
            'password' => bcrypt('password'),
            'cabang_id' => $this->cabang->id,
            'manage_type' => ['all'],
        ]);
        if (!$this->user->hasRole('Super Admin')) {
            $this->user->assignRole('Super Admin');
        }

        // Siapkan Akun COA Lengkap untuk Uji Laporan Keuangan
        $this->cashCoa = ChartOfAccount::firstOrCreate(
            ['code' => '1111.88'],
            [
                'name' => 'Kas Operasional Laporan',
                'type' => 'Asset',
                'is_active' => true,
                'is_cash_bank' => true,
                'opening_balance' => 10000000,
            ]
        );

        $this->payableCoa = ChartOfAccount::firstOrCreate(
            ['code' => '2110.88'],
            [
                'name' => 'Utang Dagang Laporan',
                'type' => 'Liability',
                'is_active' => true,
                'opening_balance' => 2000000,
            ]
        );

        $this->equityCoa = ChartOfAccount::firstOrCreate(
            ['code' => '3110.88'],
            [
                'name' => 'Modal Pemilik Laporan',
                'type' => 'Equity',
                'is_active' => true,
                'opening_balance' => 8000000,
            ]
        );

        $this->salesCoa = ChartOfAccount::firstOrCreate(
            ['code' => '4110.88'],
            [
                'name' => 'Penjualan Barang Jadi Laporan',
                'type' => 'Revenue',
                'is_active' => true,
                'opening_balance' => 0,
            ]
        );

        $this->cogsCoa = ChartOfAccount::firstOrCreate(
            ['code' => '5110.88'],
            [
                'name' => 'Beban Pokok Penjualan Laporan',
                'type' => 'Expense',
                'is_active' => true,
                'opening_balance' => 0,
            ]
        );

        $this->expenseCoa = ChartOfAccount::firstOrCreate(
            ['code' => '6110.88'],
            [
                'name' => 'Beban Operasional Kantor Laporan',
                'type' => 'Expense',
                'is_active' => true,
                'opening_balance' => 0,
            ]
        );
    }

    /**
     * 1. Test akses hub Laporan Keuangan (/laporan/keuangan) default ke Neraca Saldo.
     */
    public function test_user_can_access_financial_report_hub_defaulting_to_trial_balance(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('laporan.financial.index'));

        $response->assertStatus(200);
        $response->assertSee('Neraca Saldo (Trial Balance)');
        $response->assertSee('Mutasi Debit Periode');
        $response->assertSee('Mutasi Kredit Periode');
    }

    /**
     * 2. Test akses langsung halaman Neraca Saldo (/laporan/keuangan/neraca-saldo).
     */
    public function test_user_can_access_trial_balance_page(): void
    {
        $ref = 'JV-REP-TB-' . uniqid();
        JournalEntry::create([
            'coa_id' => $this->cashCoa->id,
            'date' => now()->toDateString(),
            'reference' => $ref,
            'description' => 'Penerimaan Penjualan Tunai',
            'debit' => 2500000,
            'credit' => 0,
            'journal_type' => 'manual',
            'cabang_id' => $this->cabang->id,
        ]);
        JournalEntry::create([
            'coa_id' => $this->salesCoa->id,
            'date' => now()->toDateString(),
            'reference' => $ref,
            'description' => 'Penerimaan Penjualan Tunai',
            'debit' => 0,
            'credit' => 2500000,
            'journal_type' => 'manual',
            'cabang_id' => $this->cabang->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('laporan.financial.trial-balance'));

        $response->assertStatus(200);
        $response->assertSee('Lembar Neraca Saldo');
        $response->assertSee('GRAND TOTAL MUTASI');
        $response->assertSee($this->cashCoa->code);
        $response->assertSee($this->salesCoa->code);
    }

    /**
     * 3. Test filter tanggal dan cabang pada Neraca Saldo.
     */
    public function test_trial_balance_filters_by_date_and_cabang(): void
    {
        $response = $this->actingAs($this->user)->get(route('laporan.financial.trial-balance', [
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'cabang_id' => $this->cabang->id,
            'show_zero_balance' => 1,
        ]));

        $response->assertStatus(200);
        $response->assertSee($this->cabang->nama);
        $response->assertSee('GRAND TOTAL MUTASI');
    }

    /**
     * 4. Test streaming PDF Neraca Saldo (A4 Landscape).
     */
    public function test_user_can_stream_trial_balance_pdf(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('laporan.financial.trial-balance.pdf', [
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => now()->endOfMonth()->toDateString(),
            ]));

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }

    /**
     * 5. Test akses Laporan Laba Rugi (/laporan/keuangan/laba-rugi).
     */
    public function test_user_can_access_income_statement_page(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('laporan.financial.income-statement'));

        $response->assertStatus(200);
        $response->assertSee('Laporan Laba Rugi (Income Statement)');
        $response->assertSee('Pendapatan Usaha');
        $response->assertSee('Laba Kotor (Gross Profit)');
        $response->assertSee('Beban Operasional');
        $response->assertSee('Laba Bersih (Net Profit)');
    }

    /**
     * 6. Test perhitungan Laba Rugi (Pendapatan - HPP - Beban = Laba Bersih).
     */
    public function test_income_statement_calculates_figures_correctly(): void
    {
        $ref1 = 'JV-REP-IS1-' . uniqid();
        // Pendapatan 5.000.000
        JournalEntry::create([
            'coa_id' => $this->cashCoa->id,
            'date' => now()->toDateString(),
            'reference' => $ref1,
            'description' => 'Pendapatan Jasa',
            'debit' => 5000000,
            'credit' => 0,
            'journal_type' => 'manual',
            'cabang_id' => $this->cabang->id,
        ]);
        JournalEntry::create([
            'coa_id' => $this->salesCoa->id,
            'date' => now()->toDateString(),
            'reference' => $ref1,
            'description' => 'Pendapatan Jasa',
            'debit' => 0,
            'credit' => 5000000,
            'journal_type' => 'manual',
            'cabang_id' => $this->cabang->id,
        ]);

        $ref2 = 'JV-REP-IS2-' . uniqid();
        // Beban Operasional 1.200.000
        JournalEntry::create([
            'coa_id' => $this->expenseCoa->id,
            'date' => now()->toDateString(),
            'reference' => $ref2,
            'description' => 'Beban Operasional',
            'debit' => 1200000,
            'credit' => 0,
            'journal_type' => 'manual',
            'cabang_id' => $this->cabang->id,
        ]);
        JournalEntry::create([
            'coa_id' => $this->cashCoa->id,
            'date' => now()->toDateString(),
            'reference' => $ref2,
            'description' => 'Beban Operasional',
            'debit' => 0,
            'credit' => 1200000,
            'journal_type' => 'manual',
            'cabang_id' => $this->cabang->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('laporan.financial.income-statement', [
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => now()->endOfMonth()->toDateString(),
            ]));

        $response->assertStatus(200);
        $response->assertSee($this->salesCoa->name);
        $response->assertSee($this->expenseCoa->name);
        $response->assertSee('1.200.000');
    }

    /**
     * 7. Test streaming PDF Laporan Laba Rugi (A4 Portrait).
     */
    public function test_user_can_stream_income_statement_pdf(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('laporan.financial.income-statement.pdf'));

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }

    /**
     * 8. Test akses Laporan Neraca (/laporan/keuangan/neraca).
     */
    public function test_user_can_access_balance_sheet_page(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('laporan.financial.balance-sheet'));

        $response->assertStatus(200);
        $response->assertSee('Laporan Neraca (Balance Sheet)');
        $response->assertSee('Total Aset (Aktiva)');
        $response->assertSee('Total Liabilitas (Utang)');
        $response->assertSee('Total Ekuitas (Modal)');
        $response->assertSee('ASET (AKTIVA)');
        $response->assertSee('LIABILITAS');
        $response->assertSee('EKUITAS');
    }

    /**
     * 9. Test filter tanggal posisi (as_of_date) dan cabang pada Neraca.
     */
    public function test_balance_sheet_filters_by_as_of_date_and_cabang(): void
    {
        $response = $this->actingAs($this->user)->get(route('laporan.financial.balance-sheet', [
            'as_of_date' => now()->toDateString(),
            'cabang_id' => $this->cabang->id,
            'show_zero_balance' => 1,
        ]));

        $response->assertStatus(200);
        $response->assertSee($this->cabang->nama);
        $response->assertSee('TOTAL ASET (AKTIVA)');
    }

    /**
     * 10. Test streaming PDF Laporan Neraca (A4 Portrait).
     */
    public function test_user_can_stream_balance_sheet_pdf(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('laporan.financial.balance-sheet.pdf', [
                'as_of_date' => now()->toDateString(),
            ]));

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }
}
