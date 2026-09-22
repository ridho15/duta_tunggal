<?php

namespace App\Console\Commands;

use App\Services\LegacyCategoryApprovalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class LegacyExportCategoryApproval extends Command
{
    protected $signature = 'legacy:export-category-approval
        {--main-cabang=2 : Cabang utama hasil import inventory}
        {--staging-cabang=3 : Cabang staging hasil import inventory_cab}
        {--staging-warehouse=3 : Warehouse staging hasil import inventory_cab}
        {--prefix=CAB- : Prefix key staging}
        {--directory=legacy-review : Direktori output pada storage local}';

    protected $description = 'Ekspor CSV approval kategori untuk duplicate products staging yang masih blocked';

    public function handle(LegacyCategoryApprovalService $service): int
    {
        $mainCabang = (int) $this->option('main-cabang');
        $stagingCabang = (int) $this->option('staging-cabang');
        $stagingWarehouse = (int) $this->option('staging-warehouse');
        $prefix = (string) $this->option('prefix');
        $directory = trim((string) $this->option('directory'), '/');
        $timestamp = now()->format('Ymd_His');

        $groups = $service->collectGroups($mainCabang, $stagingCabang, $stagingWarehouse, $prefix);
        $modeSummary = $service->summarizeModes($groups);
        $categorySummary = $service->summarizeSuggestedCategories($groups);

        $approvalPath = "{$directory}/{$timestamp}_products_category_approval.csv";
        $detailsPath = "{$directory}/{$timestamp}_products_category_approval_details.csv";
        $priorityPath = "{$directory}/{$timestamp}_products_category_priority.csv";
        $markdownPath = "{$directory}/{$timestamp}_products_category_approval_summary.md";

        Storage::disk('local')->put($approvalPath, $this->csvFromRows(
            ['target_sku', 'group_size', 'current_reason', 'post_category_reason', 'recommended_merge_mode', 'suggested_category_id', 'suggested_category_name', 'suggested_from_sku', 'suggested_rule', 'category_options', 'sku_options', 'approved_category_id', 'approved_category_name', 'decision_status', 'notes'],
            $groups->map(fn (array $group) => [
                $group['target_sku'],
                (string) $group['group_size'],
                $group['current_reason'],
                $group['post_category_reason'],
                $group['recommended_merge_mode'],
                (string) $group['suggested_category_id'],
                $group['suggested_category_name'],
                $group['suggested_from_sku'],
                $group['suggested_rule'],
                implode(' || ', array_map(fn (array $option) => $option['category_id'] . ':' . $option['category_name'] . ' [' . $option['rows'] . ']', $group['category_options'])),
                implode(' || ', array_map(fn (array $row) => $row['current_sku'], $group['rows'])),
                (string) $group['suggested_category_id'],
                $group['suggested_category_name'],
                'pending',
                '',
            ])->all()
        ));

        Storage::disk('local')->put($detailsPath, $this->csvFromRows(
            ['target_sku', 'current_sku', 'product_name', 'current_category_id', 'current_category_name', 'suggested_category_id', 'suggested_category_name', 'current_reason', 'post_category_reason', 'recommended_merge_mode', 'cost_price', 'sell_price', 'biaya', 'qty_min', 'qty_available', 'qty_reserved'],
            $groups->flatMap(fn (array $group) => $group['rows'])->map(fn (array $row) => [
                $row['target_sku'],
                $row['current_sku'],
                $row['product_name'],
                (string) $row['current_category_id'],
                $row['current_category_name'],
                (string) $row['suggested_category_id'],
                $row['suggested_category_name'],
                $row['current_reason'],
                $row['post_category_reason'],
                $row['recommended_merge_mode'],
                (string) $row['cost_price'],
                (string) $row['sell_price'],
                (string) $row['biaya'],
                (string) $row['qty_min'],
                (string) $row['qty_available'],
                (string) $row['qty_reserved'],
            ])->all()
        ));

        Storage::disk('local')->put($priorityPath, $this->csvFromRows(
            ['suggested_category_id', 'suggested_category_name', 'groups_count', 'rows_count', 'exact_after_approval', 'biaya_after_approval', 'qty_min_after_approval', 'manual_after_approval', 'sample_target_skus'],
            $categorySummary->map(fn (array $row) => [
                (string) $row['suggested_category_id'],
                $row['suggested_category_name'],
                (string) $row['groups_count'],
                (string) $row['rows_count'],
                (string) $row['exact_after_approval'],
                (string) $row['biaya_after_approval'],
                (string) $row['qty_min_after_approval'],
                (string) $row['manual_after_approval'],
                implode(' || ', $row['sample_target_skus']),
            ])->all()
        ));

        Storage::disk('local')->put($markdownPath, $this->markdownSummary(
            $mainCabang,
            $stagingCabang,
            $stagingWarehouse,
            $prefix,
            $groups->count(),
            $groups->sum('group_size'),
            $modeSummary->all(),
            $categorySummary->take(20)->all(),
            [$approvalPath, $detailsPath, $priorityPath, $markdownPath],
        ));

        $this->info('Category approval export created.');
        $this->table(
            ['Mode After Category Approval', 'Groups', 'Rows'],
            $modeSummary->map(fn (array $row) => [$row['mode'], $row['groups_count'], $row['rows_count']])->all()
        );

        $this->newLine();
        foreach ([$approvalPath, $detailsPath, $priorityPath, $markdownPath] as $path) {
            $this->line(Storage::disk('local')->path($path));
        }

        return self::SUCCESS;
    }

    private function csvFromRows(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return (string) $csv;
    }

    private function markdownSummary(
        int $mainCabang,
        int $stagingCabang,
        int $stagingWarehouse,
        string $prefix,
        int $groupsCount,
        int $rowsCount,
        array $modeSummary,
        array $categorySummary,
        array $files,
    ): string {
        $lines = [
            '# Legacy Category Approval Summary',
            '',
            '- Main cabang: ' . $mainCabang,
            '- Staging cabang: ' . $stagingCabang,
            '- Staging warehouse: ' . $stagingWarehouse,
            '- Prefix: ' . $prefix,
            '- Remaining groups requiring category decision: ' . $groupsCount,
            '- Remaining rows requiring category decision: ' . $rowsCount,
            '',
            '## Merge Potential After Category Approval',
            '',
            '| Mode | Groups | Rows |',
            '| --- | ---: | ---: |',
        ];

        foreach ($modeSummary as $row) {
            $lines[] = sprintf('| %s | %d | %d |', $row['mode'], $row['groups_count'], $row['rows_count']);
        }

        $lines[] = '';
        $lines[] = '## Suggested Category Priorities';
        $lines[] = '';
        $lines[] = '| Suggested Category ID | Suggested Category Name | Groups | Rows | Exact | Biaya | Qty Min | Manual |';
        $lines[] = '| --- | --- | ---: | ---: | ---: | ---: | ---: | ---: |';
        foreach ($categorySummary as $row) {
            $lines[] = sprintf('| %d | %s | %d | %d | %d | %d | %d | %d |', $row['suggested_category_id'], $row['suggested_category_name'], $row['groups_count'], $row['rows_count'], $row['exact_after_approval'], $row['biaya_after_approval'], $row['qty_min_after_approval'], $row['manual_after_approval']);
        }

        $lines[] = '';
        $lines[] = '## Files';
        $lines[] = '';
        foreach ($files as $file) {
            $lines[] = '- ' . Storage::disk('local')->path($file);
        }

        return implode("\n", $lines) . "\n";
    }
}