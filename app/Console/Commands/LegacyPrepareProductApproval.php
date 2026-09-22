<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class LegacyPrepareProductApproval extends Command
{
    protected $signature = 'legacy:prepare-product-approval
        {file : Path CSV hasil legacy:export-product-approval-files}
        {--output= : Path output CSV ter-prefill. Default membuat file baru di folder yang sama}
        {--shortlist-output= : Path output markdown shortlist. Default membuat file baru di folder yang sama}
        {--limit=25 : Jumlah baris shortlist yang ditulis}
        {--approval-status=APPROVE : Nilai approval status yang akan dipakai}
        {--notes=Auto-prefilled from category,biaya shortlist : Notes yang ditulis ke file output}';

    protected $description = 'Prefill approval CSV product bucket dan buat shortlist markdown dari file approval existing';

    public function handle(): int
    {
        $inputPath = $this->resolvePath((string) $this->argument('file'));
        $outputPath = $this->resolveOutputPath((string) $this->option('output'), $inputPath, '_prefilled.csv');
        $shortlistPath = $this->resolveOutputPath((string) $this->option('shortlist-output'), $inputPath, '_shortlist.md');
        $limit = max(1, (int) $this->option('limit'));
        $approvalStatus = trim((string) $this->option('approval-status')) ?: 'APPROVE';
        $notes = trim((string) $this->option('notes'));

        if (! is_file($inputPath)) {
            $this->error("File approval tidak ditemukan: {$inputPath}");
            return self::FAILURE;
        }

        $rows = $this->readCsv($inputPath);
        if ($rows === []) {
            $this->warn('File approval kosong.');
            return self::SUCCESS;
        }

        $preparedRows = [];
        foreach ($rows as $row) {
            $row['approval_status'] = $approvalStatus;
            $row['approved_canonical_sku'] = $this->filledString($row['approved_canonical_sku'] ?? null) ?: $this->filledString($row['recommended_canonical_sku'] ?? null);
            $row['approved_category_id'] = $this->filledString($row['approved_category_id'] ?? null) ?: $this->filledString($row['recommended_category_id'] ?? null);
            $row['approved_biaya'] = $this->filledString($row['approved_biaya'] ?? null) ?: $this->filledString($row['recommended_biaya'] ?? null);
            $row['notes'] = $notes;
            $preparedRows[] = $row;
        }

        $headers = array_keys($preparedRows[0]);
        $this->writeCsv($outputPath, $headers, $preparedRows);

        $shortlist = $this->buildShortlist($preparedRows, $limit);
        $this->writeMarkdown($shortlistPath, $shortlist);

        $this->info('Product approval CSV prefilled.');
        $this->table(
            ['Metric', 'Value'],
            [
                ['input_rows', count($rows)],
                ['output_rows', count($preparedRows)],
                ['approval_status', $approvalStatus],
                ['output', $outputPath],
                ['shortlist', $shortlistPath],
            ]
        );

        return self::SUCCESS;
    }

    private function resolvePath(string $file): string
    {
        return str_starts_with($file, DIRECTORY_SEPARATOR)
            ? $file
            : base_path($file);
    }

    private function resolveOutputPath(string $output, string $inputPath, string $suffix): string
    {
        if ($output !== '') {
            return $this->resolvePath($output);
        }

        $pathInfo = pathinfo($inputPath);
        return ($pathInfo['dirname'] ?? '.') . DIRECTORY_SEPARATOR . ($pathInfo['filename'] ?? 'approval') . $suffix;
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $headers = fgetcsv($handle) ?: [];
        $headers = array_map(fn ($value) => strtolower(trim((string) $value)), $headers);
        $rows = [];

        while (($values = fgetcsv($handle)) !== false) {
            if (! array_filter($values, fn ($value) => trim((string) $value) !== '')) {
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }

                $row[$header] = $values[$index] ?? null;
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    private function writeCsv(string $path, array $headers, array $rows): void
    {
        $handle = fopen($path, 'w');
        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn ($header) => $row[$header] ?? '', $headers));
        }

        fclose($handle);
    }

    private function buildShortlist(array $rows, int $limit): string
    {
        usort($rows, function (array $left, array $right) {
            $leftRows = (int) ($left['row_count'] ?? 0);
            $rightRows = (int) ($right['row_count'] ?? 0);

            return [$leftRows, (string) ($left['target_sku'] ?? '')] <=> [$rightRows, (string) ($right['target_sku'] ?? '')];
        });

        $sampleRows = array_slice($rows, 0, $limit);
        $distribution = [];
        foreach ($rows as $row) {
            $rowCount = (int) ($row['row_count'] ?? 0);
            $distribution[$rowCount] = ($distribution[$rowCount] ?? 0) + 1;
        }

        $lines = [
            '# Category, Biaya Approval Shortlist',
            '',
            '- Total groups: ' . count($rows),
            '- Shortlist rows: ' . count($sampleRows),
            '',
            '## Row Count Distribution',
            '',
            '| Row Count | Groups |',
            '| --- | ---: |',
        ];

        ksort($distribution);
        foreach ($distribution as $rowCount => $groups) {
            $lines[] = sprintf('| %d | %d |', $rowCount, $groups);
        }

        $lines[] = '';
        $lines[] = '## Shortlist';
        $lines[] = '';
        $lines[] = '| Target SKU | Rows | Recommended Canonical SKU | Category | Biaya | Candidate SKUs |';
        $lines[] = '| --- | ---: | --- | ---: | ---: | --- |';

        foreach ($sampleRows as $row) {
            $lines[] = sprintf(
                '| %s | %d | %s | %s | %s | %s |',
                (string) ($row['target_sku'] ?? ''),
                (int) ($row['row_count'] ?? 0),
                (string) ($row['recommended_canonical_sku'] ?? ''),
                (string) ($row['recommended_category_id'] ?? ''),
                (string) ($row['recommended_biaya'] ?? ''),
                (string) ($row['candidate_skus'] ?? '')
            );
        }

        $lines[] = '';
        $lines[] = '## Notes';
        $lines[] = '';
        $lines[] = '- Semua grup dalam file source ini berada pada reason bucket `category,biaya`.';
        $lines[] = '- Dalam state saat ini, semua grup yang tersisa memiliki 2 row saja.';
        $lines[] = '- Prefill ini memakai rekomendasi canonical yang sudah tersedia pada file source.';

        return implode("\n", $lines) . "\n";
    }

    private function writeMarkdown(string $path, string $markdown): void
    {
        $handle = fopen($path, 'w');
        fwrite($handle, $markdown);
        fclose($handle);
    }

    private function filledString(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}