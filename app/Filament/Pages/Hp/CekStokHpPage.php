<?php

namespace App\Filament\Pages\Hp;

use App\Filament\Resources\OrderRequestResource;
use App\Filament\Resources\StockTransferResource;
use App\Models\InventoryStock;
use App\Models\Product;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

/**
 * Cek Stok di HP (Spesifikasi UI/UX 6.12, mockup D2 + F4): cari / scan barang,
 * stok bebas sebagai angka besar dan rincian per gudang.
 * Stok bebas = stok fisik − stok dikunci DO (InventoryStock::qty_available − qty_reserved).
 */
class CekStokHpPage extends Page
{
    use \App\Filament\Concerns\HasMenuAccess;

    protected static ?string $slug = 'cek-stok-hp';

    protected static ?string $title = 'Cek Stok';

    protected static string $view = 'filament.pages.hp.cek-stok';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'produk')]
    public ?int $productId = null;

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return false;
    }

    public function getSubheading(): ?string
    {
        return 'Stok bebas = stok fisik − stok dikunci DO.';
    }

    public function updatedSearch(): void
    {
        $this->productId = null;
    }

    public function open(int $productId): void
    {
        $this->productId = $productId;
    }

    #[On('barcode-scanned')]
    public function onBarcodeScanned(string $code): void
    {
        $code = trim($code);
        $product = Product::where('sku', $code)->first();

        $this->search = $code;
        $this->productId = $product?->id;
    }

    /** @return Collection<int, Product> */
    protected function results(): Collection
    {
        $term = trim($this->search);
        if ($term === '' && ! $this->productId) {
            return collect();
        }

        return Product::query()
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('sku', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")))
            ->when($term === '' && $this->productId, fn ($q) => $q->whereKey($this->productId))
            ->with('uom')
            ->orderByRaw('CASE WHEN sku = ? THEN 0 ELSE 1 END', [$term])
            ->orderBy('name')
            ->limit(20)
            ->get();
    }

    /**
     * Ringkasan stok per produk (semua gudang yang boleh dilihat user).
     *
     * @param  array<int, int>  $productIds
     * @return array<int, array{physical: float, locked: float, free: float, min: float, warehouses: array<int, array{name: string, physical: float, locked: float, free: float}>}>
     */
    protected function stockSummary(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $user = Auth::user();
        $rows = InventoryStock::query()
            ->whereIn('product_id', $productIds)
            ->with('warehouse:id,kode,name,cabang_id,is_quarantine')
            ->get(['id', 'product_id', 'warehouse_id', 'qty_available', 'qty_reserved', 'qty_min'])
            ->filter(fn ($row) => $row->warehouse && ! $row->warehouse->is_quarantine && $user->canAccessCabang($row->warehouse->cabang_id));

        $summary = [];
        foreach ($rows->groupBy('product_id') as $productId => $productRows) {
            $warehouses = [];
            foreach ($productRows->groupBy('warehouse_id') as $warehouseRows) {
                $warehouse = $warehouseRows->first()->warehouse;
                $physical = (float) $warehouseRows->sum('qty_available');
                $locked = (float) $warehouseRows->sum('qty_reserved');
                $warehouses[] = [
                    'name' => $warehouse->kode ?: $warehouse->name,
                    'physical' => $physical,
                    'locked' => $locked,
                    'free' => $physical - $locked,
                ];
            }

            usort($warehouses, fn ($a, $b) => $b['free'] <=> $a['free']);

            $physical = array_sum(array_column($warehouses, 'physical'));
            $locked = array_sum(array_column($warehouses, 'locked'));

            $summary[(int) $productId] = [
                'physical' => $physical,
                'locked' => $locked,
                'free' => $physical - $locked,
                'min' => (float) $productRows->sum('qty_min'),
                'warehouses' => $warehouses,
            ];
        }

        return $summary;
    }

    protected function getViewData(): array
    {
        $results = $this->results();
        $summary = $this->stockSummary($results->pluck('id')->all());
        $selected = $this->productId ? $results->firstWhere('id', $this->productId) : $results->first();

        return [
            'results' => $results,
            'summary' => $summary,
            'selected' => $selected,
            'transferUrl' => StockTransferResource::canAccess() && StockTransferResource::canCreate() ? StockTransferResource::getUrl('create') : null,
            'requestUrl' => OrderRequestResource::canAccess() && OrderRequestResource::canCreate() ? OrderRequestResource::getUrl('create') : null,
        ];
    }
}
