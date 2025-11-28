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
}