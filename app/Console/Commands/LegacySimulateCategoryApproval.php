<?php

namespace App\Console\Commands;

use App\Services\LegacyCategoryApprovalService;
use Illuminate\Console\Command;

class LegacySimulateCategoryApproval extends Command
{
    protected $signature = 'legacy:simulate-category-approval
        {--main-cabang=2 : Cabang utama hasil import inventory}
        {--staging-cabang=3 : Cabang staging hasil import inventory_cab}
        {--staging-warehouse=3 : Warehouse staging hasil import inventory_cab}
        {--prefix=CAB- : Prefix key staging}
        {--top=15 : Jumlah kategori prioritas yang ditampilkan}';

    protected $description = 'Simulasikan dampak approval kategori berbasis row kanonik non-DUP untuk duplicate products staging';

    public function handle(LegacyCategoryApprovalService $service): int
    {
        $mainCabang = (int) $this->option('main-cabang');
        $stagingCabang = (int) $this->option('staging-cabang');
        $stagingWarehouse = (int) $this->option('staging-warehouse');
        $prefix = (string) $this->option('prefix');
        $top = max(1, (int) $this->option('top'));

        $groups = $service->collectGroups($mainCabang, $stagingCabang, $stagingWarehouse, $prefix);
        $modeSummary = $service->summarizeModes($groups);
        $categorySummary = $service->summarizeSuggestedCategories($groups)->take($top);

        $this->info('Category approval simulation summary');
        $this->line('Rule: gunakan category dari row kanonik non-DUP jika tersedia, lalu hitung ulang sisa difference field per target SKU.');
        $this->newLine();

        $this->table(
            ['Mode After Category Approval', 'Groups', 'Rows'],
            $modeSummary->map(fn (array $row) => [$row['mode'], $row['groups_count'], $row['rows_count']])->all()
        );

        $this->line('Top suggested categories:');
        $this->table(
            ['Category ID', 'Category Name', 'Groups', 'Rows', 'Exact', 'Biaya', 'Qty Min', 'Manual'],
            $categorySummary->map(fn (array $row) => [
                $row['suggested_category_id'],
                $row['suggested_category_name'],
                $row['groups_count'],
                $row['rows_count'],
                $row['exact_after_approval'],
                $row['biaya_after_approval'],
                $row['qty_min_after_approval'],
                $row['manual_after_approval'],
            ])->all()
        );

        $this->line('Sample groups unlocked after category approval:');
        $this->table(
            ['Target SKU', 'Current Reason', 'Post Category Reason', 'Recommended Merge Mode', 'Suggested Category ID', 'Suggested From SKU'],
            $groups
                ->whereIn('recommended_merge_mode', ['exact', 'biaya', 'qty-min'])
                ->take(15)
                ->map(fn (array $group) => [
                    $group['target_sku'],
                    $group['current_reason'],
                    $group['post_category_reason'],
                    $group['recommended_merge_mode'],
                    $group['suggested_category_id'],
                    $group['suggested_from_sku'],
                ])
                ->all()
        );

        return self::SUCCESS;
    }
}