<?php

namespace Tests\Unit\Observers;

use App\Models\CashBankTransfer;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\CashBankService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CashBankTransferObserverTest extends TestCase
{
    // Remove RefreshDatabase for faster tests
    // use RefreshDatabase;

    protected $fromCoa;
    protected $toCoa;
    protected $cashBankService;

    protected function setUp(): void
    {
        parent::setUp();

        // Only create COAs once per test class, not per test method
        if (!$this->fromCoa) {
            $this->fromCoa = ChartOfAccount::firstOrCreate([
                'code' => '1111.01'
            ], [
                'name' => 'Kas Besar',
                'type' => 'asset',
                'level' => 3,
                'is_active' => true,
            ]);

            $this->toCoa = ChartOfAccount::firstOrCreate([
                'code' => '1111.02'
            ], [
                'name' => 'Kas Kecil',
                'type' => 'asset',
                'level' => 3,
                'is_active' => true,
            ]);
        }

        $this->cashBankService = app(CashBankService::class);
    }

    protected function tearDown(): void
    {
        // Clean up test data after each test
        CashBankTransfer::where('number', 'like', 'TEST-%')->delete();
        JournalEntry::where('reference', 'like', 'TEST-%')->delete();
        
        parent::tearDown();
    }

    #[Test]
    public function creates_journal_entries_when_transfer_is_posted()
    {
        $transferNumber = 'TEST-' . time() . '-001';
        $transfer = CashBankTransfer::create([
            'number' => $transferNumber,
            'date' => now()->toDateString(),
            'from_coa_id' => $this->fromCoa->id,
            'to_coa_id' => $this->toCoa->id,
            'amount' => 1000000,
            'description' => 'Test transfer',
            'status' => 'draft',
        ]);

        // Post the transfer
        $this->cashBankService->postTransfer($transfer);

        // Refresh and check
        $transfer->refresh();

        $this->assertEquals('posted', $transfer->status);
        $this->assertCount(2, $transfer->journalEntries);

        // Check credit entry (from account - money going out)
        $creditEntry = $transfer->journalEntries->where('credit', '>', 0)->first();
        $this->assertEquals($this->fromCoa->id, $creditEntry->coa_id);
        $this->assertEquals(1000000, $creditEntry->credit);

        // Check debit entry (to account - money coming in)
        $debitEntry = $transfer->journalEntries->where('debit', '>', 0)->first();
        $this->assertEquals($this->toCoa->id, $debitEntry->coa_id);
        $this->assertEquals(1000000, $debitEntry->debit);
    }

    #[Test]
    public function syncs_journal_entries_when_amount_is_updated_on_posted_transfer()
    {
        $transferNumber = 'TEST-' . time() . '-002';
        $transfer = CashBankTransfer::create([
            'number' => $transferNumber,
            'date' => now()->toDateString(),
            'from_coa_id' => $this->fromCoa->id,
            'to_coa_id' => $this->toCoa->id,
            'amount' => 1000000,
            'description' => 'Test transfer',
            'status' => 'draft',
        ]);

        // Post the transfer
        $this->cashBankService->postTransfer($transfer);
        $originalJournalCount = $transfer->journalEntries()->count();

        // Update amount
        $transfer->update(['amount' => 2000000]);

        // Refresh and check
        $transfer->refresh();

        // Should still have same number of journal entries
        $this->assertCount($originalJournalCount, $transfer->journalEntries);

        // Check updated amounts
        $debitEntry = $transfer->journalEntries->where('debit', '>', 0)->first();
        $this->assertEquals(2000000, $debitEntry->debit);

        $creditEntry = $transfer->journalEntries->where('credit', '>', 0)->first();
        $this->assertEquals(2000000, $creditEntry->credit);
    }

    #[Test]
    public function syncs_journal_entries_when_from_coa_is_updated_on_posted_transfer()
    {
        $transferNumber = 'TEST-' . time() . '-003';
        $newFromCoa = ChartOfAccount::create([
            'code' => '1111.03',
            'name' => 'Kas Operasional',
            'type' => 'asset',
            'level' => 3,
            'is_active' => true,
        ]);

        $transfer = CashBankTransfer::create([
            'number' => $transferNumber,
            'date' => now()->toDateString(),
            'from_coa_id' => $this->fromCoa->id,
            'to_coa_id' => $this->toCoa->id,
            'amount' => 1000000,
            'description' => 'Test transfer',
            'status' => 'draft',
        ]);

        // Post the transfer
        $this->cashBankService->postTransfer($transfer);

        // Update from_coa_id
        $transfer->update(['from_coa_id' => $newFromCoa->id]);

        // Refresh and check
        $transfer->refresh();

        // Check debit entry updated to new COA
        $debitEntry = $transfer->journalEntries->where('type', 'debit')->first();
        $this->assertEquals($newFromCoa->id, $debitEntry->coa_id);
        $this->assertEquals(1000000, $debitEntry->amount);

        // Credit entry should remain the same
        $creditEntry = $transfer->journalEntries->where('type', 'credit')->first();
        $this->assertEquals($this->toCoa->id, $creditEntry->coa_id);
        $this->assertEquals(1000000, $creditEntry->amount);
    }

    #[Test]
    public function syncs_journal_entries_when_to_coa_is_updated_on_posted_transfer()
    {
        $transferNumber = 'TEST-' . time() . '-004';
        $newToCoa = ChartOfAccount::create([
            'code' => '1111.04',
            'name' => 'Kas Marketing',
            'type' => 'asset',
            'level' => 3,
            'is_active' => true,
        ]);

        $transfer = CashBankTransfer::create([
            'number' => $transferNumber,
            'date' => now()->toDateString(),
            'from_coa_id' => $this->fromCoa->id,
            'to_coa_id' => $this->toCoa->id,
            'amount' => 1000000,
            'description' => 'Test transfer',
            'status' => 'draft',
        ]);

        // Post the transfer
        $this->cashBankService->postTransfer($transfer);

        // Update to_coa_id
        $transfer->update(['to_coa_id' => $newToCoa->id]);

        // Refresh and check
        $transfer->refresh();

        // Debit entry should remain the same
        $debitEntry = $transfer->journalEntries->where('type', 'debit')->first();
        $this->assertEquals($this->fromCoa->id, $debitEntry->coa_id);
        $this->assertEquals(1000000, $debitEntry->amount);

        // Check credit entry updated to new COA
        $creditEntry = $transfer->journalEntries->where('type', 'credit')->first();
        $this->assertEquals($newToCoa->id, $creditEntry->coa_id);
        $this->assertEquals(1000000, $creditEntry->amount);
    }

    #[Test]
    public function does_not_sync_journal_entries_when_non_critical_fields_are_updated()
    {
        $transferNumber = 'TEST-' . time() . '-005';
        $transfer = CashBankTransfer::create([
            'number' => $transferNumber,
            'date' => now()->toDateString(),
            'from_coa_id' => $this->fromCoa->id,
            'to_coa_id' => $this->toCoa->id,
            'amount' => 1000000,
            'description' => 'Test transfer',
            'status' => 'draft',
        ]);

        // Post the transfer
        $this->cashBankService->postTransfer($transfer);

        $originalJournalEntries = $transfer->journalEntries->toArray();

        // Update non-critical field
        $transfer->update(['description' => 'Updated description']);

        // Refresh and check
        $transfer->refresh();

        // Journal entries should remain unchanged
        $this->assertEquals($originalJournalEntries, $transfer->journalEntries->toArray());
    }

    #[Test]
    public function handles_other_fee_in_journal_entries()
    {
        $transferNumber = 'TEST-' . time() . '-006';
        $transfer = CashBankTransfer::create([
            'number' => $transferNumber,
            'date' => now()->toDateString(),
            'from_coa_id' => $this->fromCoa->id,
            'to_coa_id' => $this->toCoa->id,
            'amount' => 1000000,
            'other_fee' => 50000,
            'description' => 'Test transfer with fee',
            'status' => 'draft',
        ]);

        // Post the transfer
        $this->cashBankService->postTransfer($transfer);

        // Refresh and check
        $transfer->refresh();

        $this->assertEquals('posted', $transfer->status);

        // Should have 3 journal entries: debit from, credit to, debit fee
        $this->assertCount(3, $transfer->journalEntries);

        // Check debit entry (from account) - amount + fee
        $debitEntry = $transfer->journalEntries->where('type', 'debit')->where('coa_id', $this->fromCoa->id)->first();
        $this->assertEquals(1050000, $debitEntry->amount);

        // Check credit entry (to account)
        $creditEntry = $transfer->journalEntries->where('type', 'credit')->first();
        $this->assertEquals($this->toCoa->id, $creditEntry->coa_id);
        $this->assertEquals(1000000, $creditEntry->amount);

        // Check fee entry (assuming fee is debited to same account)
        $feeEntry = $transfer->journalEntries->where('type', 'debit')->where('coa_id', '!=', $this->fromCoa->id)->first();
        $this->assertEquals(50000, $feeEntry->amount);
    }
}