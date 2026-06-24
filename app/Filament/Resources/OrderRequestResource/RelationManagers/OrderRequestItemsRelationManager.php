<?php

namespace App\Filament\Resources\OrderRequestResource\RelationManagers;

use App\Filament\Resources\OrderRequestResource;
use App\Filament\Resources\OrderRequestResource\Pages\EditOrderRequest;
use App\Models\Cabang;
use App\Models\OrderRequestItem;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class OrderRequestItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'orderRequestItem';

    protected static ?string $title = 'Item Order Request';

    protected static ?string $modelLabel = 'Item Order Request';

    protected static ?string $pluralModelLabel = 'Item Order Request';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if ($pageClass === EditOrderRequest::class) {
            return false;
        }

        return parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Item Order Request')
            ->description('Gunakan pencarian, filter, dan pagination untuk meninjau Order Request dengan banyak item.')
            ->defaultSort('id')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'product:id,sku,name,uom_id',
                'product.uom:id,name,abbreviation',
                'supplier:id,code,perusahaan',
                'cabang:id,kode,nama',
                'currency:id,code,symbol,name',
            ]))
            ->columns([
                TextColumn::make('product.sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('product.name')
                    ->label('Produk')
                    ->searchable()
                    ->sortable()
                    ->limit(45)
                    ->tooltip(fn ($record) => $record->product?->name),
                TextColumn::make('supplier_summary')
                    ->label('Supplier')
                    ->getStateUsing(function ($record) {
                        if (! $record->supplier || ! $record->supplier->exists) {
                            return '-';
                        }

                        $code = $record->supplier->code ?? '-';
                        $name = $record->supplier->perusahaan ?? '-';

                        return "({$code}) {$name}";
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('supplier', function (Builder $query) use ($search) {
                            $query->where('code', 'like', "%{$search}%")
                                ->orWhere('perusahaan', 'like', "%{$search}%");
                        });
                    })
                    ->limit(35)
                    ->tooltip(fn ($state) => $state),
                TextColumn::make('cabang_summary')
                    ->label('Cabang')
                    ->getStateUsing(function ($record) {
                        if (! $record->cabang || ! $record->cabang->exists) {
                            return '-';
                        }

                        $code = $record->cabang->kode ?? '-';
                        $name = $record->cabang->nama ?? '-';

                        return "({$code}) {$name}";
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('cabang', function (Builder $query) use ($search) {
                            $query->where('kode', 'like', "%{$search}%")
                                ->orWhere('nama', 'like', "%{$search}%");
                        });
                    })
                    ->limit(35)
                    ->tooltip(fn ($state) => $state),
                TextColumn::make('quantity')
                    ->label('Qty')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('uom_summary')
                    ->label('UOM')
                    ->getStateUsing(fn ($record) => $record->product?->uom?->abbreviation ?? $record->product?->uom?->name ?? '-')
                    ->toggleable(),
                TextColumn::make('fulfilled_quantity')
                    ->label('Qty Diterima')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('unit_price')
                    ->label('Harga')
                    ->formatStateUsing(fn ($state, $record) => OrderRequestResource::resolveCurrencySymbol($record->currency_id ?? $this->getOwnerRecord()?->currency_id) . ' ' . OrderRequestResource::formatMoneyPreviewState($state))
                    ->sortable(),
                TextColumn::make('discount')
                    ->label('Disc')
                    ->suffix('%')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('tax')
                    ->label('Pajak')
                    ->suffix('%')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('tipe_pajak')
                    ->label('Tipe Pajak')
                    ->formatStateUsing(fn ($state) => Str::upper($state ?: '-'))
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'inklusif' => 'success',
                        'none' => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('status')
                    ->label('Status Item')
                    ->formatStateUsing(fn ($state) => OrderRequestItem::approvalStatusLabel($state))
                    ->badge()
                    ->color(fn ($state) => OrderRequestItem::approvalStatusColor($state))
                    ->sortable(),
                TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->formatStateUsing(fn ($state, $record) => OrderRequestResource::resolveCurrencySymbol($record->currency_id ?? $this->getOwnerRecord()?->currency_id) . ' ' . OrderRequestResource::formatMoneyPreviewState($state))
                    ->sortable(),
                TextColumn::make('note')
                    ->label('Catatan')
                    ->limit(35)
                    ->tooltip(fn ($state) => $state)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('rejection_note')
                    ->label('Alasan Reject')
                    ->limit(45)
                    ->tooltip(fn ($state) => $state)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->options(fn () => Supplier::query()
                        ->orderBy('perusahaan')
                        ->limit(100)
                        ->get()
                        ->mapWithKeys(fn (Supplier $supplier) => [
                            $supplier->id => "({$supplier->code}) {$supplier->perusahaan}",
                        ]))
                    ->searchable(),
                SelectFilter::make('cabang_id')
                    ->label('Cabang')
                    ->options(fn () => Cabang::query()
                        ->orderBy('kode')
                        ->limit(100)
                        ->get()
                        ->mapWithKeys(fn (Cabang $cabang) => [
                            $cabang->id => "({$cabang->kode}) {$cabang->nama}",
                        ]))
                    ->searchable(),
                SelectFilter::make('tipe_pajak')
                    ->label('Tipe Pajak')
                    ->options([
                        'inklusif' => 'Inklusif',
                        'eklusif' => 'Eklusif',
                        'none' => 'Non Pajak',
                    ]),
                SelectFilter::make('status')
                    ->label('Status Item')
                    ->options([
                        OrderRequestItem::STATUS_DRAFT => 'Draft',
                        OrderRequestItem::STATUS_APPROVED => 'Approved',
                        OrderRequestItem::STATUS_REJECTED => 'Rejected',
                    ]),
                SelectFilter::make('fulfillment_status')
                    ->label('Status Qty')
                    ->options([
                        'open' => 'Belum diterima',
                        'partial' => 'Diterima sebagian',
                        'complete' => 'Sudah lengkap',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'open' => $query->where(fn (Builder $query) => $query
                                ->whereNull('fulfilled_quantity')
                                ->orWhere('fulfilled_quantity', '<=', 0)),
                            'partial' => $query
                                ->where('fulfilled_quantity', '>', 0)
                                ->whereColumn('fulfilled_quantity', '<', 'quantity'),
                            'complete' => $query->whereColumn('fulfilled_quantity', '>=', 'quantity'),
                            default => $query,
                        };
                    }),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
