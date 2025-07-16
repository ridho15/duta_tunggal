<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\DeliveryOrderResource;
use App\Models\DeliveryOrder;
use Filament\Tables;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables\Enums\ActionsPosition;

class DoBelumSelesaiTable extends BaseWidget
{
    protected static ?string $heading = "DO Belum Selesai";
    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                return DeliveryOrder::query()->where('status', '!=', 'completed');
            })
            ->actions([
                ViewAction::make()
                    ->color('primary')
                    ->url(function ($record) {
                        return DeliveryOrderResource::getUrl('view', ['record' => $record]);
                    })
            ], position: ActionsPosition::BeforeColumns)
            ->columns([
                TextColumn::make('do_number')
                    ->label('Delivery Order Number')
                    ->searchable(),
                TextColumn::make('delivery_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('driver.name')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('salesOrders.customer.name')
                    ->label('Customer')
                    ->badge()
                    ->searchable(),
            ]);
    }
}
