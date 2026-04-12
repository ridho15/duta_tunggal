<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class LegacyConsolidateApprovedCategoryField extends Command
{
    protected $signature = 'legacy:consolidate-approved-category-field
        {approval-file : Path CSV approval file}
        {--reason= : Reason yang didukung: category,qty_min | category,bulk_sell_qty | category,bulk_capacity}
        {--main-cabang=2 : Cabang utama hasil import inventory}
        {--staging-cabang=3 : Cabang staging hasil import inventory_cab}
        {--main-warehouse=2 : Warehouse utama hasil import inventory}
        {--staging-warehouse=3 : Warehouse staging hasil import inventory_cab}
        {--prefix=CAB- : Prefix key staging}
        {--execute : Jalankan konsolidasi berdasarkan approval file}
        {--force : Lewati konfirmasi interaktif saat execute}';

    protected $description = 'Konsolidasi approval untuk bucket duplicate product dengan perbedaan category plus field-field yang didukung';

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
        if ($approvals->isEmpty()) {
            $this->warn('Approval file kosong.');
            return self::SUCCESS;
        }

        $reason = $reason !== '' ? $reason : trim((string) ($approvals->pluck('difference_reason')->filter()->unique()->first() ?? ''));
        $supported = [
            'category',
            'category,biaya',
            'category,qty_min',
            'category,bulk_sell_qty',
            'category,bulk_capacity',
            'category,biaya,qty_min',
            'category,bulk_sell_qty,qty_min',
            'category,bulk_sell_qty,biaya',
            'category,bulk_sell_qty,biaya,qty_min',
            'category,cost',
            'category,cost,biaya',
            'category,cost,qty_min',
            'category,cost,bulk_sell_qty',
            'category,cost,bulk_capacity,biaya',
            'category,cost,biaya,qty_min',
            'category,cost,bulk_sell_qty,biaya',
            'category,cost,bulk_sell_qty,biaya,qty_min',
        ];
        if (! in_array($reason, $supported, true)) {
            $this->error('Reason tidak didukung untuk konsolidasi generic approval ini.');
            return self::FAILURE;
        }

        $approved = $approvals
            ->filter(fn (array $row) => $this->isApproved($row['approval_status'] ?? ''))
            ->keyBy('target_sku');

        $groups = $this->loadGroupsByReason($mainCabang, $stagingCabang, $stagingWarehouse, $prefix, $reason)->keyBy('target_sku');

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

            if (! ctype_digit((string) $approvedCategoryId)) {
                $invalidApprovals++;
                $notes[] = "Approved category_id untuk target {$targetSku} tidak valid.";
                continue;
            }

            $productUpdates = [
                'sku' => $targetSku,
                'cabang_id' => $mainCabang,
                'product_category_id' => (int) $approvedCategoryId,
            ];

            if ($this->reasonHasField($reason, 'cost')) {
                $productUpdates['cost_price'] = round((float) $canonical['cost_price'], 2);
            }

            if ($this->reasonHasField($reason, 'biaya')) {
                $approvedBiaya = $this->filledString($approval['approved_biaya'] ?? null)
                    ?: $this->filledString($approval['recommended_biaya'] ?? null)
                    ?: number_format((float) $canonical['biaya'], 2, '.', '');

                $productUpdates['biaya'] = round((float) $approvedBiaya, 2);
            }

            if ($this->reasonHasField($reason, 'bulk_sell_qty')) {
                $productUpdates['jumlah_jual_kategori_banyak'] = (int) $canonical['jumlah_jual_kategori_banyak'];
            }

            if ($this->reasonHasField($reason, 'bulk_capacity')) {
                $productUpdates['jumlah_kelipatan_gudang_besar'] = (int) $canonical['jumlah_kelipatan_gudang_besar'];
            }

            $validOperations->push([
                'target_sku' => $targetSku,
                'canonical_sku' => $canonicalSku,
                'canonical_id' => (int) $canonical['id'],
                'canonical_stock_id' => $canonical['stock_id'] ? (int) $canonical['stock_id'] : null,
                'product_updates' => $productUpdates,
                'row_count' => $group['row_count'],
                'total_qty_available' => $group['total_qty_available'],
                'total_qty_reserved' => $group['total_qty_reserved'],
                'qty_min' => $group['qty_min'],
                'duplicate_product_ids' => collect($group['rows'])
                    ->reject(fn (array $row) => (int) $row['id'] === (int) $canonical['id'])
                    ->pluck('id')->map(fn ($id) => (int) $id)->all(),
                'duplicate_stock_ids' => collect($group['rows'])
                    ->reject(fn (array $row) => (int) $row['id'] === (int) $canonical['id'])
                    ->pluck('stock_id')->filter()->map(fn ($id) => (int) $id)->all(),
            ]);
        }

        $this->info('Approved category-field consolidation summary');
        $this->table(
            ['Metric', 'Value'],
            [
                ['reason', $reason],
                ['approval_rows', $approvals->count()],
                ['approved_rows', $approved->count()],
                ['valid_operations', $validOperations->count()],
                ['missing_groups', $missingGroups],
                ['invalid_approvals', $invalidApprovals],
            ]
        );

        if ($validOperations->isNotEmpty()) {
            $this->table(
                ['Target SKU', 'Canonical SKU', 'Rows', 'Qty Available'],
                $validOperations->take(10)->map(fn (array $row) => [
                    $row['target_sku'],
                    $row['canonical_sku'],
                    $row['row_count'],
                    number_format((float) $row['total_qty_available'], 2, '.', ''),
                ])->all()
            );
        }

        if (! $execute) {
            $notes[] = "Dry-run only. Tambahkan --execute untuk menjalankan konsolidasi approval {$reason}.";
            $this->renderNotes($notes);
            return self::SUCCESS;
        }

        if ($validOperations->isEmpty()) {
            $this->warn('Tidak ada operasi valid untuk dieksekusi.');
            $this->renderNotes($notes);
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Jalankan konsolidasi approval {$reason} sekarang?")) {
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
                    ->update(array_merge($operation['product_updates'], ['updated_at' => $now]));

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
            $this->error('Konsolidasi approval category-field gagal: ' . $throwable->getMessage());
            return self::FAILURE;
        }

        $this->info("Konsolidasi approval {$reason} selesai.");
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

    private function loadGroupsByReason(int $mainCabang, int $stagingCabang, int $stagingWarehouse, string $prefix, string $reason): Collection
    {
        $pattern = '^' . preg_quote($prefix, '/') . '|(-DUP[0-9]+-R[0-9]+)$';

        $rows = collect(DB::select(
            "SELECT p.id, p.sku AS current_sku, REGEXP_REPLACE(p.sku, ?, '') AS target_sku, p.name AS product_name, p.product_category_id, p.uom_id, p.cost_price, p.sell_price, p.item_value, p.tipe_pajak, p.pajak, p.jumlah_kelipatan_gudang_besar, p.jumlah_jual_kategori_banyak, p.kode_merk, p.biaya, s.id AS stock_id, COALESCE(s.qty_available, 0) AS qty_available, COALESCE(s.qty_reserved, 0) AS qty_reserved, COALESCE(s.qty_min, 0) AS qty_min FROM products p LEFT JOIN products m ON m.cabang_id = ? AND m.sku = REGEXP_REPLACE(p.sku, ?, '') LEFT JOIN inventory_stocks s ON s.product_id = p.id AND s.warehouse_id = ? AND s.deleted_at IS NULL AND s.rak_id IS NULL WHERE p.cabang_id = ? AND p.deleted_at IS NULL AND m.id IS NULL ORDER BY target_sku, p.id",
            [$pattern, $mainCabang, $pattern, $stagingWarehouse, $stagingCabang]
        ));

        return $rows
            ->groupBy(fn ($row) => (string) $row->target_sku)
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->map(function (Collection $group) use ($prefix) {
                $differenceFields = $this->productDuplicateDifferenceFields($group);
                $differenceReason = implode(',', $differenceFields->all());
                $canonical = $this->selectCanonicalProductRow($group, (string) $group->first()->target_sku, $prefix);

                return [
                    'target_sku' => (string) $group->first()->target_sku,
                    'difference_reason' => $differenceReason,
                    'row_count' => $group->count(),
                    'recommended_canonical_sku' => (string) $canonical->current_sku,
                    'recommended_category_id' => (string) $canonical->product_category_id,
                    'recommended_biaya' => number_format((float) $canonical->biaya, 2, '.', ''),
                    'total_qty_available' => round((float) $group->sum('qty_available'), 2),
                    'total_qty_reserved' => round((float) $group->sum('qty_reserved'), 2),
                    'qty_min' => round((float) ($group->max('qty_min') ?? 0), 2),
                    'rows' => $group->map(fn ($row) => [
                        'id' => (int) $row->id,
                        'current_sku' => (string) $row->current_sku,
                        'product_category_id' => (string) $row->product_category_id,
                        'cost_price' => (float) $row->cost_price,
                        'biaya' => (float) $row->biaya,
                        'jumlah_jual_kategori_banyak' => (int) $row->jumlah_jual_kategori_banyak,
                        'jumlah_kelipatan_gudang_besar' => (int) $row->jumlah_kelipatan_gudang_besar,
                        'stock_id' => $row->stock_id ? (int) $row->stock_id : null,
                    ])->values()->all(),
                ];
            })
            ->filter(fn (array $group) => $group['difference_reason'] === $reason)
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

        return collect($comparisons)->filter(fn ($count) => $count > 1)->keys()->values();
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
        return str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : base_path($path);
    }

    private function filledString(mixed $value): ?string
    {
        $string = trim((string) $value);
        return $string === '' ? null : $string;
    }

    private function reasonHasField(string $reason, string $field): bool
    {
        return in_array($field, array_map('trim', explode(',', $reason)), true);
    }
}