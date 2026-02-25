<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\AttachAction;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DetachAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SuppliersRelationManager extends RelationManager
{
    protected static string $relationship = 'suppliers';

    protected static ?string $title = 'Multi Supplier';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('supplier_id')
                    ->label('Supplier')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->options(function () {
                        return Supplier::orderBy('perusahaan')
                            ->get()
                            ->mapWithKeys(fn ($s) => [$s->id => "({$s->code}) {$s->perusahaan}"]);
                    })
                    ->columnSpanFull(),
                TextInput::make('supplier_sku')
                    ->label('SKU di Supplier')
                    ->placeholder('Kode produk di sisi supplier')
                    ->maxLength(100),
                TextInput::make('supplier_price')
                    ->label('Harga Beli dari Supplier')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('Rp')
                    ->default(0),
                Toggle::make('is_primary')
                    ->label('Supplier Utama')
                    ->helperText('Tandai sebagai supplier default untuk produk ini')
                    ->default(false)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('perusahaan')
            ->heading('Daftar Supplier')
            ->description('Kelola supplier yang dapat menyediakan produk ini')
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('perusahaan')
                    ->label('Nama Supplier')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('pivot.supplier_sku')
                    ->label('SKU Supplier')
                    ->default('-'),
                TextColumn::make('pivot.supplier_price')
                    ->label('Harga Beli')
                    ->formatStateUsing(fn ($state) => $state ? 'Rp ' . number_format((float)$state, 0, ',', '.') : '-')
                    ->sortable(),
                IconColumn::make('pivot.is_primary')
                    ->label('Utama')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->falseColor('gray'),
                TextColumn::make('phone')
                    ->label('Telepon')
                    ->default('-'),
            ])
            ->filters([])
            ->headerActions([
                AttachAction::make()
                    ->label('Tambah Supplier')
                    ->recordTitle(fn (Supplier $record) => "({$record->code}) {$record->perusahaan}")
                    ->preloadRecordSelect()
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect()
                            ->label('Supplier')
                            ->columnSpanFull(),
                        TextInput::make('supplier_sku')
                            ->label('SKU di Supplier')
                            ->placeholder('Kode produk di sisi supplier')
                            ->maxLength(100),
                        TextInput::make('supplier_price')
                            ->label('Harga Beli dari Supplier')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('Rp')
                            ->default(0),
                        Toggle::make('is_primary')
                            ->label('Supplier Utama')
                            ->helperText('Tandai sebagai supplier default untuk produk ini')
                            ->default(false)
                            ->columnSpanFull(),
                    ]),
            ])
            ->actions([
                EditAction::make()
                    ->label('Edit')
                    ->form([
                        TextInput::make('supplier_sku')
                            ->label('SKU di Supplier')
                            ->placeholder('Kode produk di sisi supplier')
                            ->maxLength(100),
                        TextInput::make('supplier_price')
                            ->label('Harga Beli dari Supplier')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('Rp')
                            ->default(0),
                        Toggle::make('is_primary')
                            ->label('Supplier Utama')
                            ->helperText('Tandai sebagai supplier default untuk produk ini')
                            ->default(false)
                            ->columnSpanFull(),
                    ]),
                DetachAction::make()
                    ->label('Hapus'),
            ])
            ->bulkActions([
                Tables\Actions\DetachBulkAction::make()
                    ->label('Hapus Pilihan'),
            ]);
    }
}
