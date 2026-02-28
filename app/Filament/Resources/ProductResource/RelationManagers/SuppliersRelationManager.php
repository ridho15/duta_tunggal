<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\AttachAction;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DetachAction;
use Filament\Tables\Actions\EditAction;
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
                TextInput::make('supplier_price')
                    ->label('Harga Beli dari Supplier')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('Rp')
                    ->default(0)
                    ->columnSpanFull(),
            ])
            ->columns(1);
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
                TextColumn::make('pivot.supplier_price')
                    ->label('Harga Beli')
                    ->formatStateUsing(fn ($state) => $state ? 'Rp ' . number_format((float)$state, 0, ',', '.') : '-')
                    ->sortable(),
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
                    ->recordSelect(
                        fn (Select $select) => $select
                            ->options(function () {
                                $ownerRecord = $this->getOwnerRecord();
                                $attachedSupplierIds = $ownerRecord->suppliers()->pluck('suppliers.id')->toArray();

                                return Supplier::whereNotIn('id', $attachedSupplierIds)
                                    ->orderBy('perusahaan')
                                    ->get()
                                    ->mapWithKeys(fn ($s) => [$s->id => "({$s->code}) {$s->perusahaan}"]);
                            })
                    )
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect()
                            ->label('Supplier')
                            ->columnSpanFull(),
                        TextInput::make('supplier_price')
                            ->label('Harga Beli dari Supplier')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('Rp')
                            ->default(0)
                            ->required()
                            ->validationMessages([
                                'required' => 'Harga beli supplier harus diisi',
                                'numeric' => 'Harga beli harus berupa angka',
                                'min' => 'Harga beli tidak boleh negatif'
                            ])
                            ->columnSpanFull(),
                    ]),
            ])
            ->actions([
                EditAction::make()
                    ->label('Edit')
                    ->form([
                        TextInput::make('supplier_price')
                            ->label('Harga Beli dari Supplier')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('Rp')
                            ->default(0)
                            ->required()
                            ->validationMessages([
                                'required' => 'Harga beli supplier harus diisi',
                                'numeric' => 'Harga beli harus berupa angka',
                                'min' => 'Harga beli tidak boleh negatif'
                            ])
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
