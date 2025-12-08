<?php

namespace Database\Seeders\Finance;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Rak;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;

class FinanceSeedContext
{
    protected array $coaCache = [];
    protected ?int $defaultUserId = null;
    protected ?Cabang $cachedCabang = null;
    protected ?Warehouse $cachedWarehouse = null;
    protected ?Rak $cachedRak = null;
    protected ?ProductCategory $cachedCategory = null;
    protected ?UnitOfMeasure $cachedUnit = null;
    protected ?array $seedProductSet = null;

    public function getDefaultUserId(): int
    {
        if ($this->defaultUserId !== null) {
            return $this->defaultUserId;
        }

        $user = User::query()->first();
        if (!$user) {
            $user = User::factory()->create([
                'name' => 'Seeder Admin',
                'email' => 'seeder-admin@example.com',
            ]);
        }

        return $this->defaultUserId = $user->id;
    }

    public function ensureCabang(): Cabang
    {
        if ($this->cachedCabang) {
            return $this->cachedCabang;
        }

        $cabang = Cabang::query()->first();
        if ($cabang) {
            return $this->cachedCabang = $cabang;
        }

        return $this->cachedCabang = Cabang::create([
            'kode' => 'CBG-SEED',
            'nama' => 'Cabang Utama Seeder',
            'alamat' => 'Jl. Proklamasi No. 1',
            'telepon' => '021-7654321',
            'kenaikan_harga' => 0,
            'status' => true,
            'warna_background' => '#1F2937',
            'tipe_penjualan' => 'Semua',
        ]);
    }

    public function ensureWarehouse(): Warehouse
    {
        if ($this->cachedWarehouse) {
            return $this->cachedWarehouse;
        }

        $warehouse = Warehouse::query()->first();
        if ($warehouse) {
            return $this->cachedWarehouse = $warehouse;
        }

        $cabang = $this->ensureCabang();

        return $this->cachedWarehouse = Warehouse::create([
            'kode' => 'GUD-SEED',
            'name' => 'Gudang Utama Seeder',
            'cabang_id' => $cabang->id,
            'location' => 'Jl. Industri No. 1',
            'telepon' => '021-1234567',
            'tipe' => 'Besar',
            'status' => true,
        ]);
    }

    public function ensureRak(): Rak
    {
        if ($this->cachedRak) {
            return $this->cachedRak;
        }

        $warehouse = $this->ensureWarehouse();
        $rak = Rak::where('warehouse_id', $warehouse->id)->first();
        if ($rak) {
            return $this->cachedRak = $rak;
        }

        return $this->cachedRak = Rak::create([
            'name' => 'Rak A1',
            'code' => 'RAK-SEED-A1',
            'warehouse_id' => $warehouse->id,
        ]);
    }

    public function ensureProductCategory(): ProductCategory
    {
        if ($this->cachedCategory) {
            return $this->cachedCategory;
        }

        $cabang = $this->ensureCabang();
        $category = ProductCategory::where('cabang_id', $cabang->id)->first();
        if ($category) {
            return $this->cachedCategory = $category;
        }

        return $this->cachedCategory = ProductCategory::create([
            'name' => 'Kategori Seeder',
            'kode' => 'CAT-SEED',
            'cabang_id' => $cabang->id,
            'kenaikan_harga' => 0,
        ]);
    }

    public function ensureUnit(): UnitOfMeasure
    {
        if ($this->cachedUnit) {
            return $this->cachedUnit;
        }

        $unit = UnitOfMeasure::first();
        if ($unit) {
            return $this->cachedUnit = $unit;
        }

        return $this->cachedUnit = UnitOfMeasure::create([
            'name' => 'Piece',
            'abbreviation' => 'pcs',
        ]);
    }

    /**
     * @return array{0:\App\Models\Product,1:\App\Models\Product,2:\App\Models\Product}
     */
    public function getSeedProductSet(): array
    {
        if ($this->seedProductSet !== null) {
            return $this->seedProductSet;
        }

        $products = Product::orderBy('id')->take(3)->get();
        if ($products->count() < 3) {
            $needed = 3 - $products->count();
            $cabang = $this->ensureCabang();
            $category = $this->ensureProductCategory();
            $unit = $this->ensureUnit();

            for ($i = 0; $i < $needed; $i++) {
                $products->push(Product::create([
                    'name' => 'Produk Seeder ' . ($i + 1),
                    'sku' => 'SEED-PROD-' . ($i + 1),
                    'cabang_id' => $cabang->id,
                    'product_category_id' => $category->id,
                    'uom_id' => $unit->id,
                    'cost_price' => 1000000,
                    'sell_price' => 1500000,
                    'biaya' => 0,
                    'harga_batas' => 0,
                    'tipe_pajak' => 'Non Pajak',
                    'pajak' => 0,
                    'jumlah_kelipatan_gudang_besar' => 1,
                    'jumlah_jual_kategori_banyak' => 1,
                    'kode_merk' => 'SEED',
                    'is_manufacture' => false,
                    'is_raw_material' => false,
                    'inventory_coa_id' => $this->getCoa('1140.01')?->id,
                    'is_active' => true,
                ]));
            }

            $products = Product::orderBy('id')->take(3)->get();
        }

        $finishedPrimary = tap($products[0])->update([
            'name' => 'Panel Kontrol Industri',
            'sku' => 'FG-SEED-001',
            'sell_price' => 12500000,
            'cost_price' => 8500000,
            'is_manufacture' => true,
            'is_raw_material' => false,
            'inventory_coa_id' => $this->getCoa('1140.01')?->id,
        ]);

        $finishedSecondary = tap($products[1])->update([
            'name' => 'Sensor Tekanan Digital',
            'sku' => 'FG-SEED-002',
            'sell_price' => 2500000,
            'cost_price' => 1650000,
            'is_manufacture' => true,
            'is_raw_material' => false,
            'inventory_coa_id' => $this->getCoa('1140.01')?->id,
        ]);

        $rawMaterial = tap($products[2])->update([
            'name' => 'Bahan Baku Plastik Granul',
            'sku' => 'RM-SEED-001',
            'sell_price' => 0,
            'cost_price' => 180000,
            'is_manufacture' => false,
            'is_raw_material' => true,
            'inventory_coa_id' => $this->getCoa('1140.01')?->id,
        ]);

        return $this->seedProductSet = [$finishedPrimary, $finishedSecondary, $rawMaterial];
    }

    public function getCoa(string $code): ?ChartOfAccount
    {
        if (!$code) {
            return null;
        }

        if (!array_key_exists($code, $this->coaCache)) {
            $this->coaCache[$code] = ChartOfAccount::where('code', $code)->first();
        }

        return $this->coaCache[$code];
    }

    public function storeCoa(ChartOfAccount $coa): void
    {
        $this->coaCache[$coa->code] = $coa;
    }

    public function ensureOpeningBalance(string $code, float $amount): void
    {
        $coa = $this->getCoa($code);
        if (!$coa) {
            return;
        }

        if (abs(($coa->opening_balance ?? 0) - $amount) > 0.01) {
            $coa->opening_balance = $amount;
            $coa->save();
            $this->storeCoa($coa->refresh());
        }
    }

    public function inferAccountType(string $code): string
    {
        if (str_starts_with($code, '1220')) {
            return 'Contra Asset';
        }

        return match (substr($code, 0, 1)) {
            '1' => 'Asset',
            '2' => 'Liability',
            '3' => 'Equity',
            '4', '7' => 'Revenue',
            '5', '6', '8', '9' => 'Expense',
            default => 'Asset',
        };
    }

    public function resolveParentId(string $code): ?int
    {
        if (str_contains($code, '.')) {
            $parentCode = strtok($code, '.');
            return ChartOfAccount::where('code', $parentCode)->value('id');
        }

        $length = strlen($code);
        for ($i = $length - 1; $i >= 1; $i--) {
            $potential = substr($code, 0, $i) . str_repeat('0', $length - $i);
            $parentId = ChartOfAccount::where('code', $potential)->value('id');
            if ($parentId) {
                return $parentId;
            }
        }

        return null;
    }

    public function determineAgeingBucket(int $daysOutstanding): string
    {
        return match (true) {
            $daysOutstanding <= 30 => 'Current',
            $daysOutstanding <= 60 => '31–60',
            $daysOutstanding <= 90 => '61–90',
            default => '>90',
        };
    }

    public function calculateDaysOutstanding(Carbon $dueDate): int
    {
        return $dueDate->isFuture() ? 0 : $dueDate->diffInDays(Carbon::now());
    }

    public function recordJournalEntry(string $reference, string $coaCode, Carbon $date, float $debit, float $credit, string $description): void
    {
        $coa = $this->getCoa($coaCode);
        if (!$coa) {
            return;
        }

        JournalEntry::updateOrCreate(
            [
                'reference' => $reference,
                'coa_id' => $coa->id,
            ],
            [
                'date' => $date->toDateString(),
                'description' => $description,
                'debit' => $debit,
                'credit' => $credit,
                'journal_type' => 'seed',
                'cabang_id' => $this->ensureCabang()->id,
                'source_type' => null,
                'source_id' => null,
            ]
        );
    }
}
