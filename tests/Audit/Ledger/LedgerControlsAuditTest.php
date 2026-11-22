<?php

namespace Tests\Audit\Ledger;

use App\Exceptions\ClosedPeriodException;
use App\Models\AccountingPeriod;
use App\Models\Cabang;
use App\Models\CashBankTransaction;
use App\Models\CashBankTransfer;
use App\Models\ChartOfAccount;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AccountingPeriodService;
use App\Services\BalanceSheetService;
use App\Services\IncomeStatementService;
use App\Services\LedgerPostingService;
use App\Services\Reports\CashFlowReportService;
use App\Services\TrialBalanceService;
use Database\Seeders\ChartOfAccountSeeder;
use Database\Seeders\Finance\FinanceReportConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AUDIT 1–6 — kontrol buku besar: double entry, neraca saldo, neraca, arus kas, kas/bank, void & tutup buku.
 * Setiap test menuliskan perilaku AKUNTANSI YANG BENAR; test yang gagal = temuan audit.
 */
class LedgerControlsAuditTest extends TestCase
{
    use RefreshDatabase;

    private array $coa = [];
    private Cabang $cabang;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::withoutGlobalScopes()->first();
        $this->user = User::factory()->create(['cabang_id' => $this->cabang->id, 'manage_type' => ['all']]);
        $this->user->assignRole('Super Admin');
        $this->actingAs($this->user);
        \App\Models\Currency::firstOrCreate(['code' => 'IDR'], ['name' => 'Rupiah', 'symbol' => 'Rp', 'to_rupiah' => 1]);
    }

    private function coa(string $key, string $code, string $name, string $type, float $opening = 0, bool $current = true, bool $cash = false): ChartOfAccount
    {
        return $this->coa[$key] = ChartOfAccount::create([
            'code' => $code, 'name' => $name, 'type' => $type, 'is_active' => true,
            'opening_balance' => $opening, 'is_current' => $current, 'is_cash_bank' => $cash,
        ]);
    }

    private function basicCoa(): void
    {
        $this->coa('kas', '1111.01', 'Kas Besar', 'Asset', 0, true, true);
        $this->coa('bank', '1112.01', 'Bank BCA', 'Asset', 0, true, true);
        $this->coa('modal', '3100', 'Modal Disetor', 'Equity');
        $this->coa('re', '3400', 'Laba Ditahan', 'Equity');
        $this->coa('sales', '4000', 'Penjualan', 'Revenue');
        $this->coa('retur', '4120', 'Retur Penjualan', 'Revenue');
        $this->coa('opex', '6100', 'Beban Umum', 'Expense');
    }

    private function je(string $key, string $date, float $d, float $k, string $ref, ?int $cabangId = null, string $type = 'manual'): JournalEntry
    {
        return JournalEntry::create([
            'coa_id' => $this->coa[$key]->id, 'date' => $date, 'reference' => $ref, 'description' => $ref,
            'debit' => $d, 'credit' => $k, 'journal_type' => $type, 'cabang_id' => $cabangId ?? $this->cabang->id,
        ]);
    }

    // ---------------------------------------------------------------- 6. Tutup buku

    public function test_closing_entries_must_not_wipe_out_profit_and_loss_of_closed_period(): void
    {
        $this->basicCoa();
        $this->je('kas', '2026-10-10', 10000000, 0, 'JV-1');
        $this->je('sales', '2026-10-10', 0, 10000000, 'JV-1');
        $this->je('opex', '2026-10-20', 2000000, 0, 'JV-2');
        $this->je('kas', '2026-10-20', 0, 2000000, 'JV-2');

        $before = app(IncomeStatementService::class)->generate(['start_date' => '2026-10-01', 'end_date' => '2026-10-31']);
        $period = app(AccountingPeriodService::class)->createPeriod('2026-10-01', '2026-10-31', $this->cabang->id);
        app(AccountingPeriodService::class)->closePeriod($period, $this->user->id, true);
        $after = app(IncomeStatementService::class)->generate(['start_date' => '2026-10-01', 'end_date' => '2026-10-31']);

        fwrite(STDERR, "\n[AUDIT-6a] Laba bersih Okt sebelum tutup buku={$before['net_profit']} sesudah tutup buku={$after['net_profit']}\n");
        $this->assertEqualsWithDelta(8000000, $before['net_profit'], 0.01);
        $this->assertEqualsWithDelta(8000000, $after['net_profit'], 0.01, 'Laporan L/R periode yang sudah ditutup berubah menjadi 0 karena jurnal penutup ikut dihitung');
    }

    public function test_closing_entries_must_close_contra_revenue_with_debit_balance(): void
    {
        $this->basicCoa();
        $this->je('kas', '2026-10-10', 10000000, 0, 'JV-1');
        $this->je('sales', '2026-10-10', 0, 10000000, 'JV-1');
        $this->je('retur', '2026-10-15', 1000000, 0, 'JV-2'); // retur penjualan (tipe Revenue, saldo debit)
        $this->je('kas', '2026-10-15', 0, 1000000, 'JV-2');

        $period = app(AccountingPeriodService::class)->createPeriod('2026-10-01', '2026-10-31', $this->cabang->id);
        app(AccountingPeriodService::class)->closePeriod($period, $this->user->id, true);

        $reCredit = (float) JournalEntry::where('coa_id', $this->coa['re']->id)->sum('credit') - (float) JournalEntry::where('coa_id', $this->coa['re']->id)->sum('debit');
        $returLeft = (float) JournalEntry::where('coa_id', $this->coa['retur']->id)->sum('debit') - (float) JournalEntry::where('coa_id', $this->coa['retur']->id)->sum('credit');
        fwrite(STDERR, "[AUDIT-6b] Saldo Laba Ditahan setelah closing={$reCredit} (seharusnya 9.000.000); sisa saldo Retur Penjualan={$returLeft} (seharusnya 0)\n");
        $this->assertEqualsWithDelta(9000000, $reCredit, 0.01);
        $this->assertEqualsWithDelta(0, $returLeft, 0.01);
    }

    public function test_closed_all_branch_period_must_block_posting_for_branch_journals(): void
    {
        $this->basicCoa();
        $period = app(AccountingPeriodService::class)->createPeriod('2026-09-01', '2026-09-30', null); // "Semua cabang"
        app(AccountingPeriodService::class)->closePeriod($period, $this->user->id, false);

        $blocked = false;
        try {
            $this->je('kas', '2026-09-15', 500000, 0, 'JV-BACKDATE', $this->cabang->id);
            $this->je('sales', '2026-09-15', 0, 500000, 'JV-BACKDATE', $this->cabang->id);
        } catch (ClosedPeriodException) {
            $blocked = true;
        }
        fwrite(STDERR, '[AUDIT-6c] Jurnal cabang ke periode tertutup "semua cabang" diblokir? '.json_encode($blocked)."\n");
        $this->assertTrue($blocked, 'Periode tutup buku tanpa cabang (semua cabang) tidak mengunci jurnal cabang');
    }

    public function test_manual_journal_into_closed_period_is_blocked(): void
    {
        $this->basicCoa();
        $period = app(AccountingPeriodService::class)->createPeriod('2026-09-01', '2026-09-30', $this->cabang->id);
        app(AccountingPeriodService::class)->closePeriod($period, $this->user->id, false);

        $this->post(route('keuangan.journal.store'), [
            'reference' => 'JV-CLOSED', 'date' => '2026-09-10', 'cabang_id' => $this->cabang->id, 'description' => 'Uji', 'journal_type' => 'manual',
            'entries' => [['coa_id' => $this->coa['kas']->id, 'description' => '', 'debit' => 1000, 'credit' => 0], ['coa_id' => $this->coa['sales']->id, 'description' => '', 'debit' => 0, 'credit' => 1000]],
        ])->assertSessionHas('error');
        $this->assertSame(0, JournalEntry::where('reference', 'JV-CLOSED')->count());
    }

    public function test_deleting_cash_bank_transaction_in_closed_period_must_be_blocked(): void
    {
        $this->basicCoa();
        $this->post(route('keuangan.cashbank.store'), [
            'number' => 'BKK-AUD-9', 'date' => '2026-09-10', 'type' => 'cash_out', 'account_coa_id' => $this->coa['kas']->id,
            'offset_coa_id' => $this->coa['opex']->id, 'amount' => 750000, 'cabang_id' => $this->cabang->id,
        ])->assertSessionHasNoErrors();
        $trx = CashBankTransaction::where('number', 'BKK-AUD-9')->firstOrFail();

        $period = app(AccountingPeriodService::class)->createPeriod('2026-09-01', '2026-09-30', $this->cabang->id);
        app(AccountingPeriodService::class)->closePeriod($period, $this->user->id, false);

        $this->delete(route('keuangan.cashbank.destroy', $trx));
        $remaining = JournalEntry::where('source_type', CashBankTransaction::class)->where('source_id', $trx->id)->count();
        fwrite(STDERR, "[AUDIT-6d] Jurnal kas keluar Sep (periode tertutup) tersisa setelah hapus transaksi: {$remaining} baris (seharusnya 2, tetap)\n");
        $this->assertSame(2, $remaining, 'Hapus transaksi kas menghapus jurnal di periode tertutup (lolos kunci periode)');
    }

    public function test_cancelling_transfer_must_reverse_not_delete_and_respect_closed_period(): void
    {
        $this->basicCoa();
        $this->post(route('keuangan.cashbank.transfer.store'), [
            'number' => 'TRF-AUD-9', 'date' => '2026-09-12', 'from_coa_id' => $this->coa['bank']->id,
            'to_coa_id' => $this->coa['kas']->id, 'amount' => 1000000, 'cabang_id' => $this->cabang->id,
        ])->assertSessionHasNoErrors();
        $trf = CashBankTransfer::where('number', 'TRF-AUD-9')->firstOrFail();

        $period = app(AccountingPeriodService::class)->createPeriod('2026-09-01', '2026-09-30', $this->cabang->id);
        app(AccountingPeriodService::class)->closePeriod($period, $this->user->id, false);

        $this->post(route('keuangan.cashbank.transfer.cancel', $trf), ['cancel_reason' => 'Salah input rekening']);
        $septLines = JournalEntry::where('source_type', CashBankTransfer::class)->where('source_id', $trf->id)->where('date', '<=', '2026-09-30')->count();
        fwrite(STDERR, '[AUDIT-6e] Status transfer='.$trf->fresh()->status." ; baris jurnal Sep tersisa={$septLines} (seharusnya 2 + jurnal balik di periode terbuka)\n");
        $this->assertSame(2, $septLines, 'Pembatalan transfer menghapus jurnal di periode tertutup alih-alih membuat jurnal pembalik');
    }

    public function test_journal_cannot_be_reversed_twice(): void
    {
        $this->basicCoa();
        $this->post(route('keuangan.journal.store'), [
            'reference' => 'JV-REV', 'date' => '2026-11-10', 'cabang_id' => $this->cabang->id, 'description' => 'Uji pembalik', 'journal_type' => 'manual',
            'entries' => [['coa_id' => $this->coa['opex']->id, 'description' => '', 'debit' => 300000, 'credit' => 0], ['coa_id' => $this->coa['kas']->id, 'description' => '', 'debit' => 0, 'credit' => 300000]],
        ]);
        $this->post(route('keuangan.journal.reverse', 'JV-REV'), ['reverse_date' => '2026-11-11', 'reason' => 'Salah akun']);
        $this->travel(2)->seconds();
        $this->post(route('keuangan.journal.reverse', 'JV-REV'), ['reverse_date' => '2026-11-11', 'reason' => 'Salah akun lagi']);

        $reversals = JournalEntry::where('reversal_of_transaction_id', 'JV-REV')->distinct()->count('reference');
        $opex = (float) JournalEntry::where('coa_id', $this->coa['opex']->id)->sum('debit') - (float) JournalEntry::where('coa_id', $this->coa['opex']->id)->sum('credit');
        fwrite(STDERR, "[AUDIT-6f] Jumlah voucher pembalik atas JV-REV={$reversals}; saldo Beban Umum={$opex} (seharusnya 0)\n");
        $this->assertSame(1, $reversals, 'Satu jurnal dapat dibalik berkali-kali sehingga beban menjadi negatif');
    }

    // ---------------------------------------------------------------- 1. Integritas double entry

    public function test_manual_journal_rejects_line_with_both_debit_and_credit(): void
    {
        $this->basicCoa();
        $this->post(route('keuangan.journal.store'), [
            'reference' => 'JV-BOTH', 'date' => '2026-11-10', 'cabang_id' => $this->cabang->id, 'description' => 'Uji', 'journal_type' => 'manual',
            'entries' => [
                ['coa_id' => $this->coa['kas']->id, 'description' => '', 'debit' => 500000, 'credit' => 500000],
                ['coa_id' => $this->coa['sales']->id, 'description' => '', 'debit' => 0, 'credit' => 0],
            ],
        ]);
        $rows = JournalEntry::where('reference', 'JV-BOTH')->get();
        fwrite(STDERR, '[AUDIT-1a] Voucher JV-BOTH tersimpan '.$rows->count().' baris; baris debit&kredit sekaligus='.$rows->filter(fn ($r) => $r->debit > 0 && $r->credit > 0)->count()."\n");
        $this->assertSame(0, $rows->count(), 'Jurnal satu baris dengan debit dan kredit sekaligus diterima');
    }

    public function test_journal_on_inactive_coa_is_rejected_or_reports_stay_balanced(): void
    {
        $this->basicCoa();
        $other = $this->coa('lain', '7000.04', 'Pendapatan Lainnya', 'Revenue');
        $other->update(['is_active' => false]);

        $this->post(route('keuangan.journal.store'), [
            'reference' => 'JV-INACT', 'date' => '2026-11-10', 'cabang_id' => $this->cabang->id, 'description' => 'Uji', 'journal_type' => 'manual',
            'entries' => [['coa_id' => $this->coa['kas']->id, 'description' => '', 'debit' => 400000, 'credit' => 0], ['coa_id' => $other->id, 'description' => '', 'debit' => 0, 'credit' => 400000]],
        ]);
        $accepted = JournalEntry::where('reference', 'JV-INACT')->count();
        $tb = app(TrialBalanceService::class)->generate(['start_date' => '2026-11-01', 'end_date' => '2026-11-30']);
        $bs = app(BalanceSheetService::class)->generate(['as_of_date' => '2026-11-30']);
        fwrite(STDERR, "[AUDIT-1b] Jurnal ke COA nonaktif diterima={$accepted} baris; TB D={$tb['grand_totals']['period_debit']} K={$tb['grand_totals']['period_credit']} seimbang=".json_encode($tb['is_balanced'])."; Neraca selisih={$bs['difference']}\n");
        $this->assertTrue($accepted === 0 || ($tb['is_balanced'] && $bs['is_balanced']), 'Jurnal ke COA nonaktif diterima lalu hilang dari Neraca Saldo/Neraca');
    }

    public function test_purchase_invoice_other_fee_without_expense_coa_must_not_post_to_arbitrary_account(): void
    {
        $this->coa('kas', '1111.01', 'Kas Besar', 'Asset', 0, true, true); // id = 1
        $this->coa('inv', '1140.10', 'Persediaan', 'Asset');
        $this->coa('ap', '2110', 'Hutang Dagang', 'Liability');
        // Sengaja TIDAK ada COA 6100 (Beban Umum) dan invoice tanpa expense_coa_id.
        $supplier = Supplier::factory()->create();
        $po = PurchaseOrder::factory()->create(['supplier_id' => $supplier->id, 'status' => 'approved', 'cabang_id' => $this->cabang->id, 'total_amount' => 1000000]);
        $invoice = Invoice::withoutEvents(fn () => Invoice::factory()->create([
            'invoice_number' => 'PINV-FEE-1', 'from_model_type' => PurchaseOrder::class, 'from_model_id' => $po->id,
            'invoice_date' => '2026-11-03', 'subtotal' => 1000000, 'tax' => 0, 'ppn_rate' => 0, 'dpp' => 1000000,
            'other_fee' => [['name' => 'Ongkos kirim', 'amount' => 50000]], 'total' => 1050000, 'status' => 'unpaid', 'cabang_id' => $this->cabang->id,
        ]));
        app(LedgerPostingService::class)->postInvoice($invoice->fresh());

        $feeLine = JournalEntry::where('source_type', Invoice::class)->where('source_id', $invoice->id)->where('debit', 50000)->first();
        $coa1 = ChartOfAccount::find(1);
        fwrite(STDERR, '[AUDIT-1c] Ongkos kirim 50.000 invoice pembelian diposting ke coa_id='.($feeLine?->coa_id).' ('.($coa1?->code).' '.($coa1?->name).' / tipe '.($coa1?->type).")\n");
        $this->assertNotNull($feeLine);
        $this->assertNotSame(1, (int) $feeLine->coa_id, 'Biaya lain invoice pembelian jatuh ke COA id 1 (fallback hard-code) — akun acak');
    }

    // ---------------------------------------------------------------- 2. Neraca saldo

    public function test_trial_balance_must_flag_unbalanced_opening_balances(): void
    {
        $this->coa('kas', '1111.01', 'Kas Besar', 'Asset', 5000000, true, true); // saldo awal tanpa lawan
        $this->coa('modal', '3100', 'Modal', 'Equity', 3000000);
        $tb = app(TrialBalanceService::class)->generate(['start_date' => '2026-11-01', 'end_date' => '2026-11-30']);
        $bs = app(BalanceSheetService::class)->generate(['as_of_date' => '2026-11-30']);
        fwrite(STDERR, '[AUDIT-2a] Saldo awal D 5.000.000 vs K 3.000.000 → TB seimbang='.json_encode($tb['is_balanced']).", Neraca selisih={$bs['difference']}\n");
        $this->assertFalse($tb['is_balanced'], 'Neraca saldo menyatakan seimbang padahal saldo awal selisih 2.000.000');
    }

    // ---------------------------------------------------------------- 3. Neraca

    public function test_seeded_cash_and_receivable_are_classified_as_current_assets(): void
    {
        $this->seed(ChartOfAccountSeeder::class);
        $kas = ChartOfAccount::where('code', '1111.01')->first();
        $hutang = ChartOfAccount::where('code', '2110')->first() ?? ChartOfAccount::where('type', 'Liability')->whereDoesntHave('children')->first();
        $modal = ChartOfAccount::where('type', 'Equity')->whereDoesntHave('children')->first();
        foreach ([[$kas, 1000000, 0], [$hutang, 0, 400000], [$modal, 0, 600000]] as [$c, $d, $k]) {
            JournalEntry::create(['coa_id' => $c->id, 'date' => '2026-11-05', 'reference' => 'JV-CLS', 'description' => 'x', 'debit' => $d, 'credit' => $k, 'journal_type' => 'manual', 'cabang_id' => $this->cabang->id]);
        }
        $bs = app(BalanceSheetService::class)->generate(['as_of_date' => '2026-11-30']);
        fwrite(STDERR, "[AUDIT-3a] COA seeder: Kas {$kas->code} is_current=".var_export($kas->is_current, true)." → aset lancar={$bs['current_assets']['total']} aset tidak lancar={$bs['fixed_assets']['total']}; hutang {$hutang->code} lancar={$bs['current_liabilities']['total']} jangka panjang={$bs['long_term_liabilities']['total']}\n");
        $this->assertEqualsWithDelta(1000000, $bs['current_assets']['total'], 0.01, 'Kas tersaji sebagai aset tidak lancar');
        $this->assertEqualsWithDelta(400000, $bs['current_liabilities']['total'], 0.01, 'Hutang dagang tersaji sebagai kewajiban jangka panjang');
    }

    public function test_branch_balance_sheets_add_up_to_consolidated(): void
    {
        $cabang2 = Cabang::create(['kode' => 'CB-AUD2', 'nama' => 'Cabang Dua', 'alamat' => 'x', 'telepon' => '1']);
        $this->coa('bank', '1112.01', 'Bank', 'Asset', 10000000, true, true);
        $this->coa('modal', '3100', 'Modal', 'Equity', 10000000);
        $this->coa('sales', '4000', 'Penjualan', 'Revenue');
        $this->je('bank', '2026-11-05', 1000000, 0, 'JV-B1', $this->cabang->id);
        $this->je('sales', '2026-11-05', 0, 1000000, 'JV-B1', $this->cabang->id);
        $this->je('bank', '2026-11-06', 2000000, 0, 'JV-B2', $cabang2->id);
        $this->je('sales', '2026-11-06', 0, 2000000, 'JV-B2', $cabang2->id);

        $svc = app(BalanceSheetService::class);
        $all = $svc->generate(['as_of_date' => '2026-11-30']);
        $b1 = $svc->generate(['as_of_date' => '2026-11-30', 'cabang_id' => $this->cabang->id]);
        $b2 = $svc->generate(['as_of_date' => '2026-11-30', 'cabang_id' => $cabang2->id]);
        fwrite(STDERR, "[AUDIT-3b] Total aset konsolidasi={$all['total_assets']} ; cabang1={$b1['total_assets']} + cabang2={$b2['total_assets']} = ".($b1['total_assets'] + $b2['total_assets'])."\n");
        $this->assertEqualsWithDelta($all['total_assets'], $b1['total_assets'] + $b2['total_assets'], 0.01, 'Saldo awal COA dihitung penuh di setiap cabang');
    }

    // ---------------------------------------------------------------- 4. Arus kas

    public function test_indirect_cash_flow_must_include_investing_cash_out(): void
    {
        $this->basicCoa();
        $this->coa('fa', '1210.01', 'Peralatan Kantor', 'Asset', 0, false);
        $this->seed(FinanceReportConfigSeeder::class);
        $this->post(route('keuangan.cashbank.store'), [
            'number' => 'BKK-FA-1', 'date' => '2026-11-08', 'type' => 'cash_out', 'account_coa_id' => $this->coa['kas']->id,
            'offset_coa_id' => $this->coa['fa']->id, 'amount' => 3000000, 'cabang_id' => $this->cabang->id,
        ])->assertSessionHasNoErrors();
        $this->post(route('keuangan.cashbank.store'), [
            'number' => 'BKM-MDL-1', 'date' => '2026-11-02', 'type' => 'cash_in', 'account_coa_id' => $this->coa['kas']->id,
            'offset_coa_id' => $this->coa['modal']->id, 'amount' => 5000000, 'cabang_id' => $this->cabang->id,
        ])->assertSessionHasNoErrors();

        $direct = app(CashFlowReportService::class)->generate('2026-11-01', '2026-11-30', ['method' => 'direct']);
        $indirect = app(CashFlowReportService::class)->generate('2026-11-01', '2026-11-30', ['method' => 'indirect']);
        $d = collect($direct['sections'])->mapWithKeys(fn ($s) => [$s['key'] => $s['total']]);
        $i = collect($indirect['sections'])->mapWithKeys(fn ($s) => [$s['key'] => $s['total']]);
        fwrite(STDERR, '[AUDIT-4b] Setoran modal +5.000.000 & beli aset −3.000.000 (mutasi GL kas +2.000.000). Langsung='.json_encode($d)." net={$direct['net_change']} ; Tidak langsung=".json_encode($i)." net={$indirect['net_change']}\n");
        $this->assertEqualsWithDelta(-3000000, $d['investing'], 0.01);
        $this->assertEqualsWithDelta(5000000, $d['financing'], 0.01, 'Setoran modal (ekuitas 3xxx) tidak masuk aktivitas pendanaan');
        $this->assertEqualsWithDelta(2000000, $direct['net_change'], 0.01);
        $this->assertEqualsWithDelta(2000000, $indirect['net_change'], 0.01, 'Metode tidak langsung mengabaikan investasi & pendanaan (hard-coded 0)');
    }
}
