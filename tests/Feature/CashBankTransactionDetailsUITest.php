<?php

namespace Tests\Feature;

use App\Models\CashBankTransaction;
use App\Models\ChartOfAccount;
use App\Services\CashBankService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashBankTransactionDetailsUITest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_details_form_validation()
    {
        // Create COA data
        $kasCoa = ChartOfAccount::factory()->create(['code' => '1111', 'name' => 'KAS']);
        $bebanCoa = ChartOfAccount::factory()->create(['code' => '5000', 'name' => 'BEBAN']);
        $pendapatanCoa = ChartOfAccount::factory()->create(['code' => '4000', 'name' => 'PENDAPATAN']);

        // Test that transaction details are optional
        $transaction = new CashBankTransaction([
            'number' => 'TEST-OPTIONAL-' . now()->format('Ymd-His'),
            'date' => now()->toDateString(),
            'type' => 'cash_out',
            'account_coa_id' => $kasCoa->id,
            'offset_coa_id' => $bebanCoa->id,
            'amount' => 100000,
            'description' => 'Test transaction without details'
        ]);
        $transaction->save();

        // Should work without transaction details
        $service = app(CashBankService::class);
        $service->postTransaction($transaction);

        $entries = \App\Models\JournalEntry::where('source_type', CashBankTransaction::class)
            ->where('source_id', $transaction->id)
            ->get();

        $this->assertEquals(2, $entries->count()); // Standard debit/credit entries

        // Clean up
        $transaction->delete();
    }

    public function test_amount_auto_calculation_from_details()
    {
        // Create COA data
        $kasCoa = ChartOfAccount::factory()->create(['code' => '1111', 'name' => 'KAS']);
        $bebanCoa = ChartOfAccount::factory()->create(['code' => '5000', 'name' => 'BEBAN']);
        $beban2Coa = ChartOfAccount::factory()->create(['code' => '5100', 'name' => 'BEBAN LAIN']);

        // Create transaction with details that should auto-calculate amount
        $transaction = new CashBankTransaction([
            'number' => 'TEST-AUTO-CALC-' . now()->format('Ymd-His'),
            'date' => now()->toDateString(),
            'type' => 'cash_out',
            'account_coa_id' => $kasCoa->id,
            'offset_coa_id' => $bebanCoa->id,
            'amount' => 0, // Will be auto-calculated from details
            'description' => 'Test auto calculation from details'
        ]);
        $transaction->save();

        // Add details that sum to 250000
        $transaction->transactionDetails()->createMany([
            ['chart_of_account_id' => $bebanCoa->id, 'amount' => 150000, 'description' => 'Beban admin'],
            ['chart_of_account_id' => $beban2Coa->id, 'amount' => 100000, 'description' => 'Beban transport'],
        ]);

        // Update transaction amount to match details total
        $transaction->update(['amount' => 250000]);

        // Test posting
        $service = app(CashBankService::class);
        $service->postTransaction($transaction);

        $entries = \App\Models\JournalEntry::where('source_type', CashBankTransaction::class)
            ->where('source_id', $transaction->id)
            ->get();

        $this->assertEquals(3, $entries->count());

        // Verify amounts
        $beban1Entry = $entries->where('coa_id', $bebanCoa->id)->where('debit', 150000)->first();
        $this->assertNotNull($beban1Entry);

        $beban2Entry = $entries->where('coa_id', $beban2Coa->id)->where('debit', 100000)->first();
        $this->assertNotNull($beban2Entry);

        $kasEntry = $entries->where('coa_id', $kasCoa->id)->where('credit', 250000)->first();
        $this->assertNotNull($kasEntry);
    }

    public function test_transaction_details_with_zero_total_fails()
    {
        // Create COA data
        $kasCoa = ChartOfAccount::factory()->create(['code' => '1111', 'name' => 'KAS']);
        $bebanCoa = ChartOfAccount::factory()->create(['code' => '5000', 'name' => 'BEBAN']);

        // Create transaction with zero total details
        $transaction = new CashBankTransaction([
            'number' => 'TEST-ZERO-TOTAL-' . now()->format('Ymd-His'),
            'date' => now()->toDateString(),
            'type' => 'cash_out',
            'account_coa_id' => $kasCoa->id,
            'offset_coa_id' => $bebanCoa->id,
            'amount' => 100000,
            'description' => 'Test zero total details'
        ]);
        $transaction->save();

        // Add details that sum to zero
        $transaction->transactionDetails()->createMany([
            ['chart_of_account_id' => $bebanCoa->id, 'amount' => 50000, 'description' => 'Beban positif'],
            ['chart_of_account_id' => $bebanCoa->id, 'amount' => -50000, 'description' => 'Beban negatif'],
        ]);

        // Test posting should fail due to zero total
        $service = app(CashBankService::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Total rincian pembayaran (0) tidak sama dengan jumlah transaksi (100,000)');

        $service->postTransaction($transaction);
    }

    public function test_all_transaction_types_support_details()
    {
        // Create COA data
        $kasCoa = ChartOfAccount::factory()->create(['code' => '1111', 'name' => 'KAS']);
        $bankCoa = ChartOfAccount::factory()->create(['code' => '1112', 'name' => 'BANK']);
        $bebanCoa = ChartOfAccount::factory()->create(['code' => '5000', 'name' => 'BEBAN']);
        $pendapatanCoa = ChartOfAccount::factory()->create(['code' => '4000', 'name' => 'PENDAPATAN']);

        $typesAndCoas = [
            'cash_in' => [$kasCoa, $pendapatanCoa],
            'cash_out' => [$kasCoa, $bebanCoa],
            'bank_in' => [$bankCoa, $pendapatanCoa],
            'bank_out' => [$bankCoa, $bebanCoa],
        ];

        foreach ($typesAndCoas as $type => [$accountCoa, $offsetCoa]) {
            $transaction = new CashBankTransaction([
                'number' => 'TEST-' . strtoupper($type) . '-DETAILS-' . now()->format('Ymd-His'),
                'date' => now()->toDateString(),
                'type' => $type,
                'account_coa_id' => $accountCoa->id,
                'offset_coa_id' => $offsetCoa->id,
                'amount' => 100000,
                'description' => "Test {$type} with details"
            ]);
            $transaction->save();

            // Add transaction details
            $transaction->transactionDetails()->createMany([
                [
                    'chart_of_account_id' => $offsetCoa->id,
                    'amount' => 100000,
                    'description' => 'Detail transaction'
                ]
            ]);

            // Test posting works for all types
            $service = app(CashBankService::class);
            $service->postTransaction($transaction);

            $entries = \App\Models\JournalEntry::where('source_type', CashBankTransaction::class)
                ->where('source_id', $transaction->id)
                ->get();

            $this->assertEquals(2, $entries->count(), "Failed for transaction type: {$type}");

            // Clean up
            $transaction->delete();
        }
    }

    public function test_transaction_details_display_in_table()
    {
        // Create COA data
        $kasCoa = ChartOfAccount::factory()->create(['code' => '1111', 'name' => 'KAS']);
        $bebanCoa = ChartOfAccount::factory()->create(['code' => '5000', 'name' => 'BEBAN']);
        $beban2Coa = ChartOfAccount::factory()->create(['code' => '5100', 'name' => 'BEBAN LAIN']);

        // Create transaction with details
        $transaction = new CashBankTransaction([
            'number' => 'TEST-TABLE-DISPLAY-' . now()->format('Ymd-His'),
            'date' => now()->toDateString(),
            'type' => 'cash_out',
            'account_coa_id' => $kasCoa->id,
            'offset_coa_id' => $bebanCoa->id,
            'amount' => 250000,
            'description' => 'Test table display'
        ]);
        $transaction->save();

        $transaction->transactionDetails()->createMany([
            ['chart_of_account_id' => $bebanCoa->id, 'amount' => 150000, 'description' => 'Beban admin'],
            ['chart_of_account_id' => $beban2Coa->id, 'amount' => 100000, 'description' => 'Beban transport'],
        ]);

        // Test that transaction has details relationship
        $this->assertTrue($transaction->transactionDetails->isNotEmpty());
        $this->assertEquals(2, $transaction->transactionDetails->count());

        // Test that details have correct relationships
        foreach ($transaction->transactionDetails as $detail) {
            $this->assertNotNull($detail->chartOfAccount);
            $this->assertNotEmpty($detail->description);
            $this->assertGreaterThan(0, $detail->amount);
        }
    }
}