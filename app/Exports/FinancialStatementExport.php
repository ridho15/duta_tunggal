<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FinancialStatementExport implements WithMultipleSheets
{
    public function __construct(protected array $report)
    {
    }

    public function sheets(): array
    {
        $sheets = [new FinancialStatementSummarySheet($this->report)];

        if (! empty($this->report['pl'])) {
            $sheets[] = new FinancialStatementProfitLossSheet($this->report);
        }

        if (! empty($this->report['bs'])) {
            $sheets[] = new FinancialStatementBalanceSheetSheet($this->report);
        }

        if (! empty($this->report['cogm'])) {
            $sheets[] = new FinancialStatementCogmSheet($this->report);
        }

        return $sheets;
    }
}

abstract class FinancialStatementSheet
{
    protected function applyTableFrame(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
        ]);
    }

    protected function applyTitleRow(Worksheet $sheet, string $range, string $fillColor): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $fillColor],
            ],
        ]);
    }

    protected function applySectionRow(Worksheet $sheet, int $row, string $endColumn): void
    {
        $sheet->getStyle("A{$row}:{$endColumn}{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F8FAFC'],
            ],
        ]);
    }

    protected function applyTotalRow(Worksheet $sheet, int $row, string $endColumn, string $fillColor): void
    {
        $sheet->getStyle("A{$row}:{$endColumn}{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $fillColor],
            ],
            'borders' => [
                'top' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color' => ['rgb' => '94A3B8'],
                ],
            ],
        ]);
    }

    protected function applyMoneyFormat(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }
}

class FinancialStatementSummarySheet extends FinancialStatementSheet implements FromArray, WithTitle, ShouldAutoSize, WithEvents
{
    public function __construct(protected array $report)
    {
    }

    public function title(): string
    {
        return 'Summary';
    }

    public function array(): array
    {
        return [
            ['Financial Statement Summary', ''],
            ['Statement Type', strtoupper((string) ($this->report['statement_type'] ?? 'ALL'))],
            ['Periode', (string) ($this->report['period_label'] ?? '-')],
            ['Cabang', (string) ($this->report['branch_name'] ?? 'Semua Cabang')],
            ['', ''],
            ['Pendapatan', (float) data_get($this->report, 'pl.revenue', 0)],
            ['Laba Bersih', (float) data_get($this->report, 'pl.net_profit', 0)],
            ['COGM', (float) data_get($this->report, 'cogm.cogm', 0)],
            ['Total Aset', (float) data_get($this->report, 'bs.total_assets', 0)],
            ['Total Liabilitas', (float) data_get($this->report, 'bs.total_liabilities', 0)],
            ['Total Ekuitas', (float) data_get($this->report, 'bs.total_equity', 0)],
            ['Liabilitas + Ekuitas', (float) data_get($this->report, 'bs.total_liabilities_and_equity', data_get($this->report, 'bs.total_liabilities', 0) + data_get($this->report, 'bs.total_equity', 0))],
            ['Status Neraca', data_get($this->report, 'bs.is_balanced', false) ? 'Seimbang' : 'Tidak Seimbang'],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                $sheet->mergeCells('A1:B1');
                $this->applyTitleRow($sheet, 'A1:B1', '0F766E');
                $this->applyTableFrame($sheet, 'A1:B13');
                $this->applyMoneyFormat($sheet, 'B6:B12');

                $sheet->getStyle('A2:B4')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F8FAFC'],
                    ],
                ]);

                $sheet->getStyle('A6:A13')->getAlignment()->setWrapText(true);
            },
        ];
    }
}

class FinancialStatementProfitLossSheet extends FinancialStatementSheet implements FromArray, WithTitle, ShouldAutoSize, WithEvents
{
    public function __construct(protected array $report)
    {
    }

    public function title(): string
    {
        return 'Profit Loss';
    }

    public function array(): array
    {
        $pl = $this->report['pl'] ?? [];
        $rows = [
            ['Section', 'Code', 'Account', 'Amount'],
        ];

        foreach ($pl['sales_revenue_accounts'] ?? [] as $account) {
            $rows[] = ['Pendapatan', $account['code'] ?? '', $account['name'] ?? '', (float) ($account['balance'] ?? 0)];
        }

        foreach ($pl['cogs_accounts'] ?? [] as $account) {
            $rows[] = ['HPP', $account['code'] ?? '', $account['name'] ?? '', (float) ($account['balance'] ?? 0)];
        }

        foreach ($pl['operating_expense_accounts'] ?? [] as $account) {
            $rows[] = ['OPEX', $account['code'] ?? '', $account['name'] ?? '', (float) ($account['balance'] ?? 0)];
        }

        foreach ($pl['other_income_accounts'] ?? [] as $account) {
            $rows[] = ['Pendapatan Lain', $account['code'] ?? '', $account['name'] ?? '', (float) ($account['balance'] ?? 0)];
        }

        foreach ($pl['other_expense_accounts'] ?? [] as $account) {
            $rows[] = ['Beban Lain', $account['code'] ?? '', $account['name'] ?? '', (float) ($account['balance'] ?? 0)];
        }

        foreach ($pl['tax_accounts'] ?? [] as $account) {
            $rows[] = ['Pajak', $account['code'] ?? '', $account['name'] ?? '', (float) ($account['balance'] ?? 0)];
        }

        $rows[] = ['', '', 'Total Pendapatan', (float) ($pl['revenue'] ?? 0)];
        $rows[] = ['', '', 'Total HPP', (float) ($pl['cogs'] ?? 0)];
        $rows[] = ['', '', 'Total OPEX', (float) ($pl['opex'] ?? 0)];
        $rows[] = ['', '', 'Laba Kotor', (float) ($pl['gross_profit'] ?? 0)];
        $rows[] = ['', '', 'Laba Usaha', (float) ($pl['operating_profit'] ?? 0)];
        $rows[] = ['', '', 'Laba Sebelum Pajak', (float) ($pl['profit_before_tax'] ?? 0)];
        $rows[] = ['', '', 'Laba Bersih', (float) ($pl['net_profit'] ?? 0)];

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                $this->applyTitleRow($sheet, 'A1:D1', '166534');
                $this->applyTableFrame($sheet, "A1:D{$highestRow}");
                $this->applyMoneyFormat($sheet, "D2:D{$highestRow}");

                for ($row = 2; $row <= $highestRow; $row++) {
                    $sectionLabel = trim((string) $sheet->getCell("A{$row}")->getValue());
                    $accountLabel = trim((string) $sheet->getCell("C{$row}")->getValue());

                    if ($sectionLabel !== '' && $accountLabel !== '' && ! str_starts_with($accountLabel, 'Total ')) {
                        $this->applySectionRow($sheet, $row, 'D');
                    }

                    if (str_starts_with($accountLabel, 'Total ')) {
                        $this->applyTotalRow($sheet, $row, 'D', 'DCFCE7');
                    }

                    if (in_array($accountLabel, ['Laba Kotor', 'Laba Usaha', 'Laba Sebelum Pajak', 'Laba Bersih'], true)) {
                        $this->applyTotalRow($sheet, $row, 'D', 'DBEAFE');
                    }
                }

                $sheet->getStyle('A2:C' . $highestRow)->getAlignment()->setWrapText(true);
            },
        ];
    }
}

class FinancialStatementBalanceSheetSheet extends FinancialStatementSheet implements FromArray, WithTitle, ShouldAutoSize, WithEvents
{
    public function __construct(protected array $report)
    {
    }

    public function title(): string
    {
        return 'Balance Sheet';
    }

    public function array(): array
    {
        $bs = $this->report['bs'] ?? [];
        $rows = [
            ['Section', 'Code', 'Account', 'Amount'],
        ];

        foreach ([
            'current_assets' => 'Aset Lancar',
            'fixed_assets' => 'Aset Tetap',
            'contra_assets' => 'Contra Asset',
            'current_liabilities' => 'Liabilitas Pendek',
            'long_term_liabilities' => 'Liabilitas Panjang',
            'equity' => 'Ekuitas',
        ] as $key => $label) {
            foreach (($bs[$key]['accounts'] ?? []) as $account) {
                $rows[] = [
                    $label,
                    (string) ($account->code ?? ''),
                    (string) ($account->name ?? ''),
                    (float) ($account->balance ?? 0),
                ];
            }

            $rows[] = ['', '', 'Total ' . $label, (float) data_get($bs, $key . '.total', 0)];
        }

        if (($bs['retained_earnings'] ?? 0) != 0) {
            $rows[] = ['Ekuitas', '', 'Laba Ditahan', (float) ($bs['retained_earnings'] ?? 0)];
        }

        $rows[] = ['', '', 'Total Aset', (float) ($bs['total_assets'] ?? 0)];
        $rows[] = ['', '', 'Total Liabilitas', (float) ($bs['total_liabilities'] ?? 0)];
        $rows[] = ['', '', 'Total Ekuitas', (float) ($bs['total_equity'] ?? 0)];
        $rows[] = ['', '', 'Liabilitas + Ekuitas', (float) data_get($bs, 'total_liabilities_and_equity', data_get($bs, 'total_liabilities', 0) + data_get($bs, 'total_equity', 0))];

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                $this->applyTitleRow($sheet, 'A1:D1', '1D4ED8');
                $this->applyTableFrame($sheet, "A1:D{$highestRow}");
                $this->applyMoneyFormat($sheet, "D2:D{$highestRow}");

                for ($row = 2; $row <= $highestRow; $row++) {
                    $sectionLabel = trim((string) $sheet->getCell("A{$row}")->getValue());
                    $accountLabel = trim((string) $sheet->getCell("C{$row}")->getValue());

                    if ($sectionLabel !== '' && $accountLabel !== '' && ! str_starts_with($accountLabel, 'Total ')) {
                        $this->applySectionRow($sheet, $row, 'D');
                    }

                    if (str_starts_with($accountLabel, 'Total ') || $accountLabel === 'Liabilitas + Ekuitas') {
                        $this->applyTotalRow($sheet, $row, 'D', 'E0F2FE');
                    }
                }

                $sheet->getStyle('A2:C' . $highestRow)->getAlignment()->setWrapText(true);
            },
        ];
    }
}

class FinancialStatementCogmSheet extends FinancialStatementSheet implements FromArray, WithTitle, ShouldAutoSize, WithEvents
{
    public function __construct(protected array $report)
    {
    }

    public function title(): string
    {
        return 'COGM';
    }

    public function array(): array
    {
        $cogm = $this->report['cogm'] ?? [];
        $rows = [
            ['Section', 'Label', 'Amount'],
            ['Bahan Baku', 'Opening', (float) data_get($cogm, 'raw_materials.opening', 0)],
            ['Bahan Baku', 'Purchases', (float) data_get($cogm, 'raw_materials.purchases', 0)],
            ['Bahan Baku', 'Available', (float) data_get($cogm, 'raw_materials.available', 0)],
            ['Bahan Baku', 'Closing', (float) data_get($cogm, 'raw_materials.closing', 0)],
            ['Bahan Baku', 'Used', (float) data_get($cogm, 'raw_materials.used', 0)],
            ['Produksi', 'Direct Labor', (float) data_get($cogm, 'direct_labor', 0)],
        ];

        foreach (data_get($cogm, 'overhead.items', []) as $item) {
            $rows[] = ['Overhead', (string) ($item['label'] ?? ''), (float) ($item['amount'] ?? 0)];
        }

        $rows[] = ['Overhead', 'Total', (float) data_get($cogm, 'overhead.total', 0)];
        $rows[] = ['WIP', 'Opening', (float) data_get($cogm, 'wip.opening', 0)];
        $rows[] = ['WIP', 'Closing', (float) data_get($cogm, 'wip.closing', 0)];
        $rows[] = ['Produksi', 'Production Cost', (float) data_get($cogm, 'production_cost', 0)];
        $rows[] = ['Produksi', 'COGM', (float) data_get($cogm, 'cogm', 0)];

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                $this->applyTitleRow($sheet, 'A1:C1', '92400E');
                $this->applyTableFrame($sheet, "A1:C{$highestRow}");
                $this->applyMoneyFormat($sheet, "C2:C{$highestRow}");

                for ($row = 2; $row <= $highestRow; $row++) {
                    $sectionLabel = trim((string) $sheet->getCell("A{$row}")->getValue());
                    $metricLabel = trim((string) $sheet->getCell("B{$row}")->getValue());

                    if ($sectionLabel !== '' && ! in_array($metricLabel, ['Total', 'COGM'], true)) {
                        $this->applySectionRow($sheet, $row, 'C');
                    }

                    if (in_array($metricLabel, ['Total', 'COGM'], true)) {
                        $this->applyTotalRow($sheet, $row, 'C', 'FEF3C7');
                    }
                }

                $sheet->getStyle('A2:C' . $highestRow)->getAlignment()->setWrapText(true);
            },
        ];
    }
}