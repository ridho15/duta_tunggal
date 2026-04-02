<?php

use Livewire\Livewire;
use App\Filament\Resources\Reports\BalanceSheetResource\Pages\ViewBalanceSheet;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('printPdf does not throw when as_of_date is set', function () {
    // Instantiate directly to avoid Filament/Livewire mount complexity in tests
    $page = new ViewBalanceSheet();
    $page->as_of_date = now()->format('Y-m-d');

    // Call printPdf; ensure no exception is thrown
    $result = $page->printPdf();

    // Should return a RedirectResponse or StreamedResponse; assert it's not null
    expect($result)->not()->toBeNull();
});

it('getReportData exposes actual difference for unbalanced report', function () {
    $cabang = Cabang::create([
        'kode' => 'TST',
        'nama' => 'Test Branch',
        'alamat' => 'Test Address',
        'telepon' => '0123456789',
    ]);

    $cash = ChartOfAccount::create([
        'code' => '1-1001',
        'name' => 'Kas',
        'type' => 'Asset',
        'is_current' => true,
        'is_active' => true,
    ]);

    $capital = ChartOfAccount::create([
        'code' => '3-1001',
        'name' => 'Modal',
        'type' => 'Equity',
        'is_active' => true,
    ]);

    JournalEntry::create([
        'coa_id' => $cash->id,
        'cabang_id' => $cabang->id,
        'date' => now(),
        'reference' => 'VR-BS-001',
        'source_type' => 'manual',
        'source_id' => 1,
        'description' => 'Cash',
        'debit' => 5000000,
        'credit' => 0,
    ]);

    JournalEntry::create([
        'coa_id' => $capital->id,
        'cabang_id' => $cabang->id,
        'date' => now(),
        'reference' => 'VR-BS-002',
        'source_type' => 'manual',
        'source_id' => 1,
        'description' => 'Capital',
        'debit' => 0,
        'credit' => 3000000,
    ]);

    $page = new ViewBalanceSheet();
    $page->as_of_date = now()->format('Y-m-d');

    $data = $page->getReportData();

    expect($data['balanced'])->toBeFalse();
    expect($data['difference'])->toBe(2000000.0);
    expect($data['balance_warning'])->not()->toBeNull();
});
