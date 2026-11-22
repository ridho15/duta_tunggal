<?php

namespace Tests\Audit\Ledger;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Cabang;
use App\Models\CashBankTransaction;
use App\Models\CashBankTransfer;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptItem;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorPayment;
use App\Models\VendorPaymentDetail;
use App\Services\BalanceSheetService;
use App\Services\CashBankService;
use App\Services\IncomeStatementService;
use App\Services\LedgerPostingService;
use App\Services\Reports\CashFlowReportService;
use App\Services\TrialBalanceService;
use Database\Seeders\Finance\FinanceReportConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * AUDIT 7 — Skenario campuran kecil (beli → jual → terima bayar → bayar hutang → transfer kas → biaya)
 * dicocokkan dengan perhitungan manual auditor.
 *
 * Angka manual (periode 1–30 Nov 2026, saldo awal: Bank 100.000.000 = Modal 100.000.000):
 *  1. Beli persediaan kredit        Dr Persediaan 10.000.000 / Cr Hutang Dagang 10.000.000
 *  2. Jual kredit                    Dr Piutang 15.000.000 / Cr Penjualan 15.000.000
 *     HPP                            Dr HPP 6.000.000 / Cr Persediaan 6.000.000
 *  3. Terima pelunasan ke Bank       Dr Bank 15.000.000 / Cr Piutang 15.000.000
 *  4. Bayar hutang dari Bank         Dr Hutang 10.000.000 / Cr Bank 10.000.000
 *  5. Transfer Bank → Kas 2.000.000 + biaya admin 5.000
 *                                    Dr Kas 2.000.000, Dr Beban Adm Bank 5.000 / Cr Bank 2.005.000
 *  6. Biaya kantor tunai             Dr Beban Umum 500.000 / Cr Kas 500.000
 *
 *  Saldo akhir: Bank 102.995.000; Kas 1.500.000; Persediaan 4.000.000 → Aset 108.495.000
 *  Laba bersih = 15.000.000 − 6.000.000 − 5.000 − 500.000 = 8.495.000
 *  Ekuitas = 100.000.000 + 8.495.000 = 108.495.000; Kewajiban 0.
 *  Arus kas: Operasi +15.000.000 −10.000.000 −500.000 −5.000 = +4.495.000; investasi 0; pendanaan 0.
 *  Kas awal 100.000.000 → kas akhir 104.495.000 (= saldo GL Kas+Bank).
 */
class LedgerMixedScenarioAuditTest extends TestCase
{
    use RefreshDatabase;

    private const START = '2026-11-01';
    private const END = '2026-11-30';

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

        $make = function (string $key, string $code, string $name, string $type, float $opening = 0, bool $current = true, bool $cash = false) {
            $this->coa[$key] = ChartOfAccount::create([
                'code' => $code, 'name' => $name, 'type' => $type, 'is_active' => true,
                'opening_balance' => $opening, 'is_current' => $current, 'is_cash_bank' => $cash,
            ]);
        };
        $make('kas', '1111.01', 'Kas Besar', 'Asset', 0, true, true);
        $make('bank', '1112.01', 'Bank BCA', 'Asset', 100000000, true, true);
        $make('ar', '1120', 'Piutang Dagang', 'Asset');
        $make('inv', '1140.10', 'Persediaan Barang Dagangan', 'Asset');
        $make('ap', '2110', 'Hutang Dagang', 'Liability');
        $make('modal', '3100', 'Modal Disetor', 'Equity', 100000000);
        $make('re', '3400', 'Laba Ditahan', 'Equity');
        $make('sales', '4000', 'Penjualan', 'Revenue');
        $make('cogs', '5100.10', 'HPP', 'Expense');
        $make('opex', '6100', 'Beban Umum', 'Expense');
        $make('bankfee', '8000.01', 'Beban Administrasi Bank', 'Expense');

        $this->seed(FinanceReportConfigSeeder::class);
    }

    private function runScenario(): array
    {
        $supplier = Supplier::factory()->create();
        $customer = Customer::factory()->create(['cabang_id' => $this->cabang->id]);

        // 1. Pembelian kredit (invoice pembelian dari PO tanpa penerimaan → Dr Persediaan / Cr Hutang)
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id, 'status' => 'approved', 'cabang_id' => $this->cabang->id,
            'total_amount' => 10000000, 'order_date' => '2026-11-02',
        ]);
        $purchaseInvoice = Invoice::factory()->create([
            'invoice_number' => 'PINV-AUD-001', 'from_model_type' => PurchaseOrder::class, 'from_model_id' => $po->id,
            'invoice_date' => '2026-11-02', 'due_date' => '2026-12-02', 'subtotal' => 10000000, 'tax' => 0, 'ppn_rate' => 0,
            'dpp' => 10000000, 'total' => 10000000, 'status' => 'unpaid', 'supplier_name' => $supplier->name, 'cabang_id' => $this->cabang->id,
        ]);
        app(LedgerPostingService::class)->postInvoice($purchaseInvoice->fresh());

        // 2. Penjualan kredit + HPP (jurnal penjualan dibuat sebagai jurnal umum bersumber Invoice penjualan)
        $salesInvoice = Invoice::withoutEvents(fn () => Invoice::factory()->create([
            'invoice_number' => 'SINV-AUD-001', 'from_model_type' => 'App\\Models\\SaleOrder', 'from_model_id' => 0,
            'invoice_date' => '2026-11-05', 'due_date' => '2026-12-05', 'subtotal' => 15000000, 'tax' => 0, 'ppn_rate' => 0,
            'dpp' => 15000000, 'total' => 15000000, 'status' => 'unpaid', 'customer_name' => $customer->name, 'cabang_id' => $this->cabang->id,
        ]));
        foreach ([
            [$this->coa['ar'], 15000000, 0], [$this->coa['sales'], 0, 15000000],
            [$this->coa['cogs'], 6000000, 0], [$this->coa['inv'], 0, 6000000],
        ] as [$c, $d, $k]) {
            JournalEntry::create([
                'coa_id' => $c->id, 'date' => '2026-11-05', 'reference' => 'SINV-AUD-001', 'description' => 'Penjualan',
                'debit' => $d, 'credit' => $k, 'journal_type' => 'sales', 'cabang_id' => $this->cabang->id,
                'source_type' => Invoice::class, 'source_id' => $salesInvoice->id,
            ]);
        }
        AccountReceivable::create([
            'invoice_id' => $salesInvoice->id, 'customer_id' => $customer->id, 'total' => 15000000, 'paid' => 0,
            'remaining' => 15000000, 'status' => 'Belum Lunas', 'created_by' => $this->user->id, 'cabang_id' => $this->cabang->id,
        ]);

        // 3. Penerimaan pelunasan pelanggan ke Bank (alur CustomerReceipt → LedgerPostingService)
        $receipt = CustomerReceipt::withoutEvents(fn () => CustomerReceipt::create([
            'customer_id' => $customer->id, 'payment_date' => '2026-11-10', 'total_payment' => 15000000,
            'payment_method' => 'Bank Transfer', 'coa_id' => $this->coa['bank']->id, 'status' => 'paid', 'cabang_id' => $this->cabang->id,
        ]));
        CustomerReceiptItem::withoutEvents(fn () => CustomerReceiptItem::create([
            'customer_receipt_id' => $receipt->id, 'invoice_id' => $salesInvoice->id, 'method' => 'Bank Transfer',
            'amount' => 15000000, 'coa_id' => $this->coa['bank']->id, 'payment_date' => '2026-11-10',
        ]));
        $receiptResult = app(LedgerPostingService::class)->postCustomerReceipt($receipt->fresh());

        // 4. Pembayaran hutang dari Bank (VendorPayment → LedgerPostingService)
        $payment = VendorPayment::withoutEvents(fn () => VendorPayment::create([
            'payment_number' => 'VP-AUD-001', 'supplier_id' => $supplier->id, 'payment_date' => '2026-11-15',
            'total_payment' => 10000000, 'coa_id' => $this->coa['bank']->id, 'payment_method' => 'Bank Transfer',
            'status' => 'Paid', 'cabang_id' => $this->cabang->id,
        ]));
        VendorPaymentDetail::withoutEvents(fn () => VendorPaymentDetail::create([
            'vendor_payment_id' => $payment->id, 'method' => 'Bank Transfer', 'amount' => 10000000,
            'coa_id' => $this->coa['bank']->id, 'payment_date' => '2026-11-15', 'invoice_id' => $purchaseInvoice->id,
        ]));
        $paymentResult = app(LedgerPostingService::class)->postVendorPayment($payment->fresh());

        // 5. Transfer Bank → Kas (alur controller kas/bank)
        $this->post(route('keuangan.cashbank.transfer.store'), [
            'number' => 'TRF-AUD-001', 'date' => '2026-11-20', 'from_coa_id' => $this->coa['bank']->id,
            'to_coa_id' => $this->coa['kas']->id, 'amount' => 2000000, 'other_costs' => 5000,
            'other_costs_coa_id' => $this->coa['bankfee']->id, 'cabang_id' => $this->cabang->id,
        ])->assertSessionHasNoErrors();

        // 6. Biaya kantor dibayar tunai (alur controller kas/bank)
        $this->post(route('keuangan.cashbank.store'), [
            'number' => 'BKK-AUD-001', 'date' => '2026-11-25', 'type' => 'cash_out', 'account_coa_id' => $this->coa['kas']->id,
            'offset_coa_id' => $this->coa['opex']->id, 'amount' => 500000, 'description' => 'Biaya kantor', 'cabang_id' => $this->cabang->id,
        ])->assertSessionHasNoErrors();

        return compact('purchaseInvoice', 'salesInvoice', 'receipt', 'payment', 'receiptResult', 'paymentResult');
    }

    private function glBalance(string $key, ?string $asOf = null): float
    {
        $c = $this->coa[$key];
        $q = JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')->where('coa_id', $c->id)
            ->when($asOf, fn ($x) => $x->where('date', '<=', $asOf));
        $d = (float) (clone $q)->sum('debit');
        $k = (float) (clone $q)->sum('credit');
        $debitNormal = in_array($c->type, ['Asset', 'Expense'], true);

        return (float) $c->opening_balance + ($debitNormal ? $d - $k : $k - $d);
    }

    public function test_mixed_scenario_every_report_matches_manual_computation(): void
    {
        $ctx = $this->runScenario();

        // --- Double entry per sumber ---
        $perSource = JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')
            ->selectRaw('source_type, source_id, SUM(debit) d, SUM(credit) k, COUNT(*) n')
            ->groupBy('source_type', 'source_id')->get();
        $lines = $perSource->map(fn ($r) => class_basename((string) $r->source_type)."#{$r->source_id} D={$r->d} K={$r->k} n={$r->n}")->implode(' | ');
        fwrite(STDERR, "\n[AUDIT-7] Jurnal per sumber: {$lines}\n");
        fwrite(STDERR, '[AUDIT-7] receipt='.json_encode($ctx['receiptResult']['status'] ?? null).' payment='.json_encode($ctx['paymentResult']['status'] ?? null)."\n");
        foreach ($perSource as $r) {
            $this->assertEqualsWithDelta((float) $r->d, (float) $r->k, 0.01, 'Jurnal tidak seimbang untuk '.$r->source_type.'#'.$r->source_id);
        }

        // --- Saldo GL manual ---
        $gl = [
            'bank' => $this->glBalance('bank'), 'kas' => $this->glBalance('kas'), 'ar' => $this->glBalance('ar'),
            'inv' => $this->glBalance('inv'), 'ap' => $this->glBalance('ap'),
        ];
        fwrite(STDERR, '[AUDIT-7] Saldo GL: '.json_encode($gl)."\n");
        $this->assertEqualsWithDelta(102995000, $gl['bank'], 0.01);
        $this->assertEqualsWithDelta(1500000, $gl['kas'], 0.01);
        $this->assertEqualsWithDelta(0, $gl['ar'], 0.01);
        $this->assertEqualsWithDelta(4000000, $gl['inv'], 0.01);
        $this->assertEqualsWithDelta(0, $gl['ap'], 0.01);

        // --- Neraca saldo ---
        $tb = app(TrialBalanceService::class)->generate(['start_date' => self::START, 'end_date' => self::END]);
        fwrite(STDERR, '[AUDIT-7] TB: D='.$tb['grand_totals']['period_debit'].' K='.$tb['grand_totals']['period_credit'].' balanced='.json_encode($tb['is_balanced'])."\n");
        $this->assertTrue($tb['is_balanced']);

        // --- Laba rugi ---
        $pl = app(IncomeStatementService::class)->generate(['start_date' => self::START, 'end_date' => self::END]);
        fwrite(STDERR, "[AUDIT-7] L/R: pendapatan={$pl['sales_revenue']['total']} hpp={$pl['cogs']['total']} opex={$pl['operating_expenses']['total']} lain={$pl['net_other_income_expense']} laba_bersih={$pl['net_profit']}\n");
        $this->assertEqualsWithDelta(8495000, $pl['net_profit'], 0.01, 'Laba bersih harus 8.495.000');

        // --- Neraca ---
        $bs = app(BalanceSheetService::class)->generate(['as_of_date' => self::END]);
        fwrite(STDERR, "[AUDIT-7] Neraca: aset={$bs['total_assets']} kewajiban={$bs['total_liabilities']} ekuitas={$bs['total_equity']} laba_berjalan={$bs['retained_earnings']} selisih={$bs['difference']}\n");
        $this->assertEqualsWithDelta(108495000, $bs['total_assets'], 0.01);
        $this->assertEqualsWithDelta(0, $bs['total_liabilities'], 0.01);
        $this->assertEqualsWithDelta(108495000, $bs['total_equity'], 0.01);
        $this->assertTrue($bs['is_balanced']);
        $this->assertEqualsWithDelta($pl['net_profit'], $bs['retained_earnings'], 0.01, 'Laba berjalan di neraca = laba bersih L/R');

        // --- Arus kas (metode langsung) ---
        $cf = app(CashFlowReportService::class)->generate(self::START, self::END, ['method' => 'direct']);
        $sec = collect($cf['sections'])->mapWithKeys(fn ($s) => [$s['key'] => $s['total']]);
        $items = collect($cf['sections'])->flatMap(fn ($s) => collect($s['items'])->filter(fn ($i) => $i['amount'] != 0)->map(fn ($i) => $s['key'].':'.$i['key'].'='.$i['amount']))->implode(', ');
        fwrite(STDERR, '[AUDIT-7] Arus kas langsung: '.json_encode($sec)." net={$cf['net_change']} awal={$cf['opening_balance']} akhir={$cf['closing_balance']} | {$items}\n");

        $cfi = app(CashFlowReportService::class)->generate(self::START, self::END, ['method' => 'indirect']);
        $isec = collect($cfi['sections'])->mapWithKeys(fn ($s) => [$s['key'] => $s['total']]);
        fwrite(STDERR, '[AUDIT-7] Arus kas tidak langsung: '.json_encode($isec)." net={$cfi['net_change']} awal={$cfi['opening_balance']} akhir={$cfi['closing_balance']}\n");

        $glCashEnd = $gl['bank'] + $gl['kas'];
        $checks = [
            'Arus kas operasi (seharusnya 4.495.000)' => [4495000, $sec['operating']],
            'Arus kas pendanaan (bayar hutang dagang bukan pendanaan; seharusnya 0)' => [0, $sec['financing']],
            'Kenaikan kas bersih metode langsung = mutasi GL kas/bank (4.495.000)' => [4495000, $cf['net_change']],
            'Kas awal = saldo awal GL kas/bank (100.000.000)' => [100000000, $cf['opening_balance']],
            'Kas akhir metode langsung = saldo GL kas/bank' => [$glCashEnd, $cf['closing_balance']],
            'Kas akhir metode tidak langsung = saldo GL kas/bank' => [$glCashEnd, $cfi['closing_balance']],
        ];
        $mismatch = [];
        foreach ($checks as $label => [$expected, $actual]) {
            if (abs($expected - $actual) > 0.01) {
                $mismatch[] = "{$label}: aktual={$actual} seharusnya={$expected}";
            }
        }
        fwrite(STDERR, "[AUDIT-7] Selisih arus kas:\n  - ".implode("\n  - ", $mismatch)."\n");
        $this->assertSame([], $mismatch, 'Laporan arus kas tidak cocok dengan buku besar kas/bank');
    }
}
