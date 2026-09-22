<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class LegacyPrefillProductApprovalFile extends Command
{
    protected $signature = 'legacy:prefill-product-approval-file
        {file : Path CSV approval file}
        {--output= : Path output CSV. Default menambah suffix -prefilled}
        {--status=APPROVE : Nilai approval_status yang diisikan}';

    protected $description = 'Prefill approval CSV dengan rekomendasi canonical/category/biaya agar siap dieksekusi atau direview cepat';

    public function handle(): int
    {
        $inputPath = $this->resolvePath((string) $this->argument('file'));
        $outputPath = $this->resolveOutputPath($inputPath, (string) $this->option('output'));
        $status = trim((string) $this->option('status')) ?: 'APPROVE';

        if (! is_file($inputPath)) {
            $this->error("Approval file tidak ditemukan: {$inputPath}");
            return self::FAILURE;
        }

        [$headers, $rows] = $this->readCsv($inputPath);

        $prefilledRows = array_map(function (array $row) use ($status) {
            if (array_key_exists('recommended_canonical_sku', $row) && array_key_exists('approved_canonical_sku', $row) && trim((string) $row['approved_canonical_sku']) === '') {
                $row['approved_canonical_sku'] = $row['recommended_canonical_sku'];
            }

            if (array_key_exists('recommended_category_id', $row) && array_key_exists('approved_category_id', $row) && trim((string) $row['approved_category_id']) === '') {
                $row['approved_category_id'] = $row['recommended_category_id'];
            }

            if (array_key_exists('recommended_biaya', $row) && array_key_exists('approved_biaya', $row) && trim((string) $row['approved_biaya']) === '') {
                $row['approved_biaya'] = $row['recommended_biaya'];
            }

            if (array_key_exists('approval_status', $row) && trim((string) $row['approval_status']) === '') {
                $row['approval_status'] = $status;
            }

            if (array_key_exists('notes', $row)) {
                $note = trim((string) ($row['notes'] ?? ''));
                $row['notes'] = $note === ''
                    ? 'Auto-prefilled from approval recommendations'
                    : $note;
            }

            return $row;
        }, $rows);

        $this->writeCsv($outputPath, $headers, $prefilledRows);

        $this->info('Approval file prefilled.');
        $this->table(
            ['Metric', 'Value'],
            [
                ['input_rows', count($rows)],
                ['output', $outputPath],
                ['status', $status],
            ]
        );

        return self::SUCCESS;
    }

    private function resolvePath(string $path): string
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            ? $path
            : base_path($path);
    }

    private function resolveOutputPath(string $inputPath, string $output): string
    {
        if (trim($output) !== '') {
            return $this->resolvePath($output);
        }

        $pathInfo = pathinfo($inputPath);
        return ($pathInfo['dirname'] ?? '.') . DIRECTORY_SEPARATOR . ($pathInfo['filename'] ?? 'approval') . '-prefilled.' . ($pathInfo['extension'] ?? 'csv');
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $headers = fgetcsv($handle) ?: [];
        $normalizedHeaders = array_map(fn ($value) => strtolower(trim((string) $value)), $headers);
        $rows = [];

        while (($values = fgetcsv($handle)) !== false) {
            if (! array_filter($values, fn ($value) => trim((string) $value) !== '')) {
                continue;
            }

            $row = [];
            foreach ($normalizedHeaders as $index => $header) {
                if ($header === '') {
                    continue;
                }

                $row[$header] = $values[$index] ?? null;
            }
            $rows[] = $row;
        }

        fclose($handle);

        return [$normalizedHeaders, $rows];
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
}