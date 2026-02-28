<?php

namespace App\Filament\Pages;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\ManufacturingOrder;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class CostOfGoodsManufacturingPage extends Page
{
    protected static string $view = 'filament.pages.cost-of-goods-manufacturing-page';

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Finance - Laporan';

    protected static ?string $navigationLabel = 'Cost of Goods Manufacturing';

    protected static ?int $navigationSort = 8;

    protected static ?string $slug = 'cost-of-goods-manufacturing';

    // Filter state
    public bool $showPreview = false;
    public ?string $start_date = null;
    public ?string $end_date = null;
    public ?int $cabang_id = null;
    public ?int $product_id = null;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('preview')
                ->label('Tampilkan Laporan')
                ->icon('heroicon-o-eye')
                ->color('primary')
                ->action(fn () => $this->generateReport()),

            \Filament\Actions\Action::make('reset')
                ->label('Reset')
                ->icon('heroicon-o-x-circle')
                ->color('gray')
                ->visible(fn () => $this->showPreview)
                ->action(fn () => $this->resetReport()),
        ];
    }

    public function generateReport(): void
    {
        $this->showPreview = true;
    }

    public function resetReport(): void
    {
        $this->showPreview = false;
    }

    public function mount(): void
    {
        $this->start_date = now()->startOfMonth()->format('Y-m-d');
        $this->end_date = now()->endOfMonth()->format('Y-m-d');
    }

    public function getCogmData(): array
    {
        if (!$this->showPreview) {
            return [];
        }

        $start = Carbon::parse($this->start_date)->startOfDay();
        $end = Carbon::parse($this->end_date)->endOfDay();

        // Fetch manufacturing orders in period
        $moQuery = ManufacturingOrder::whereBetween('created_at', [$start, $end]);
        if ($this->cabang_id) {
            $moQuery->where('cabang_id', $this->cabang_id);
        }
        if ($this->product_id) {
            $moQuery->whereHas('productionPlan', fn ($q) => $q->where('product_id', $this->product_id));
        }
        $orders = $moQuery->with(['productionPlan.product', 'productionPlan.billOfMaterial'])->get();

        // Aggregate from journal entries for COGM-related accounts
        // Raw material consumption (Material Issue journal entries)
        $rawMaterialCoa = ChartOfAccount::where('name', 'like', '%Bahan Baku%')
            ->orWhere('name', 'like', '%Raw Material%')
            ->orWhere('name', 'like', '%Material Issue%')
            ->pluck('id');

        $laborCoa = ChartOfAccount::where('name', 'like', '%Tenaga Kerja%')
            ->orWhere('name', 'like', '%Direct Labor%')
            ->orWhere('name', 'like', '%Upah%')
            ->pluck('id');

        $overheadCoa = ChartOfAccount::where('name', 'like', '%Overhead%')
            ->orWhere('name', 'like', '%BOP%')
            ->pluck('id');

        $wipCoa = ChartOfAccount::where('name', 'like', '%WIP%')
            ->orWhere('name', 'like', '%Barang Dalam Proses%')
            ->pluck('id');

        $rawMaterialUsed = $this->sumJe($rawMaterialCoa, $start, $end, 'debit');
        $laborCost       = $this->sumJe($laborCoa, $start, $end, 'debit');
        $overhead        = $this->sumJe($overheadCoa, $start, $end, 'debit');

        $epoch = Carbon::createFromTimestamp(0);
        $openingWip = $this->sumJe($wipCoa, $epoch, $start->copy()->subDay(), 'debit')
                    - $this->sumJe($wipCoa, $epoch, $start->copy()->subDay(), 'credit');
        $closingWip = $this->sumJe($wipCoa, $epoch, $end, 'debit')
                    - $this->sumJe($wipCoa, $epoch, $end, 'credit');

        $cogm = $openingWip + $rawMaterialUsed + $laborCost + $overhead - $closingWip;

        return [
            'orders'            => $orders,
            'opening_wip'       => $openingWip,
            'raw_material_used' => $rawMaterialUsed,
            'labor_cost'        => $laborCost,
            'overhead'          => $overhead,
            'closing_wip'       => $closingWip,
            'cogm'              => $cogm,
            'period'            => $start->format('d M Y') . ' s/d ' . $end->format('d M Y'),
            'mo_count'          => $orders->count(),
        ];
    }

    protected function sumJe($ids, $start, $end, string $col): float
    {
        if (empty($ids) || (is_object($ids) && $ids->isEmpty())) return 0.0;
        return (float) JournalEntry::whereIn('coa_id', $ids)
            ->whereBetween('date', [$start, $end])
            ->when($this->cabang_id, fn ($q) => $q->where('cabang_id', $this->cabang_id))
            ->sum($col);
    }

    public function getProductOptionsProperty(): array
    {
        return \App\Models\Product::query()->get()
            ->mapWithKeys(fn ($p) => [$p->id => "{$p->sku} - {$p->name}"])
            ->toArray();
    }
}
