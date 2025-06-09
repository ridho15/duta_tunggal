<?php

namespace App\Filament\Resources\PurchaseReceiptResource\RelationManagers;

use App\Models\Currency;
use App\Models\QualityControl;
use App\Services\QualityControlService;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PurchaseReceiptItemRelationManager extends RelationManager
{
    protected static string $relationship = 'purchaseReceiptItem';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('product_id')
                    ->label('Product')
                    ->preload()
                    ->searchable()
                    ->required()
                    ->relationship('product', 'name', function ($get, Builder $query) {
                        $purchaseOrderId = $get('../../purchase_order_id');
                        return $query->whereHas('purchaseOrderItem', function (Builder $query) use ($purchaseOrderId) {
                            $query->where('purchase_order_id', $purchaseOrderId);
                        });
                    })
                    ->reactive()
                    ->getOptionLabelFromRecordUsing(function ($record) {
                        return "{$record->sku} - {$record->name}";
                    }),
                Select::make('warehouse_id')
                    ->label('Warehouse')
                    ->preload()
                    ->searchable()
                    ->relationship('warehouse', 'name'),
                TextInput::make('qty_received')
                    ->label('Quantity Received')
                    ->numeric()
                    ->required()
                    ->default(0),
                TextInput::make('qty_accepted')
                    ->label('Quantity Accepted')
                    ->numeric()
                    ->default(0)
                    ->required(),
                TextInput::make('qty_rejected')
                    ->label('Quantity Rejected')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Repeater::make('purchaseReceiptItemPhoto')
                    ->relationship()
                    ->addActionLabel('Tambah Photo')
                    ->schema([
                        FileUpload::make('photo_url')
                            ->label('Photo')
                            ->image()
                            ->maxSize(1024)
                            ->required(),
                    ]),
                Repeater::make('purchaseReceiptItemNominal')
                    ->relationship()
                    ->columnSpanFull()
                    ->defaultItems(0)
                    ->addActionLabel('Tambah Currency')
                    ->columns(2)
                    ->schema([
                        Select::make('currency_id')
                            ->label('Currency')
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function ($set, $state) {
                                $currency = Currency::find($state);
                                $set('symbol', $currency->symbol);
                            })
                            ->searchable()
                            ->required()
                            ->relationship('currency', 'name'),
                        TextInput::make('nominal')
                            ->label('Nominal')
                            ->numeric()
                            ->reactive()
                            ->prefix(function ($get) {
                                return $get('symbol');
                            })
                            ->default(0)
                    ])
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('product')
                    ->label('Product')
                    ->formatStateUsing(function ($state) {
                        return "({$state->sku}) {$state->name}";
                    })->searchable(),
                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->searchable(),
                TextColumn::make('qty_received')
                    ->label('Quantity Received')
                    ->sortable(),
                TextColumn::make('qty_accepted')
                    ->label('Quantity Accepted')
                    ->sortable(),
                TextColumn::make('qty_rejected')
                    ->label('Quantity Rejected')
                    ->sortable(),
                ImageColumn::make('purchaseReceiptItemPhoto.photo_url')
                    ->label('Photo'),
                TextColumn::make('is_sent')
                    ->label('Terkirim?')
                    ->badge()
                    ->color(function ($state) {
                        if ($state == 0) {
                            return 'gray';
                        } else {
                            return 'success';
                        }
                    })
                    ->formatStateUsing(function ($state) {
                        if ($state == 1) {
                            return "Terkirim QC";
                        } else {
                            return "Belum Terkirim QC";
                        }
                    })
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Item')
                    ->icon('heroicon-o-plus-circle'),
            ])
            ->actions([
                EditAction::make()
                    ->hidden(function ($record) {
                        return $record->is_sent == 1;
                    }),
                DeleteAction::make()
                    ->hidden(function ($record) {
                        return $record->is_sent == 1;
                    }),
                Action::make('kirim_qc')
                    ->label('Kirim QC')
                    ->color('success')
                    ->hidden(function ($record) {
                        return $record->is_sent == 1;
                    })
                    ->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $qualityControlService = new QualityControlService;
                        $record->update([
                            'is_sent' => 1
                        ]);

                        $qualityControlService->createQCFromPurchaseReceiptItem($record);
                        Notification::make()
                            ->title("Success")
                            ->color('success')
                            ->body('Berhasil mengirimkan data ke quality control')
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
