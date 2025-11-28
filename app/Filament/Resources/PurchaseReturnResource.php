<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseReturnResource\Pages;
use App\Filament\Resources\PurchaseReturnResource\Pages\ViewPurchaseReturn;
use App\Models\Product;
use App\Models\PurchaseReceiptItem;
use App\Models\PurchaseReturn;
use App\Services\PurchaseReturnService;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Database\Eloquent\Builder;

class PurchaseReturnResource extends Resource
{
    protected static ?string $model = PurchaseReturn::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-on-square-stack';

    // Group updated to the standardized Purchase Order group
    protected static ?string $navigationGroup = 'Pembelian (Purchase Order)';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Purchase Return')
                    ->schema([
                        TextInput::make('nota_retur')
                            ->required()
                            ->label('Note Return')
                            ->reactive()
                            ->suffixAction(Action::make('generateNotaRetur')
                                ->icon('heroicon-m-arrow-path') // ikon reload
                                ->tooltip('Generate Nota Retur')
                                ->action(function ($set, $get, $state) {
                                    $purchaseReturnService = app(PurchaseReturnService::class);
                                    $set('nota_retur', $purchaseReturnService->generateNotaRetur());
                                }))
                            ->maxLength(50),
                        Select::make('purchase_receipt_id')
                            ->required()
                            ->label('Purchase Receipt')
                            ->preload()
                            ->reactive()
                            ->searchable()
                            ->relationship('purchaseReceipt', 'receipt_number', function (Builder $query) {
                                $query->whereHas('purchaseOrder', function (Builder $query) {
                                    $query->where('status', 'closed');
                                });
                            }),
                        DateTimePicker::make('return_date')
                            ->label('Return Date')
                            ->required(),
                        Textarea::make('notes')
                            ->label('Keterangan')
                            ->nullable(),
                        Repeater::make('purchaseReturnItem')
                            ->relationship()
                            ->label('Return Item')
                            ->columnSpanFull()
                            ->columns(2)
                            ->reactive()
                            ->schema([
                                Select::make('purchase_receipt_item_id')
                                    ->label('Purchase Receipt Item')
                                    ->preload()
                                    ->reactive()
                                    ->searchable()
                                    ->required()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $purchaseReceiptItem = PurchaseReceiptItem::find($state);
                                        $set('product_id', $purchaseReceiptItem->product_id);
                                        $set('unit_price', $purchaseReceiptItem->purchaseOrderItem->unit_price);
                                    })
                                    ->relationship('purchaseReceiptItem', 'id', function (Builder $query, $get) {
                                        $query->where('purchase_receipt_id', $get('../../purchase_receipt_id'));
                                    })
                                    ->getOptionLabelFromRecordUsing(function (PurchaseReceiptItem $purchaseReceiptItem) {
                                        return "({$purchaseReceiptItem->product->sku}) {$purchaseReceiptItem->product->name}";
                                    }),
                                Select::make('product_id')
                                    ->label('Product')
                                    ->preload()
                                    ->searchable()
                                    ->required()
                                    ->disabled()
                                    ->reactive()
                                    ->relationship('product', 'id')
                                    ->getOptionLabelFromRecordUsing(function (Product $product) {
                                        return "({$product->sku} {$product->name})";
                                    }),
                                TextInput::make('qty_returned')
                                    ->label('Quantity Return')
                                    ->numeric()
                                    ->default(0)
                                    ->required(),
                                TextInput::make('unit_price')
                                    ->label('Unit Price (Rp.)')
                                    ->numeric()
                                    ->indonesianMoney()
                                    ->default(0)
                                    ->required(),
                                Textarea::make('reason')
                                    ->label('Reason')
                                    ->nullable(),
                            ])
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nota_retur')
                    ->label('Nota Return')
                    ->searchable(),
                TextColumn::make('purchaseReceipt.receipt_number')
                    ->label('Receipt Number')
                    ->sortable(),
                TextColumn::make('return_date')
                    ->dateTime()
                    ->sortable(),
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
                TextColumn::make('nota_retur')
                    ->searchable(),
                TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->searchable()
            ])
            ->filters([
                //
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make()
                        ->color('primary'),
                    EditAction::make()
                        ->color('success'),
                    DeleteAction::make()
                ])
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListPurchaseReturns::route('/'),
            'create' => Pages\CreatePurchaseReturn::route('/create'),
            'view' => ViewPurchaseReturn::route('/{record}'),
            'edit' => Pages\EditPurchaseReturn::route('/{record}/edit'),
        ];
    }
}
