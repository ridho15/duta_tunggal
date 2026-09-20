<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\SaleOrderResource;
use App\Models\SaleOrder;
use Filament\Tables;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Filament\Tables\Enums\ActionsPosition;

class SoBelumSelesaiTable extends BaseWidget
{
    protected static ?string $heading = 'SO Belum Selesai';
    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->query(function () {
                // Hanya SO yang benar-benar masih berjalan (disetujui, belum selesai) — bukan draft/menunggu
                // persetujuan/ditutup/dibatalkan/ditolak, yang sebelumnya ikut terhitung "belum selesai".
                return SaleOrder::query()
                    ->whereIn('status', SaleOrder::OUTSTANDING_STATUSES);
            })->actions([
                ViewAction::make()
                    ->color('primary')
                    ->url(function ($record) {
                        return SaleOrderResource::getUrl('view', ['record' => $record]);
                    })
            ], position: ActionsPosition::BeforeColumns)
            ->columns([
                TextColumn::make('customer')
                    ->label('Customer')
                    ->formatStateUsing(function ($state) {
                        return "({$state->code}) {$state->name}";
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('customer', function ($query) use ($search) {
                            $query->where('code', 'LIKE', '%' . $search . '%')
                                ->orWhere('name', 'LIKE', '%' . $search . '%');
                        });
                    }),
                TextColumn::make('so_number')
                    ->searchable(),
                TextColumn::make('order_date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => \App\Models\SaleOrder::statusLabel($state))
                    ->color(fn ($state) => \App\Models\SaleOrder::statusColor($state))
                    ->badge(),
                TextColumn::make('shipped_to')
                    ->label('Shipped To')
                    ->searchable(),
            ]);
    }
}
