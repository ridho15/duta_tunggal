<?php

/**
 * GroupedJournalEntriesPageTest
 *
 * Verifies the data accuracy and UI behaviour of the
 * /admin/journal-entries/grouped page (GroupedJournalEntries Livewire component).
 *
 * Tests cover:
 *  - Page mounts without errors
 *  - Summary statistics (total_entries, total_debit, total_credit, is_balanced)
 *  - Balance calculation per account type (Asset, Liability, Equity, Revenue, Expense)
 *  - Correct grouping by parent COA
 *  - Filter by date range (start_date / end_date)
 *  - Filter by journal_type
 *  - Reset filters restores full dataset
 */

use App\Filament\Resources\JournalEntryResource\Pages\GroupedJournalEntries;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\JournalEntryAggregationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function makeAdmin(): User
{
    $user = User::factory()->create(['email' => 'admin@test.com']);
    if (class_exists(\Spatie\Permission\Models\Role::class)) {
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Super Admin']);
        $user->assignRole($role);
    }
    return $user;
}

function makeParentCoa(string $type = 'Asset', string $code = '1-0000'): ChartOfAccount
{
    return ChartOfAccount::factory()->create([
        'code'      => $code,
        'name'      => "Parent COA {$code}",
        'type'      => $type,
        'parent_id' => null,
    ]);
}

function makeChildCoa(ChartOfAccount $parent, string $code): ChartOfAccount
{
    return ChartOfAccount::factory()->create([
        'code'      => $code,
        'name'      => "Child COA {$code}",
        'type'      => $parent->type,
        'parent_id' => $parent->id,
    ]);
}

// ──────────────────────────────────────────────────────────────────────────────
// Page mount & access
// ──────────────────────────────────────────────────────────────────────────────

test('page mounts successfully for authenticated user', function () {
    $user = makeAdmin();
    $this->actingAs($user);

    Livewire::test(GroupedJournalEntries::class)
        ->assertOk();
});

test('page shows filter labels after form is enabled', function () {
    $user = makeAdmin();
    $this->actingAs($user);

    Livewire::test(GroupedJournalEntries::class)
        ->assertOk()
        ->assertSee('Start Date')
        ->assertSee('End Date')
        ->assertSee('Journal Type')
        ->assertSee('Branch');
});

test('page shows grouped view heading', function () {
    $user = makeAdmin();
    $this->actingAs($user);

    Livewire::test(GroupedJournalEntries::class)
        ->assertSee('Journal Entries');
});

// ──────────────────────────────────────────────────────────────────────────────
// Summary statistics correctness (via service, also reflected in page)
// ──────────────────────────────────────────────────────────────────────────────

test('summary returns zero totals when no journal entries exist', function () {
    $service = new JournalEntryAggregationService();
    $summary = $service->getSummary();

    expect($summary['total_entries'])->toBe(0)
        ->and($summary['total_debit'])->toEqual(0)
        ->and($summary['total_credit'])->toEqual(0)
        ->and($summary['is_balanced'])->toBeTrue();
});

test('summary counts entries and sums debit/credit correctly', function () {
    $coa = makeParentCoa('Asset', '1-1000');

    JournalEntry::factory()->create(['coa_id' => $coa->id, 'debit' => 500_000,  'credit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $coa->id, 'debit' => 300_000,  'credit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $coa->id, 'debit' => 0,        'credit' => 800_000]);

    $service = new JournalEntryAggregationService();
    $summary = $service->getSummary();

    expect($summary['total_entries'])->toBe(3)
        ->and((float) $summary['total_debit'])->toEqual(800_000.0)
        ->and((float) $summary['total_credit'])->toEqual(800_000.0)
        ->and($summary['is_balanced'])->toBeTrue();
});

test('summary detects unbalanced entries', function () {
    $coa = makeParentCoa('Asset', '1-2000');

    JournalEntry::factory()->create(['coa_id' => $coa->id, 'debit' => 1_000_000, 'credit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $coa->id, 'debit' => 0,         'credit' => 600_000]);

    $service = new JournalEntryAggregationService();
    $summary = $service->getSummary();

    expect($summary['is_balanced'])->toBeFalse()
        ->and((float) $summary['net_balance'])->toEqual(400_000.0);
});

// ──────────────────────────────────────────────────────────────────────────────
// Balance calculation per account type
// ──────────────────────────────────────────────────────────────────────────────

test('asset account balance = debit minus credit', function () {
    $parent = makeParentCoa('Asset', '1-3000');
    $child  = makeChildCoa($parent, '1-3001');

    JournalEntry::factory()->create(['coa_id' => $child->id, 'debit' => 700_000, 'credit' => 200_000]);

    $service = new JournalEntryAggregationService();
    $grouped = $service->getGroupedByParent();

    expect((float) $grouped->first()['balance'])->toEqual(500_000.0);
});

test('liability account balance = credit minus debit', function () {
    $parent = makeParentCoa('Liability', '2-1000');
    $child  = makeChildCoa($parent, '2-1001');

    JournalEntry::factory()->create(['coa_id' => $child->id, 'debit' => 100_000, 'credit' => 900_000]);

    $service = new JournalEntryAggregationService();
    $grouped = $service->getGroupedByParent();

    expect((float) $grouped->first()['balance'])->toEqual(800_000.0);
});

test('equity account balance = credit minus debit', function () {
    $parent = makeParentCoa('Equity', '3-1000');
    $child  = makeChildCoa($parent, '3-1001');

    JournalEntry::factory()->create(['coa_id' => $child->id, 'debit' => 50_000, 'credit' => 300_000]);

    $service = new JournalEntryAggregationService();
    $grouped = $service->getGroupedByParent();

    expect((float) $grouped->first()['balance'])->toEqual(250_000.0);
});

test('revenue account balance = credit minus debit', function () {
    $parent = makeParentCoa('Revenue', '4-1000');
    $child  = makeChildCoa($parent, '4-1001');

    JournalEntry::factory()->create(['coa_id' => $child->id, 'debit' => 0, 'credit' => 1_000_000]);

    $service = new JournalEntryAggregationService();
    $grouped = $service->getGroupedByParent();

    expect((float) $grouped->first()['balance'])->toEqual(1_000_000.0);
});

test('expense account balance = debit minus credit', function () {
    $parent = makeParentCoa('Expense', '5-1000');
    $child  = makeChildCoa($parent, '5-1001');

    JournalEntry::factory()->create(['coa_id' => $child->id, 'debit' => 250_000, 'credit' => 0]);

    $service = new JournalEntryAggregationService();
    $grouped = $service->getGroupedByParent();

    expect((float) $grouped->first()['balance'])->toEqual(250_000.0);
});

// ──────────────────────────────────────────────────────────────────────────────
// Grouping logic
// ──────────────────────────────────────────────────────────────────────────────

test('entries on child COA are grouped under parent', function () {
    $parent = makeParentCoa('Asset', '1-5000');
    $child1 = makeChildCoa($parent, '1-5001');
    $child2 = makeChildCoa($parent, '1-5002');

    JournalEntry::factory()->create(['coa_id' => $child1->id, 'debit' => 400_000, 'credit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $child2->id, 'debit' => 600_000, 'credit' => 0]);

    $service = new JournalEntryAggregationService();
    $grouped = $service->getGroupedByParent();

    $parentGroup = $grouped->first();

    expect($grouped)->toHaveCount(1)
        ->and($parentGroup['id'])->toBe($parent->id)
        ->and((float) $parentGroup['total_debit'])->toEqual(1_000_000.0)
        ->and($parentGroup['children'])->toHaveCount(2);
});

test('child totals accumulate correctly', function () {
    $parent = makeParentCoa('Asset', '1-6000');
    $child  = makeChildCoa($parent, '1-6001');

    JournalEntry::factory()->create(['coa_id' => $child->id, 'debit' => 100_000, 'credit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $child->id, 'debit' => 200_000, 'credit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $child->id, 'debit' => 0,       'credit' => 50_000]);

    $service = new JournalEntryAggregationService();
    $grouped = $service->getGroupedByParent();

    $childGroup = collect($grouped->first()['children'])->first();

    expect((float) $childGroup['total_debit'])->toEqual(300_000.0)
        ->and((float) $childGroup['total_credit'])->toEqual(50_000.0);
});

test('entries directly on parent COA appear in parent entries array', function () {
    $parent = makeParentCoa('Asset', '1-7000');

    JournalEntry::factory()->create(['coa_id' => $parent->id, 'debit' => 888_000, 'credit' => 0]);

    $service = new JournalEntryAggregationService();
    $grouped = $service->getGroupedByParent();

    $parentGroup = $grouped->first();

    expect($parentGroup)->toHaveKey('entries')
        ->and($parentGroup['entries'])->toHaveCount(1)
        ->and((float) $parentGroup['entries'][0]['debit'])->toEqual(888_000.0);
});

test('two distinct parent COAs create two groups', function () {
    $p1 = makeParentCoa('Asset',     '1-8000');
    $p2 = makeParentCoa('Liability', '2-8000');
    $c1 = makeChildCoa($p1, '1-8001');
    $c2 = makeChildCoa($p2, '2-8001');

    JournalEntry::factory()->create(['coa_id' => $c1->id, 'debit' => 1_000_000, 'credit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $c2->id, 'debit' => 0, 'credit' => 1_000_000]);

    $service = new JournalEntryAggregationService();
    $grouped = $service->getGroupedByParent();

    expect($grouped)->toHaveCount(2);
});

// ──────────────────────────────────────────────────────────────────────────────
// Filter: date range
// ──────────────────────────────────────────────────────────────────────────────

test('start_date filter excludes entries before the date', function () {
    $parent = makeParentCoa('Asset', '1-9000');
    $child  = makeChildCoa($parent, '1-9001');

    JournalEntry::factory()->create(['coa_id' => $child->id, 'date' => '2024-01-10', 'debit' => 200_000, 'credit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $child->id, 'date' => '2024-03-01', 'debit' => 500_000, 'credit' => 0]);

    $service = new JournalEntryAggregationService();
    $grouped = $service->getGroupedByParent(['start_date' => '2024-02-01']);

    expect($grouped)->toHaveCount(1)
        ->and((float) $grouped->first()['total_debit'])->toEqual(500_000.0);
});

test('end_date filter excludes entries after the date', function () {
    $parent = makeParentCoa('Asset', '1-9100');
    $child  = makeChildCoa($parent, '1-9101');

    JournalEntry::factory()->create(['coa_id' => $child->id, 'date' => '2024-01-10', 'debit' => 200_000, 'credit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $child->id, 'date' => '2024-03-01', 'debit' => 500_000, 'credit' => 0]);

    $service = new JournalEntryAggregationService();
    $grouped = $service->getGroupedByParent(['end_date' => '2024-01-31']);

    expect($grouped)->toHaveCount(1)
        ->and((float) $grouped->first()['total_debit'])->toEqual(200_000.0);
});

test('date range filter returns only entries within range', function () {
    $parent = makeParentCoa('Asset', '1-9200');
    $child  = makeChildCoa($parent, '1-9201');

    JournalEntry::factory()->create(['coa_id' => $child->id, 'date' => '2024-01-01', 'debit' => 100_000, 'credit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $child->id, 'date' => '2024-03-15', 'debit' => 300_000, 'credit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $child->id, 'date' => '2024-06-30', 'debit' => 700_000, 'credit' => 0]);

    $service = new JournalEntryAggregationService();
    $grouped = $service->getGroupedByParent(['start_date' => '2024-02-01', 'end_date' => '2024-04-30']);

    expect($grouped)->toHaveCount(1)
        ->and((float) $grouped->first()['total_debit'])->toEqual(300_000.0);
});

// ──────────────────────────────────────────────────────────────────────────────
// Filter: journal_type
// ──────────────────────────────────────────────────────────────────────────────

test('journal_type filter returns only matching entries', function () {
    $parent = makeParentCoa('Asset', '1-9300');
    $child  = makeChildCoa($parent, '1-9301');

    JournalEntry::factory()->create(['coa_id' => $child->id, 'journal_type' => 'sales',    'debit' => 400_000, 'credit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $child->id, 'journal_type' => 'purchase',  'debit' => 100_000, 'credit' => 0]);

    $service = new JournalEntryAggregationService();
    $grouped = $service->getGroupedByParent(['journal_type' => 'sales']);

    expect($grouped)->toHaveCount(1)
        ->and((float) $grouped->first()['total_debit'])->toEqual(400_000.0);
});

test('journal_type filter excludes all entries when no match', function () {
    $parent = makeParentCoa('Asset', '1-9400');
    $child  = makeChildCoa($parent, '1-9401');

    JournalEntry::factory()->create(['coa_id' => $child->id, 'journal_type' => 'sales', 'debit' => 500_000, 'credit' => 0]);

    $service = new JournalEntryAggregationService();
    $grouped = $service->getGroupedByParent(['journal_type' => 'depreciation']);

    expect($grouped)->toHaveCount(0);
});

// ──────────────────────────────────────────────────────────────────────────────
// Summary filter consistency
// ──────────────────────────────────────────────────────────────────────────────

test('summary totals match manual sum of grouped data totals', function () {
    $p1 = makeParentCoa('Asset',   '1-9500');
    $p2 = makeParentCoa('Expense', '5-9500');
    $c1 = makeChildCoa($p1, '1-9501');
    $c2 = makeChildCoa($p2, '5-9501');

    JournalEntry::factory()->create(['coa_id' => $c1->id, 'debit' => 600_000, 'credit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $c2->id, 'debit' => 0, 'credit' => 600_000]);

    $service = new JournalEntryAggregationService();
    $filters = ['start_date' => '2020-01-01'];

    $grouped = $service->getGroupedByParent($filters);
    $summary = $service->getSummary($filters);

    $manualDebit  = $grouped->sum('total_debit');
    $manualCredit = $grouped->sum('total_credit');

    expect((float) $summary['total_debit'])->toEqual((float) $manualDebit)
        ->and((float) $summary['total_credit'])->toEqual((float) $manualCredit);
});

// ──────────────────────────────────────────────────────────────────────────────
// Livewire component: data flows to view
// ──────────────────────────────────────────────────────────────────────────────

test('component exposes groupedData property with correct count', function () {
    $user = makeAdmin();
    $this->actingAs($user);

    $p1 = makeParentCoa('Asset',    '1-9600');
    $p2 = makeParentCoa('Expense',  '5-9600');
    $c1 = makeChildCoa($p1, '1-9601');
    $c2 = makeChildCoa($p2, '5-9601');

    JournalEntry::factory()->create(['coa_id' => $c1->id, 'debit' => 100_000, 'credit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $c2->id, 'debit' => 0, 'credit' => 100_000]);

    Livewire::test(GroupedJournalEntries::class)
        ->assertOk()
        ->assertSet('groupedData', fn ($data) => count($data) === 2);
});

test('component summary property is_balanced is true when debit equals credit', function () {
    $user = makeAdmin();
    $this->actingAs($user);

    $coa = makeParentCoa('Asset', '1-9700');

    JournalEntry::factory()->create(['coa_id' => $coa->id, 'debit' => 1_000_000, 'credit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $coa->id, 'debit' => 0, 'credit' => 1_000_000]);

    Livewire::test(GroupedJournalEntries::class)
        ->assertOk()
        ->assertSet('summary', fn ($s) => $s['is_balanced'] === true);
});

test('component summary property is_balanced is false when unbalanced', function () {
    $user = makeAdmin();
    $this->actingAs($user);

    $coa = makeParentCoa('Asset', '1-9800');

    JournalEntry::factory()->create(['coa_id' => $coa->id, 'debit' => 500_000, 'credit' => 0]);

    Livewire::test(GroupedJournalEntries::class)
        ->assertOk()
        ->assertSet('summary', fn ($s) => $s['is_balanced'] === false);
});

test('applyFilters action reloads data after filter change', function () {
    $user = makeAdmin();
    $this->actingAs($user);

    $parent = makeParentCoa('Asset', '1-9900');
    $child  = makeChildCoa($parent, '1-9901');

    JournalEntry::factory()->create(['coa_id' => $child->id, 'date' => '2023-06-15', 'debit' => 200_000, 'credit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $child->id, 'date' => '2024-06-15', 'debit' => 900_000, 'credit' => 0]);

    Livewire::test(GroupedJournalEntries::class)
        ->assertSet('groupedData', fn ($d) => count($d) === 1) // both dates → 1 group
        ->set('data.start_date', '2024-01-01')
        ->call('applyFilters')
        ->assertSet('groupedData', fn ($d) =>
            count($d) === 1 &&
            (float) $d[0]['total_debit'] === 900_000.0
        );
});

test('resetFilters restores all data', function () {
    $user = makeAdmin();
    $this->actingAs($user);

    $parent = makeParentCoa('Asset', '1-9950');
    $child  = makeChildCoa($parent, '1-9951');

    JournalEntry::factory()->create(['coa_id' => $child->id, 'date' => '2022-01-01', 'debit' => 111_000, 'credit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $child->id, 'date' => '2024-01-01', 'debit' => 222_000, 'credit' => 0]);

    Livewire::test(GroupedJournalEntries::class)
        ->set('data.start_date', '2024-01-01')
        ->call('applyFilters')
        ->assertSet('groupedData', fn ($d) => (float) $d[0]['total_debit'] === 222_000.0)
        ->call('resetFilters')
        ->assertSet('groupedData', fn ($d) => (float) $d[0]['total_debit'] === 333_000.0);
});
