<?php

namespace Tests\Feature\Finance;

use App\Models\Cabang;
use App\Models\CashBankAccount;
use App\Models\CashBankTransaction;
use App\Models\CashBankTransfer;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BladeCashBankWorkflowTest extends TestCase
{
    protected User $user;
    protected Cabang $cabang;
    protected ChartOfAccount $cashCoa;
    protected ChartOfAccount $bankCoa;
    protected ChartOfAccount $expenseCoa;
    protected ChartOfAccount $incomeCoa;
    protected ChartOfAccount $adminFeeCoa;
    protected CashBankAccount $cashAccount;
    protected CashBankAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::withoutGlobalScopes()->first() ?? Cabang::create([
            'kode' => 'CBG-TEST-CB',
            'nama' => 'Cabang Test KasBank',
            'alamat' => 'Jl. Test KasBank No. 1',
            'status' => true,
        ]);

        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $perms = [
            'view any cash bank transaction',
            'view cash bank transaction',
            'create cash bank transaction',
            'update cash bank transaction',
            'delete cash bank transaction',
        ];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        $role->givePermissionTo($perms);

        $this->user = User::withoutGlobalScopes()->first() ?? User::create([
            'username' => 'finance_test',
            'email' => 'finance-test@dutatunggal.co.id',
            'name' => 'Finance Tester',
            'password' => bcrypt('password'),
            'cabang_id' => $this->cabang->id,
            'manage_type' => ['all'],
        ]);
        if (!$this->user->hasRole('Super Admin')) {
            $this->user->assignRole('Super Admin');
        }

        // Siapkan Akun COA Kas (1111) dan Bank (1112)
        $this->cashCoa = ChartOfAccount::firstOrCreate(
            ['code' => '1111.99'],
            [
                'name' => 'Kas Kecil Test',
                'type' => 'Asset',
                'is_active' => true,
                'is_cash_bank' => true,
                'opening_balance' => 10000000,
            ]
        );

        $this->bankCoa = ChartOfAccount::firstOrCreate(
            ['code' => '1112.99'],
            [
                'name' => 'Bank BCA Test',
                'type' => 'Asset',
                'is_active' => true,
                'is_cash_bank' => true,
                'opening_balance' => 50000000,
            ]
        );

        $this->expenseCoa = ChartOfAccount::firstOrCreate(
            ['code' => '6000.99'],
            [
                'name' => 'Biaya Listrik & Air Test',
                'type' => 'Expense',
                'is_active' => true,
                'opening_balance' => 0,
            ]
        );

        $this->incomeCoa = ChartOfAccount::firstOrCreate(
            ['code' => '4000.99'],
            [
                'name' => 'Pendapatan Jasa Test',
                'type' => 'Revenue',
                'is_active' => true,
                'opening_balance' => 0,
            ]
        );

        $this->adminFeeCoa = ChartOfAccount::firstOrCreate(
            ['code' => '8000.99'],
            [
                'name' => 'Biaya Administrasi Bank Test',
                'type' => 'Expense',
                'is_active' => true,
                'opening_balance' => 0,
            ]
        );

        // Buat CashBankAccount master record
        $this->cashAccount = CashBankAccount::firstOrCreate(
            ['coa_id' => $this->cashCoa->id],
            [
                'name' => 'Kas Kecil Test',
                'bank_name' => 'Tunai',
                'account_number' => 'KAS-001',
            ]
        );

        $this->bankAccount = CashBankAccount::firstOrCreate(
            ['coa_id' => $this->bankCoa->id],
            [
                'name' => 'Bank BCA Test',
                'bank_name' => 'Bank Central Asia',
                'account_number' => '1234567890',
            ]
        );
    }

    /**
     * Test 1: Halaman Index Kas & Bank dapat diakses dan menampilkan KPI serta daftar rekening.
     */
    public function test_can_render_cash_bank_index_page_with_kpi_and_accounts(): void
    {
        $response = $this->actingAs($this->user)->get(route('keuangan.cashbank.index'));

        $response->assertStatus(200);
        $response->assertSee('Kas &amp; Bank', false);
        $response->assertSee('Total Kas &amp; Setara Kas', false);
        $response->assertSee($this->cashAccount->name);
        $response->assertSee($this->bankAccount->name);
    }

    /**
     * Test 2: Form Create Transaksi Kas & Bank dapat dibuka.
     */
    public function test_can_render_cash_bank_create_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('keuangan.cashbank.create'));

        $response->assertStatus(200);
        $response->assertSee('Catat Transaksi Kas &amp; Bank', false);
        $response->assertSee('Kas Keluar');
        $response->assertSee('Bank Masuk');
    }

    /**
     * Test 3: Simpan transaksi Kas Keluar dan auto-posting jurnal seimbang.
     */
    public function test_can_store_cash_out_transaction_and_posts_balanced_journal(): void
    {
        $payload = [
            'number' => 'CB-TEST-OUT-' . rand(1000, 9999),
            'date' => now()->toDateString(),
            'type' => 'cash_out',
            'account_coa_id' => $this->cashCoa->id,
            'offset_coa_id' => $this->expenseCoa->id,
            'amount' => 750000,
            'counterparty' => 'PLN Persero',
            'description' => 'Pembayaran tagihan listrik kantor bulan ini',
            'cabang_id' => $this->cabang->id,
        ];

        $response = $this->actingAs($this->user)->post(route('keuangan.cashbank.store'), $payload);

        $transaction = CashBankTransaction::where('number', $payload['number'])->first();
        $this->assertNotNull($transaction);
        $this->assertEquals(750000, (float)$transaction->amount);
        $this->assertEquals('cash_out', $transaction->type);

        $response->assertRedirect(route('keuangan.cashbank.show', $transaction));

        // Verifikasi Jurnal Akuntansi (Dr Expense, Cr Kas)
        $entries = JournalEntry::where('source_type', CashBankTransaction::class)
            ->where('source_id', $transaction->id)
            ->get();

        $this->assertCount(2, $entries);
        $totalDebit = $entries->sum('debit');
        $totalCredit = $entries->sum('credit');
        $this->assertEquals(750000, $totalDebit);
        $this->assertEquals(750000, $totalCredit);

        // Expense harus di-Debit, Kas harus di-Kredit
        $expenseEntry = $entries->where('coa_id', $this->expenseCoa->id)->first();
        $this->assertNotNull($expenseEntry);
        $this->assertEquals(750000, (float)$expenseEntry->debit);

        $cashEntry = $entries->where('coa_id', $this->cashCoa->id)->first();
        $this->assertNotNull($cashEntry);
        $this->assertEquals(750000, (float)$cashEntry->credit);
    }

    /**
     * Test 4: Simpan transaksi Bank Masuk dan auto-posting jurnal seimbang.
     */
    public function test_can_store_bank_in_transaction_and_posts_balanced_journal(): void
    {
        $payload = [
            'number' => 'CB-TEST-IN-' . rand(1000, 9999),
            'date' => now()->toDateString(),
            'type' => 'bank_in',
            'account_coa_id' => $this->bankCoa->id,
            'offset_coa_id' => $this->incomeCoa->id,
            'amount' => 2500000,
            'counterparty' => 'Customer VIP',
            'description' => 'Penerimaan jasa konsultasi',
            'cabang_id' => $this->cabang->id,
        ];

        $response = $this->actingAs($this->user)->post(route('keuangan.cashbank.store'), $payload);

        $transaction = CashBankTransaction::where('number', $payload['number'])->first();
        $this->assertNotNull($transaction);
        $this->assertEquals(2500000, (float)$transaction->amount);
        $this->assertEquals('bank_in', $transaction->type);

        $response->assertRedirect(route('keuangan.cashbank.show', $transaction));

        // Verifikasi Jurnal Akuntansi (Dr Bank, Cr Revenue)
        $entries = JournalEntry::where('source_type', CashBankTransaction::class)
            ->where('source_id', $transaction->id)
            ->get();

        $this->assertCount(2, $entries);
        $totalDebit = $entries->sum('debit');
        $totalCredit = $entries->sum('credit');
        $this->assertEquals(2500000, $totalDebit);
        $this->assertEquals(2500000, $totalCredit);

        $bankEntry = $entries->where('coa_id', $this->bankCoa->id)->first();
        $this->assertNotNull($bankEntry);
        $this->assertEquals(2500000, (float)$bankEntry->debit);

        $incomeEntry = $entries->where('coa_id', $this->incomeCoa->id)->first();
        $this->assertNotNull($incomeEntry);
        $this->assertEquals(2500000, (float)$incomeEntry->credit);
    }

    /**
     * Test 5: Simpan transaksi dengan rincian breakdown (multi-pos COA).
     */
    public function test_can_store_transaction_with_breakdown_details_and_balanced_journal(): void
    {
        $secondExpenseCoa = ChartOfAccount::firstOrCreate(
            ['code' => '6000.98'],
            [
                'name' => 'Biaya Kebersihan & Keamanan Test',
                'type' => 'Expense',
                'is_active' => true,
                'opening_balance' => 0,
            ]
        );

        $payload = [
            'number' => 'CB-TEST-BRK-' . rand(1000, 9999),
            'date' => now()->toDateString(),
            'type' => 'cash_out',
            'account_coa_id' => $this->cashCoa->id,
            'amount' => 500000,
            'counterparty' => 'Vendor Pengelola Gedung',
            'description' => 'Iuran bulanan gedung & kebersihan',
            'cabang_id' => $this->cabang->id,
            'details' => [
                [
                    'chart_of_account_id' => $this->expenseCoa->id,
                    'amount' => 300000,
                    'description' => 'Iuran listrik',
                ],
                [
                    'chart_of_account_id' => $secondExpenseCoa->id,
                    'amount' => 200000,
                    'description' => 'Iuran kebersihan',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('keuangan.cashbank.store'), $payload);

        $transaction = CashBankTransaction::where('number', $payload['number'])->first();
        $this->assertNotNull($transaction);
        $this->assertCount(2, $transaction->transactionDetails);

        $response->assertRedirect(route('keuangan.cashbank.show', $transaction));

        // Verifikasi Jurnal Akuntansi (Dr Expense 1 300rb, Dr Expense 2 200rb, Cr Kas 500rb)
        $entries = JournalEntry::where('source_type', CashBankTransaction::class)
            ->where('source_id', $transaction->id)
            ->get();

        $this->assertCount(3, $entries);
        $this->assertEquals(500000, $entries->sum('debit'));
        $this->assertEquals(500000, $entries->sum('credit'));
    }

    /**
     * Test 6: Form Transfer Antar Kas/Bank dapat dibuka.
     */
    public function test_can_render_transfer_create_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('keuangan.cashbank.transfer.create'));

        $response->assertStatus(200);
        $response->assertSee('Transfer Antar Kas &amp; Bank', false);
    }

    /**
     * Test 7: Simpan transfer antar rekening beserta biaya admin dan jurnalnya.
     */
    public function test_can_store_transfer_between_cash_bank_and_posts_balanced_journal(): void
    {
        $payload = [
            'number' => 'TRF-TEST-' . rand(1000, 9999),
            'date' => now()->toDateString(),
            'from_coa_id' => $this->bankCoa->id,
            'to_coa_id' => $this->cashCoa->id,
            'amount' => 2000000,
            'other_costs' => 6500,
            'other_costs_coa_id' => $this->adminFeeCoa->id,
            'reference' => 'TARIK-TUNAI-01',
            'description' => 'Penarikan tunai operasional kas kecil',
            'cabang_id' => $this->cabang->id,
        ];

        $response = $this->actingAs($this->user)->post(route('keuangan.cashbank.transfer.store'), $payload);

        $transfer = CashBankTransfer::where('number', $payload['number'])->first();
        $this->assertNotNull($transfer);
        $this->assertEquals(2000000, (float)$transfer->amount);
        $this->assertEquals(6500, (float)$transfer->other_costs);

        $response->assertRedirect(route('keuangan.cashbank.transfer.show', $transfer));

        // Verifikasi Jurnal Transfer:
        // Cr From (Bank) = 2.006.500
        // Dr To (Kas) = 2.000.000
        // Dr Admin Expense = 6.500
        $entries = JournalEntry::where('source_type', CashBankTransfer::class)
            ->where('source_id', $transfer->id)
            ->get();

        $this->assertCount(3, $entries);
        $this->assertEquals(2006500, $entries->sum('debit'));
        $this->assertEquals(2006500, $entries->sum('credit'));

        $fromEntry = $entries->where('coa_id', $this->bankCoa->id)->first();
        $this->assertEquals(2006500, (float)$fromEntry->credit);

        $toEntry = $entries->where('coa_id', $this->cashCoa->id)->first();
        $this->assertEquals(2000000, (float)$toEntry->debit);

        $adminEntry = $entries->where('coa_id', $this->adminFeeCoa->id)->first();
        $this->assertEquals(6500, (float)$adminEntry->debit);
    }

    /**
     * Test 8: Streaming voucher PDF untuk transaksi kas/bank.
     */
    public function test_can_stream_pdf_voucher_for_transaction(): void
    {
        $transaction = CashBankTransaction::create([
            'number' => 'CB-PDF-' . rand(1000, 9999),
            'date' => now()->toDateString(),
            'type' => 'cash_out',
            'account_coa_id' => $this->cashCoa->id,
            'offset_coa_id' => $this->expenseCoa->id,
            'amount' => 150000,
            'counterparty' => 'Toko Alat Tulis',
            'description' => 'Pembelian ATK',
            'cabang_id' => $this->cabang->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('keuangan.cashbank.pdf', $transaction));

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    /**
     * Test 9: Streaming voucher PDF untuk transfer antar kas/bank.
     */
    public function test_can_stream_pdf_transfer_voucher(): void
    {
        $transfer = CashBankTransfer::create([
            'number' => 'TRF-PDF-' . rand(1000, 9999),
            'date' => now()->toDateString(),
            'from_coa_id' => $this->bankCoa->id,
            'to_coa_id' => $this->cashCoa->id,
            'amount' => 1000000,
            'other_costs' => 0,
            'status' => 'posted',
            'cabang_id' => $this->cabang->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('keuangan.cashbank.transfer.pdf', $transfer));

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    /**
     * Test 10: Hapus transaksi dan rollback jurnal akuntansinya.
     */
    public function test_can_delete_transaction_and_cleans_up_journal_entries(): void
    {
        $transaction = CashBankTransaction::create([
            'number' => 'CB-DEL-' . rand(1000, 9999),
            'date' => now()->toDateString(),
            'type' => 'cash_out',
            'account_coa_id' => $this->cashCoa->id,
            'offset_coa_id' => $this->expenseCoa->id,
            'amount' => 300000,
            'cabang_id' => $this->cabang->id,
        ]);

        app(\App\Services\CashBankService::class)->postTransaction($transaction);

        $this->assertTrue(JournalEntry::where('source_type', CashBankTransaction::class)->where('source_id', $transaction->id)->exists());

        $response = $this->actingAs($this->user)->delete(route('keuangan.cashbank.destroy', $transaction));

        $response->assertRedirect(route('keuangan.cashbank.index'));
        $this->assertNull(CashBankTransaction::find($transaction->id));
        $this->assertFalse(JournalEntry::where('source_type', CashBankTransaction::class)->where('source_id', $transaction->id)->exists());
    }

    /**
     * Test 11: Hapus transfer dan rollback jurnal akuntansinya.
     */
    public function test_can_delete_transfer_and_cleans_up_journal_entries(): void
    {
        $transfer = CashBankTransfer::create([
            'number' => 'TRF-DEL-' . rand(1000, 9999),
            'date' => now()->toDateString(),
            'from_coa_id' => $this->bankCoa->id,
            'to_coa_id' => $this->cashCoa->id,
            'amount' => 500000,
            'other_costs' => 0,
            'status' => 'draft',
            'cabang_id' => $this->cabang->id,
        ]);

        app(\App\Services\CashBankService::class)->postTransfer($transfer);

        $this->assertTrue(JournalEntry::where('source_type', CashBankTransfer::class)->where('source_id', $transfer->id)->exists());

        $response = $this->actingAs($this->user)->delete(route('keuangan.cashbank.transfer.destroy', $transfer));

        $response->assertRedirect(route('keuangan.cashbank.index', ['tab' => 'transfer']));
        $this->assertNull(CashBankTransfer::find($transfer->id));
        $this->assertFalse(JournalEntry::where('source_type', CashBankTransfer::class)->where('source_id', $transfer->id)->exists());
    }
}
