<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QualityControlManufactureResource\Pages;
use App\Filament\Resources\QualityControlManufactureResource\Pages\ViewQualityControlManufacture;
use App\Filament\Resources\QualityControlManufactureResource\Widgets;
use App\Http\Controllers\HelperController;
use App\Models\InventoryStock;
use App\Models\Production;
use App\Models\QualityControl;
use App\Models\Rak;
use App\Models\ReturnProduct;
use App\Models\Warehouse;
use App\Services\PurchaseReturnAutomationService;
use App\Services\QualityControlService;
use App\Services\ReturnProductService;
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

class QualityControlManufactureResource extends Resource
{
    protected static ?string $model = QualityControl::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Manufacturing Order';

    protected static ?string $navigationLabel = 'Quality Control Manufacture';

    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Quality Control Manufacture')
                    ->schema([
                        Section::make('From Production')
                            ->description('Quality Control untuk Production')
                            ->columns(2)
                            ->columnSpanFull()
                            ->schema([
                                Select::make('from_model_id')
                                    ->label('From Production')
                                    ->options(Production::with(['manufacturingOrder.product'])
                                        ->whereDoesntHave('qualityControl')
                                        ->where('status', 'completed')
                                        ->get()
                                        ->mapWithKeys(function ($production) {
                                            $mo = $production->manufacturingOrder;
                                            $product = $mo->product;

                                            $label = "MO: {$mo->mo_number} - {$product->name} (Qty: {$production->quantity_produced})";
                                            return [$production->id => $label];
                                        }))
                                    ->preload()
                                    ->searchable()
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $production = Production::with(['manufacturingOrder'])->find($state);
                                        if ($production) {
                                            $set('product_id', $production->manufacturingOrder->product_id);
                                            $set('warehouse_id', $production->warehouse_id);
                                            $set('passed_quantity', $production->quantity_produced);
                                            $set('rejected_quantity', 0);
                                        }
                                    })
                                    ->required(),
                                TextInput::make('qc_number')
                                    ->label('QC Number')
                                    ->default(function () {
                                        return HelperController::generateUniqueCode('quality_controls', 'qc_number', 'QC-M-' . date('Ymd') . '-', 4);
                                    })
                                    ->required()
                                    ->unique(ignoreRecord: true),
                            ]),
                        Section::make('Product Information')
                            ->columns(2)
                            ->schema([
                                TextInput::make('product_name')
                                    ->label('Product')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->afterStateUpdated(function ($set, $get) {
                                        $productId = $get('product_id');
                                        if ($productId) {
                                            $product = \App\Models\Product::find($productId);
                                            $set('product_name', $product ? $product->name : '');
                                        }
                                    }),
                                Select::make('warehouse_id')
                                    ->label('Warehouse')
                                    ->options(Warehouse::pluck('name', 'id'))
                                    ->required()
                                    ->reactive(),
                                Select::make('rak_id')
                                    ->label('Rak')
                                    ->options(function ($get) {
                                        $warehouseId = $get('warehouse_id');
                                        if ($warehouseId) {
                                            return Rak::where('warehouse_id', $warehouseId)->pluck('name', 'id');
                                        }
                                        return [];
                                    })
                                    ->searchable()
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

                                        $productionId = $get('from_model_id');
                                        if ($productionId) {
                                            $production = Production::find($productionId);
                                            if ($production && $totalInspected > $production->quantity_produced) {
                                                $set('passed_quantity', $production->quantity_produced - $rejected);
                                            }
                                        }
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

                                        $productionId = $get('from_model_id');
                                        if ($productionId) {
                                            $production = Production::find($productionId);
                                            if ($production && $totalInspected > $production->quantity_produced) {
                                                $set('rejected_quantity', $production->quantity_produced - $passed);
                                            }
                                        }
                                    }),
                                TextInput::make('total_inspected')
                                    ->label('Total Inspected')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get) {
                                        $passed = (float) $get('passed_quantity');
                                        $rejected = (float) $get('rejected_quantity');
                                        $set('total_inspected', $passed + $rejected);
                                    }),
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
                TextColumn::make('fromModel.manufacturingOrder.mo_number')
                    ->label('Manufacturing Order')
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
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Quality Control Manufacture Completed");
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
            'index' => Pages\ListQualityControlManufactures::route('/'),
            'create' => Pages\CreateQualityControlManufacture::route('/create'),
            'view' => Pages\ViewQualityControlManufacture::route('/{record}'),
            'edit' => Pages\EditQualityControlManufacture::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('from_model_type', 'App\Models\Production');
    }
}