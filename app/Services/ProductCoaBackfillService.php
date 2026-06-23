<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\Product;

class ProductCoaBackfillService
{
    /**
     * @var array<string, int|null>
     */
    private array $codeToIdCache = [];

    /**
     * @var array<int, string|null>
     */
    private array $idToCodeCache = [];

    /**
     * @var array<string, int|null>
     */
    private array $inventoryDefaultCache = [];

    /**
     * @return array<int, string>
     */
    public function managedFields(): array
    {
        return [
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
        ];
    }

    public function resolveDefaultValues(Product $product): array
    {
        return Product::resolveDefaultProductCoaMap(
            (bool) $product->getAttribute('is_manufacture'),
            (bool) $product->getAttribute('is_raw_material'),
        );
    }

    public function resolveMissingDefaultValues(Product $product): array
    {
        return array_filter(
            $this->resolveDefaultValues($product),
            static fn ($value) => $value !== null
        );
    }

    /**
     * Compare current product COA values against the create-form defaults.
     *
     * @return array<int, array<string, mixed>>
     */
    public function compareToDefaultValues(Product $product): array
    {
        $defaults = $this->resolveDefaultValues($product);
        $rows = [];

        foreach ($this->managedFields() as $field) {
            $currentId = $product->{$field};
            $expectedId = $defaults[$field] ?? null;

            if ((string) $currentId === (string) $expectedId) {
                continue;
            }

            $rows[] = [
                'field' => $field,
                'current_id' => $currentId ? (int) $currentId : null,
                'expected_id' => $expectedId ? (int) $expectedId : null,
                'current_code' => $currentId ? $this->resolveCodeById((int) $currentId) : null,
                'expected_code' => $this->defaultCodeForField($product, $field),
            ];
        }

        return $rows;
    }

    public function defaultCodeForField(Product $product, string $field): ?string
    {
        $codes = Product::resolveDefaultProductCoaCodes(
            $field,
            (bool) $product->getAttribute('is_manufacture'),
            (bool) $product->getAttribute('is_raw_material'),
        );

        return $codes[0] ?? null;
    }

    public function missingDefaultCodes(): array
    {
        $missing = [];

        foreach (Product::productCoaFields() as $field) {
            $codes = Product::resolveDefaultProductCoaCodes($field);

            foreach ($codes as $code) {
                if ($this->resolveIdByCode($code) !== null) {
                    continue 2;
                }

                $missing[$field] = $code;
            }
        }

        return $missing;
    }

    private function resolveInventoryCoaId(Product $product): ?int
    {
        $cacheKey = implode(':', [
            $product->getAttribute('is_manufacture') ? '1' : '0',
            $product->getAttribute('is_raw_material') ? '1' : '0',
        ]);

        if (! array_key_exists($cacheKey, $this->inventoryDefaultCache)) {
            $this->inventoryDefaultCache[$cacheKey] = Product::resolveDefaultProductCoaId(
                'inventory_coa_id',
                (bool) $product->getAttribute('is_manufacture'),
                (bool) $product->getAttribute('is_raw_material'),
            );
        }

        return $this->inventoryDefaultCache[$cacheKey];
    }

    private function resolveIdByCode(string $code): ?int
    {
        if (! array_key_exists($code, $this->codeToIdCache)) {
            $this->codeToIdCache[$code] = ChartOfAccount::query()->where('code', $code)->value('id');
        }

        return $this->codeToIdCache[$code];
    }

    private function resolveCodeById(int $id): ?string
    {
        if (! array_key_exists($id, $this->idToCodeCache)) {
            $this->idToCodeCache[$id] = ChartOfAccount::query()->whereKey($id)->value('code');
        }

        return $this->idToCodeCache[$id];
    }
}