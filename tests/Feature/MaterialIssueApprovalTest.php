<?php

use App\Models\MaterialIssue;
use App\Models\User;
use Database\Seeders\FinanceSeeder;

test('material issue can be created with pending approval status', function () {
    $user = User::factory()->create();

    $materialIssue = MaterialIssue::create([
        'issue_number' => 'MI-TEST-001',
        'issue_date' => now(),
        'type' => 'issue',
        'status' => MaterialIssue::STATUS_PENDING_APPROVAL,
        'total_cost' => 10000,
        'created_by' => $user->id,
    ]);

    expect($materialIssue->status)->toBe(MaterialIssue::STATUS_PENDING_APPROVAL);
});

test('material issue can be approved', function () {
    $user = User::factory()->create();
    $approver = User::factory()->create();

    $materialIssue = MaterialIssue::create([
        'issue_number' => 'MI-TEST-002',
        'issue_date' => now(),
        'type' => 'issue',
        'status' => MaterialIssue::STATUS_PENDING_APPROVAL,
        'total_cost' => 10000,
        'created_by' => $user->id,
    ]);

    $materialIssue->update([
        'status' => MaterialIssue::STATUS_APPROVED,
        'approved_by' => $approver->id,
        'approved_at' => now(),
    ]);

    expect($materialIssue->status)->toBe(MaterialIssue::STATUS_APPROVED);
    expect($materialIssue->approved_by)->toBe($approver->id);
    expect($materialIssue->approved_at)->not->toBeNull();
});

test('material issue can be rejected', function () {
    $user = User::factory()->create();
    $approver = User::factory()->create();

    $materialIssue = MaterialIssue::create([
        'issue_number' => 'MI-TEST-003',
        'issue_date' => now(),
        'type' => 'issue',
        'status' => MaterialIssue::STATUS_PENDING_APPROVAL,
        'total_cost' => 10000,
        'created_by' => $user->id,
    ]);

    $materialIssue->update([
        'status' => MaterialIssue::STATUS_DRAFT,
        'approved_by' => null,
        'approved_at' => null,
    ]);

    expect($materialIssue->status)->toBe(MaterialIssue::STATUS_DRAFT);
    expect($materialIssue->approved_by)->toBeNull();
    expect($materialIssue->approved_at)->toBeNull();
});

test('journal entry only created when approved and completed', function () {
    // Create required COAs for manufacturing journal
    $generalRawMaterialsCoa = \App\Models\ChartOfAccount::create([
        'code' => '1140',
        'name' => 'Persediaan Bahan Baku',
        'type' => 'Asset',
        'is_active' => true,
    ]);
    $workInProgressCoa = \App\Models\ChartOfAccount::create([
        'code' => '1150',
        'name' => 'Persediaan Barang Dalam Proses',
        'type' => 'Asset',
        'is_active' => true,
    ]);
    $productInventoryCoa = \App\Models\ChartOfAccount::create([
        'code' => '1140.01',
        'name' => 'Persediaan Bahan Baku - Gudang Utama',
        'type' => 'Asset',
        'is_active' => true,
    ]);

    $user = User::factory()->create();
    $approver = User::factory()->create();
    $warehouse = \App\Models\Warehouse::factory()->create();
    $product = \App\Models\Product::factory()->create([
        'inventory_coa_id' => $productInventoryCoa->id,
    ]);

    $materialIssue = MaterialIssue::create([
        'issue_number' => 'MI-TEST-004',
        'issue_date' => now(),
        'type' => 'issue',
        'status' => MaterialIssue::STATUS_PENDING_APPROVAL,
        'total_cost' => 10000,
        'created_by' => $user->id,
        'warehouse_id' => $warehouse->id,
        'wip_coa_id' => $workInProgressCoa->id,
    ]);

    // Add an item to the material issue
    $materialIssue->items()->create([
        'product_id' => $product->id,
        'uom_id' => $product->uom_id,
        'quantity' => 10,
        'cost_per_unit' => 1000,
        'total_cost' => 10000,
        'warehouse_id' => $warehouse->id,
    ]);

    // Before approval and completion, no journal entry should exist
    expect(\App\Models\JournalEntry::where('source_type', MaterialIssue::class)
        ->where('source_id', $materialIssue->id)->exists())->toBeFalse();

    // Approve the material issue
    $materialIssue->update([
        'approved_by' => $approver->id,
        'approved_at' => now(),
        'status' => MaterialIssue::STATUS_APPROVED,
    ]);

    // Still no journal entry after approval
    expect(\App\Models\JournalEntry::where('source_type', MaterialIssue::class)
        ->where('source_id', $materialIssue->id)->exists())->toBeFalse();

    // Complete the material issue - this should create journal entries
    $materialIssue->update(['status' => MaterialIssue::STATUS_COMPLETED]);

    // Now journal entries should exist (1 debit + 1 credit per product)
    $journalEntries = \App\Models\JournalEntry::where('source_type', MaterialIssue::class)
        ->where('source_id', $materialIssue->id)->get();

    expect($journalEntries)->toHaveCount(2); // 1 debit + 1 credit

    // Check debit entry (work in progress)
    $debitEntry = $journalEntries->where('debit', '>', 0)->first();
    expect($debitEntry)->not->toBeNull();
    expect($debitEntry->coa_id)->toBe($workInProgressCoa->id);
    expect((float) $debitEntry->debit)->toBe(10000.0);

    // Check credit entry (product-specific inventory)
    $creditEntry = $journalEntries->where('credit', '>', 0)->first();
    expect($creditEntry)->not->toBeNull();
    expect($creditEntry->coa_id)->toBe($productInventoryCoa->id); // Should use product's specific COA
    expect((float) $creditEntry->credit)->toBe(10000.0);
});