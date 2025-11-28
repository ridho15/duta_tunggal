<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\StockMovementResource;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use Filament\Tables;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class MutasiMasukBelumSelesaiTable extends BaseWidget
{
    protected static ?string $heading = "Stock Transfer In";
    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                return StockMovement::query()
                    ->whereIn('type', ['purchase_in', 'transfer_in', 'manufacture_in', 'adjustment_in']);
            })
            ->actions([
                ViewAction::make()
                    ->color('primary')
                    ->url(function ($record) {
                        return StockMovementResource::getUrl('view', ['record' => $record]);
                    })
            ], position: ActionsPosition::BeforeColumns)
            ->columns([
                TextColumn::make('product')
                    ->label('Product')
                    ->formatStateUsing(function ($state) {
                        return "({$state->sku}) {$state->name}";
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('product', function (Builder $query) use ($search) {
                            $query->where('sku', 'LIKE', '%' . $search . '%')
                                ->orWhere('name', 'LIKE', '%' . $search . '%');
                        });
                    }),
                TextColumn::make('warehouse')
                    ->label('Gudang')
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('warehouse', function ($query) use ($search) {
                            $query->where('kode', 'LIKE', '%' . $search . '%')
                                ->orWhere('name', 'LIKE', '%' . $search . '%');
                        });
                    })->formatStateUsing(function ($state) {
                        return "({$state->kode}) {$state->name}";
                    }),
                TextColumn::make('rak')
                    ->label('Rak')
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('rak', function ($query) use ($search) {
                            return $query->where('code', 'LIKE', '%' . $search . '%')
                                ->orWhere('name', 'LIKE', '%' . $search . '%');
                        });
                    })->formatStateUsing(function ($state) {
                        return "({$state->code}) {$state->name}";
                    }),
                TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('type')
                    ->color(function ($state) {
                        return match ($state) {
                            'purchase_in' => 'success',
                            'transfer_in' => 'primary',
                            'transfer_out' => 'warning',
                            'manufacture_in' => 'info',
                            'manufacture_out' => 'warning',
                            'adjustment_in' => 'secondary',
                            default => 'gray',
                        };
                    })->formatStateUsing(function ($state) {
                        return match ($state) {
                            'purchase_in' => 'Purchase In',
                            'transfer_in' => 'Transfer In',
                            'transfer_out' => 'Transfer Out',
                            'manufacture_in' => 'Manufacture In',
                            'manufacture_out' => 'Manufacture Out',
                            'adjustment_in' => 'Adjustment In',
                            default => '-'
                        };
                    })
                    ->badge(),
                TextColumn::make('date')
                    ->dateTime()
                    ->sortable(),
            ]);
    }
}
