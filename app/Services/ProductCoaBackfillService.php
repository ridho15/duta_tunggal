<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\Product;

class ProductCoaBackfillService
{
    /**
     * Default COA codes used by the Product create form.
     *
     * @var array<string, string>
     */
    private const DEFAULT_COA_CODES = [
        'sales_coa_id' => '4100.10',
        'sales_return_coa_id' => '4120.10',
        'sales_discount_coa_id' => '4110.10',
        'goods_delivery_coa_id' => '1140.20',
        'cogs_coa_id' => '5100.10',
        'purchase_return_coa_id' => '5120.10',
        'unbilled_purchase_coa_id' => '2100.10',
        'temporary_procurement_coa_id' => '2100.10',
        'manufacturing_labor_coa_id' => '5230',
        'manufacturing_overhead_coa_id' => '6000',
    ];

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

    public function resolveDefaultValues(Product $product): array
    {
        return [
            'inventory_coa_id' => $this->resolveInventoryCoaId($product),
            'sales_coa_id' => $this->resolveIdByCode(self::DEFAULT_COA_CODES['sales_coa_id']),
            'sales_return_coa_id' => $this->resolveIdByCode(self::DEFAULT_COA_CODES['sales_return_coa_id']),
            'sales_discount_coa_id' => $this->resolveIdByCode(self::DEFAULT_COA_CODES['sales_discount_coa_id']),
            'goods_delivery_coa_id' => $this->resolveIdByCode(self::DEFAULT_COA_CODES['goods_delivery_coa_id']),
            'cogs_coa_id' => $this->resolveIdByCode(self::DEFAULT_COA_CODES['cogs_coa_id']),
            'purchase_return_coa_id' => $this->resolveIdByCode(self::DEFAULT_COA_CODES['purchase_return_coa_id']),
            'unbilled_purchase_coa_id' => $this->resolveIdByCode(self::DEFAULT_COA_CODES['unbilled_purchase_coa_id']),
            'temporary_procurement_coa_id' => $this->resolveIdByCode(self::DEFAULT_COA_CODES['temporary_procurement_coa_id']),
            'manufacturing_labor_coa_id' => $this->resolveIdByCode(self::DEFAULT_COA_CODES['manufacturing_labor_coa_id']),
            'manufacturing_overhead_coa_id' => $this->resolveIdByCode(self::DEFAULT_COA_CODES['manufacturing_overhead_coa_id']),
        ];
    }

    public function resolveMissingDefaultValues(Product $product): array
    {
        return array_filter(
            $this->resolveDefaultValues($product),
            static fn ($value) => $value !== null
        );
    }

    public function defaultCodeForField(Product $product, string $field): ?string
    {
        if ($field === 'inventory_coa_id') {
            $coaId = $this->resolveInventoryCoaId($product);

            return $coaId ? $this->resolveCodeById($coaId) : null;
        }

        return self::DEFAULT_COA_CODES[$field] ?? null;
    }

    public function missingDefaultCodes(): array
    {
        $missing = [];

        foreach (self::DEFAULT_COA_CODES as $field => $code) {
            if ($this->resolveIdByCode($code) === null) {
                $missing[$field] = $code;
            }
        }

        return $missing;
    }

    private function resolveInventoryCoaId(Product $product): ?int
    {
        $cacheKey = implode(':', [
            $product->is_manufacture ? '1' : '0',
            $product->is_raw_material ? '1' : '0',
        ]);

        if (! array_key_exists($cacheKey, $this->inventoryDefaultCache)) {
            $this->inventoryDefaultCache[$cacheKey] = Product::resolveDefaultInventoryCoaId(
                (bool) $product->is_manufacture,
                (bool) $product->is_raw_material,
            );
        }

        return $this->inventoryDefaultCache[$cacheKey];
    }

    private function resolveIdByCode(string $code): ?int
    {
        if (! array_key_exists($code, $this->codeToIdCache)) {
            $this->codeToIdCache[$code] = ChartOfAccount::where('code', $code)->value('id');
        }

        return $this->codeToIdCache[$code];
    }

    private function resolveCodeById(int $id): ?string
    {
        if (! array_key_exists($id, $this->idToCodeCache)) {
            $this->idToCodeCache[$id] = ChartOfAccount::whereKey($id)->value('code');
        }

        return $this->idToCodeCache[$id];
    }
}