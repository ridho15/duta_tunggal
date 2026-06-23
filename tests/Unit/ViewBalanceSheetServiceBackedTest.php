<?php

use App\Filament\Resources\Reports\BalanceSheetResource\Pages\ViewBalanceSheet;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\BalanceSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('adapts balance sheet service totals into the legacy page payload', function () {
    $cabang = Cabang::create([
        'kode' => 'BSA',
        'nama' => 'Balance Sheet Adapter',
        'alamat' => 'Test Address',
        'telepon' => '08123456789',
    ]);

    $cash = ChartOfAccount::create([
        'code' => '1-1001',
        'name' => 'Kas',
        'type' => 'Asset',
        'is_current' => true,
        'is_active' => true,
    ]);

    $equipment = ChartOfAccount::create([
        'code' => '1-2001',
        'name' => 'Peralatan',
        'type' => 'Asset',
        'is_current' => false,
        'is_active' => true,
    ]);

    $accumulatedDepreciation = ChartOfAccount::create([
        'code' => '1-2999',
        'name' => 'Akumulasi Penyusutan',
        'type' => 'Contra Asset',
        'is_active' => true,
    ]);

    $payable = ChartOfAccount::create([
        'code' => '2-1001',
        'name' => 'Utang Dagang',
        'type' => 'Liability',
        'is_current' => true,
        'is_active' => true,
    ]);

    $capital = ChartOfAccount::create([
        'code' => '3-1001',
        'name' => 'Modal',
        'type' => 'Equity',
        'is_active' => true,
    ]);

    foreach ([
        [$cash, 400000, 0, 'BS-ADAPT-001'],
        [$equipment, 850000, 0, 'BS-ADAPT-002'],
        [$accumulatedDepreciation, 0, 250000, 'BS-ADAPT-003'],
        [$payable, 0, 300000, 'BS-ADAPT-004'],
        [$capital, 0, 700000, 'BS-ADAPT-005'],
    ] as [$coa, $debit, $credit, $reference]) {
        JournalEntry::create([
            'coa_id' => $coa->id,
            'cabang_id' => $cabang->id,
            'date' => '2026-04-30',
            'reference' => $reference,
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => $reference,
            'debit' => $debit,
            'credit' => $credit,
        ]);
    }

    $page = new ViewBalanceSheet();
    $page->as_of_date = '2026-04-30';
    $page->cabang_id = (string) $cabang->id;

    $serviceData = app(BalanceSheetService::class)->generate([
        'as_of_date' => '2026-04-30',
        'cabang_id' => $cabang->id,
    ]);

    $data = $page->getReportData();
    $classicData = $page->getClassicReportData();

    expect($data['asset_total'])->toBe($serviceData['total_assets'])
        ->and($data['liab_total'])->toBe($serviceData['total_liabilities'])
        ->and($data['equity_total'])->toBe($serviceData['total_equity'])
        ->and($data['balanced'])->toBeTrue()
        ->and(collect($data['assets'])
            ->flatMap(fn ($group) => $group['items'])
            ->firstWhere(fn ($item) => $item['coa']->code === '1-2999')['balance'])
        ->toBe(-250000.0)
        ->and($classicData['total_assets'])->toBe($serviceData['total_assets'])
        ->and($classicData['total_liabilities_and_equity'])->toBe($serviceData['total_liabilities_and_equity'])
        ->and($classicData['is_balanced'])->toBeTrue();
});