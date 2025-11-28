<?php

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\JournalEntryAggregationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new JournalEntryAggregationService();
});

test('service can group journal entries by parent COA', function () {
    // Create parent COA
    $parentCoa = ChartOfAccount::factory()->create([
        'code' => '1-1000',
        'name' => 'Aset Lancar',
        'type' => 'Asset',
        'parent_id' => null,
    ]);

    // Create child COAs
    $childCoa1 = ChartOfAccount::factory()->create([
        'code' => '1-1001',
        'name' => 'Kas',
        'type' => 'Asset',
        'parent_id' => $parentCoa->id,
    ]);

    $childCoa2 = ChartOfAccount::factory()->create([
        'code' => '1-1002',
        'name' => 'Bank',
        'type' => 'Asset',
        'parent_id' => $parentCoa->id,
    ]);

    // Create journal entries for child COAs
    JournalEntry::factory()->create([
        'coa_id' => $childCoa1->id,
        'date' => now(),
        'debit' => 1000000,
        'credit' => 0,
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $childCoa2->id,
        'date' => now(),
        'debit' => 500000,
        'credit' => 0,
    ]);

    $grouped = $this->service->getGroupedByParent();

    expect($grouped)->toHaveCount(1)
        ->and($grouped->first()['id'])->toBe($parentCoa->id)
        ->and($grouped->first()['total_debit'])->toEqual(1500000.0)
        ->and($grouped->first()['children'])->toHaveCount(2);
});

test('service correctly accumulates debit and credit to parent', function () {
    $parentCoa = ChartOfAccount::factory()->create([
        'code' => '2-1000',
        'name' => 'Liabilities',
        'type' => 'Liability',
        'parent_id' => null,
    ]);

    $childCoa = ChartOfAccount::factory()->create([
        'code' => '2-1001',
        'name' => 'Accounts Payable',
        'type' => 'Liability',
        'parent_id' => $parentCoa->id,
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $childCoa->id,
        'date' => now(),
        'debit' => 200000,
        'credit' => 0,
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $childCoa->id,
        'date' => now(),
        'debit' => 0,
        'credit' => 500000,
    ]);

    $grouped = $this->service->getGroupedByParent();

    expect($grouped)->toHaveCount(1)
        ->and($grouped->first()['total_debit'])->toEqual(200000.0)
        ->and($grouped->first()['total_credit'])->toEqual(500000.0);
});

test('service calculates balance correctly for asset accounts', function () {
    $parentCoa = ChartOfAccount::factory()->create([
        'type' => 'Asset',
        'parent_id' => null,
    ]);

    $childCoa = ChartOfAccount::factory()->create([
        'type' => 'Asset',
        'parent_id' => $parentCoa->id,
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $childCoa->id,
        'debit' => 1000000,
        'credit' => 300000,
    ]);

    $grouped = $this->service->getGroupedByParent();

    // Balance for Asset = Debit - Credit
    expect($grouped->first()['balance'])->toEqual(700000.0);
});

test('service calculates balance correctly for liability accounts', function () {
    $parentCoa = ChartOfAccount::factory()->create([
        'type' => 'Liability',
        'parent_id' => null,
    ]);

    $childCoa = ChartOfAccount::factory()->create([
        'type' => 'Liability',
        'parent_id' => $parentCoa->id,
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $childCoa->id,
        'debit' => 200000,
        'credit' => 800000,
    ]);

    $grouped = $this->service->getGroupedByParent();

    // Balance for Liability = Credit - Debit
    expect($grouped->first()['balance'])->toEqual(600000.0);
});

test('service filters journal entries by date range', function () {
    $parentCoa = ChartOfAccount::factory()->create(['parent_id' => null]);
    $childCoa = ChartOfAccount::factory()->create(['parent_id' => $parentCoa->id]);

    // Entry within range
    JournalEntry::factory()->create([
        'coa_id' => $childCoa->id,
        'date' => '2024-01-15',
        'debit' => 1000000,
    ]);

    // Entry outside range
    JournalEntry::factory()->create([
        'coa_id' => $childCoa->id,
        'date' => '2024-03-15',
        'debit' => 500000,
    ]);

    $grouped = $this->service->getGroupedByParent([
        'start_date' => '2024-01-01',
        'end_date' => '2024-01-31',
    ]);

    expect($grouped)->toHaveCount(1)
        ->and($grouped->first()['total_debit'])->toEqual(1000000.0);
});

test('service filters journal entries by journal type', function () {
    $parentCoa = ChartOfAccount::factory()->create(['parent_id' => null]);
    $childCoa = ChartOfAccount::factory()->create(['parent_id' => $parentCoa->id]);

    JournalEntry::factory()->create([
        'coa_id' => $childCoa->id,
        'journal_type' => 'sales',
        'debit' => 1000000,
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $childCoa->id,
        'journal_type' => 'purchase',
        'debit' => 500000,
    ]);

    $grouped = $this->service->getGroupedByParent([
        'journal_type' => 'sales',
    ]);

    expect($grouped)->toHaveCount(1)
        ->and($grouped->first()['total_debit'])->toEqual(1000000.0);
});

test('service handles parent COA with direct entries', function () {
    $parentCoa = ChartOfAccount::factory()->create(['parent_id' => null]);

    // Direct entry on parent
    JournalEntry::factory()->create([
        'coa_id' => $parentCoa->id,
        'debit' => 750000,
        'credit' => 0,
    ]);

    $grouped = $this->service->getGroupedByParent();

    expect($grouped)->toHaveCount(1)
        ->and($grouped->first()['total_debit'])->toEqual(750000.0)
        ->and($grouped->first())->toHaveKey('entries')
        ->and($grouped->first()['entries'])->toHaveCount(1);
});

test('service provides summary statistics', function () {
    $coa = ChartOfAccount::factory()->create();

    JournalEntry::factory()->create([
        'coa_id' => $coa->id,
        'debit' => 1000000,
        'credit' => 0,
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $coa->id,
        'debit' => 0,
        'credit' => 1000000,
    ]);

    $summary = $this->service->getSummary();

    expect($summary)
        ->toHaveKey('total_entries')
        ->toHaveKey('total_debit')
        ->toHaveKey('total_credit')
        ->toHaveKey('is_balanced')
        ->and($summary['total_entries'])->toBe(2)
        ->and($summary['total_debit'])->toEqual(1000000.0)
        ->and($summary['total_credit'])->toEqual(1000000.0)
        ->and($summary['is_balanced'])->toBeTrue();
});

test('service detects unbalanced entries', function () {
    $coa = ChartOfAccount::factory()->create();

    JournalEntry::factory()->create([
        'coa_id' => $coa->id,
        'debit' => 1000000,
        'credit' => 0,
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $coa->id,
        'debit' => 0,
        'credit' => 500000,
    ]);

    $summary = $this->service->getSummary();

    expect($summary['is_balanced'])->toBeFalse()
        ->and($summary['net_balance'])->toEqual(500000.0);
});

test('service returns parent COAs with entry counts', function () {
    $parent1 = ChartOfAccount::factory()->create([
        'code' => '1-1000',
        'parent_id' => null,
    ]);

    $parent2 = ChartOfAccount::factory()->create([
        'code' => '2-1000',
        'parent_id' => null,
    ]);

    $child1 = ChartOfAccount::factory()->create(['parent_id' => $parent1->id]);
    $child2 = ChartOfAccount::factory()->create(['parent_id' => $parent2->id]);

    JournalEntry::factory()->count(3)->create(['coa_id' => $child1->id]);
    JournalEntry::factory()->count(2)->create(['coa_id' => $child2->id]);

    $parents = $this->service->getParentCoasWithEntries();

    expect($parents)->toHaveCount(2)
        ->and($parents->first()->entry_count)->toBeGreaterThan(0);
});

test('service handles multiple children under same parent', function () {
    $parent = ChartOfAccount::factory()->create(['parent_id' => null]);
    
    $children = ChartOfAccount::factory()->count(5)->create([
        'parent_id' => $parent->id,
    ]);

    foreach ($children as $child) {
        JournalEntry::factory()->create([
            'coa_id' => $child->id,
            'debit' => 100000,
            'credit' => 0,
        ]);
    }

    $grouped = $this->service->getGroupedByParent();

    expect($grouped)->toHaveCount(1)
        ->and($grouped->first()['children'])->toHaveCount(5)
        ->and($grouped->first()['total_debit'])->toEqual(500000.0);
});

test('service handles nested entries correctly', function () {
    $parent = ChartOfAccount::factory()->create([
        'code' => '1-0000',
        'name' => 'Parent Account',
        'type' => 'Asset',
        'parent_id' => null,
    ]);

    $child1 = ChartOfAccount::factory()->create([
        'code' => '1-0001',
        'name' => 'Child 1',
        'type' => 'Asset',
        'parent_id' => $parent->id,
    ]);

    $child2 = ChartOfAccount::factory()->create([
        'code' => '1-0002',
        'name' => 'Child 2',
        'type' => 'Asset',
        'parent_id' => $parent->id,
    ]);

    // Create multiple entries for each child
    JournalEntry::factory()->count(3)->create([
        'coa_id' => $child1->id,
        'debit' => 100000,
        'credit' => 0,
    ]);

    JournalEntry::factory()->count(2)->create([
        'coa_id' => $child2->id,
        'debit' => 50000,
        'credit' => 0,
    ]);

    $grouped = $this->service->getGroupedByParent();

    $firstGroup = $grouped->first();
    
    expect($firstGroup['children'])->toHaveCount(2)
        ->and($firstGroup['total_debit'])->toEqual(400000.0);
    
    // Check child details
    // Map children by their COA id so assertions don't rely on ordering
    $childMap = collect($firstGroup['children'])->mapWithKeys(function ($c) {
        return [$c['id'] => $c];
    });

    expect($childMap[$child1->id]['entries'])->toHaveCount(3)
        ->and($childMap[$child2->id]['entries'])->toHaveCount(2);
});

test('service returns empty collection when no entries exist', function () {
    ChartOfAccount::factory()->create(['parent_id' => null]);

    $grouped = $this->service->getGroupedByParent();

    expect($grouped)->toBeEmpty();
});

test('service filters by cabang correctly', function () {
    $parentCoa = ChartOfAccount::factory()->create(['parent_id' => null]);
    $childCoa = ChartOfAccount::factory()->create(['parent_id' => $parentCoa->id]);

    JournalEntry::factory()->create([
        'coa_id' => $childCoa->id,
        'cabang_id' => 1,
        'debit' => 1000000,
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $childCoa->id,
        'cabang_id' => 2,
        'debit' => 500000,
    ]);

    $grouped = $this->service->getGroupedByParent([
        'cabang_id' => 1,
    ]);

    expect($grouped)->toHaveCount(1)
        ->and($grouped->first()['total_debit'])->toEqual(1000000.0);
});
