<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockAdjustmentResource\Pages;
use App\Filament\Resources\StockAdjustmentResource\RelationManagers;
use App\Models\StockAdjustment;
use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class StockAdjustmentResource extends Resource
{
    protected static ?string $model = StockAdjustment::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Gudang';

    protected static ?int $navigationSort = 4;

    protected static ?string $label = 'Stock Adjustment';

    protected static ?string $pluralLabel = 'Stock Adjustments';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Informasi Adjustment')
                    ->schema([
                        TextInput::make('adjustment_number')
                            ->label('Nomor Adjustment')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(fn () => \App\Models\StockAdjustment::generateAdjustmentNumber())
                            ->readonly()
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('regenerate')
                                    ->label('Generate Baru')
                                    ->icon('heroicon-o-arrow-path')
                                    ->action(function (Forms\Set $set) {
                                        $set('adjustment_number', \App\Models\StockAdjustment::generateAdjustmentNumber());
                                    })
                            )
                            ->validationMessages([
                                'required' => 'Nomor adjustment harus diisi',
                                'unique' => 'Nomor adjustment sudah digunakan'
                            ]),

                        DatePicker::make('adjustment_date')
                            ->label('Tanggal Adjustment')
                            ->required()
                            ->default(now())
                            ->validationMessages([
                                'required' => 'Tanggal adjustment harus diisi'
                            ]),

                        Select::make('warehouse_id')
                            ->label('Warehouse')
                            ->options(Warehouse::pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->validationMessages([
                                'required' => 'Warehouse harus dipilih'
                            ]),

                        Select::make('adjustment_type')
                            ->label('Tipe Adjustment')
                            ->options([
                                'increase' => 'Penambahan Stock (+)',
                                'decrease' => 'Pengurangan Stock (-)',
                            ])
                            ->required()
                            ->validationMessages([
                                'required' => 'Tipe adjustment harus dipilih'
                            ]),

                        TextInput::make('reason')
                            ->label('Alasan')
                            ->required()
                            ->validationMessages([
                                'required' => 'Alasan adjustment harus diisi'
                            ]),

                        Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'draft' => 'Draft',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                            ])
                            ->default('draft')
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('adjustment_number')
                    ->label('Nomor')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('adjustment_date')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('adjustment_type')
                    ->label('Tipe')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'increase' => 'success',
                        'decrease' => 'danger',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'increase' => 'Penambahan (+)',
                        'decrease' => 'Pengurangan (-)',
                    }),

                TextColumn::make('reason')
                    ->label('Alasan')
                    ->limit(30)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 30) {
                            return null;
                        }
                        return $state;
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'approved' => 'success',
                        'rejected' => 'danger',
                    }),

                TextColumn::make('creator.name')
                    ->label('Dibuat Oleh')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('warehouse_id')
                    ->label('Warehouse')
                    ->options(Warehouse::pluck('name', 'id')),

                SelectFilter::make('adjustment_type')
                    ->label('Tipe Adjustment')
                    ->options([
                        'increase' => 'Penambahan Stock (+)',
                        'decrease' => 'Pengurangan Stock (-)',
                    ]),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\StockAdjustmentItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockAdjustments::route('/'),
            'create' => Pages\CreateStockAdjustment::route('/create'),
            'view' => Pages\ViewStockAdjustment::route('/{record}'),
            'edit' => Pages\EditStockAdjustment::route('/{record}/edit'),
        ];
    }
}
