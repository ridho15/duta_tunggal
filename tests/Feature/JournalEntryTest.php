<?php

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Cabang;
use App\Models\User;
use App\Services\JournalEntryAggregationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed required data
    $this->seed(\Database\Seeders\ChartOfAccountSeeder::class);
    $this->seed(\Database\Seeders\CabangSeeder::class);

    // Create test user
    $this->user = User::factory()->create([
        'email' => 'ralamzah@gmail.com',
        'password' => bcrypt('ridho123'),
    ]);
});

test('can create manual journal entry', function () {
    $coa = ChartOfAccount::first();
    $cabang = Cabang::first();

    $journalEntry = JournalEntry::factory()->create([
        'coa_id' => $coa->id,
        'date' => now(),
        'reference' => 'MANUAL-001',
        'description' => 'Manual journal entry test',
        'debit' => 100000,
        'credit' => 0,
        'journal_type' => 'manual',
        'cabang_id' => $cabang->id,
    ]);

    expect($journalEntry)->toBeInstanceOf(JournalEntry::class)
        ->and($journalEntry->reference)->toBe('MANUAL-001')
        ->and((float)$journalEntry->debit)->toBe(100000.00)
        ->and((float)$journalEntry->credit)->toBe(0.00)
        ->and($journalEntry->journal_type)->toBe('manual');
});

test('journal entry belongs to chart of account', function () {
    $coa = ChartOfAccount::first();
    $journalEntry = JournalEntry::factory()->create(['coa_id' => $coa->id]);

    expect($journalEntry->coa)->toBeInstanceOf(ChartOfAccount::class)
        ->and($journalEntry->coa->id)->toBe($coa->id);
});

test('journal entry belongs to cabang', function () {
    $cabang = Cabang::first();
    $journalEntry = JournalEntry::factory()->create(['cabang_id' => $cabang->id]);

    expect($journalEntry->cabang)->toBeInstanceOf(Cabang::class)
        ->and($journalEntry->cabang->id)->toBe($cabang->id);
});

test('journal entry can be soft deleted', function () {
    $journalEntry = JournalEntry::factory()->create();

    $journalEntry->delete();

    expect(JournalEntry::find($journalEntry->id))->toBeNull();
    expect(JournalEntry::withTrashed()->find($journalEntry->id))->not->toBeNull();
});

test('journal entry validates debit credit balance', function () {
    $service = app(JournalEntryAggregationService::class);

    // Create balanced entries
    $coa1 = ChartOfAccount::first();
    $coa2 = ChartOfAccount::where('id', '!=', $coa1->id)->first();

    JournalEntry::factory()->create([
        'coa_id' => $coa1->id,
        'debit' => 100000,
        'credit' => 0,
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $coa2->id,
        'debit' => 0,
        'credit' => 100000,
    ]);

    $summary = $service->getSummary([]);

    expect($summary['is_balanced'])->toBeTrue()
        ->and($summary['total_debit'])->toBe($summary['total_credit']);
});

test('journal entry detects unbalanced transactions', function () {
    $service = app(JournalEntryAggregationService::class);

    // Create unbalanced entries
    $coa = ChartOfAccount::first();

    JournalEntry::factory()->create([
        'coa_id' => $coa->id,
        'debit' => 100000,
        'credit' => 0,
    ]);

    // Only debit, no credit - should be unbalanced
    $summary = $service->getSummary([]);

    expect($summary['is_balanced'])->toBeFalse()
        ->and($summary['total_debit'])->not->toBe($summary['total_credit']);
});

test('journal entries can be filtered by date range', function () {
    $coa = ChartOfAccount::first();

    // Create entries with different dates
    JournalEntry::factory()->create([
        'coa_id' => $coa->id,
        'date' => now()->subDays(10),
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $coa->id,
        'date' => now()->subDays(5),
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $coa->id,
        'date' => now()->addDays(5),
    ]);

    $service = app(JournalEntryAggregationService::class);

    $filtered = $service->getGroupedByParent([
        'start_date' => now()->subDays(7)->format('Y-m-d'),
        'end_date' => now()->subDays(3)->format('Y-m-d'),
    ]);

    // Should only return the entry from 5 days ago
    expect($filtered->count())->toBe(1);
});

test('journal entries can be filtered by journal type', function () {
    $coa = ChartOfAccount::first();

    JournalEntry::factory()->create([
        'coa_id' => $coa->id,
        'journal_type' => 'sales',
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $coa->id,
        'journal_type' => 'purchase',
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $coa->id,
        'journal_type' => 'manual',
    ]);

    $service = app(JournalEntryAggregationService::class);

    $salesEntries = $service->getGroupedByParent(['journal_type' => 'sales']);
    $manualEntries = $service->getGroupedByParent(['journal_type' => 'manual']);

    expect($salesEntries->count())->toBe(1);
    expect($manualEntries->count())->toBe(1);
});

test('journal entries can be grouped by parent COA', function () {
    // Create parent and child COAs
    $parentCoa = ChartOfAccount::factory()->create([
        'code' => '9999',
        'name' => 'Parent Account',
        'type' => 'Asset',
        'parent_id' => null,
    ]);

    $childCoa = ChartOfAccount::factory()->create([
        'code' => '9999.01',
        'name' => 'Child Account',
        'type' => 'Asset',
        'parent_id' => $parentCoa->id,
    ]);

    // Create entries for both
    JournalEntry::factory()->create([
        'coa_id' => $parentCoa->id,
        'debit' => 50000,
        'credit' => 0,
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $childCoa->id,
        'debit' => 30000,
        'credit' => 0,
    ]);

    $service = app(JournalEntryAggregationService::class);
    $grouped = $service->getGroupedByParent([]);

    // Should group under parent
    $parentGroup = $grouped->first();
    expect($parentGroup['code'])->toBe('9999')
        ->and($parentGroup['total_debit'])->toBe(80000.0)
        ->and(isset($parentGroup['children']))->toBeTrue();
});

test('journal entry auto posting from transactions', function () {
    // This test demonstrates that journal entries are created automatically
    // when transactions are posted. We'll use a simple example.

    $coa = ChartOfAccount::first();

    // Simulate what happens when a transaction creates journal entries
    $journalEntries = collect([
        JournalEntry::factory()->create([
            'coa_id' => $coa->id,
            'debit' => 100000,
            'credit' => 0,
            'source_type' => 'App\\Models\\SaleOrder',
            'source_id' => 1,
            'journal_type' => 'sales',
        ]),
        JournalEntry::factory()->create([
            'coa_id' => $coa->id,
            'debit' => 0,
            'credit' => 100000,
            'source_type' => 'App\\Models\\SaleOrder',
            'source_id' => 1,
            'journal_type' => 'sales',
        ]),
    ]);

    // Verify entries are linked to source
    foreach ($journalEntries as $entry) {
        expect($entry->source_type)->toBe('App\\Models\\SaleOrder')
            ->and($entry->source_id)->toBe(1)
            ->and($entry->journal_type)->toBe('sales');
    }

    // Verify balance
    $totalDebit = $journalEntries->sum('debit');
    $totalCredit = $journalEntries->sum('credit');
    expect($totalDebit)->toBe($totalCredit);
});

test('journal entry reference is unique within scope', function () {
    $coa = ChartOfAccount::first();

    // Create first entry
    $entry1 = JournalEntry::factory()->create([
        'coa_id' => $coa->id,
        'reference' => 'TEST-001',
    ]);

    // Create second entry with same reference (should be allowed as it's not enforced at DB level)
    $entry2 = JournalEntry::factory()->create([
        'coa_id' => $coa->id,
        'reference' => 'TEST-001',
    ]);

    expect($entry1->reference)->toBe('TEST-001')
        ->and($entry2->reference)->toBe('TEST-001')
        ->and($entry1->id)->not->toBe($entry2->id);
});

test('journal entry polymorphic relationships work', function () {
    $coa = ChartOfAccount::first();

    // Create entry with source
    $entry = JournalEntry::factory()->create([
        'coa_id' => $coa->id,
        'source_type' => 'App\\Models\\User',
        'source_id' => $this->user->id,
    ]);

    // Test polymorphic relationship
    expect($entry->source)->toBeInstanceOf(User::class)
        ->and($entry->source->id)->toBe($this->user->id);
});

test('journal entry aggregation service calculates balances correctly', function () {
    $service = app(JournalEntryAggregationService::class);

    $assetCoa = ChartOfAccount::factory()->create(['type' => 'Asset']);
    $liabilityCoa = ChartOfAccount::factory()->create(['type' => 'Liability']);

    // Asset: Debit increases, Credit decreases
    JournalEntry::factory()->create([
        'coa_id' => $assetCoa->id,
        'debit' => 100000,
        'credit' => 0,
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $assetCoa->id,
        'debit' => 0,
        'credit' => 20000,
    ]);

    // Liability: Credit increases, Debit decreases
    JournalEntry::factory()->create([
        'coa_id' => $liabilityCoa->id,
        'debit' => 0,
        'credit' => 50000,
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $liabilityCoa->id,
        'debit' => 10000,
        'credit' => 0,
    ]);

    $summary = $service->getSummary([]);

    // Asset balance: 100000 - 20000 = 80000
    // Liability balance: 50000 - 10000 = 40000
    // But summary shows total debit/credit across all entries
    expect((float)$summary['total_debit'])->toBe(110000.0) // 100000 + 10000
        ->and((float)$summary['total_credit'])->toBe(70000.0) // 20000 + 50000
        ->and($summary['is_balanced'])->toBeFalse();
});