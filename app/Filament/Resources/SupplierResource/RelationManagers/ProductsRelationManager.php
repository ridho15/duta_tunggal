<?php

namespace App\Filament\Resources\SupplierResource\RelationManagers;

use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DetachAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;

class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'productSuppliers';
    
    protected static ?string $title = 'Produk Supplier';

    public function table(Table $table): Table
    {
        return $table
            // Ensure sorting is explicitly on the pivot table to avoid ambiguity when joining products.
            ->defaultSort('product_supplier.created_at', 'desc')
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Produk')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('productCategory.name')
                    ->label('Kategori')
                    ->sortable(),
                Tables\Columns\TextColumn::make('pivot.supplier_price')
                    ->label('Harga Beli Supplier')
                    ->rupiah()
                    ->sortable(),
                Tables\Columns\TextColumn::make('uom.name')
                    ->label('Satuan'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Action::make('associateProduct')
                    ->label('Associate Product')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('product_id')
                            ->label('Produk')
                            ->options(Product::all()->mapWithKeys(function ($product) {
                                return [$product->id => "{$product->sku} - {$product->name}"];
                            }))
                            ->searchable()
                            ->required()
                            ->validationMessages([
                                'required' => 'Produk harus dipilih'
                            ]),
                        Forms\Components\TextInput::make('supplier_price')
                            ->label('Harga Beli Supplier')
                            ->indonesianMoney()
                            ->required()
                            ->validationMessages([
                                'required' => 'Harga beli supplier tidak boleh kosong'
                            ]),
                    ])
                    ->action(function (array $data) {
                        $this->ownerRecord->productSuppliers()->attach($data['product_id'], [
                            'supplier_price' => $data['supplier_price'],
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('Product associated successfully')
                            ->success()
                            ->send();
                    })
                    ->modalHeading('Associate Product')
                    ->modalSubmitActionLabel('Associate')
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->fillForm(function ($record) {
                        return [
                            'supplier_price' => $record->pivot->supplier_price ?? null,
                        ];
                    })
                    ->form([
                        Forms\Components\TextInput::make('supplier_price')
                            ->label('Harga Beli Supplier')
                            ->indonesianMoney()
                            ->required(),
                    ]),
                DetachAction::make()
                    ->label('Dissociate')
                    ->requiresConfirmation()
                    ->successNotificationTitle('Product dissociated successfully'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
