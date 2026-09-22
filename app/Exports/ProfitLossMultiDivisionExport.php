<?php

namespace App\Exports;

use App\Support\Reports\ProfitLossMultiDivisionExportPresenter;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Excel export for the Profit & Loss Multiple By Division report.
 *
 * Accepts the array produced by ProfitLossMultiDivisionService::generate().
 */
class ProfitLossMultiDivisionExport implements FromArray, ShouldAutoSize, WithTitle, WithEvents
{
    private array $reportData;

    public function __construct(array $reportData)
    {
        $this->reportData = $reportData;
    }

    public function title(): string
    {
        return 'Profit Loss Multi Division';
    }

    public function array(): array
    {
        return app(ProfitLossMultiDivisionExportPresenter::class)->rows($this->reportData);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $data  = $this->reportData;
                $divCount = count($data['divisions'] ?? []);

                // ── Company name row (row 1) ─────────────────────────────────
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 13, 'color' => ['argb' => 'FFCC0000']],
                ]);

                // ── Report title row (row 2) ─────────────────────────────────
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12],
                ]);

                // ── Column header row (row 5) ────────────────────────────────
                $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(2 + $divCount * 2);
                $headerRange = 'A5:' . $lastCol . '5';
                $sheet->getStyle($headerRange)->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2B5FA5']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // ── Highlight Gross Profit row ──────────────────────────────
                $highestRow = $sheet->getHighestRow();
                for ($r = 1; $r <= $highestRow; $r++) {
                    $cellVal = $sheet->getCell('B' . $r)->getValue();
                    if ($cellVal === 'Gross Profit') {
                        $gpRange = 'A' . $r . ':' . $lastCol . $r;
                        $sheet->getStyle($gpRange)->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF06B6D4']],
                        ]);
                    }
                    if ($cellVal === 'Net Profit' || $cellVal === 'Net Loss') {
                        $npRange = 'A' . $r . ':' . $lastCol . $r;
                        $sheet->getStyle($npRange)->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF065F46']],
                        ]);
                    }
                    // Bold subtotals / totals
                    if (str_starts_with((string) $cellVal, 'Total ') || $cellVal === 'Operating Profit (EBIT)') {
                        $sheet->getStyle('B' . $r)->applyFromArray([
                            'font' => ['bold' => true],
                        ]);
                    }
                }
            },
        ];
    }
}
