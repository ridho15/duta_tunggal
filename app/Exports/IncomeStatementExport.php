<?php

namespace App\Exports;

use App\Services\IncomeStatementService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class IncomeStatementExport implements FromCollection, ShouldAutoSize, WithHeadings, WithEvents
{
    protected Collection $exportRows;

    public function __construct(private string $startDate, private string $endDate, private ?int $cabangId = null) {}

    public function collection(): Collection
    {
        // Generate grouped data using the same service as the page
        $incomeStatementService = app(IncomeStatementService::class);
        $incomeData = $incomeStatementService->getGroupedByParent([
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'cabang_id' => $this->cabangId,
        ]);

        $rows = collect();

        // Header information
        $rows->push(['Laporan Laba Rugi']);
        $rows->push(['Periode: ' . $this->startDate . ' - ' . $this->endDate]);

        if ($this->cabangId) {
            $cabang = \App\Models\Cabang::find($this->cabangId);
            if ($cabang) {
                $rows->push(['Cabang: (' . $cabang->kode . ') ' . $cabang->nama]);
            }
        }

        $rows->push(['']);
        $rows->push(['Tanggal Export: ' . now()->format('d M Y H:i:s')]);
        $rows->push(['']);

        // Flatten the grouped data structure to include parent subtotals and section totals
        $this->addGroupedSectionToRows($rows, $incomeData, 'sales_revenue', 'Pendapatan Usaha');
        $this->addGroupedSectionToRows($rows, $incomeData, 'cogs', 'Harga Pokok Penjualan');
        // Add Gross Profit after COGS
        $rows->push(['', 'LABA KOTOR (Gross Profit)', $incomeData['gross_profit'] ?? 0]);

        $this->addGroupedSectionToRows($rows, $incomeData, 'operating_expenses', 'Biaya Operasional');
        // Add Operating Profit after operating expenses
        $rows->push(['', 'LABA OPERASIONAL (Operating Profit)', $incomeData['operating_profit'] ?? 0]);

        $this->addGroupedSectionToRows($rows, $incomeData, 'other_income', 'Pendapatan Lain');
        $this->addGroupedSectionToRows($rows, $incomeData, 'other_expense', 'Biaya Lain');
        // Add Profit Before Tax
        $rows->push(['', 'LABA SEBELUM PAJAK (Profit Before Tax)', $incomeData['profit_before_tax'] ?? 0]);

        $this->addGroupedSectionToRows($rows, $incomeData, 'tax_expense', 'Pajak');
        // Add Net Profit after tax
        $rows->push(['', 'LABA BERSIH (Net Profit)', $incomeData['net_profit'] ?? 0]);

        // Keep a copy for styling in AfterSheet
        $this->exportRows = $rows;

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                // Basic formatting: make header rows (title / period) bold and merge cells
                for ($r = 1; $r <= 6; $r++) {
                    $val = trim((string) $sheet->getCell('A' . $r)->getValue());
                    if (empty($val)) {
                        continue;
                    }
                        // Merge across 3 columns for title-like rows (Kode, Deskripsi, Jumlah)
                        $sheet->mergeCells("A{$r}:C{$r}");
                        $sheet->getStyle("A{$r}:C{$r}")->getFont()->setBold(true)->setSize(12);
                }

                // Style section headers, subtotals and totals using exact colours
                $sectionNames = [
                    'Pendapatan Usaha',
                    'Harga Pokok Penjualan',
                    'Biaya Operasional',
                    'Pendapatan Lain',
                    'Biaya Lain',
                    'Pajak',
                ];

                // Hex colours from requirements
                $colourSectionHeader = '2C7BE5'; // blue
                $colourTotal = 'D9534F'; // red
                $colourSubtotal = 'FFF2CC'; // light yellow
                $colourProfit = '5CB85C'; // green

                for ($row = 1; $row <= $highestRow; $row++) {
                    $cellB = trim((string) $sheet->getCell('B' . $row)->getValue());
                    if ($cellB === '') {
                        continue;
                    }

                    // Section header
                    if (in_array($cellB, $sectionNames, true)) {
                        $sheet->mergeCells("A{$row}:C{$row}");
                        $sheet->getStyle("A{$row}:C{$row}")->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colourSectionHeader]],
                        ]);
                        continue;
                    }

                    // Subtotal row (starts with 'Subtotal ')
                    if (str_starts_with($cellB, 'Subtotal')) {
                        $sheet->getStyle("A{$row}:C{$row}")->applyFromArray([
                            'font' => ['bold' => true],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colourSubtotal]],
                            'borders' => ['top' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
                        ]);
                        continue;
                    }

                    // Section total row (starts with 'Total ')
                    if (str_starts_with($cellB, 'Total ')) {
                        $sheet->getStyle("A{$row}:C{$row}")->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colourTotal]],
                            'borders' => ['top' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM]],
                        ]);
                        continue;
                    }

                    // Profit / computed rows (contains 'LABA')
                    if (stripos($cellB, 'LABA') !== false) {
                        $sheet->getStyle("A{$row}:C{$row}")->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colourProfit]],
                            'borders' => ['top' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM]],
                        ]);
                        continue;
                    }
                }

                // Ensure alignment for amount column
                $sheet->getStyle("C1:C{$highestRow}")->getAlignment()->setHorizontal(
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT
                );

                // Format numbers in column C with thousand separators and negative numbers in red parentheses
                // Format code: positive;negative;zero
                // #,##0 = number with thousand separator
                // [Red](#,##0) = negative numbers in red with parentheses
                $sheet->getStyle("C1:C{$highestRow}")->getNumberFormat()->setFormatCode('#,##0;[Red](#,##0);0');

                // Indent child rows by applying an indent where description starts with spaces
                for ($row = 1; $row <= $highestRow; $row++) {
                    $cellB = (string) $sheet->getCell('B' . $row)->getValue();
                    if (preg_match('/^\s{2,}/', $cellB)) {
                        $sheet->getStyle("B{$row}")->getAlignment()->setIndent(1);
                    }
                }
            },
        ];
    }

    private function addSectionToRows(Collection &$rows, array $incomeData, string $sectionKey, string $sectionName): void
    {
        // Legacy method kept for compatibility but grouped export uses addGroupedSectionToRows
        if (!isset($incomeData[$sectionKey]['accounts'])) {
            return;
        }

        $rows->push([$sectionName]);

        foreach ($incomeData[$sectionKey]['accounts'] as $account) {
            $label = $account['account_name'] ?? $account['name'] ?? ($account['code'] ?? '');
            $debit = $account['debit'] ?? $account['total_debit'] ?? 0;
            $credit = $account['credit'] ?? $account['total_credit'] ?? 0;
            $balance = $account['balance'] ?? ($debit - $credit);

            $rows->push([
                $label,
                $debit,
                $credit,
                $balance,
            ]);
        }

        // Add total for the section
        $rows->push([
            'Total ' . $sectionName,
            '',
            '',
            $incomeData[$sectionKey]['total'] ?? 0,
        ]);

        $rows->push(['']);
    }

    private function addGroupedSectionToRows(Collection &$rows, array $incomeData, string $sectionKey, string $sectionName): void
    {
        if (!isset($incomeData[$sectionKey]['grouped'])) {
            // Fallback to legacy shape
            $this->addSectionToRows($rows, $incomeData, $sectionKey, $sectionName);
            return;
        }

        $rows->push([$sectionName]);

        $groups = $incomeData[$sectionKey]['grouped'];
        foreach ($groups as $group) {
            $parent = $group['account'] ?? null;
            $children = $group['children'] ?? collect();

            if ($parent) {
                $rows->push([$parent['code'] ?? '', $parent['name'] ?? '', $parent['balance'] ?? 0]);
            }

            foreach ($children as $child) {
                // Add spaces to indent child rows visually
                $childName = '  ' . ($child['name'] ?? '');
                $rows->push([$child['code'] ?? '', $childName, $child['balance'] ?? 0]);
            }

            // No subtotal per parent - balance already includes children
        }

        // Section total
        $rows->push(['', 'Total ' . $sectionName, $incomeData[$sectionKey]['total'] ?? 0]);
        $rows->push(['', '', '']);
    }

    public function headings(): array
    {
        return [
            'Kode',
            'Deskripsi',
            'Jumlah'
        ];
    }
}