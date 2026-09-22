<?php

namespace App\Console\Commands;

use App\Services\LegacyInventoryMigrationService;
use Illuminate\Console\Command;

class LegacyAuditMerge extends Command
{
    protected $signature = 'legacy:audit-merge {--sample=10 : Jumlah sample konflik per entitas}';

    protected $description = 'Audit overlap dan potensi konflik merge antara database legacy inventory dan inventory_cab';

    public function handle(LegacyInventoryMigrationService $service): int
    {
        $sample = max(1, (int) $this->option('sample'));
        $report = $service->auditMerge($sample);

        $this->info('Legacy merge audit summary');
        $this->newLine();

        foreach ($report['sources'] as $sourceName => $source) {
            $this->line($source['label'] . ' (' . $sourceName . ')');
            $this->table(
                ['ID', 'Code', 'Store', 'Status'],
                array_map(fn ($store) => [$store['id'], $store['code'], $store['name'], $store['status']], $source['stores'])
            );
        }

        foreach ($report['entities'] as $entity => $stats) {
            $this->newLine();
            $this->info(strtoupper($entity));
            $this->table(
                ['Metric', 'Value'],
                [
                    ['inventory rows', $stats['left_rows']],
                    ['inventory_cab rows', $stats['right_rows']],
                    ['inventory duplicate codes', $stats['left_duplicate_codes']],
                    ['inventory duplicate conflicts', $stats['left_duplicate_conflicts']],
                    ['inventory_cab duplicate codes', $stats['right_duplicate_codes']],
                    ['inventory_cab duplicate conflicts', $stats['right_duplicate_conflicts']],
                    ['overlap codes', $stats['overlap_codes']],
                    ['overlap normalized name conflicts', $stats['overlap_name_conflicts']],
                    ['already existing in ERP from inventory', $stats['existing_in_erp_from_left']],
                    ['already existing in ERP from inventory_cab', $stats['existing_in_erp_from_right']],
                ]
            );

            if ($stats['left_duplicate_samples']) {
                $this->line('Sample duplicate conflicts in inventory:');
                $this->table(['Code', 'Names'], array_map(fn ($sampleRow) => [$sampleRow['code'], $sampleRow['names']], $stats['left_duplicate_samples']));
            }

            if ($stats['right_duplicate_samples']) {
                $this->line('Sample duplicate conflicts in inventory_cab:');
                $this->table(['Code', 'Names'], array_map(fn ($sampleRow) => [$sampleRow['code'], $sampleRow['names']], $stats['right_duplicate_samples']));
            }

            if ($stats['overlap_samples']) {
                $this->line('Sample overlap conflicts between inventory and inventory_cab:');
                $this->table(
                    ['Code', 'Inventory Name', 'Inventory CAB Name'],
                    array_map(fn ($sampleRow) => [$sampleRow['code'], $sampleRow['left_name'], $sampleRow['right_name']], $stats['overlap_samples'])
                );
            }
        }

        $this->newLine();
        $this->info('Recommendations');
        foreach ($report['recommendations'] as $recommendation) {
            $this->line('- ' . $recommendation);
        }

        return self::SUCCESS;
    }
}