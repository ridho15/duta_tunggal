<?php

namespace Tests\Feature;

use App\Models\CashBankTransaction;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\CashBankService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashBankTransactionPostingTest extends TestCase
{
    use RefreshDatabase;

    public function test_multiple_coa_posting_action()
    {
        // Create COA data
        $kasCoa = ChartOfAccount::factory()->create(['code' => '1111', 'name' => 'KAS']);
        $bebanCoa = ChartOfAccount::factory()->create(['code' => '5000', 'name' => 'BEBAN']);
        $beban2Coa = ChartOfAccount::factory()->create(['code' => '5100', 'name' => 'BEBAN LAIN']);

        // Create transaction with multiple COA breakdown
        $transaction = new CashBankTransaction([
            'number' => 'TEST-CB-' . now()->format('Ymd-His'),
            'date' => now()->toDateString(),
            'type' => 'cash_out',
            'account_coa_id' => $kasCoa->id,
            'offset_coa_id' => $bebanCoa->id,
            'amount' => 150000,
            'description' => 'Test transaction with multiple COA breakdown'
        ]);
        $transaction->save();

        $transaction->transactionDetails()->createMany([
            ['chart_of_account_id' => $bebanCoa->id, 'amount' => 100000, 'description' => 'Beban admin'],
            ['chart_of_account_id' => $beban2Coa->id, 'amount' => 50000, 'description' => 'Beban transport'],
        ]);

        // Test posting service
        $service = app(CashBankService::class);
        $service->postTransaction($transaction);

        // Assert journal entries created
        $entries = JournalEntry::where('source_type', CashBankTransaction::class)
            ->where('source_id', $transaction->id)
            ->where('journal_type', 'cashbank')
            ->get();

        $this->assertEquals(3, $entries->count());

        // Check debit entries (beban accounts)
        $beban1Entry = $entries->where('coa_id', $bebanCoa->id)->where('debit', 100000)->first();
        $this->assertNotNull($beban1Entry);

        $beban2Entry = $entries->where('coa_id', $beban2Coa->id)->where('debit', 50000)->first();
        $this->assertNotNull($beban2Entry);

        // Check credit entry (kas account)
        $kasEntry = $entries->where('coa_id', $kasCoa->id)->where('credit', 150000)->first();
        $this->assertNotNull($kasEntry);
    }

    public function test_posting_validation_fails_with_wrong_total()
    {
        // Create COA data
        $kasCoa = ChartOfAccount::factory()->create(['code' => '1111', 'name' => 'KAS']);
        $bebanCoa = ChartOfAccount::factory()->create(['code' => '5000', 'name' => 'BEBAN']);

        // Create transaction with wrong total breakdown
        $transaction = new CashBankTransaction([
            'number' => 'TEST-CB-VALIDATION-' . now()->format('Ymd-His'),
            'date' => now()->toDateString(),
            'type' => 'cash_out',
            'account_coa_id' => $kasCoa->id,
            'offset_coa_id' => $bebanCoa->id,
            'amount' => 150000,
            'description' => 'Test transaction with wrong breakdown total'
        ]);
        $transaction->save();

        $transaction->transactionDetails()->createMany([
            ['chart_of_account_id' => $bebanCoa->id, 'amount' => 100000, 'description' => 'Beban admin'],
            ['chart_of_account_id' => $bebanCoa->id, 'amount' => 30000, 'description' => 'Beban lain'], // Total 130k, not 150k
        ]);

        // Test posting should fail
        $service = app(CashBankService::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Total rincian pembayaran (130,000) tidak sama dengan jumlah transaksi (150,000)');

        $service->postTransaction($transaction);
    }

    public function test_cash_in_transaction_with_income_breakdown()
    {
        // Create COA data
        $kasCoa = ChartOfAccount::factory()->create(['code' => '1111', 'name' => 'KAS']);
        $pendapatanCoa = ChartOfAccount::factory()->create(['code' => '4000', 'name' => 'PENDAPATAN JASA']);
        $pendapatan2Coa = ChartOfAccount::factory()->create(['code' => '4100', 'name' => 'PENDAPATAN LAIN']);

        // Create cash in transaction with income breakdown
        $transaction = new CashBankTransaction([
            'number' => 'TEST-CASH-IN-' . now()->format('Ymd-His'),
            'date' => now()->toDateString(),
            'type' => 'cash_in',
            'account_coa_id' => $kasCoa->id,
            'offset_coa_id' => $pendapatanCoa->id,
            'amount' => 200000,
            'description' => 'Test cash in transaction with income breakdown'
        ]);
        $transaction->save();

        $transaction->transactionDetails()->createMany([
            ['chart_of_account_id' => $pendapatanCoa->id, 'amount' => 150000, 'description' => 'Pendapatan jasa konsultasi'],
            ['chart_of_account_id' => $pendapatan2Coa->id, 'amount' => 50000, 'description' => 'Pendapatan bunga bank'],
        ]);

        // Test posting service
        $service = app(CashBankService::class);
        $service->postTransaction($transaction);

        // Assert journal entries created
        $entries = JournalEntry::where('source_type', CashBankTransaction::class)
            ->where('source_id', $transaction->id)
            ->where('journal_type', 'cashbank')
            ->get();

        $this->assertEquals(3, $entries->count());

        // Check debit entry (kas account)
        $kasEntry = $entries->where('coa_id', $kasCoa->id)->where('debit', 200000)->first();
        $this->assertNotNull($kasEntry);

        // Check credit entries (income accounts)
        $pendapatan1Entry = $entries->where('coa_id', $pendapatanCoa->id)->where('credit', 150000)->first();
        $this->assertNotNull($pendapatan1Entry);

        $pendapatan2Entry = $entries->where('coa_id', $pendapatan2Coa->id)->where('credit', 50000)->first();
        $this->assertNotNull($pendapatan2Entry);
    }

    public function test_transaction_with_negative_amounts_for_tax_reductions()
    {
        // Create COA data
        $kasCoa = ChartOfAccount::factory()->create(['code' => '1111', 'name' => 'KAS']);
        $pendapatanCoa = ChartOfAccount::factory()->create(['code' => '4000', 'name' => 'PENDAPATAN JASA']);
        $pajakCoa = ChartOfAccount::factory()->create(['code' => '2000', 'name' => 'PAJAK PENGHASILAN']);

        // Create transaction with negative amounts (tax reductions)
        $transaction = new CashBankTransaction([
            'number' => 'TEST-NEGATIVE-' . now()->format('Ymd-His'),
            'date' => now()->toDateString(),
            'type' => 'cash_in',
            'account_coa_id' => $kasCoa->id,
            'offset_coa_id' => $pendapatanCoa->id,
            'amount' => 180000, // Net amount after tax
            'description' => 'Test transaction with tax reductions'
        ]);
        $transaction->save();

        $transaction->transactionDetails()->createMany([
            ['chart_of_account_id' => $pendapatanCoa->id, 'amount' => 200000, 'description' => 'Pendapatan bruto'],
            ['chart_of_account_id' => $pajakCoa->id, 'amount' => -20000, 'description' => 'Pemotongan PPh 21'], // Negative for tax reduction
        ]);

        // Test posting service
        $service = app(CashBankService::class);
        $service->postTransaction($transaction);

        // Assert journal entries created
        $entries = JournalEntry::where('source_type', CashBankTransaction::class)
            ->where('source_id', $transaction->id)
            ->where('journal_type', 'cashbank')
            ->get();

        $this->assertEquals(3, $entries->count());

        // Check debit entry (kas account)
        $kasEntry = $entries->where('coa_id', $kasCoa->id)->where('debit', 180000)->first();
        $this->assertNotNull($kasEntry);

        // Check credit entries (income and tax reduction)
        $pendapatanEntry = $entries->where('coa_id', $pendapatanCoa->id)->where('credit', 200000)->first();
        $this->assertNotNull($pendapatanEntry);

        // For negative amount (tax reduction), it should debit the tax account (reducing income)
        $pajakEntry = $entries->where('coa_id', $pajakCoa->id)->where('debit', 20000)->first();
        $this->assertNotNull($pajakEntry);
    }

    public function test_bank_transaction_with_details_available_for_all_types()
    {
        // Test that transaction details work for bank_in, bank_out, cash_in, cash_out
        $kasCoa = ChartOfAccount::factory()->create(['code' => '1111', 'name' => 'KAS']);
        $bankCoa = ChartOfAccount::factory()->create(['code' => '1112', 'name' => 'BANK']);
        $bebanCoa = ChartOfAccount::factory()->create(['code' => '5000', 'name' => 'BEBAN']);
        $pendapatanCoa = ChartOfAccount::factory()->create(['code' => '4000', 'name' => 'PENDAPATAN']);

        $transactionTypes = ['cash_in', 'cash_out', 'bank_in', 'bank_out'];

        foreach ($transactionTypes as $type) {
            $transaction = new CashBankTransaction([
                'number' => 'TEST-' . strtoupper($type) . '-' . now()->format('Ymd-His'),
                'date' => now()->toDateString(),
                'type' => $type,
                'account_coa_id' => in_array($type, ['cash_in', 'cash_out']) ? $kasCoa->id : $bankCoa->id,
                'offset_coa_id' => in_array($type, ['cash_in', 'bank_in']) ? $pendapatanCoa->id : $bebanCoa->id,
                'amount' => 100000,
                'description' => "Test {$type} with transaction details"
            ]);
            $transaction->save();

            $transaction->transactionDetails()->createMany([
                [
                    'chart_of_account_id' => in_array($type, ['cash_in', 'bank_in']) ? $pendapatanCoa->id : $bebanCoa->id,
                    'amount' => 100000,
                    'description' => 'Detail transaction'
                ]
            ]);

            // Test posting service
            $service = app(CashBankService::class);
            $service->postTransaction($transaction);

            // Assert journal entries created
            $entries = JournalEntry::where('source_type', CashBankTransaction::class)
                ->where('source_id', $transaction->id)
                ->where('journal_type', 'cashbank')
                ->get();

            $this->assertEquals(2, $entries->count(), "Failed for transaction type: {$type}");

            // Clean up for next iteration
            $transaction->delete();
        }
    }

    public function test_transaction_details_with_ntpn_for_tax_transactions()
    {
        // Create COA data
        $kasCoa = ChartOfAccount::factory()->create(['code' => '1111', 'name' => 'KAS']);
        $bebanCoa = ChartOfAccount::factory()->create(['code' => '5000', 'name' => 'BEBAN']);
        $pajakCoa = ChartOfAccount::factory()->create(['code' => '2000', 'name' => 'PAJAK PENGHASILAN']);

        // Create transaction with NTPN for tax payment
        $transaction = new CashBankTransaction([
            'number' => 'TEST-NTPN-' . now()->format('Ymd-His'),
            'date' => now()->toDateString(),
            'type' => 'cash_out',
            'account_coa_id' => $kasCoa->id,
            'offset_coa_id' => $bebanCoa->id,
            'amount' => 150000,
            'description' => 'Test transaction with NTPN'
        ]);
        $transaction->save();

        $transaction->transactionDetails()->createMany([
            ['chart_of_account_id' => $bebanCoa->id, 'amount' => 100000, 'description' => 'Beban operasional'],
            ['chart_of_account_id' => $pajakCoa->id, 'amount' => 50000, 'description' => 'PPh 22 Import', 'ntpn' => 'NTPN20241208001234'],
        ]);

        // Test posting service
        $service = app(CashBankService::class);
        $service->postTransaction($transaction);

        // Assert journal entries created
        $entries = JournalEntry::where('source_type', CashBankTransaction::class)
            ->where('source_id', $transaction->id)
            ->where('journal_type', 'cashbank')
            ->get();

        $this->assertEquals(3, $entries->count());

        // Verify NTPN is stored in transaction detail
        $taxDetail = $transaction->transactionDetails()->where('ntpn', 'NTPN20241208001234')->first();
        $this->assertNotNull($taxDetail);
        $this->assertEquals('NTPN20241208001234', $taxDetail->ntpn);
    }
}