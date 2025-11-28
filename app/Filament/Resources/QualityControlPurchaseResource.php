<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QualityControlPurchaseResource\Pages;
use App\Http\Controllers\HelperController;
use App\Models\PurchaseReceiptItem;
use App\Models\QualityControl;
use App\Models\Rak;
use App\Models\Warehouse;
use App\Services\QualityControlService;
use Filament\Forms\Components\Actions\Action as ActionsAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Enums\ActionsPosition;

class QualityControlPurchaseResource extends Resource
{
    protected static ?string $model = QualityControl::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box-arrow-down';

    protected static ?string $navigationGroup = 'Pembelian (Purchase Order)';

    protected static ?string $navigationLabel = 'Quality Control Purchase';

    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Quality Control Purchase')
                    ->schema([
                        Section::make('From Purchase Receipt Item')
                            ->description('Quality Control untuk Purchase Receipt Item')
                            ->columns(2)
                            ->columnSpanFull()
                            ->schema([
                                Select::make('from_model_id')
                                    ->label('From Purchase Receipt Item')
                                    ->options(PurchaseReceiptItem::with(['purchaseReceipt.purchaseOrder.supplier', 'product'])
                                        ->whereDoesntHave('qualityControl')
                                        ->get()
                                        ->mapWithKeys(function ($item) {
                                            $receipt = $item->purchaseReceipt;
                                            $po = $receipt->purchaseOrder;
                                            $supplier = $po->supplier;
                                            $product = $item->product;

                                            $label = "PR: {$receipt->receipt_number} - PO: {$po->po_number} - {$supplier->name} - {$product->name} (Qty: {$item->quantity_received})";
                                            return [$item->id => $label];
                                        }))
                                    ->preload()
                                    ->searchable()
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $purchaseReceiptItem = PurchaseReceiptItem::with(['purchaseReceipt', 'product'])->find($state);
                                        if ($purchaseReceiptItem) {
                                            $set('product_id', $purchaseReceiptItem->product_id);
                                            $set('product_name', $purchaseReceiptItem->product ? $purchaseReceiptItem->product->name : '');
                                            $set('warehouse_id', $purchaseReceiptItem->purchaseReceipt->warehouse_id);
                                            $set('passed_quantity', $purchaseReceiptItem->quantity_received);
                                            $set('rejected_quantity', 0);
                                            $set('total_inspected', $purchaseReceiptItem->quantity_received);
                                        }
                                    })
                                    ->required(),
                                TextInput::make('qc_number')
                                    ->label('QC Number')
                                    ->default(function () {
                                        return HelperController::generateUniqueCode('quality_controls', 'qc_number', 'QC-P-' . date('Ymd') . '-', 4);
                                    })
                                    ->required()
                                    ->unique(ignoreRecord: true),
                            ]),
                        Section::make('Product Information')
                            ->columns(2)
                            ->schema([
                                TextInput::make('product.name')
                                    ->label('Product')
                                    ->formatStateUsing(function ($state, $get) {
                                        $purchaseReceiptItemId = $get('from_model_id');
                                        if ($purchaseReceiptItemId) {
                                            $item = PurchaseReceiptItem::find($purchaseReceiptItemId);
                                            if ($item && $item->product) {
                                                $sku = $item->product->sku ? ' (' . $item->product->sku . ')' : '';
                                                return $item->product->name . $sku;
                                            }
                                        }
                                        return '';
                                    })
                                    ->disabled()
                                    ->dehydrated(false),
                                Select::make('warehouse_id')
                                    ->label('Warehouse')
                                    ->options(Warehouse::all()->mapWithKeys(function ($warehouse) {
                                        return [$warehouse->id => "({$warehouse->kode}) {$warehouse->name}"];
                                    }))
                                    ->searchable(['kode', 'name'])
                                    ->required()
                                    ->reactive(),
                                Select::make('rak_id')
                                    ->label('Rak')
                                    ->options(function ($get) {
                                        $warehouseId = $get('warehouse_id');
                                        if ($warehouseId) {
                                            return Rak::where('warehouse_id', $warehouseId)
                                                ->get()
                                                ->mapWithKeys(function ($rak) {
                                                    return [$rak->id => "({$rak->code}) {$rak->name}"];
                                                });
                                        }
                                        return [];
                                    })
                                    ->searchable(['code', 'name'])
                                    ->preload(),
                            ]),
                        Section::make('Quality Control Result')
                            ->columns(3)
                            ->schema([
                                TextInput::make('passed_quantity')
                                    ->label('Passed Quantity')
                                    ->numeric()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get) {
                                        $passed = (float) $get('passed_quantity');
                                        $rejected = (float) $get('rejected_quantity');
                                        $totalInspected = $passed + $rejected;

                                        $purchaseReceiptItemId = $get('from_model_id');
                                        if ($purchaseReceiptItemId) {
                                            $item = PurchaseReceiptItem::find($purchaseReceiptItemId);
                                            if ($item && $item->quantity_received > 0 && $totalInspected > $item->quantity_received) {
                                                $set('passed_quantity', $item->quantity_received - $rejected);
                                            }
                                        }

                                        // Update total_inspected
                                        $set('total_inspected', $passed + $rejected);
                                    }),
                                TextInput::make('rejected_quantity')
                                    ->label('Rejected Quantity')
                                    ->numeric()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get) {
                                        $passed = (float) $get('passed_quantity');
                                        $rejected = (float) $get('rejected_quantity');
                                        $totalInspected = $passed + $rejected;

                                        $purchaseReceiptItemId = $get('from_model_id');
                                        if ($purchaseReceiptItemId) {
                                            $item = PurchaseReceiptItem::find($purchaseReceiptItemId);
                                            if ($item && $item->quantity_received > 0 && $totalInspected > $item->quantity_received) {
                                                $set('rejected_quantity', $item->quantity_received - $passed);
                                            }
                                        }

                                        // Update total_inspected
                                        $set('total_inspected', $passed + $rejected);
                                    }),
                                TextInput::make('total_inspected')
                                    ->label('Total Inspected')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->reactive(),
                            ]),
                        Section::make('Additional Information')
                            ->columns(2)
                            ->schema([
                                Select::make('inspected_by')
                                    ->label('Inspected By')
                                    ->options(\App\Models\User::pluck('name', 'id'))
                                    ->required(),
                                DatePicker::make('date_send_stock')
                                    ->label('Date Send to Stock'),
                                Textarea::make('notes')
                                    ->label('Notes')
                                    ->rows(3),
                                Textarea::make('reason_reject')
                                    ->label('Reason Reject')
                                    ->rows(3),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('qc_number')
                    ->label('QC Number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('fromModel.purchaseReceipt.receipt_number')
                    ->label('Purchase Receipt')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('fromModel.purchaseReceipt.purchaseOrder.po_number')
                    ->label('Purchase Order')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('passed_quantity')
                    ->label('Passed')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('rejected_quantity')
                    ->label('Rejected')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status_formatted')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Sudah diproses' => 'success',
                        'Belum diproses' => 'warning',
                    }),
                TextColumn::make('inspectedBy.name')
                    ->label('Inspected By')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        0 => 'Belum diproses',
                        1 => 'Sudah diproses',
                    ]),
                SelectFilter::make('warehouse_id')
                    ->label('Warehouse')
                    ->options(Warehouse::pluck('name', 'id')),
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from'),
                        DatePicker::make('created_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    Action::make('process_qc')
                        ->label('Process QC')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn ($record) => !$record->status)
                        ->action(function ($record) {
                            $qcService = new QualityControlService();
                            $qcService->completeQualityControl($record, []);
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Quality Control Purchase Completed");
                        }),
                    DeleteAction::make(),
                ])
                ->icon('heroicon-m-ellipsis-horizontal'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
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
            'index' => Pages\ListQualityControlPurchases::route('/'),
            'create' => Pages\CreateQualityControlPurchase::route('/create'),
            'view' => Pages\ViewQualityControlPurchase::route('/{record}'),
            'edit' => Pages\EditQualityControlPurchase::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('from_model_type', 'App\Models\PurchaseReceiptItem');
    }
}