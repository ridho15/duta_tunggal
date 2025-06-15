<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseReceiptItemResource\Pages;
use App\Http\Controllers\HelperController;
use App\Models\PurchaseReceiptItem;
use App\Services\QualityControlService;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;

class PurchaseReceiptItemResource extends Resource
{
    protected static ?string $model = PurchaseReceiptItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-chevron-up-down';

    protected static ?string $navigationGroup = 'Purchase Order';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make()
                    ->schema([
                        TextInput::make('purchase_receipt_id')
                            ->required()
                            ->numeric(),
                        TextInput::make('product_id')
                            ->required()
                            ->numeric(),
                        TextInput::make('qty_received')
                            ->required()
                            ->numeric()
                            ->default(0),
                        TextInput::make('qty_accepted')
                            ->required()
                            ->numeric()
                            ->default(0),
                        TextInput::make('qty_rejected')
                            ->required()
                            ->numeric()
                            ->default(0),
                        Textarea::make('reason_rejected')
                            ->columnSpanFull(),
                        TextInput::make('warehouse_id')
                            ->required()
                            ->numeric(),
                        TextInput::make('purchase_order_item_id')
                            ->numeric()
                            ->default(null),
                        TextInput::make('rak_id')
                            ->numeric()
                            ->default(null),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('purchaseReceipt.receipt_number')
                    ->label('Receipt Number')
                    ->searchable(),
                TextColumn::make('product.sku')
                    ->label('SKU')
                    ->searchable(),
                TextColumn::make('product.name')
                    ->label('Product Name')
                    ->searchable(),
                TextColumn::make('qty_received')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('qty_accepted')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('qty_rejected')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->searchable(),
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
                    }),
                TextColumn::make('rak.name')
                    ->searchable()
                    ->label('Rak'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

            ])
            ->filters([
                //
            ])
            ->actions([
                Action::make('kirim_qc')
                    ->label('Kirim QC')
                    ->color('success')
                    ->hidden(function ($record) {
                        return $record->is_sent == 1;
                    })
                    ->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $qualityControlService = app(QualityControlService::class);
                        $record->update([
                            'is_sent' => 1
                        ]);

                        $qualityControlService->createQCFromPurchaseReceiptItem($record);
                        HelperController::sendNotification(isSuccess: true, title: 'Information', message: 'Berhasil mengirimkan data ke quality control');
                    }),
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([]);
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
            'index' => Pages\ListPurchaseReceiptItems::route('/'),
            // 'create' => Pages\CreatePurchaseReceiptItem::route('/create'),
            // 'edit' => Pages\EditPurchaseReceiptItem::route('/{record}/edit'),
        ];
    }
}
