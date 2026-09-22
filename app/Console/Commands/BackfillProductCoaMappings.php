<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductCoaBackfillService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BackfillProductCoaMappings extends Command
{
    protected $signature = 'products:backfill-default-coa
        {--execute : Jalankan backfill ke database ERP}
        {--force : Lewati konfirmasi interaktif saat execute}
        {--chunk=1000 : Jumlah record yang diproses per batch}';

    protected $description = 'Backfill COA pada product master mengikuti default COA pada form create product';

    public function handle(ProductCoaBackfillService $service): int
    {
        $execute = (bool) $this->option('execute');
        $chunkSize = max(1, (int) $this->option('chunk'));

        $missingDefaultCodes = $service->missingDefaultCodes();
        if ($missingDefaultCodes !== []) {
            $this->warn('Ada COA default yang belum ditemukan di chart of accounts. Field terkait akan dilewati:');
            foreach ($missingDefaultCodes as $field => $code) {
                $this->warn("- {$field}: {$code}");
            }
        }

        $stats = [
            'scanned' => 0,
            'needs_update' => 0,
            'updated' => 0,
            'by_field' => [
                'inventory_coa_id' => 0,
                'sales_coa_id' => 0,
                'sales_return_coa_id' => 0,
                'sales_discount_coa_id' => 0,
                'goods_delivery_coa_id' => 0,
                'cogs_coa_id' => 0,
                'purchase_return_coa_id' => 0,
                'unbilled_purchase_coa_id' => 0,
                'temporary_procurement_coa_id' => 0,
                'manufacturing_labor_coa_id' => 0,
                'manufacturing_overhead_coa_id' => 0,
            ],
        ];

        $samples = [];

        Product::query()
            ->select([
                'id',
                'sku',
                'name',
                'is_manufacture',
                'is_raw_material',
                'inventory_coa_id',
                'sales_coa_id',
                'sales_return_coa_id',
                'sales_discount_coa_id',
                'goods_delivery_coa_id',
                'cogs_coa_id',
                'purchase_return_coa_id',
                'unbilled_purchase_coa_id',
                'temporary_procurement_coa_id',
                'manufacturing_labor_coa_id',
                'manufacturing_overhead_coa_id',
            ])
            ->orderBy('id')
            ->chunkById($chunkSize, function (Collection $products) use (&$stats, &$samples, $service) {
                foreach ($products as $product) {
                    $stats['scanned']++;

                    $updates = $service->resolveMissingDefaultValues($product);
                    if ($updates === []) {
                        continue;
                    }

                    $stats['needs_update']++;

                    foreach (array_keys($updates) as $field) {
                        $stats['by_field'][$field]++;
                    }

                    if (count($samples) < 10) {
                        $samples[] = [
                            'sku' => $product->sku,
                            'name' => $product->name,
                            'flags' => trim(implode(', ', array_filter([
                                $product->is_manufacture ? 'manufacture' : null,
                                $product->is_raw_material ? 'raw_material' : null,
                            ]))) ?: 'standard',
                            'missing_fields' => implode(', ', array_keys($updates)),
                        ];
                    }
                }
            });

        $this->info('Product COA backfill summary');
        $this->table(
            ['Metric', 'Value'],
            [
                ['scanned_products', $stats['scanned']],
                ['products_needing_update', $stats['needs_update']],
            ]
        );

        $fieldRows = [];
        foreach ($stats['by_field'] as $field => $count) {
            $fieldRows[] = [
                $field,
                $count,
            ];
        }

        $this->table(['Field', 'Missing Count'], $fieldRows);

        if ($samples !== []) {
            $this->table(['SKU', 'Name', 'Flags', 'Missing Fields'], $samples);
        }

        if (! $execute) {
            $this->warn('Dry-run only. Tambahkan --execute untuk menulis perubahan ke database.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Jalankan backfill COA product master sekarang?')) {
            $this->warn('Backfill dibatalkan.');

            return self::INVALID;
        }

        $updated = 0;

        Product::query()
            ->select([
                'id',
                'is_manufacture',
                'is_raw_material',
                'inventory_coa_id',
                'sales_coa_id',
                'sales_return_coa_id',
                'sales_discount_coa_id',
                'goods_delivery_coa_id',
                'cogs_coa_id',
                'purchase_return_coa_id',
                'unbilled_purchase_coa_id',
                'temporary_procurement_coa_id',
                'manufacturing_labor_coa_id',
                'manufacturing_overhead_coa_id',
            ])
            ->orderBy('id')
            ->chunkById($chunkSize, function (Collection $products) use ($service, &$updated) {
                DB::transaction(function () use ($products, $service, &$updated) {
                    foreach ($products as $product) {
                        $updates = $service->resolveMissingDefaultValues($product);

                        if ($updates === []) {
                            continue;
                        }

                        $product->forceFill($updates);
                        $product->saveQuietly();
                        $updated++;
                    }
                });
            });

        $this->info("Updated {$updated} product records.");

        return self::SUCCESS;
    }
}