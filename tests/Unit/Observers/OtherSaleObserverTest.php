<?php

namespace Tests\Unit\Observers;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\OtherSale;
use App\Models\User;
use App\Services\OtherSaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OtherSaleObserverTest extends TestCase
{
    // Remove RefreshDatabase for faster tests
    // use RefreshDatabase;

    protected $coa;
    protected $cabang;
    protected $user;
    protected $otherSaleService;

    protected function setUp(): void
    {
        parent::setUp();

        // Use existing data instead of creating new ones
        $this->coa = ChartOfAccount::first();
        $this->cabang = Cabang::first();
        $this->user = User::first();

        // If no existing data, skip tests
        if (!$this->coa || !$this->cabang || !$this->user) {
            $this->markTestSkipped('Required test data (COA, Cabang, User) not found in database');
        }

        $this->otherSaleService = app(OtherSaleService::class);
    }

    protected function tearDown(): void
    {
        // Clean up test data after each test
        OtherSale::where('reference_number', 'like', 'TEST-%')->delete();
        JournalEntry::where('reference', 'like', 'TEST-%')->delete();

        parent::tearDown();
    }

    #[Test]
    public function creates_journal_entries_when_other_sale_is_posted()
    {
        $referenceNumber = 'TEST-' . time() . '-001';
        $otherSale = OtherSale::create([
            'reference_number' => $referenceNumber,
            'transaction_date' => now(),
            'type' => 'service',
            'description' => 'Test Other Sale',
            'amount' => 500000,
            'coa_id' => $this->coa->id,
            'cash_bank_account_id' => null,
            'customer_id' => null,
            'cabang_id' => $this->cabang->id,
            'created_by' => $this->user->id,
            'status' => 'draft',
            'notes' => 'Test record',
        ]);

        // Post the other sale
        $this->otherSaleService->postJournalEntries($otherSale);

        // Refresh and check
        $otherSale->refresh();

        $this->assertEquals('posted', $otherSale->status);
        $this->assertCount(2, $otherSale->journalEntries);

        // Check debit entry (Accounts Receivable - money coming in)
        $debitEntry = $otherSale->journalEntries->where('debit', '>', 0)->first();
        $this->assertEquals(500000, $debitEntry->debit);
        $this->assertEquals(0, $debitEntry->credit);
        $this->assertEquals($referenceNumber, $debitEntry->reference);

        // Check credit entry (Revenue account - income)
        $creditEntry = $otherSale->journalEntries->where('credit', '>', 0)->first();
        $this->assertEquals($this->coa->id, $creditEntry->coa_id);
        $this->assertEquals(500000, $creditEntry->credit);
        $this->assertEquals(0, $creditEntry->debit);
        $this->assertEquals($referenceNumber, $creditEntry->reference);
    }

    #[Test]
    public function syncs_journal_entries_when_amount_is_updated_on_posted_other_sale()
    {
        $referenceNumber = 'TEST-' . time() . '-002';
        $otherSale = OtherSale::create([
            'reference_number' => $referenceNumber,
            'transaction_date' => now(),
            'type' => 'service',
            'description' => 'Test Other Sale Update',
            'amount' => 300000,
            'coa_id' => $this->coa->id,
            'cash_bank_account_id' => null,
            'customer_id' => null,
            'cabang_id' => $this->cabang->id,
            'created_by' => $this->user->id,
            'status' => 'draft',
            'notes' => 'Test record for update',
        ]);

        // Post the other sale
        $this->otherSaleService->postJournalEntries($otherSale);

        // Verify initial journal entries
        $this->assertCount(2, $otherSale->journalEntries);
        $initialDebit = $otherSale->journalEntries->where('debit', '>', 0)->first();
        $initialCredit = $otherSale->journalEntries->where('credit', '>', 0)->first();
        $this->assertEquals(300000, $initialDebit->debit);
        $this->assertEquals(300000, $initialCredit->credit);

        // Update the amount
        $otherSale->update(['amount' => 450000]);

        // Refresh and check journal entries are updated
        $otherSale->refresh();
        $this->assertCount(2, $otherSale->journalEntries); // Should still have 2 entries

        $updatedDebit = $otherSale->journalEntries->where('debit', '>', 0)->first();
        $updatedCredit = $otherSale->journalEntries->where('credit', '>', 0)->first();

        $this->assertEquals(450000, $updatedDebit->debit);
        $this->assertEquals(450000, $updatedCredit->credit);
        $this->assertEquals('posted', $otherSale->status);
    }

    #[Test]
    public function syncs_journal_entries_when_transaction_date_is_updated_on_posted_other_sale()
    {
        $referenceNumber = 'TEST-' . time() . '-003';
        $originalDate = now()->subDays(5);
        $otherSale = OtherSale::create([
            'reference_number' => $referenceNumber,
            'transaction_date' => $originalDate,
            'type' => 'service',
            'description' => 'Test Other Sale Date Update',
            'amount' => 200000,
            'coa_id' => $this->coa->id,
            'cash_bank_account_id' => null,
            'customer_id' => null,
            'cabang_id' => $this->cabang->id,
            'created_by' => $this->user->id,
            'status' => 'draft',
            'notes' => 'Test record for date update',
        ]);

        // Post the other sale
        $this->otherSaleService->postJournalEntries($otherSale);

        // Verify initial journal entries date
        $initialEntries = $otherSale->journalEntries;
        foreach ($initialEntries as $entry) {
            $this->assertEquals($originalDate->toDateString(), $entry->date->toDateString());
        }

        // Update the transaction date
        $newDate = now()->addDays(2);
        $otherSale->update(['transaction_date' => $newDate]);

        // Refresh and check journal entries date is updated
        $otherSale->refresh();
        $updatedEntries = $otherSale->journalEntries;

        foreach ($updatedEntries as $entry) {
            $this->assertEquals($newDate->toDateString(), $entry->date->toDateString());
        }
    }

    #[Test]
    public function deletes_journal_entries_when_other_sale_is_deleted()
    {
        $referenceNumber = 'TEST-' . time() . '-004';
        $otherSale = OtherSale::create([
            'reference_number' => $referenceNumber,
            'transaction_date' => now(),
            'type' => 'service',
            'description' => 'Test Other Sale Delete',
            'amount' => 100000,
            'coa_id' => $this->coa->id,
            'cash_bank_account_id' => null,
            'customer_id' => null,
            'cabang_id' => $this->cabang->id,
            'created_by' => $this->user->id,
            'status' => 'draft',
            'notes' => 'Test record for delete',
        ]);

        // Post the other sale
        $this->otherSaleService->postJournalEntries($otherSale);

        // Verify journal entries exist
        $this->assertCount(2, $otherSale->journalEntries);
        $journalEntryIds = $otherSale->journalEntries->pluck('id')->toArray();

        // Delete the other sale
        $otherSale->delete();

        // Check that journal entries are deleted
        foreach ($journalEntryIds as $id) {
            $this->assertNull(JournalEntry::find($id));
        }

        // Verify no journal entries remain for this source
        $remainingEntries = JournalEntry::where('source_type', OtherSale::class)
            ->where('source_id', $otherSale->id)
            ->count();
        $this->assertEquals(0, $remainingEntries);
    }

    #[Test]
    public function does_not_create_journal_entries_for_draft_other_sale()
    {
        $referenceNumber = 'TEST-' . time() . '-005';
        $otherSale = OtherSale::create([
            'reference_number' => $referenceNumber,
            'transaction_date' => now(),
            'type' => 'service',
            'description' => 'Test Draft Other Sale',
            'amount' => 150000,
            'coa_id' => $this->coa->id,
            'cash_bank_account_id' => null,
            'customer_id' => null,
            'cabang_id' => $this->cabang->id,
            'created_by' => $this->user->id,
            'status' => 'draft',
            'notes' => 'Test draft record',
        ]);

        // Update amount on draft (should not trigger journal creation)
        $otherSale->update(['amount' => 200000]);

        // Check no journal entries created
        $this->assertCount(0, $otherSale->journalEntries);
        $this->assertEquals('draft', $otherSale->status);
    }

    #[Test]
    public function handles_multiple_other_sale_updates_correctly()
    {
        $referenceNumber = 'TEST-' . time() . '-006';
        $otherSale = OtherSale::create([
            'reference_number' => $referenceNumber,
            'transaction_date' => now(),
            'type' => 'service',
            'description' => 'Test Multiple Updates',
            'amount' => 100000,
            'coa_id' => $this->coa->id,
            'cash_bank_account_id' => null,
            'customer_id' => null,
            'cabang_id' => $this->cabang->id,
            'created_by' => $this->user->id,
            'status' => 'draft',
            'notes' => 'Test multiple updates',
        ]);

        // Post the other sale
        $this->otherSaleService->postJournalEntries($otherSale);

        // First update - amount
        $otherSale->update(['amount' => 150000]);
        $otherSale->refresh();
        $this->assertEquals(150000, $otherSale->journalEntries->sum('debit'));

        // Second update - date
        $newDate = now()->addDays(1);
        $otherSale->update(['transaction_date' => $newDate]);
        $otherSale->refresh();
        $this->assertEquals($newDate->toDateString(), $otherSale->journalEntries->first()->date->toDateString());

        // Third update - amount again
        $otherSale->update(['amount' => 200000]);
        $otherSale->refresh();
        $this->assertEquals(200000, $otherSale->journalEntries->sum('debit'));

        // Verify final state
        $this->assertCount(2, $otherSale->journalEntries);
        $this->assertEquals('posted', $otherSale->status);
    }
}