<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class LegacyConsolidateStaging extends Command
{
    protected $signature = 'legacy:consolidate-staging
        {--main-cabang=2 : Cabang utama hasil import inventory}
        {--staging-cabang=3 : Cabang staging hasil import inventory_cab}
        {--main-warehouse=2 : Warehouse utama hasil import inventory}
        {--staging-warehouse=3 : Warehouse staging hasil import inventory_cab}
        {--prefix=CAB- : Prefix key staging}
        {--product-duplicate-mode=block : Mode duplicate product: block, exact, biaya, atau qty-min}
        {--entities=customers,suppliers,products,stocks : Entitas yang ingin diproses}
        {--execute : Jalankan konsolidasi staging-only ke source utama}
        {--force : Lewati konfirmasi interaktif saat execute}';

    protected $description = 'Konsolidasi bertahap data staging inventory_cab ke source utama inventory. Default dry-run dan hanya memproses staging-only records.';

    public function handle(): int
    {
        $mainCabang = (int) $this->option('main-cabang');
        $stagingCabang = (int) $this->option('staging-cabang');
        $mainWarehouse = (int) $this->option('main-warehouse');
        $stagingWarehouse = (int) $this->option('staging-warehouse');
        $prefix = (string) $this->option('prefix');
        $productDuplicateMode = strtolower(trim((string) $this->option('product-duplicate-mode')));
        $execute = (bool) $this->option('execute');
        $entities = collect(explode(',', (string) $this->option('entities')))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->values()
            ->all();

        $valid = ['customers', 'suppliers', 'products', 'stocks'];
        foreach ($entities as $entity) {
            if (! in_array($entity, $valid, true)) {
                $this->error("Entity tidak dikenal: {$entity}");
                return self::FAILURE;
            }
        }

        if (! in_array($productDuplicateMode, ['block', 'exact', 'biaya', 'qty-min'], true)) {
            $this->error('Mode duplicate product tidak dikenal. Gunakan block, exact, biaya, atau qty-min.');
            return self::FAILURE;
        }

        $customerCandidates = in_array('customers', $entities, true)
            ? $this->collectStagingOnly('customers', 'code', 'name', $mainCabang, $stagingCabang, $prefix)
            : collect();
        $supplierCandidates = in_array('suppliers', $entities, true)
            ? $this->collectStagingOnly('suppliers', 'code', 'perusahaan', $mainCabang, $stagingCabang, $prefix)
            : collect();
        $productCandidates = in_array('products', $entities, true)
            ? $this->collectStagingOnly('products', 'sku', 'name', $mainCabang, $stagingCabang, $prefix)
            : collect();

        [$customerCandidates, $blockedCustomerCandidates] = $this->splitUniqueTargets($customerCandidates);
        [$supplierCandidates, $blockedSupplierCandidates] = $this->splitUniqueTargets($supplierCandidates);
        [$directProductCandidates, $duplicateProductGroups] = $this->splitTargetGroups($productCandidates);

        $autoProductDuplicateGroups = collect();
        $blockedProductCandidates = collect();

        if ($productDuplicateMode !== 'block') {
            [$autoProductDuplicateGroups, $blockedDuplicateGroups] = $this->classifyProductDuplicateGroups(
                $duplicateProductGroups,
                $stagingWarehouse,
                $prefix,
                $productDuplicateMode,
            );

            $blockedProductCandidates = $blockedDuplicateGroups->flatten(1)->values();
        } else {
            $blockedProductCandidates = $duplicateProductGroups->flatten(1)->values();
        }

        $productCandidates = $directProductCandidates;

        $stockCandidates = collect();
        if (in_array('stocks', $entities, true)) {
            $productIds = $productCandidates->pluck('id')->all();
            if ($productIds) {
                $stockCandidates = DB::table('inventory_stocks')
                    ->select('id', 'product_id', 'warehouse_id', 'qty_available', 'qty_reserved', 'qty_min')
                    ->where('warehouse_id', $stagingWarehouse)
                    ->whereIn('product_id', $productIds)
                    ->get();
            }
        }

        $this->info('Staging consolidation summary');
        $this->table(
            ['Entity', 'Eligible direct rows', 'Auto duplicate rows', 'Blocked duplicate rows'],
            [
                ['customers', $customerCandidates->count(), 0, $blockedCustomerCandidates->count()],
                ['suppliers', $supplierCandidates->count(), 0, $blockedSupplierCandidates->count()],
                ['products', $productCandidates->count(), $autoProductDuplicateGroups->sum('row_count'), $blockedProductCandidates->count()],
                ['stocks', $stockCandidates->count(), $autoProductDuplicateGroups->count(), 0],
            ]
        );

        if ($autoProductDuplicateGroups->isNotEmpty()) {
            $this->line('Auto-resolvable product duplicate groups:');
            $this->table(
                ['Target SKU', 'Rows', 'Difference Policy', 'Canonical SKU', 'Canonical Name', 'Qty Available', 'Qty Reserved'],
                $autoProductDuplicateGroups->take(10)->map(fn ($group) => [
                    $group['target_code'],
                    $group['row_count'],
                    implode(',', $group['difference_fields']),
                    $group['canonical']->current_code,
                    $group['canonical']->entity_name,
                    $group['total_qty_available'],
                    $group['total_qty_reserved'],
                ])->all()
            );
        }

        if ($customerCandidates->isNotEmpty()) {
            $this->line('Sample customer promotions:');
            $this->table(
                ['ID', 'Current Code', 'Target Code', 'Name'],
                $customerCandidates->take(10)->map(fn ($row) => [$row->id, $row->current_code, $row->target_code, $row->entity_name])->all()
            );
        }

        if ($supplierCandidates->isNotEmpty()) {
            $this->line('Sample supplier promotions:');
            $this->table(
                ['ID', 'Current Code', 'Target Code', 'Name'],
                $supplierCandidates->take(10)->map(fn ($row) => [$row->id, $row->current_code, $row->target_code, $row->entity_name])->all()
            );
        }

        if ($productCandidates->isNotEmpty()) {
            $this->line('Sample product promotions:');
            $this->table(
                ['ID', 'Current SKU', 'Target SKU', 'Name'],
                $productCandidates->take(10)->map(fn ($row) => [$row->id, $row->current_code, $row->target_code, $row->entity_name])->all()
            );
        }

        if ($blockedProductCandidates->isNotEmpty()) {
            $this->line('Sample blocked product duplicates:');
            $this->table(
                ['ID', 'Current SKU', 'Target SKU', 'Name'],
                $blockedProductCandidates->take(10)->map(fn ($row) => [$row->id, $row->current_code, $row->target_code, $row->entity_name])->all()
            );
        }

        if (! $execute) {
            $this->warn('Dry-run only. Tambahkan --execute untuk menjalankan promosi staging-only ke source utama.');
            $this->line('Overlap records tidak disentuh oleh command ini.');
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Jalankan konsolidasi staging-only ke source utama sekarang?')) {
            $this->warn('Konsolidasi dibatalkan.');
            return self::INVALID;
        }

        try {
            DB::beginTransaction();
            $now = now();

            foreach ($customerCandidates as $row) {
                DB::table('customers')
                    ->where('id', $row->id)
                    ->update([
                        'code' => $row->target_code,
                        'cabang_id' => $mainCabang,
                        'updated_at' => $now,
                    ]);
            }

            foreach ($supplierCandidates as $row) {
                DB::table('suppliers')
                    ->where('id', $row->id)
                    ->update([
                        'code' => $row->target_code,
                        'cabang_id' => $mainCabang,
                        'updated_at' => $now,
                    ]);
            }

            foreach ($productCandidates as $row) {
                DB::table('products')
                    ->where('id', $row->id)
                    ->update([
                        'sku' => $row->target_code,
                        'cabang_id' => $mainCabang,
                        'updated_at' => $now,
                    ]);
            }

            foreach ($autoProductDuplicateGroups as $group) {
                $canonical = $group['canonical'];

                DB::table('products')
                    ->where('id', $canonical->id)
                    ->update([
                        'sku' => $group['target_code'],
                        'cabang_id' => $mainCabang,
                        'updated_at' => $now,
                    ]);

                if ($canonical->stock_id) {
                    DB::table('inventory_stocks')
                        ->where('id', $canonical->stock_id)
                        ->update([
                            'warehouse_id' => $mainWarehouse,
                            'qty_available' => $group['total_qty_available'],
                            'qty_reserved' => $group['total_qty_reserved'],
                            'qty_min' => $group['qty_min'],
                            'deleted_at' => null,
                            'updated_at' => $now,
                        ]);
                } else {
                    DB::table('inventory_stocks')->insert([
                        'product_id' => $canonical->id,
                        'warehouse_id' => $mainWarehouse,
                        'qty_available' => $group['total_qty_available'],
                        'qty_reserved' => $group['total_qty_reserved'],
                        'qty_min' => $group['qty_min'],
                        'rak_id' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                        'deleted_at' => null,
                    ]);
                }

                if ($group['duplicate_stock_ids']) {
                    DB::table('inventory_stocks')
                        ->whereIn('id', $group['duplicate_stock_ids'])
                        ->update([
                            'qty_available' => 0,
                            'qty_reserved' => 0,
                            'qty_min' => 0,
                            'deleted_at' => $now,
                            'updated_at' => $now,
                        ]);
                }

                if ($group['duplicate_product_ids']) {
                    DB::table('products')
                        ->whereIn('id', $group['duplicate_product_ids'])
                        ->update([
                            'is_active' => 0,
                            'deleted_at' => $now,
                            'updated_at' => $now,
                        ]);
                }
            }

            if ($stockCandidates->isNotEmpty()) {
                DB::table('inventory_stocks')
                    ->where('warehouse_id', $stagingWarehouse)
                    ->whereIn('product_id', $stockCandidates->pluck('product_id')->all())
                    ->update([
                        'warehouse_id' => $mainWarehouse,
                        'updated_at' => $now,
                    ]);
            }

            DB::commit();
        } catch (Throwable $throwable) {
            DB::rollBack();
            $this->error('Konsolidasi gagal: ' . $throwable->getMessage());
            return self::FAILURE;
        }

        $this->info('Konsolidasi staging-only selesai.');
        return self::SUCCESS;
    }

    private function collectStagingOnly(string $table, string $codeColumn, string $nameColumn, int $mainCabang, int $stagingCabang, string $prefix)
    {
        $pattern = '^' . preg_quote($prefix, '/') . '|(-DUP[0-9]+-R[0-9]+)$';

        return DB::table("{$table} as s")
            ->selectRaw("s.id, s.{$codeColumn} as current_code, REGEXP_REPLACE(s.{$codeColumn}, ?, '') as target_code, s.{$nameColumn} as entity_name", [$pattern])
            ->leftJoin("{$table} as m", function ($join) use ($mainCabang, $codeColumn, $pattern) {
                $join->on(DB::raw("m.{$codeColumn}"), '=', DB::raw("REGEXP_REPLACE(s.{$codeColumn}, '{$pattern}', '')"))
                    ->where('m.cabang_id', '=', $mainCabang);
            })
            ->where('s.cabang_id', $stagingCabang)
            ->whereNull('m.id')
            ->orderBy('s.id')
            ->get();
    }

    private function splitUniqueTargets($candidates): array
    {
        [$eligible, $blockedGroups] = $this->splitTargetGroups($candidates);

        return [$eligible, $blockedGroups->flatten(1)->values()];
    }

    private function splitTargetGroups(Collection $candidates): array
    {
        $grouped = $candidates->groupBy(fn ($row) => mb_strtolower((string) $row->target_code));

        $eligible = $grouped
            ->filter(fn ($rows) => $rows->count() === 1)
            ->flatten(1)
            ->values();

        $blocked = $grouped
            ->filter(fn ($rows) => $rows->count() > 1)
            ->map(fn ($rows) => $rows->values());

        return [$eligible, $blocked];
    }

    private function classifyProductDuplicateGroups(Collection $duplicateGroups, int $stagingWarehouse, string $prefix, string $mode): array
    {
        if ($duplicateGroups->isEmpty()) {
            return [collect(), collect()];
        }

        $allowedDifferences = match ($mode) {
            'exact' => [],
            'biaya' => ['biaya'],
            'qty-min' => ['qty_min'],
            default => [],
        };

        $productIds = $duplicateGroups->flatten(1)->pluck('id')->all();

        $details = DB::table('products as p')
            ->leftJoin('inventory_stocks as s', function ($join) use ($stagingWarehouse) {
                $join->on('s.product_id', '=', 'p.id')
                    ->where('s.warehouse_id', '=', $stagingWarehouse)
                    ->whereNull('s.rak_id');
            })
            ->select([
                'p.id',
                'p.name',
                'p.sku',
                'p.product_category_id',
                'p.uom_id',
                'p.cost_price',
                'p.sell_price',
                'p.item_value',
                'p.tipe_pajak',
                'p.pajak',
                'p.jumlah_kelipatan_gudang_besar',
                'p.jumlah_jual_kategori_banyak',
                'p.kode_merk',
                'p.biaya',
                's.id as stock_id',
                's.qty_available',
                's.qty_reserved',
                's.qty_min',
            ])
            ->whereIn('p.id', $productIds)
            ->get()
            ->keyBy('id');

        $auto = collect();
        $blocked = collect();

        foreach ($duplicateGroups as $targetCode => $rows) {
            $resolvedRows = $rows
                ->map(function ($row) use ($details) {
                    $detail = $details->get($row->id);

                    if (! $detail) {
                        return null;
                    }

                    return (object) array_merge((array) $row, [
                        'product_name' => $detail->name,
                        'product_category_id' => $detail->product_category_id,
                        'uom_id' => $detail->uom_id,
                        'cost_price' => $detail->cost_price,
                        'sell_price' => $detail->sell_price,
                        'item_value' => $detail->item_value,
                        'tipe_pajak' => $detail->tipe_pajak,
                        'pajak' => $detail->pajak,
                        'jumlah_kelipatan_gudang_besar' => $detail->jumlah_kelipatan_gudang_besar,
                        'jumlah_jual_kategori_banyak' => $detail->jumlah_jual_kategori_banyak,
                        'kode_merk' => $detail->kode_merk,
                        'biaya' => $detail->biaya,
                        'stock_id' => $detail->stock_id,
                        'qty_available' => (float) ($detail->qty_available ?? 0),
                        'qty_reserved' => (float) ($detail->qty_reserved ?? 0),
                        'qty_min' => (float) ($detail->qty_min ?? 0),
                    ]);
                })
                ->filter()
                ->values();

            if ($resolvedRows->count() !== $rows->count()) {
                $blocked->put($targetCode, $rows);
                continue;
            }

            $differenceFields = $this->productDuplicateDifferenceFields($resolvedRows);

            if ($differenceFields->diff($allowedDifferences)->isNotEmpty()) {
                $blocked->put($targetCode, $rows);
                continue;
            }

            $canonical = $this->selectCanonicalProductRow($resolvedRows, (string) $targetCode, $prefix);
            $duplicateRows = $resolvedRows->reject(fn ($row) => (int) $row->id === (int) $canonical->id)->values();

            $auto->push([
                'target_code' => (string) $targetCode,
                'row_count' => $resolvedRows->count(),
                'canonical' => $canonical,
                'duplicate_product_ids' => $duplicateRows->pluck('id')->map(fn ($id) => (int) $id)->all(),
                'duplicate_stock_ids' => $duplicateRows->pluck('stock_id')->filter()->map(fn ($id) => (int) $id)->all(),
                'total_qty_available' => $resolvedRows->sum('qty_available'),
                'total_qty_reserved' => $resolvedRows->sum('qty_reserved'),
                'qty_min' => $resolvedRows->max('qty_min') ?? 0,
                'difference_fields' => $differenceFields->values()->all(),
            ]);
        }

        return [$auto->values(), $blocked];
    }

    private function productDuplicateDifferenceFields(Collection $rows): Collection
    {
        $comparisons = [
            'name' => $rows->map(fn ($row) => $this->normalizeProductName($row->product_name ?: $row->entity_name))->unique()->count(),
            'category' => $rows->pluck('product_category_id')->unique()->count(),
            'uom' => $rows->pluck('uom_id')->unique()->count(),
            'cost' => $rows->map(fn ($row) => $this->decimalFingerprint($row->cost_price))->unique()->count(),
            'sell' => $rows->map(fn ($row) => $this->decimalFingerprint($row->sell_price))->unique()->count(),
            'item_value' => $rows->map(fn ($row) => $this->decimalFingerprint($row->item_value))->unique()->count(),
            'tax_type' => $rows->map(fn ($row) => strtoupper(trim((string) $row->tipe_pajak)))->unique()->count(),
            'tax' => $rows->map(fn ($row) => $this->decimalFingerprint($row->pajak))->unique()->count(),
            'bulk_capacity' => $rows->pluck('jumlah_kelipatan_gudang_besar')->unique()->count(),
            'bulk_sell_qty' => $rows->pluck('jumlah_jual_kategori_banyak')->unique()->count(),
            'brand' => $rows->map(fn ($row) => strtoupper(trim((string) $row->kode_merk)))->unique()->count(),
            'biaya' => $rows->map(fn ($row) => $this->decimalFingerprint($row->biaya))->unique()->count(),
            'qty_min' => $rows->map(fn ($row) => $this->decimalFingerprint($row->qty_min))->unique()->count(),
        ];

        return collect($comparisons)
            ->filter(fn ($count) => $count > 1)
            ->keys()
            ->values();
    }

    private function selectCanonicalProductRow(Collection $rows, string $targetCode, string $prefix): object
    {
        $preferredCode = $prefix . $targetCode;

        return $rows
            ->sortBy(fn ($row) => [
                (string) $row->current_code === $preferredCode ? 0 : 1,
                (int) $row->id,
            ])
            ->first();
    }

    private function normalizeProductName(?string $value): string
    {
        $value = strtoupper(trim((string) $value));
        $normalized = preg_replace('/[^A-Z0-9]+/', '', $value);

        return $normalized ?: '';
    }

    private function decimalFingerprint($value): string
    {
        return number_format((float) $value, 6, '.', '');
    }
}