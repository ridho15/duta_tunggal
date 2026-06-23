<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class LegacyPrepareCategoryApproval extends Command
{
    protected $signature = 'legacy:prepare-category-approval
        {file : Path CSV hasil legacy:export-category-approval}
        {--output= : Path output CSV ter-prepare. Default membuat file baru di folder yang sama}
        {--approve-modes=exact,biaya,qty-min : Mode rekomendasi yang otomatis di-approve}
        {--reject-manual : Tandai row manual sebagai rejected daripada pending}';

    protected $description = 'Siapkan CSV approval kategori dengan auto-approve untuk mode yang aman dan sisakan manual untuk review';

    public function handle(): int
    {
        $inputPath = $this->resolvePath((string) $this->argument('file'));
        $outputPath = $this->resolveOutputPath((string) $this->option('output'), $inputPath);
        $approvedModes = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('approve-modes')))));
        $rejectManual = (bool) $this->option('reject-manual');

        if (! is_file($inputPath)) {
            $this->error("File approval tidak ditemukan: {$inputPath}");
            return self::FAILURE;
        }

        $rows = $this->readCsv($inputPath);
        $preparedRows = [];

        foreach ($rows as $row) {
            $mode = strtolower(trim((string) ($row['recommended_merge_mode'] ?? '')));
            $row['decision_status'] = in_array($mode, $approvedModes, true) ? 'approved' : ($rejectManual ? 'rejected' : 'pending');
            $row['notes'] = $row['decision_status'] === 'approved'
                ? 'Auto-approved by legacy:prepare-category-approval'
                : ($rejectManual ? 'Marked rejected by legacy:prepare-category-approval' : 'Awaiting manual review');
            $preparedRows[] = $row;
        }

        $headers = array_keys($preparedRows[0] ?? []);
        Storage::disk('local')->put($this->storageRelativePath($outputPath), $this->csvFromRows($headers, $preparedRows));

        $this->info('Category approval CSV prepared.');
        $this->table(
            ['Metric', 'Value'],
            [
                ['input_rows', count($rows)],
                ['approved_rows', collect($preparedRows)->where('decision_status', 'approved')->count()],
                ['pending_rows', collect($preparedRows)->where('decision_status', 'pending')->count()],
                ['rejected_rows', collect($preparedRows)->where('decision_status', 'rejected')->count()],
                ['output', Storage::disk('local')->path($this->storageRelativePath($outputPath))],
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

    private function resolveOutputPath(string $output, string $inputPath): string
    {
        if ($output !== '') {
            return $this->storageRelativePath($this->resolvePath($output));
        }

        $pathInfo = pathinfo($inputPath);
        return $this->storageRelativePath(($pathInfo['dirname'] ?? '.') . DIRECTORY_SEPARATOR . ($pathInfo['filename'] ?? 'category_approval') . '_prepared.csv');
    }

    private function storageRelativePath(string $path): string
    {
        $diskRoot = storage_path('app/private');

        if (str_starts_with($path, $diskRoot . DIRECTORY_SEPARATOR)) {
            return substr($path, strlen($diskRoot) + 1);
        }

        if (str_starts_with($path, $diskRoot)) {
            return ltrim(substr($path, strlen($diskRoot)), DIRECTORY_SEPARATOR);
        }

        return $path;
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

    private function csvFromRows(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn ($header) => $row[$header] ?? '', $headers));
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return (string) $csv;
    }
}