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

class AlkGrafikExport implements WithMultipleSheets
{
    public function __construct(protected array $report)
    {
    }

    public function sheets(): array
    {
        return [
            new AlkGrafikSummarySheet($this->report),
            new AlkGrafikRatioSheet($this->report),
            new AlkGrafikCompositionSheet($this->report),
            new AlkGrafikTrendSheet($this->report),
        ];
    }
}

abstract class AlkGrafikSheet
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

    protected function applyMoneyFormat(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    protected function applyDecimalFormat(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getNumberFormat()->setFormatCode('0.00');
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    protected function applyPercentFormat(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getNumberFormat()->setFormatCode('0.00%');
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    protected function freezePane(Worksheet $sheet, string $coordinate): void
    {
        $sheet->freezePane($coordinate);
    }

    protected function boldRange(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true);
    }

    protected function highlightStatus(Worksheet $sheet, string $cell, string $status): void
    {
        $styles = match ($status) {
            'Seimbang' => [
                'fill' => 'DCFCE7',
                'font' => '166534',
            ],
            'Belum seimbang' => [
                'fill' => 'FEE2E2',
                'font' => '991B1B',
            ],
            'Baik' => [
                'fill' => 'DCFCE7',
                'font' => '166534',
            ],
            'Perlu perhatian' => [
                'fill' => 'FEE2E2',
                'font' => '991B1B',
            ],
            default => [
                'fill' => 'E2E8F0',
                'font' => '475569',
            ],
        };

        $sheet->getStyle($cell)->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $styles['fill']],
            ],
            'font' => [
                'bold' => true,
                'color' => ['rgb' => $styles['font']],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);
    }

    protected function ratioDefinitions(): array
    {
        return [
            ['label' => 'Current Ratio', 'key' => 'current_ratio', 'unit' => 'x', 'note' => 'Likuiditas ideal minimal 1.50x.', 'ok' => fn (?float $value) => $value !== null && $value >= 1.5],
            ['label' => 'Debt to Equity', 'key' => 'debt_to_equity', 'unit' => 'x', 'note' => 'Semakin rendah semakin aman.', 'ok' => fn (?float $value) => $value !== null && $value <= 1],
            ['label' => 'ROA', 'key' => 'roa', 'unit' => '%', 'note' => 'Aset menghasilkan laba positif.', 'ok' => fn (?float $value) => $value !== null && $value > 0],
            ['label' => 'ROE', 'key' => 'roe', 'unit' => '%', 'note' => 'Pengembalian modal seharusnya positif.', 'ok' => fn (?float $value) => $value !== null && $value > 0],
            ['label' => 'Profit Margin', 'key' => 'profit_margin', 'unit' => '%', 'note' => 'Margin positif menandakan operasi sehat.', 'ok' => fn (?float $value) => $value !== null && $value > 0],
        ];
    }

    protected function ratioRows(array $report): array
    {
        $rows = [];

        foreach ($this->ratioDefinitions() as $definition) {
            $value = data_get($report, 'ratios.' . $definition['key']);
            $rows[] = [
                $definition['label'],
                $value,
                $definition['unit'],
                $value === null ? 'N/A' : ($definition['ok']($value) ? 'Baik' : 'Perlu perhatian'),
                $definition['note'],
            ];
        }

        return $rows;
    }

    protected function percentOf(float $value, float $base): ?float
    {
        if ($base == 0.0) {
            return null;
        }

        return $value / $base;
    }
}

class AlkGrafikSummarySheet extends AlkGrafikSheet implements FromArray, WithTitle, ShouldAutoSize, WithEvents
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
            ['ALK Grafik Summary', ''],
            ['Periode', (string) ($this->report['period_label'] ?? '-')],
            ['Cabang', (string) ($this->report['branch_name'] ?? 'Semua Cabang')],
            ['Status Neraca', data_get($this->report, 'summary.is_balanced') ? 'Seimbang' : 'Belum seimbang'],
            ['Selisih Neraca', (float) data_get($this->report, 'summary.difference', 0)],
            ['', ''],
            ['Ringkasan', 'Nilai'],
            ['Total Aset', (float) data_get($this->report, 'summary.total_assets', 0)],
            ['Total Liabilitas', (float) data_get($this->report, 'summary.total_liabilities', 0)],
            ['Total Ekuitas', (float) data_get($this->report, 'summary.total_equity', 0)],
            ['Aset Lancar', (float) data_get($this->report, 'summary.current_assets', 0)],
            ['Liabilitas Lancar', (float) data_get($this->report, 'summary.current_liabilities', 0)],
            ['Pendapatan', (float) data_get($this->report, 'summary.revenue', 0)],
            ['Beban', (float) data_get($this->report, 'summary.expense', 0)],
            ['Laba Bersih', (float) data_get($this->report, 'summary.net_profit', 0)],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                $sheet->mergeCells('A1:B1');
                $this->applyTitleRow($sheet, 'A1:B1', '0F766E');
                $this->applyTitleRow($sheet, 'A7:B7', '1E3A8A');
                $this->applyTableFrame($sheet, 'A1:B15');
                $this->applyMoneyFormat($sheet, 'B5:B5');
                $this->applyMoneyFormat($sheet, 'B8:B15');
                $this->freezePane($sheet, 'A8');
                $this->boldRange($sheet, 'A8:B10');
                $this->boldRange($sheet, 'A13:B15');

                $sheet->getStyle('A2:B3')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F8FAFC'],
                    ],
                ]);
                $this->highlightStatus($sheet, 'B4', (string) data_get($this->report, 'summary.is_balanced') ? 'Seimbang' : 'Belum seimbang');
            },
        ];
    }
}

class AlkGrafikRatioSheet extends AlkGrafikSheet implements FromArray, WithTitle, ShouldAutoSize, WithEvents
{
    public function __construct(protected array $report)
    {
    }

    public function title(): string
    {
        return 'Ratios';
    }

    public function array(): array
    {
        return array_merge([
            ['Metrik', 'Nilai', 'Unit', 'Status', 'Interpretasi'],
        ], $this->ratioRows($this->report));
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                $this->applyTitleRow($sheet, 'A1:E1', '0F766E');
                $this->applyTableFrame($sheet, "A1:E{$highestRow}");
                $this->applyDecimalFormat($sheet, "B2:B{$highestRow}");
                $sheet->getStyle("C2:D{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $this->freezePane($sheet, 'A2');

                foreach ($this->ratioRows($this->report) as $offset => $row) {
                    $this->highlightStatus($sheet, 'D' . ($offset + 2), (string) $row[3]);
                }
            },
        ];
    }
}

class AlkGrafikCompositionSheet extends AlkGrafikSheet implements FromArray, WithTitle, ShouldAutoSize, WithEvents
{
    public function __construct(protected array $report)
    {
    }

    public function title(): string
    {
        return 'Composition';
    }

    public function array(): array
    {
        $summary = $this->report['summary'] ?? [];
        $totalAssets = (float) ($summary['total_assets'] ?? 0);
        $totalLiabilities = (float) ($summary['total_liabilities'] ?? 0);
        $revenue = (float) ($summary['revenue'] ?? 0);
        $netWorkingCapital = (float) ($summary['current_assets'] ?? 0) - (float) ($summary['current_liabilities'] ?? 0);

        return [
            ['Komponen', 'Nilai', 'Basis', 'Porsi'],
            ['Aset Lancar', (float) ($summary['current_assets'] ?? 0), 'Terhadap Total Aset', $this->percentOf((float) ($summary['current_assets'] ?? 0), $totalAssets)],
            ['Liabilitas Lancar', (float) ($summary['current_liabilities'] ?? 0), 'Terhadap Total Liabilitas', $this->percentOf((float) ($summary['current_liabilities'] ?? 0), $totalLiabilities)],
            ['Total Liabilitas', (float) ($summary['total_liabilities'] ?? 0), 'Terhadap Total Aset', $this->percentOf((float) ($summary['total_liabilities'] ?? 0), $totalAssets)],
            ['Total Ekuitas', (float) ($summary['total_equity'] ?? 0), 'Terhadap Total Aset', $this->percentOf((float) ($summary['total_equity'] ?? 0), $totalAssets)],
            ['Modal Kerja Bersih', $netWorkingCapital, 'Terhadap Total Aset', $this->percentOf($netWorkingCapital, $totalAssets)],
            ['Laba Bersih', (float) ($summary['net_profit'] ?? 0), 'Terhadap Pendapatan', $this->percentOf((float) ($summary['net_profit'] ?? 0), $revenue)],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                $this->applyTitleRow($sheet, 'A1:D1', '1E3A8A');
                $this->applyTableFrame($sheet, "A1:D{$highestRow}");
                $this->applyMoneyFormat($sheet, "B2:B{$highestRow}");
                $this->applyPercentFormat($sheet, "D2:D{$highestRow}");
                $this->freezePane($sheet, 'A2');
                $this->boldRange($sheet, 'A4:D7');
            },
        ];
    }
}

class AlkGrafikTrendSheet extends AlkGrafikSheet implements FromArray, WithTitle, ShouldAutoSize, WithEvents
{
    public function __construct(protected array $report)
    {
    }

    public function title(): string
    {
        return 'Trend';
    }

    public function array(): array
    {
        $rows = [
            ['Bulan', 'Pendapatan', 'Beban', 'Laba Bersih', 'Margin Bersih (%)'],
        ];

        $totalRevenue = 0.0;
        $totalExpense = 0.0;
        $totalProfit = 0.0;
        $profitRows = 0;

        foreach ($this->report['trend'] ?? [] as $row) {
            $revenue = (float) ($row['revenue'] ?? 0);
            $expense = (float) ($row['expense'] ?? 0);
            $profit = (float) ($row['profit'] ?? 0);

            $rows[] = [
                $row['month'] ?? '-',
                $revenue,
                $expense,
                $profit,
                $revenue > 0 ? round(($profit / $revenue) * 100, 2) : null,
            ];

            $totalRevenue += $revenue;
            $totalExpense += $expense;
            $totalProfit += $profit;
            $profitRows++;
        }

        if ($profitRows > 0) {
            $rows[] = [
                'TOTAL',
                $totalRevenue,
                $totalExpense,
                $totalProfit,
                $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 2) : null,
            ];
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                $this->applyTitleRow($sheet, 'A1:E1', '1E3A8A');
                $this->applyTableFrame($sheet, "A1:E{$highestRow}");
                $this->applyMoneyFormat($sheet, "B2:D{$highestRow}");
                $this->applyDecimalFormat($sheet, "E2:E{$highestRow}");
                $this->freezePane($sheet, 'A2');
                $this->boldRange($sheet, "A{$highestRow}:E{$highestRow}");
            },
        ];
    }
}