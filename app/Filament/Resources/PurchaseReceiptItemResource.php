<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseReceiptItemResource\Pages;
use App\Http\Controllers\HelperController;
use App\Models\Product;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceiptItem;
use App\Models\User;
use App\Services\QualityControlService;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Database\Eloquent\Builder;

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
                        Select::make('purchase_receipt_id')
                            ->label('Purchase Receipt')
                            ->preload()
                            ->searchable()
                            ->relationship('purchaseReceipt', 'receipt_number')
                            ->required(),
                        Select::make('product_id')
                            ->label('Product')
                            ->preload()
                            ->searchable()
                            ->relationship('product', 'id')
                            ->getOptionLabelFromRecordUsing(function (Product $product) {
                                return "({$product->sku}) {$product->name}";
                            })
                            ->required(),
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
                        Textarea::make('reason_rejected'),
                        Select::make('warehouse_id')
                            ->label('Warehouse')
                            ->preload()
                            ->reactive()
                            ->searchable()
                            ->relationship('warehouse', 'name')
                            ->required(),
                        Select::make('purchase_order_item_id')
                            ->label('Purchase Order')
                            ->preload()
                            ->searchable()
                            ->relationship('purchaseOrderItem', 'id')
                            ->getOptionLabelFromRecordUsing(function (PurchaseOrderItem $purchaseOrderItem) {
                                return "({$purchaseOrderItem->purchaseOrder->po_number}) - ({$purchaseOrderItem->product->sku}) {$purchaseOrderItem->product->sku}";
                            })
                            ->nullable(),
                        Select::make('rak_id')
                            ->label('Rak')
                            ->preload()
                            ->searchable()
                            ->relationship('rak', 'name', function ($get, Builder $query) {
                                return $query->where('warehouse_id', $get('warehouse_id'));
                            })
                            ->nullable(),
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
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
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
                TextColumn::make('purchaseReceipt.receipt_number')
                    ->label('Receipt Number')
                    ->searchable(),
                TextColumn::make('product')
                    ->label('Product')
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('product', function (Builder $query) use ($search) {
                            $query->where('sku', 'LIKE', '%' . $search . '%')
                                ->orWhere('name', 'LIKE', '%' . $search . '%');
                        });
                    })->formatStateUsing(function ($state) {
                        return "({$state->sku}) {$state->name}";
                    }),
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
                TextColumn::make('rak.name')
                    ->label('Rak')
                    ->searchable(),
                TextColumn::make('rak.name')
                    ->searchable()
                    ->label('Rak'),
                ImageColumn::make('purchaseReceiptItemPhoto.photo_url')
                    ->label('Photo')
                    ->circular()
                    ->stacked()
                    ->size(75),
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
                EditAction::make()
                    ->color('success')
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
                    ->form([
                        Select::make('inspected_by')
                            ->label('Inspected By')
                            ->preload()
                            ->searchable()
                            ->options(function () {
                                return User::select(['name', 'id'])->get()->pluck('name', 'id');
                            })
                            ->required()
                    ])
                    ->action(function (array $data, $record) {
                        $qualityControlService = app(QualityControlService::class);
                        $record->update([
                            'is_sent' => 1
                        ]);

                        $qualityControlService->createQCFromPurchaseReceiptItem($record, $data);
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
