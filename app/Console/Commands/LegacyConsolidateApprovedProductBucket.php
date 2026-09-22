<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class LegacyConsolidateApprovedProductBucket extends Command
{
    protected $signature = 'legacy:consolidate-approved-product-bucket
        {approval-file : Path CSV approval}
        {--reason=category,biaya : Reason bucket exact match, contoh category,biaya atau category,cost,biaya}
        {--main-cabang=2 : Cabang utama hasil import inventory}
        {--staging-cabang=3 : Cabang staging hasil import inventory_cab}
        {--main-warehouse=2 : Warehouse utama hasil import inventory}
        {--staging-warehouse=3 : Warehouse staging hasil import inventory_cab}
        {--prefix=CAB- : Prefix key staging}
        {--execute : Jalankan konsolidasi berdasarkan approval file}
        {--force : Lewati konfirmasi interaktif saat execute}';

    protected $description = 'Konsolidasi bucket product staging-only berdasarkan approval CSV dan reason bucket exact';

    public function handle(): int
    {
        $approvalFile = $this->resolvePath((string) $this->argument('approval-file'));
        $reason = trim((string) $this->option('reason'));
        $mainCabang = (int) $this->option('main-cabang');
        $stagingCabang = (int) $this->option('staging-cabang');
        $mainWarehouse = (int) $this->option('main-warehouse');
        $stagingWarehouse = (int) $this->option('staging-warehouse');
        $prefix = (string) $this->option('prefix');
        $execute = (bool) $this->option('execute');

        if (! is_file($approvalFile)) {
            $this->error("Approval file tidak ditemukan: {$approvalFile}");
            return self::FAILURE;
        }

        $approvals = $this->loadApprovals($approvalFile);
        $approved = $approvals
            ->filter(fn (array $row) => $this->isApproved($row['approval_status'] ?? ''))
            ->keyBy('target_sku');

        $groups = $this->loadBucketGroups($mainCabang, $stagingCabang, $stagingWarehouse, $prefix, $reason)
            ->keyBy('target_sku');

        $validOperations = collect();
        $notes = [];
        $missingGroups = 0;
        $invalidApprovals = 0;

        foreach ($approved as $targetSku => $approval) {
            $group = $groups->get($targetSku);

            if (! $group) {
                $missingGroups++;
                $notes[] = "Target SKU {$targetSku} tidak lagi berada pada bucket {$reason} atau sudah berubah state.";
                continue;
            }

            $canonicalSku = $this->filledString($approval['approved_canonical_sku'] ?? null)
                ?: $this->filledString($approval['recommended_canonical_sku'] ?? null)
                ?: $group['recommended_canonical_sku'];

            $canonical = collect($group['rows'])->firstWhere('current_sku', $canonicalSku);
            if (! $canonical) {
                $invalidApprovals++;
                $notes[] = "Canonical SKU {$canonicalSku} untuk target {$targetSku} tidak ditemukan di candidate group.";
                continue;
            }

            $approvedCategoryId = $this->filledString($approval['approved_category_id'] ?? null)
                ?: $this->filledString($approval['recommended_category_id'] ?? null)
                ?: $group['recommended_category_id'];

            $approvedBiaya = $this->filledString($approval['approved_biaya'] ?? null)
                ?: $this->filledString($approval['recommended_biaya'] ?? null)
                ?: $group['recommended_biaya'];

            if (! ctype_digit((string) $approvedCategoryId)) {
                $invalidApprovals++;
                $notes[] = "Approved category_id untuk target {$targetSku} tidak valid.";
                continue;
            }

            $validOperations->push([
                'target_sku' => $targetSku,
                'canonical_sku' => $canonicalSku,
                'canonical_id' => (int) $canonical['id'],
                'canonical_stock_id' => $canonical['stock_id'] ? (int) $canonical['stock_id'] : null,
                'approved_category_id' => (int) $approvedCategoryId,
                'approved_biaya' => round((float) $approvedBiaya, 2),
                'row_count' => $group['row_count'],
                'total_qty_available' => $group['total_qty_available'],
                'total_qty_reserved' => $group['total_qty_reserved'],
                'qty_min' => $group['qty_min'],
                'duplicate_product_ids' => collect($group['rows'])
                    ->reject(fn (array $row) => (int) $row['id'] === (int) $canonical['id'])
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all(),
                'duplicate_stock_ids' => collect($group['rows'])
                    ->reject(fn (array $row) => (int) $row['id'] === (int) $canonical['id'])
                    ->pluck('stock_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->all(),
            ]);
        }

        $this->info('Approved ' . $reason . ' consolidation summary');
        $this->table(
            ['Metric', 'Value'],
            [
                ['approval_rows', $approvals->count()],
                ['approved_rows', $approved->count()],
                ['valid_operations', $validOperations->count()],
                ['missing_groups', $missingGroups],
                ['invalid_approvals', $invalidApprovals],
            ]
        );

        if ($validOperations->isNotEmpty()) {
            $this->table(
                ['Target SKU', 'Canonical SKU', 'Category', 'Biaya', 'Rows', 'Qty Available'],
                $validOperations->take(10)->map(fn (array $row) => [
                    $row['target_sku'],
                    $row['canonical_sku'],
                    $row['approved_category_id'],
                    number_format((float) $row['approved_biaya'], 2, '.', ''),
                    $row['row_count'],
                    number_format((float) $row['total_qty_available'], 2, '.', ''),
                ])->all()
            );
        }

        if (! $execute) {
            $notes[] = 'Dry-run only. Tambahkan --execute untuk menjalankan konsolidasi approval ' . $reason . '.';
            $this->renderNotes($notes);
            return self::SUCCESS;
        }

        if ($validOperations->isEmpty()) {
            $this->warn('Tidak ada operasi valid untuk dieksekusi.');
            $this->renderNotes($notes);
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Jalankan konsolidasi approval ' . $reason . ' sekarang?')) {
            $this->warn('Konsolidasi dibatalkan.');
            $this->renderNotes($notes);
            return self::INVALID;
        }

        try {
            DB::beginTransaction();
            $now = now();

            foreach ($validOperations as $operation) {
                DB::table('products')
                    ->where('id', $operation['canonical_id'])
                    ->update([
                        'sku' => $operation['target_sku'],
                        'cabang_id' => $mainCabang,
                        'product_category_id' => $operation['approved_category_id'],
                        'biaya' => $operation['approved_biaya'],
                        'updated_at' => $now,
                    ]);

                if ($operation['canonical_stock_id']) {
                    DB::table('inventory_stocks')
                        ->where('id', $operation['canonical_stock_id'])
                        ->update([
                            'warehouse_id' => $mainWarehouse,
                            'qty_available' => $operation['total_qty_available'],
                            'qty_reserved' => $operation['total_qty_reserved'],
                            'qty_min' => $operation['qty_min'],
                            'deleted_at' => null,
                            'updated_at' => $now,
                        ]);
                } else {
                    DB::table('inventory_stocks')->insert([
                        'product_id' => $operation['canonical_id'],
                        'warehouse_id' => $mainWarehouse,
                        'qty_available' => $operation['total_qty_available'],
                        'qty_reserved' => $operation['total_qty_reserved'],
                        'qty_min' => $operation['qty_min'],
                        'rak_id' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                        'deleted_at' => null,
                    ]);
                }

                if ($operation['duplicate_stock_ids'] !== []) {
                    DB::table('inventory_stocks')
                        ->whereIn('id', $operation['duplicate_stock_ids'])
                        ->update([
                            'qty_available' => 0,
                            'qty_reserved' => 0,
                            'qty_min' => 0,
                            'deleted_at' => $now,
                            'updated_at' => $now,
                        ]);
                }

                if ($operation['duplicate_product_ids'] !== []) {
                    DB::table('products')
                        ->whereIn('id', $operation['duplicate_product_ids'])
                        ->update([
                            'is_active' => 0,
                            'deleted_at' => $now,
                            'updated_at' => $now,
                        ]);
                }
            }

            DB::commit();
        } catch (Throwable $throwable) {
            DB::rollBack();
            $this->error('Konsolidasi approval ' . $reason . ' gagal: ' . $throwable->getMessage());
            return self::FAILURE;
        }

        $this->info('Konsolidasi approval ' . $reason . ' selesai.');
        $this->renderNotes($notes);
        return self::SUCCESS;
    }

    private function loadApprovals(string $path): Collection
    {
        $handle = fopen($path, 'r');
        $headers = fgetcsv($handle);
        $headers = array_map(fn ($value) => strtolower(trim((string) $value)), $headers ?: []);
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if (! array_filter($data, fn ($value) => trim((string) $value) !== '')) {
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }

                $row[$header] = $data[$index] ?? null;
            }

            $rows[] = $row;
        }

        fclose($handle);

        return collect($rows);
    }

    private function loadBucketGroups(int $mainCabang, int $stagingCabang, int $stagingWarehouse, string $prefix, string $reason): Collection
    {
        $pattern = '^' . preg_quote($prefix, '/') . '|(-DUP[0-9]+-R[0-9]+)$';

        $rows = collect(DB::select(
            "SELECT
                p.id,
                p.sku AS current_sku,
                REGEXP_REPLACE(p.sku, ?, '') AS target_sku,
                p.name AS product_name,
                p.product_category_id,
                p.uom_id,
                p.cost_price,
                p.sell_price,
                p.item_value,
                p.tipe_pajak,
                p.pajak,
                p.jumlah_kelipatan_gudang_besar,
                p.jumlah_jual_kategori_banyak,
                p.kode_merk,
                p.biaya,
                s.id AS stock_id,
                COALESCE(s.qty_available, 0) AS qty_available,
                COALESCE(s.qty_reserved, 0) AS qty_reserved,
                COALESCE(s.qty_min, 0) AS qty_min
            FROM products p
            LEFT JOIN products m
                ON m.cabang_id = ?
                AND m.sku = REGEXP_REPLACE(p.sku, ?, '')
            LEFT JOIN inventory_stocks s
                ON s.product_id = p.id
                AND s.warehouse_id = ?
                AND s.deleted_at IS NULL
                AND s.rak_id IS NULL
            WHERE p.cabang_id = ?
                AND p.deleted_at IS NULL
                AND m.id IS NULL
            ORDER BY target_sku, p.id",
            [$pattern, $mainCabang, $pattern, $stagingWarehouse, $stagingCabang]
        ));

        return $rows
            ->groupBy(fn ($row) => (string) $row->target_sku)
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->map(function (Collection $group) use ($prefix, $reason) {
                $differenceFields = $this->productDuplicateDifferenceFields($group);

                if (implode(',', $differenceFields->all()) !== $reason) {
                    return null;
                }

                $canonical = $this->selectCanonicalProductRow($group, (string) $group->first()->target_sku, $prefix);

                return [
                    'target_sku' => (string) $group->first()->target_sku,
                    'difference_reason' => implode(',', $differenceFields->all()),
                    'difference_fields' => $differenceFields->all(),
                    'row_count' => $group->count(),
                    'total_qty_available' => round((float) $group->sum('qty_available'), 2),
                    'total_qty_reserved' => round((float) $group->sum('qty_reserved'), 2),
                    'qty_min' => round((float) ($group->max('qty_min') ?? 0), 2),
                    'recommended_canonical_sku' => (string) $canonical->current_sku,
                    'recommended_category_id' => (string) $canonical->product_category_id,
                    'recommended_biaya' => $this->decimalString($canonical->biaya),
                    'rows' => $group->map(fn ($row) => [
                        'id' => (int) $row->id,
                        'current_sku' => (string) $row->current_sku,
                        'product_name' => (string) $row->product_name,
                        'product_category_id' => (string) $row->product_category_id,
                        'uom_id' => (string) $row->uom_id,
                        'cost_price' => $this->decimalString($row->cost_price),
                        'sell_price' => $this->decimalString($row->sell_price),
                        'item_value' => $this->decimalString($row->item_value),
                        'tipe_pajak' => (string) $row->tipe_pajak,
                        'pajak' => $this->decimalString($row->pajak),
                        'jumlah_kelipatan_gudang_besar' => (string) $row->jumlah_kelipatan_gudang_besar,
                        'jumlah_jual_kategori_banyak' => (string) $row->jumlah_jual_kategori_banyak,
                        'kode_merk' => (string) $row->kode_merk,
                        'biaya' => $this->decimalString($row->biaya),
                        'qty_available' => $this->decimalString($row->qty_available),
                        'qty_reserved' => $this->decimalString($row->qty_reserved),
                        'qty_min' => $this->decimalString($row->qty_min),
                        'stock_id' => $row->stock_id ? (int) $row->stock_id : null,
                    ])->values()->all(),
                ];
            })
            ->filter()
            ->sortByDesc('row_count')
            ->values();
    }

    private function productDuplicateDifferenceFields(Collection $rows): Collection
    {
        $comparisons = [
            'name' => $rows->map(fn ($row) => $this->normalizeProductName($row->product_name))->unique()->count(),
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
                (string) $row->current_sku === $preferredCode ? 0 : 1,
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

    private function decimalFingerprint(mixed $value): string
    {
        return number_format((float) $value, 6, '.', '');
    }

    private function decimalString(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function isApproved(string $value): bool
    {
        return in_array(strtoupper(trim($value)), ['APPROVE', 'APPROVED', 'YES', 'Y', '1'], true);
    }

    private function renderNotes(array $notes): void
    {
        $notes = array_values(array_unique(array_filter($notes)));

        if ($notes === []) {
            return;
        }

        $this->newLine();
        $this->warn('Notes');
        foreach ($notes as $note) {
            $this->line('- ' . $note);
        }
    }

    private function resolvePath(string $path): string
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            ? $path
            : base_path($path);
    }

    private function filledString(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}