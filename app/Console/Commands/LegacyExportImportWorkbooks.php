<?php

namespace App\Console\Commands;

use App\Services\LegacyInventoryMigrationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class LegacyExportImportWorkbooks extends Command
{
    protected $signature = 'legacy:export-import-workbooks
        {--source=* : inventory dan/atau inventory_cab. Omit untuk generate keduanya}
        {--output-dir=docs : Direktori output workbook}
        {--split-files : Generate juga file XLSX terpisah per tabel/sheet}
        {--split-csv : Generate juga file CSV terpisah per tabel/sheet}
        {--limit=0 : Batasi row per sheet untuk validasi cepat}';

    protected $description = 'Generate workbook Excel import-ready dari database legacy inventory dan inventory_cab';

    public function handle(LegacyInventoryMigrationService $service): int
    {
        $sources = $this->normalizeSources($this->option('source'));
        $outputDir = $this->resolveOutputDir((string) $this->option('output-dir'));
        $splitFiles = (bool) $this->option('split-files');
        $splitCsv = (bool) $this->option('split-csv');
        $limit = max(0, (int) $this->option('limit'));

        File::ensureDirectoryExists($outputDir);
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $rows = [];

        foreach ($sources as $source) {
            $defaults = $this->sourceDefaults($source);
            $workbook = $service->buildImportWorkbookData($source, [
                'cabang_id' => $defaults['cabang_id'],
                'warehouse_id' => $defaults['warehouse_id'],
                'key_prefix' => $defaults['key_prefix'],
                'limit' => $limit,
            ]);

            $path = $outputDir . DIRECTORY_SEPARATOR . $defaults['filename'];
            $this->writeWorkbook($workbook, $path);

            if ($splitFiles) {
                $this->writeSplitWorkbooks($workbook, $source, $outputDir);
            }

            if ($splitCsv) {
                $this->writeSplitCsvFiles($workbook, $source, $outputDir);
            }

            $summarySheet = $workbook['summary']['rows'] ?? [];
            $totalRows = array_sum(array_map(fn (array $row) => (int) $row['rows'], $summarySheet));

            $rows[] = [
                $source,
                $path,
                $defaults['cabang_id'],
                $defaults['warehouse_id'],
                $defaults['key_prefix'] ?: '-',
                $totalRows,
            ];
        }

        $this->table(
            ['Source', 'File', 'Cabang', 'Warehouse', 'Key Prefix', 'Total Rows'],
            $rows,
        );

        return self::SUCCESS;
    }

    private function normalizeSources(array $sources): array
    {
        $sources = array_values(array_filter(array_map('trim', $sources)));

        if ($sources === []) {
            return ['inventory', 'inventory_cab'];
        }

        $allowed = ['inventory', 'inventory_cab'];

        foreach ($sources as $source) {
            if (! in_array($source, $allowed, true)) {
                throw new \InvalidArgumentException('Source tidak valid. Gunakan inventory atau inventory_cab.');
            }
        }

        return array_values(array_unique($sources));
    }

    private function resolveOutputDir(string $outputDir): string
    {
        if ($outputDir === '') {
            return base_path('docs');
        }

        return str_starts_with($outputDir, DIRECTORY_SEPARATOR)
            ? $outputDir
            : base_path($outputDir);
    }

    private function sourceDefaults(string $source): array
    {
        $date = now()->format('Ymd');

        return match ($source) {
            'inventory' => [
                'cabang_id' => 2,
                'warehouse_id' => 2,
                'key_prefix' => null,
                'filename' => "legacy-import-inventory-{$date}.xlsx",
            ],
            'inventory_cab' => [
                'cabang_id' => 3,
                'warehouse_id' => 3,
                'key_prefix' => 'CAB-',
                'filename' => "legacy-import-inventory-cab-{$date}.xlsx",
            ],
        };
    }

    private function writeWorkbook(array $workbook, string $path): void
    {
        $spreadsheet = new Spreadsheet();
        $sheetIndex = 0;

        foreach ($workbook as $sheetName => $sheetData) {
            $sheet = $sheetIndex === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $sheet->setTitle(substr($sheetName, 0, 31));

            $columns = $sheetData['columns'] ?? [];
            $rows = $sheetData['rows'] ?? [];
            $sheet->fromArray([$columns], null, 'A1', true);

            $rowIndex = 2;
            foreach ($rows as $row) {
                $sheet->fromArray([array_map(fn (string $column) => $row[$column] ?? '', $columns)], null, 'A' . $rowIndex, true);
                $rowIndex++;
            }

            if ($columns !== []) {
                $lastColumn = Coordinate::stringFromColumnIndex(count($columns));
                $lastRow = max(1, count($rows) + 1);

                $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
                $sheet->getStyle("A1:{$lastColumn}1")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFEFEFEF');
                $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");
            }

            $sheet->freezePane('A2');
            foreach (range(1, max(1, count($columns))) as $index) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
            }

            $sheetIndex++;
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();
        unset($writer, $spreadsheet);
        gc_collect_cycles();
    }

    private function writeSplitWorkbooks(array $workbook, string $source, string $outputDir): void
    {
        $date = now()->format('Ymd');

        foreach ($workbook as $sheetName => $sheetData) {
            if (in_array($sheetName, ['meta', 'summary'], true)) {
                continue;
            }

            $summaryRow = [
                [
                    'sheet' => $sheetName,
                    'rows' => count($sheetData['rows'] ?? []),
                ],
            ];

            $splitWorkbook = [
                'meta' => $workbook['meta'],
                'summary' => [
                    'columns' => ['sheet', 'rows'],
                    'rows' => $summaryRow,
                ],
                $sheetName => $sheetData,
            ];

            $fileName = sprintf('legacy-import-%s-%s-%s.xlsx', str_replace('_', '-', $source), $sheetName, $date);
            $this->writeWorkbook($splitWorkbook, $outputDir . DIRECTORY_SEPARATOR . $fileName);
        }
    }

    private function writeSplitCsvFiles(array $workbook, string $source, string $outputDir): void
    {
        $date = now()->format('Ymd');

        foreach ($workbook as $sheetName => $sheetData) {
            if (in_array($sheetName, ['meta', 'summary'], true)) {
                continue;
            }

            $fileName = sprintf('legacy-import-%s-%s-%s.csv', str_replace('_', '-', $source), $sheetName, $date);
            File::put(
                $outputDir . DIRECTORY_SEPARATOR . $fileName,
                $this->csvFromSheetData($sheetData)
            );
        }
    }

    private function csvFromSheetData(array $sheetData): string
    {
        $handle = fopen('php://temp', 'r+');
        $columns = $sheetData['columns'] ?? [];
        $rows = $sheetData['rows'] ?? [];

        fputcsv($handle, $columns);

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn (string $column) => $row[$column] ?? '', $columns));
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return (string) $csv;
    }
}