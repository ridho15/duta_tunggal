<?php

use App\Filament\Pages\AlkGraficPage;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\Reports\AlkGrafikReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->branch = Cabang::create([
        'kode' => 'ALK',
        'nama' => 'Cabang ALK',
        'alamat' => 'Jl. Test',
        'telepon' => '08123456789',
    ]);

    $cash = ChartOfAccount::create([
        'code' => '1-1001',
        'name' => 'Kas',
        'type' => 'Asset',
        'is_current' => true,
        'is_active' => true,
    ]);

    $inventory = ChartOfAccount::create([
        'code' => '1-1002',
        'name' => 'Persediaan',
        'type' => 'Asset',
        'is_current' => true,
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
        'name' => 'Modal Pemilik',
        'type' => 'Equity',
        'is_active' => true,
    ]);

    $sales = ChartOfAccount::create([
        'code' => '4-1001',
        'name' => 'Pendapatan Penjualan',
        'type' => 'Revenue',
        'is_active' => true,
    ]);

    $expense = ChartOfAccount::create([
        'code' => '6-1001',
        'name' => 'Beban Operasional',
        'type' => 'Expense',
        'is_active' => true,
    ]);

    createAlkJournal($cash, $this->branch->id, '2026-04-01', 'ALK-CAP-001', 900000, 0);
    createAlkJournal($capital, $this->branch->id, '2026-04-01', 'ALK-CAP-001', 0, 900000);
    createAlkJournal($cash, $this->branch->id, '2026-04-05', 'ALK-SAL-001', 1250000, 0);
    createAlkJournal($sales, $this->branch->id, '2026-04-05', 'ALK-SAL-001', 0, 1250000);
    createAlkJournal($inventory, $this->branch->id, '2026-04-06', 'ALK-PUR-001', 300000, 0);
    createAlkJournal($payable, $this->branch->id, '2026-04-06', 'ALK-PUR-001', 0, 300000);
    createAlkJournal($expense, $this->branch->id, '2026-04-08', 'ALK-EXP-001', 175000, 0);
    createAlkJournal($cash, $this->branch->id, '2026-04-08', 'ALK-EXP-001', 0, 175000);
});

function createAlkJournal(ChartOfAccount $coa, int $branchId, string $date, string $reference, float $debit, float $credit): void
{
    JournalEntry::create([
        'coa_id' => $coa->id,
        'cabang_id' => $branchId,
        'date' => $date,
        'reference' => $reference,
        'description' => 'ALK test journal',
        'debit' => $debit,
        'credit' => $credit,
        'source_type' => 'test',
        'source_id' => 1,
        'journal_type' => 'manual',
    ]);
}

it('returns empty report data when preview is disabled', function () {
    $page = new AlkGraficPage();
    $page->showPreview = false;

    expect($page->getReportData())->toBe([]);
});

it('returns report data from the shared ALK report service', function () {
    $page = new AlkGraficPage();
    $page->showPreview = true;
    $page->start_date = '2026-04-01';
    $page->end_date = '2026-04-30';
    $page->cabang_id = $this->branch->id;

    $expected = app(AlkGrafikReportService::class)->generate([
        'start_date' => '2026-04-01',
        'end_date' => '2026-04-30',
        'cabang_id' => $this->branch->id,
    ]);

    expect($page->getReportData())
        ->toMatchArray([
            'period_label' => $expected['period_label'],
            'branch_name' => $expected['branch_name'],
        ])
        ->and((float) data_get($page->getReportData(), 'summary.revenue'))->toBe((float) data_get($expected, 'summary.revenue'))
        ->and((float) data_get($page->getReportData(), 'summary.net_profit'))->toBe((float) data_get($expected, 'summary.net_profit'))
        ->and((float) data_get($page->getReportData(), 'summary.total_assets'))->toBe((float) data_get($expected, 'summary.total_assets'))
        ->and((float) data_get($page->getReportData(), 'ratios.current_ratio'))->toBe((float) data_get($expected, 'ratios.current_ratio'));
});

it('builds preview and export urls using current filters', function () {
    $page = new AlkGraficPage();
    $page->start_date = '2026-04-01';
    $page->end_date = '2026-04-30';
    $page->cabang_id = $this->branch->id;

    expect($page->getPreviewUrl())->toContain('/reports/alk-grafik/preview')
        ->and($page->getPreviewUrl())->toContain('start_date=2026-04-01')
    ->and($page->getPreviewUrl(true))->toContain('embedded=1')
        ->and($page->getExportUrl('excel'))->toContain('/reports/alk-grafik/download-excel')
        ->and($page->getExportUrl('pdf'))->toContain('/reports/alk-grafik/download-pdf')
        ->and($page->getExportUrl('pdf'))->toContain('cabang_id=' . $this->branch->id);
});

it('resetReport clears preview state', function () {
    $page = new AlkGraficPage();
    $page->showPreview = true;
    $page->resetReport();

    expect($page->showPreview)->toBeFalse();
});