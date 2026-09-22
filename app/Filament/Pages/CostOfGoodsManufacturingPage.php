<?php

namespace App\Filament\Pages;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\ManufacturingOrder;
use App\Models\Cabang;
use App\Models\Product;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;

class CostOfGoodsManufacturingPage extends Page implements HasForms
{
    use InteractsWithForms;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static string $view = 'filament.pages.cost-of-goods-manufacturing-page';

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Laporan Keuangan';

    protected static ?string $navigationLabel = 'Cost of Goods Manufacturing';

    protected static ?string $navigationParentItem = 'Laporan Keuangan';

    protected static ?int $navigationSort = 8;

    protected static ?string $slug = 'cost-of-goods-manufacturing';

    // Filter state
    public bool $showPreview = false;
    public ?string $start_date = null;
    public ?string $end_date = null;
    public ?int $cabang_id = null;
    public ?int $product_id = null;

    public function mount(): void
    {
        $this->showPreview = filter_var(request('preview', false), FILTER_VALIDATE_BOOL);

        $this->form->fill([
            'start_date' => request('start_date', now()->startOfMonth()->format('Y-m-d')),
            'end_date'   => request('end_date', now()->endOfMonth()->format('Y-m-d')),
            'cabang_id'  => request('cabang_id'),
            'product_id' => request('product_id'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Filter Laporan Harga Pokok Produksi')
                    ->schema([
                        DatePicker::make('start_date')
                            ->label('Tanggal Mulai')
                            ->required()
                            ->displayFormat('d/m/Y')
                            ->default(now()->startOfMonth()),

                        DatePicker::make('end_date')
                            ->label('Tanggal Akhir')
                            ->required()
                            ->displayFormat('d/m/Y')
                            ->default(now()->endOfMonth()),

                        Select::make('cabang_id')
                            ->label('Cabang')
                            ->options(function () {
                                return Cabang::all()->mapWithKeys(function ($cabang) {
                                    return [$cabang->id => "({$cabang->kode}) {$cabang->nama}"];
                                });
                            })
                            ->searchable()
                            ->preload()
                            ->placeholder('Semua Cabang')
                            ->getSearchResultsUsing(function (string $search) {
                                return Cabang::where('nama', 'like', "%{$search}%")
                                    ->orWhere('kode', 'like', "%{$search}%")
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn ($c) => [$c->id => "({$c->kode}) {$c->nama}"]);
                            }),

                        Select::make('product_id')
                            ->label('Produk')
                            ->options(function () {
                                return Product::query()->get()
                                    ->mapWithKeys(fn ($p) => [$p->id => "{$p->sku} - {$p->name}"]);
                            })
                            ->searchable()
                            ->preload()
                            ->placeholder('Semua Produk'),
                    ])->columns(4),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('preview')
                ->label('Tampilkan Laporan')
                ->icon('heroicon-o-eye')
                ->color('primary')
                ->action(fn () => $this->generateReport()),

            \Filament\Actions\Action::make('print')
                ->label('Cetak')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->visible(fn () => $this->showPreview)
                ->url(fn () => $this->getPreviewUrl())
                ->openUrlInNewTab(),

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
        $data = $this->form->getState();

        if (empty($data['start_date']) || empty($data['end_date'])) {
            Notification::make()
                ->title('Error')
                ->danger()
                ->body('Tanggal mulai dan akhir harus diisi.')
                ->send();
            return;
        }

        if ($data['start_date'] > $data['end_date']) {
            Notification::make()
                ->title('Error')
                ->danger()
                ->body('Tanggal mulai tidak boleh lebih besar dari tanggal akhir.')
                ->send();
            return;
        }

        $this->start_date = $data['start_date'];
        $this->end_date   = $data['end_date'];
        $this->cabang_id  = $data['cabang_id'] ?? null;
        $this->product_id = $data['product_id'] ?? null;

        $this->dispatch('open-report-preview', url: $this->getPreviewUrl());
    }

    public function resetReport(): void
    {
        $this->redirect(static::getUrl());
    }

    public function getPreviewUrl(): string
    {
        return route('reports.cogm.preview', array_filter([
            'start_date' => $this->start_date,
            'end_date'   => $this->end_date,
            'cabang_id'  => $this->cabang_id,
            'product_id' => $this->product_id,
        ], fn ($v) => $v !== null && $v !== ''));
    }

    public function getCogmData(): array
    {
        if (!$this->showPreview) {
            return [];
        }

        $start = Carbon::parse($this->start_date)->startOfDay();
        $end = Carbon::parse($this->end_date)->endOfDay();

        $moQuery = ManufacturingOrder::whereBetween('created_at', [$start, $end]);
        if ($this->cabang_id) {
            $moQuery->where('cabang_id', $this->cabang_id);
        }
        if ($this->product_id) {
            $moQuery->whereHas('productionPlan', fn ($q) => $q->where('product_id', $this->product_id));
        }
        $orders = $moQuery->with(['productionPlan.product', 'productionPlan.billOfMaterial'])->get();

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
        if (empty($ids) || (is_object($ids) && $ids->isEmpty())) {
            return 0.0;
        }
        return (float) JournalEntry::whereIn('coa_id', $ids)
            ->whereBetween('date', [$start, $end])
            ->when($this->cabang_id, fn ($q) => $q->where('cabang_id', $this->cabang_id))
            ->sum($col);
    }
}

