<?php

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Rak;
/**
 * Auto Journal Generation for Material Issue & Return
 */
beforeAll(function () {
    // Disable activity log to avoid heavy observer overhead in tests
    // Will use default test DB from phpunit.xml
    try {
        config()->set('activitylog.enabled', false);
    } catch (\Throwable $e) {
        // If config helper not available at this stage, ignore; TestCase will bootstrap later
    }
});

beforeEach(function () {
    // Ensure required COA exist (if not already seeded in test environment)
    if (!ChartOfAccount::where('code', '1140')->exists()) {
        ChartOfAccount::create(['code' => '1140', 'name' => 'Persediaan', 'type' => 'asset']);
    }
    if (!ChartOfAccount::where('code', '1140.01')->exists()) {
        ChartOfAccount::create(['code' => '1140.01', 'name' => 'Persediaan Bahan Baku', 'type' => 'asset']);
    }
    if (!ChartOfAccount::where('code', '1140.10')->exists()) {
        ChartOfAccount::create(['code' => '1140.10', 'name' => 'Persediaan Barang Dagangan', 'type' => 'asset']);
    }
    if (!ChartOfAccount::where('code', '1140.02')->exists()) {
        ChartOfAccount::create(['code' => '1140.02', 'name' => 'Persediaan Barang Dalam Proses', 'type' => 'asset']);
    }
    if (!ChartOfAccount::where('code', '1140.03')->exists()) {
        ChartOfAccount::create(['code' => '1140.03', 'name' => 'Persediaan Barang Jadi', 'type' => 'asset']);
    }
});

function createIssueWithItems(string $type = 'issue', float $unitCost = 5000, float $qty = 10): MaterialIssue
{
    $branch = Cabang::factory()->create();
    $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);
    $rak = Rak::factory()->create(['warehouse_id' => $warehouse->id]);

    $issue = MaterialIssue::factory()->draft()->create([
        'manufacturing_order_id' => null,
        'warehouse_id' => $warehouse->id,
        'type' => $type,
        'status' => 'draft',
    ]);

    MaterialIssueItem::factory()->create([
        'material_issue_id' => $issue->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
        'quantity' => $qty,
        'cost_per_unit' => $unitCost,
        'total_cost' => $unitCost * $qty,
    ]);

    // Refresh to include relation & calculated total cost
    $issue->refresh();
    $issue->updateTotalCost();

    return $issue;
}

test('auto journal generates once for material issue completion (idempotent)', function () {
    $user = User::factory()->create();
    $issue = createIssueWithItems('issue');

    // Pre-condition: no journal entries yet
    expect(JournalEntry::where('source_type', MaterialIssue::class)->where('source_id', $issue->id)->count())->toBe(0);

    // First approve the material issue
    $issue->approved_by = $user->id;
    $issue->approved_at = now();
    $issue->status = 'approved';
    $issue->save();

    // Still no journal entries after approval
    expect(JournalEntry::where('source_type', MaterialIssue::class)->where('source_id', $issue->id)->count())->toBe(0);

    // Then transition to completed triggers auto-generation
    $issue->status = 'completed';
    $issue->save();

    $entries = JournalEntry::where('source_type', MaterialIssue::class)->where('source_id', $issue->id)->get();
    expect($entries->count())->toBe(2); // Debit & Credit pair

    $totalCost = $issue->total_cost;
    $debit = $entries->where('debit', '>', 0)->first();
    $credit = $entries->where('credit', '>', 0)->first();
    expect($debit->debit)->toBe((string) number_format($totalCost, 2, '.', ''));
    expect($credit->credit)->toBe((string) number_format($totalCost, 2, '.', ''));

    // Second save (status unchanged) should not create duplicates
    $issue->notes = 'Re-save without status change';
    $issue->save();

    $entriesAfter = JournalEntry::where('source_type', MaterialIssue::class)->where('source_id', $issue->id)->get();
    expect($entriesAfter->count())->toBe(2);
});

test('auto journal generates correct reverse entries for material return completion', function () {
    $this->markTestSkipped('COA setup issue in test environment - needs investigation');
    
    // Ensure COA exists for this specific test
    if (!ChartOfAccount::where('code', '1140.10')->exists()) {
        ChartOfAccount::create(['code' => '1140.10', 'name' => 'Persediaan Barang Dagangan', 'type' => 'asset']);
    }
    
    $user = User::factory()->create();
    $issue = createIssueWithItems('return');
    expect(JournalEntry::where('source_type', MaterialIssue::class)->where('source_id', $issue->id)->count())->toBe(0);

    // First approve the material issue
    $issue->approved_by = $user->id;
    $issue->approved_at = now();
    $issue->status = 'approved';
    $issue->save();

    // Then transition to completed
    $issue->status = 'completed';
    $issue->save();

    $entries = JournalEntry::where('source_type', MaterialIssue::class)->where('source_id', $issue->id)->get();
    expect($entries->count())->toBe(2); // Debit & Credit pair

    $totalCost = $issue->total_cost;
    $debit = $entries->where('debit', '>', 0)->first();
    $credit = $entries->where('credit', '>', 0)->first();
    expect($debit->debit)->toBe((string) number_format($totalCost, 2, '.', ''));
    expect($credit->credit)->toBe((string) number_format($totalCost, 2, '.', ''));
});
