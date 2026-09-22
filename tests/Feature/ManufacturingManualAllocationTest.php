<?php

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\ManufacturingOrder;
use App\Services\ManufacturingJournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    \Tests\TestCase::disableBaseSeeding();

    ChartOfAccount::firstOrCreate(['code' => '1400.04'], ['name' => 'POS SEMENTARA PRODUKSI', 'type' => 'Asset', 'is_active' => true]);
    ChartOfAccount::firstOrCreate(['code' => '5230'], ['name' => 'BIAYA TENAGA KERJA PROSES PRODUKSI', 'type' => 'Expense', 'is_active' => true]);
    ChartOfAccount::firstOrCreate(['code' => '6100'], ['name' => 'Beban Overhead Produksi', 'type' => 'Expense', 'is_active' => true]);
});

test('manual manufacturing allocation splits labor and overhead into separate journal entries', function () {
    $manufacturingOrder = ManufacturingOrder::factory()->create([
        'status' => 'in_progress',
    ]);

    app(ManufacturingJournalService::class)->allocateLaborAndOverhead(
        3000,
        1200,
        'MAN-ALLOC-002',
        now(),
        null,
        null,
        'Alokasi manual TKL dan BOP',
        $manufacturingOrder
    );

    $entries = JournalEntry::where('journal_type', 'manufacturing_allocation')
        ->where('reference', 'MAN-ALLOC-002')
        ->orderBy('id')
        ->get();

    expect($entries)->toHaveCount(3)
        ->and((float) $entries->sum('debit'))->toBe(4200.0)
        ->and((float) $entries->sum('credit'))->toBe(4200.0)
        ->and($entries->where('coa_id', ChartOfAccount::where('code', '1400.04')->value('id')))->toHaveCount(1)
        ->and($entries->where('coa_id', ChartOfAccount::where('code', '5230')->value('id')))->toHaveCount(1)
        ->and($entries->where('coa_id', ChartOfAccount::where('code', '6100')->value('id')))->toHaveCount(1);
});