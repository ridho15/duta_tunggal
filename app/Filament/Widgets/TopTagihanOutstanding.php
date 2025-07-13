<?php

namespace App\Filament\Widgets;

use App\Models\AgeingSchedule;
use App\Models\Customer;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopTagihanOutstanding extends BaseWidget
{
    public function table(Table $table): Table
    {
        return $table
            ->query(
                function () {
                    return AgeingSchedule::query()
                        ->where('from_type', 'customer') // sesuaikan jika perlu
                        ->orderByDesc('days_outstanding')
                        ->limit(10);
                }
            )
            ->columns([
                TextColumn::make('from_id')
                    ->label('Customer')
                    ->formatStateUsing(function ($state) {
                        $customer = Customer::find($state);
                        return $customer ? $customer->name : 'Unknown';
                    })
                    ->sortable(),

                TextColumn::make('invoice_date')
                    ->label('Tgl Invoice')
                    ->date()
                    ->sortable(),

                TextColumn::make('due_date')
                    ->label('Jatuh Tempo')
                    ->date()
                    ->sortable(),

                TextColumn::make('days_outstanding')
                    ->label('Hari Lewat')
                    ->badge()
                    ->color(fn(string $state): string => match (true) {
                        $state > 90 => 'danger',
                        $state > 60 => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('bucket')
                    ->label('Kategori Aging')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Current' => 'success',
                        '31–60' => 'warning',
                        '61–90' => 'orange',
                        default => 'danger',
                    }),
            ]);
    }
}
