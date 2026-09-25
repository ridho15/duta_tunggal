<?php

namespace App\Filament\Resources\StockOpnameResource\RelationManagers;

use App\Helpers\MoneyHelper;
use App\Models\Product;
use App\Models\Rak;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class StockOpnameItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('product_id')
                    ->label('Product')
                    ->options(Product::pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                        if ($state) {
                            $product = Product::withoutGlobalScope('product_cabang')->find($state);
                            // Get current stock from inventory_stocks
                            $warehouseId = $this->getOwnerRecord()->warehouse_id;
                            $inventoryStock = \App\Models\InventoryStock::where('product_id', $state)
                                ->where('warehouse_id', $warehouseId)
                                ->first();

                            if ($inventoryStock) {
                                $set('system_qty', $inventoryStock->qty_available);
                            } else {
                                $set('system_qty', 0);
                            }

                            // Calculate average cost from purchase history
                            $opnameDate = $this->getOwnerRecord()->opname_date ?? now();
                            $averageCost = $this->calculateAverageCostForProduct($state, $opnameDate);
                            $set('average_cost', $this->formatMoney($averageCost));
                            $set('unit_cost', $this->formatMoney($averageCost)); // Set unit cost to average cost by default

                            $set('difference_qty', (float) ($get('physical_qty') ?? 0) - (float) ($get('system_qty') ?? 0));
                            $this->syncValues($set, $get, $averageCost);
                        }
                    }),

                Select::make('rak_id')
                    ->label('Rak')
                    ->options(function () {
                        $warehouseId = $this->getOwnerRecord()->warehouse_id ?? null;

                        if (!$warehouseId) {
                            return [];
                        }

                        return Rak::where('warehouse_id', $warehouseId)
                            ->orderBy('name')
                            ->pluck('name', 'id');
                    })
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->placeholder('Pilih rak (opsional)'),

                TextInput::make('system_qty')
                    ->label('Qty Sistem')
                    ->numeric()
                    ->default(0)
                    ->disabled()
                    ->dehydrated(),

                TextInput::make('physical_qty')
                    ->label('Qty Fisik (Opname)')
                    ->numeric()
                    ->default(0)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                        $systemQty = $get('system_qty') ?? 0;
                        $physicalQty = $state ?? 0;
                        $difference = $physicalQty - $systemQty;
                        $set('difference_qty', $difference);
                        $this->syncValues($set, $get, $get('unit_cost'));
                    }),

                TextInput::make('difference_qty')
                    ->label('Selisih Qty')
                    ->disabled()
                    ->dehydrated(),

                TextInput::make('unit_cost')
                    ->label('Harga Satuan')
                    ->indonesianMoney()
                    ->default(0)
                    ->live(debounce: 500)
                    ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                        $this->syncValues($set, $get, $state);
                    }),

                TextInput::make('average_cost')
                    ->label('Average Cost')
                    ->indonesianMoney()
                    ->default(0)
                    ->disabled()
                    ->dehydrated()
                    ->helperText('Harga rata-rata berdasarkan riwayat pembelian'),

                TextInput::make('difference_value')
                    ->label('Nilai Selisih')
                    ->indonesianMoney()
                    ->disabled()
                    ->dehydrated(),

                TextInput::make('total_value')
                    ->label('Total Nilai')
                    ->indonesianMoney()
                    ->disabled()
                    ->dehydrated()
                    ->helperText('Total nilai berdasarkan qty fisik × harga satuan'),

                Textarea::make('notes')
                    ->label('Catatan')
                    ->rows(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->recordTitleAttribute('product.name')
            ->columns([
                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('product.sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('rak.name')
                    ->label('Rak')
                    ->searchable(),

                TextColumn::make('system_qty')
                    ->label('Qty Sistem')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextInputColumn::make('physical_qty')
                    ->label('Qty Fisik')
                    ->rules(['numeric', 'min:0'])
                    ->disabled(fn () => $this->getOwnerRecord()->status === 'approved')
                    ->sortable(),

                TextColumn::make('difference_qty')
                    ->label('Selisih Qty')
                    ->numeric()
                    ->color(fn ($record) => $record->difference_qty > 0 ? 'success' : ($record->difference_qty < 0 ? 'danger' : 'gray'))
                    ->sortable(),

                TextColumn::make('unit_cost')
                    ->label('Harga Satuan')
                    ->rupiah()
                    ->sortable(),

                TextColumn::make('average_cost')
                    ->label('Average Cost')
                    ->rupiah()
                    ->sortable()
                    ->color('info'),

                TextColumn::make('difference_value')
                    ->label('Nilai Selisih')
                    ->rupiah()
                    ->color(fn ($record) => $record->difference_value > 0 ? 'success' : ($record->difference_value < 0 ? 'danger' : 'gray'))
                    ->sortable(),

                TextColumn::make('total_value')
                    ->label('Total Nilai')
                    ->rupiah()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\Action::make('populate_products')
                    ->label('Muat / Perbarui Stok Produk')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->visible(fn () => $this->getOwnerRecord()->status !== 'approved')
                    ->requiresConfirmation()
                    ->modalHeading('Muat Stok Produk Gudang')
                    ->modalDescription('Sistem akan memuat seluruh daftar stok produk di gudang ini untuk proses stock opname.')
                    ->modalSubmitActionLabel('Ya, Muat Produk')
                    ->action(function () {
                        $count = app(\App\Services\StockOpnameService::class)->startPhysicalCount($this->getOwnerRecord());
                        \App\Http\Controllers\HelperController::sendNotification(
                            isSuccess: true,
                            title: 'Stok Produk Dimuat',
                            message: "Berhasil memuat {$count} produk ke daftar hitung fisik."
                        );
                    }),
                Tables\Actions\CreateAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->status !== 'approved'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->status !== 'approved'),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->status !== 'approved'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => $this->getOwnerRecord()->status !== 'approved'),
                ]),
            ]);
    }

    /**
     * Calculate average cost for a product based on purchase history
     */
    /**
     * Harga satuan datang sebagai string bermask ("12.500,00"), jadi di-parse dengan MoneyHelper (bukan cast (float)).
     * Nilai Selisih dan Total Nilai dihitung ulang setiap qty atau harga berubah dan disimpan ke state dalam format uang
     * yang sama dengan input lain, supaya tampil konsisten dan tetap dibaca benar saat dehydrate (macro indonesianMoney).
     */
    private function syncValues(Forms\Set $set, Forms\Get $get, mixed $unitCost): void
    {
        $unit = MoneyHelper::safeParse($unitCost);

        $set('difference_value', $this->formatMoney((float) ($get('difference_qty') ?? 0) * $unit));
        $set('total_value', $this->formatMoney((float) ($get('physical_qty') ?? 0) * $unit));
    }

    private function formatMoney(mixed $value): string
    {
        return number_format(MoneyHelper::safeParse($value), 2, ',', '.');
    }

    private function calculateAverageCostForProduct($productId, $opnameDate)
    {
        // Get all purchase receipts for this product before the opname date
        $purchaseItems = \App\Models\PurchaseReceiptItem::where('product_id', $productId)
            ->whereHas('purchaseReceipt', function($query) use ($opnameDate) {
                $query->where('receipt_date', '<=', $opnameDate);
            })
            ->with('purchaseReceipt')
            ->orderBy('purchase_receipt_items.created_at', 'asc')
            ->get();

        if ($purchaseItems->isEmpty()) {
            // If no purchase history, return 0 or get from product cost
            $product = Product::find($productId);
            return $product ? ($product->cost ?? 0) : 0;
        }

        $totalQuantity = 0;
        $totalValue = 0;

        foreach ($purchaseItems as $item) {
            $quantity = $item->quantity_received ?? $item->quantity ?? 0;
            $unitPrice = $item->unit_price ?? 0;

            $totalQuantity += $quantity;
            $totalValue += ($quantity * $unitPrice);
        }

        return $totalQuantity > 0 ? $totalValue / $totalQuantity : 0;
    }
}
