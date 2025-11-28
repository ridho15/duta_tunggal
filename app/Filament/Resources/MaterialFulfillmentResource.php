<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaterialFulfillmentResource\Pages;
use App\Models\ProductionPlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\ViewColumn;

class MaterialFulfillmentResource extends Resource
{
    protected static ?string $model = ProductionPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Manufacturing Order';

    protected static ?string $navigationLabel = 'Pemenuhan Bahan Baku';

    protected static ?string $modelLabel = 'Pemenuhan Bahan Baku';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('plan_number')
                    ->label('Nomor Rencana')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Rencana')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Produk')
                    ->formatStateUsing(function ($state) {
                        return $state ?? '-';
                    }),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Kuantitas')
                    ->numeric(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status Rencana')
                    ->colors([
                        'gray' => 'draft',
                        'warning' => 'scheduled',
                        'info' => 'in_progress',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'draft' => 'Draft',
                            'scheduled' => 'Terjadwal',
                            'in_progress' => 'Dalam Proses',
                            'completed' => 'Selesai',
                            'cancelled' => 'Dibatalkan',
                            default => $state,
                        };
                    }),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Tanggal Mulai')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('Tanggal Selesai')
                    ->date('d/m/Y')
                    ->sortable(),

                // Ketersediaan bahan baku 100% atau belum (summary dari MaterialFulfillment)
                Tables\Columns\BadgeColumn::make('material_availability')
                    ->label('Ketersediaan Bahan')
                    ->getStateUsing(function ($record) {
                        $summary = \App\Models\MaterialFulfillment::getFulfillmentSummary($record);
                        if (($summary['total_materials'] ?? 0) === 0) {
                            return 'Belum dihitung';
                        }
                        $total = $summary['total_materials'];
                        $full = $summary['fully_available'];
                        return $full === $total ? '100% Tersedia' : "$full/$total";
                    })
                    ->colors([
                        'success' => fn ($state) => $state === '100% Tersedia',
                        'warning' => fn ($state) => $state !== '100% Tersedia' && $state !== 'Belum dihitung',
                        'gray' => fn ($state) => $state === 'Belum dihitung',
                    ]),

                // Pengambilan / Terpakai 100% atau belum (summary dari MaterialFulfillment)
                Tables\Columns\BadgeColumn::make('material_usage')
                    ->label('Pengambilan/Terpakai')
                    ->getStateUsing(function ($record) {
                        $summary = \App\Models\MaterialFulfillment::getFulfillmentSummary($record);
                        if (($summary['total_materials'] ?? 0) === 0) {
                            return 'Belum dihitung';
                        }
                        $total = $summary['total_materials'];
                        $full = $summary['fully_issued'];
                        return $full === $total ? '100% Terpakai' : "$full/$total";
                    })
                    ->colors([
                        'success' => fn ($state) => $state === '100% Terpakai',
                        'warning' => fn ($state) => $state !== '100% Terpakai' && $state !== 'Belum dihitung',
                        'gray' => fn ($state) => $state === 'Belum dihitung',
                    ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status Rencana')
                    ->options([
                        'draft' => 'Draft',
                        'scheduled' => 'Terjadwal',
                        'in_progress' => 'Dalam Proses',
                        'completed' => 'Selesai',
                        'cancelled' => 'Dibatalkan',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Lihat Detail'),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaterialFulfillments::route('/'),
            'view' => Pages\ViewMaterialFulfillment::route('/{record}'),
        ];
    }
}
