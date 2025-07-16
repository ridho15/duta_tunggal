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
            ->query(function () {
                return SaleOrder::query()
                    ->where('status', '!=', 'completed');
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
                    ->formatStateUsing(function ($state) {
                        return Str::upper($state);
                    })
                    ->color(function ($state) {
                        return match ($state) {
                            'draft' => 'gray',
                            'process' => 'warning',
                            'completed' => 'success',
                            'received' => 'primary',
                            'approved' => 'success',
                            'confirmed' => 'success',
                            'canceled' => 'danger',
                            'reject' => 'danger',
                            'request_approve' => 'primary',
                            'request_close' => 'warning',
                            'closed' => 'danger',
                            default => '-'
                        };
                    })
                    ->badge(),
                TextColumn::make('shipped_to')
                    ->label('Shipped To')
                    ->searchable(),
            ]);
    }
}
