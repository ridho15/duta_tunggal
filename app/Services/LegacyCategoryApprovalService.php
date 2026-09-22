<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LegacyCategoryApprovalService
{
    public function collectGroups(
        int $mainCabang,
        int $stagingCabang,
        int $stagingWarehouse,
        string $prefix,
    ): Collection {
        $pattern = '^' . preg_quote($prefix, '/') . '|(-DUP[0-9]+-R[0-9]+)$';

        $rows = DB::table('products as p')
            ->leftJoin('products as m', function ($join) use ($mainCabang, $pattern) {
                $join->on('m.sku', '=', DB::raw("REGEXP_REPLACE(p.sku, '{$pattern}', '')"))
                    ->where('m.cabang_id', '=', $mainCabang)
                    ->whereNull('m.deleted_at');
            })
            ->leftJoin('inventory_stocks as s', function ($join) use ($stagingWarehouse) {
                $join->on('s.product_id', '=', 'p.id')
                    ->where('s.warehouse_id', '=', $stagingWarehouse)
                    ->whereNull('s.rak_id')
                    ->whereNull('s.deleted_at');
            })
            ->leftJoin('product_categories as c', 'c.id', '=', 'p.product_category_id')
            ->selectRaw("p.id, REGEXP_REPLACE(p.sku, ?, '') as target_sku, p.sku as current_sku, p.name as product_name, p.product_category_id, c.name as category_name, p.uom_id, p.cost_price, p.sell_price, p.item_value, p.tipe_pajak, p.pajak, p.jumlah_kelipatan_gudang_besar, p.jumlah_jual_kategori_banyak, p.kode_merk, p.biaya, COALESCE(s.qty_min, 0) as qty_min, COALESCE(s.qty_available, 0) as qty_available, COALESCE(s.qty_reserved, 0) as qty_reserved", [$pattern])
            ->where('p.cabang_id', $stagingCabang)
            ->whereNull('p.deleted_at')
            ->whereNull('m.id')
            ->orderBy('p.id')
            ->get();

        return $rows
            ->groupBy('target_sku')
            ->filter(fn (Collection $group) => $group->count() > 1 && $group->pluck('product_category_id')->unique()->count() > 1)
            ->map(function (Collection $group) use ($prefix) {
                $group = $group->values();
                $canonical = $this->selectCanonicalRow($group, (string) $group->first()->target_sku, $prefix);
                $currentReasonFields = $this->differenceFields($group);
                $postApprovalReasonFields = $this->differenceFields(
                    $group->map(function ($row) use ($canonical) {
                        $clone = clone $row;
                        $clone->product_category_id = $canonical->product_category_id;
                        $clone->category_name = $canonical->category_name;

                        return $clone;
                    })
                );

                $categoryOptions = $group
                    ->groupBy('product_category_id')
                    ->map(function (Collection $rows, $categoryId) {
                        $first = $rows->first();

                        return [
                            'category_id' => (int) $categoryId,
                            'category_name' => (string) ($first->category_name ?? ''),
                            'rows' => $rows->count(),
                            'skus' => $rows->pluck('current_sku')->values()->all(),
                        ];
                    })
                    ->sortByDesc('rows')
                    ->values();

                return [
                    'target_sku' => (string) $group->first()->target_sku,
                    'group_size' => $group->count(),
                    'current_reason_fields' => $currentReasonFields->values()->all(),
                    'current_reason' => $this->reasonLabel($currentReasonFields),
                    'suggested_category_id' => (int) $canonical->product_category_id,
                    'suggested_category_name' => (string) ($canonical->category_name ?? ''),
                    'suggested_from_sku' => (string) $canonical->current_sku,
                    'suggested_rule' => 'prefer-base-sku',
                    'post_category_reason_fields' => $postApprovalReasonFields->values()->all(),
                    'post_category_reason' => $this->reasonLabel($postApprovalReasonFields),
                    'recommended_merge_mode' => $this->recommendedMergeMode($postApprovalReasonFields),
                    'category_options' => $categoryOptions->all(),
                    'rows' => $group->map(function ($row) use ($canonical, $currentReasonFields, $postApprovalReasonFields) {
                        return [
                            'target_sku' => (string) $row->target_sku,
                            'current_sku' => (string) $row->current_sku,
                            'product_name' => (string) $row->product_name,
                            'current_category_id' => (int) $row->product_category_id,
                            'current_category_name' => (string) ($row->category_name ?? ''),
                            'suggested_category_id' => (int) $canonical->product_category_id,
                            'suggested_category_name' => (string) ($canonical->category_name ?? ''),
                            'current_reason' => $this->reasonLabel($currentReasonFields),
                            'post_category_reason' => $this->reasonLabel($postApprovalReasonFields),
                            'recommended_merge_mode' => $this->recommendedMergeMode($postApprovalReasonFields),
                            'cost_price' => (float) $row->cost_price,
                            'sell_price' => (float) $row->sell_price,
                            'biaya' => (float) $row->biaya,
                            'qty_min' => (float) $row->qty_min,
                            'qty_available' => (float) $row->qty_available,
                            'qty_reserved' => (float) $row->qty_reserved,
                        ];
                    })->all(),
                ];
            })
            ->sortBy([
                fn (array $group) => $group['recommended_merge_mode'],
                fn (array $group) => $group['target_sku'],
            ])
            ->values();
    }

    public function summarizeModes(Collection $groups): Collection
    {
        return $groups
            ->groupBy('recommended_merge_mode')
            ->map(function (Collection $items, string $mode) {
                return [
                    'mode' => $mode,
                    'groups_count' => $items->count(),
                    'rows_count' => $items->sum('group_size'),
                ];
            })
            ->sortBy('mode')
            ->values();
    }

    public function summarizeSuggestedCategories(Collection $groups): Collection
    {
        return $groups
            ->groupBy(fn (array $group) => $group['suggested_category_id'])
            ->map(function (Collection $items, $categoryId) {
                $first = $items->first();

                return [
                    'suggested_category_id' => (int) $categoryId,
                    'suggested_category_name' => (string) $first['suggested_category_name'],
                    'groups_count' => $items->count(),
                    'rows_count' => $items->sum('group_size'),
                    'exact_after_approval' => $items->where('recommended_merge_mode', 'exact')->count(),
                    'biaya_after_approval' => $items->where('recommended_merge_mode', 'biaya')->count(),
                    'qty_min_after_approval' => $items->where('recommended_merge_mode', 'qty-min')->count(),
                    'manual_after_approval' => $items->where('recommended_merge_mode', 'manual')->count(),
                    'sample_target_skus' => $items->pluck('target_sku')->take(5)->values()->all(),
                ];
            })
            ->sortByDesc('groups_count')
            ->values();
    }

    private function selectCanonicalRow(Collection $rows, string $targetSku, string $prefix): object
    {
        $preferredCode = $prefix . $targetSku;

        return $rows
            ->sortBy(fn ($row) => [
                (string) $row->current_sku === $preferredCode ? 0 : 1,
                (int) $row->id,
            ])
            ->first();
    }

    private function differenceFields(Collection $rows): Collection
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
            ->filter(fn (int $count) => $count > 1)
            ->keys()
            ->values();
    }

    private function recommendedMergeMode(Collection $postApprovalReasonFields): string
    {
        $fields = $postApprovalReasonFields->values()->all();

        if ($fields === []) {
            return 'exact';
        }

        if ($fields === ['biaya']) {
            return 'biaya';
        }

        if ($fields === ['qty_min']) {
            return 'qty-min';
        }

        return 'manual';
    }

    private function reasonLabel(Collection $fields): string
    {
        return $fields->isEmpty() ? 'exact' : $fields->implode(',');
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