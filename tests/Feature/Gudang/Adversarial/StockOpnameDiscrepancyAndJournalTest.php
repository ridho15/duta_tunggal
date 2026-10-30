<?php

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\StockOpname;
use App\Services\StockOpnameService;
use Illuminate\Validation\ValidationException;

test('OPN-GL-01: surplus fisik menerbitkan jurnal Dr Persediaan dan Cr Penyesuaian Persediaan secara seimbang', function () {
    $ctx = opnContext();
    $service = app(StockOpnameService::class);

    $opname = opnCreate($ctx, ['status' => 'completed']);
    opnItem($opname, $ctx, [
        'system_qty' => 10.0,
        'physical_qty' => 15.0,
        'unit_cost' => 50000.0, // Selisih +5 * 50,000 = +250,000
    ]);

    $approved = $service->approveStockOpname($opname, $ctx['user']->id);

    expect($approved->status)->toBe('approved');

    $journals = JournalEntry::where('source_type', StockOpname::class)
        ->where('source_id', $opname->id)
        ->get();

    expect($journals)->toHaveCount(2);

    $debitEntry = $journals->firstWhere('debit', '>', 0);
    $creditEntry = $journals->firstWhere('credit', '>', 0);

    expect((float) $debitEntry->debit)->toBe(250000.0);
    expect((int) $debitEntry->coa_id)->toBe((int) $ctx['inventoryCoa']->id);

    expect((float) $creditEntry->credit)->toBe(250000.0);
    expect((int) $creditEntry->coa_id)->toBe((int) $ctx['adjustmentCoa']->id);

    expect((float) $journals->sum('debit'))->toBe((float) $journals->sum('credit'));
});

test('OPN-GL-02: defisit fisik (shrinkage) menerbitkan jurnal Dr Penyesuaian Persediaan dan Cr Persediaan secara seimbang', function () {
    $ctx = opnContext();
    $service = app(StockOpnameService::class);

    $opname = opnCreate($ctx, ['status' => 'completed']);
    opnItem($opname, $ctx, [
        'system_qty' => 10.0,
        'physical_qty' => 6.0,
        'unit_cost' => 50000.0, // Selisih -4 * 50,000 = -200,000
    ]);

    $approved = $service->approveStockOpname($opname, $ctx['user']->id);

    expect($approved->status)->toBe('approved');

    $journals = JournalEntry::where('source_type', StockOpname::class)
        ->where('source_id', $opname->id)
        ->get();

    expect($journals)->toHaveCount(2);

    $debitEntry = $journals->firstWhere('debit', '>', 0);
    $creditEntry = $journals->firstWhere('credit', '>', 0);

    expect((float) $debitEntry->debit)->toBe(200000.0);
    expect((int) $debitEntry->coa_id)->toBe((int) $ctx['adjustmentCoa']->id);

    expect((float) $creditEntry->credit)->toBe(200000.0);
    expect((int) $creditEntry->coa_id)->toBe((int) $ctx['inventoryCoa']->id);

    expect((float) $journals->sum('debit'))->toBe((float) $journals->sum('credit'));
});

test('OPN-GL-03: opname tanpa selisih tidak menerbitkan jurnal, dan melempar Exception jika COA hilang', function () {
    $ctx = opnContext();
    $service = app(StockOpnameService::class);

    // Kasus 1: Selisih 0
    $opnameZero = opnCreate($ctx, ['status' => 'completed']);
    opnItem($opnameZero, $ctx, [
        'system_qty' => 10.0,
        'physical_qty' => 10.0,
        'unit_cost' => 50000.0, // Selisih 0
    ]);

    $service->approveStockOpname($opnameZero, $ctx['user']->id);

    $journalsZero = JournalEntry::where('source_type', StockOpname::class)
        ->where('source_id', $opnameZero->id)
        ->get();

    expect($journalsZero)->toHaveCount(0);

    // Kasus 2: Nonaktifkan seluruh akun COA kandidat saat ada selisih
    config(['coa.inventory' => 'NONEXISTENT_INV', 'coa.general_expense' => 'NONEXISTENT_EXP']);
    \Illuminate\Support\Facades\DB::table('chart_of_accounts')
        ->whereIn('code', ['1100', '1140.01', '1140.10', '5100', '5100.10', '6100'])
        ->update(['code' => \Illuminate\Support\Facades\DB::raw("CONCAT('DISABLED_', code)")]);
    \Illuminate\Support\Facades\DB::table('chart_of_accounts')
        ->whereIn('type', ['Asset', 'asset', 'Expense', 'expense'])
        ->update(['type' => 'Equity']);

    $opnameDiscrepancy = opnCreate($ctx, ['status' => 'completed']);
    opnItem($opnameDiscrepancy, $ctx, [
        'system_qty' => 10.0,
        'physical_qty' => 12.0,
        'unit_cost' => 50000.0,
    ]);

    expect(fn () => $service->approveStockOpname($opnameDiscrepancy, $ctx['user']->id))
        ->toThrow(ValidationException::class);
});
