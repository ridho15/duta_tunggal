<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductCoaBackfillService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class AuditProductCoaMappings extends Command
{
    protected $signature = 'products:audit-default-coa
        {--limit=10 : Jumlah sample produk yang ditampilkan}
        {--chunk=1000 : Jumlah record yang diproses per batch}';

    protected $description = 'Audit product master untuk melihat apakah COA masih sinkron dengan default COA pada create form';

    public function handle(ProductCoaBackfillService $service): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $chunkSize = max(1, (int) $this->option('chunk'));

        $missingDefaultCodes = $service->missingDefaultCodes();
        if ($missingDefaultCodes !== []) {
            $this->warn('Ada default COA yang belum ditemukan di chart of accounts. Audit tetap berjalan, tetapi field terkait mungkin tidak dapat divalidasi penuh:');
            foreach ($missingDefaultCodes as $field => $code) {
                $this->warn("- {$field}: {$code}");
            }
        }

        $stats = [
            'scanned' => 0,
            'in_sync' => 0,
            'out_of_sync' => 0,
            'mismatch_by_field' => array_fill_keys($service->managedFields(), 0),
        ];

        $samples = [];

        Product::query()
            ->select(array_merge(
                ['id', 'sku', 'name', 'is_manufacture', 'is_raw_material'],
                $service->managedFields()
            ))
            ->orderBy('id')
            ->chunkById($chunkSize, function (Collection $products) use (&$stats, &$samples, $service, $limit) {
                foreach ($products as $product) {
                    $stats['scanned']++;

                    $mismatches = $service->compareToDefaultValues($product);
                    if ($mismatches === []) {
                        $stats['in_sync']++;
                        continue;
                    }

                    $stats['out_of_sync']++;

                    foreach ($mismatches as $mismatch) {
                        $stats['mismatch_by_field'][$mismatch['field']]++;
                    }

                    if (count($samples) < $limit) {
                        $samples[] = [
                            'sku' => $product->sku,
                            'name' => $product->name,
                            'flags' => trim(implode(', ', array_filter([
                                $product->is_manufacture ? 'manufacture' : null,
                                $product->is_raw_material ? 'raw_material' : null,
                            ]))) ?: 'standard',
                            'mismatch_fields' => implode(', ', array_map(
                                static fn (array $row) => $row['field'],
                                $mismatches
                            )),
                        ];
                    }
                }
            });

        $this->info('Product COA audit summary');
        $this->table(
            ['Metric', 'Value'],
            [
                ['scanned_products', $stats['scanned']],
                ['products_in_sync', $stats['in_sync']],
                ['products_out_of_sync', $stats['out_of_sync']],
            ]
        );

        $fieldRows = [];
        foreach ($stats['mismatch_by_field'] as $field => $count) {
            $fieldRows[] = [$field, $count];
        }

        $this->table(['Field', 'Mismatch Count'], $fieldRows);

        if ($samples !== []) {
            $this->table(['SKU', 'Name', 'Flags', 'Mismatch Fields'], $samples);
        } else {
            $this->info('Semua product sudah sinkron dengan default COA form.');
        }

        return self::SUCCESS;
    }
}