<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QualityControlResource\Pages;
use App\Http\Controllers\HelperController;
use App\Models\QualityControl;
use App\Models\Rak;
use App\Models\Warehouse;
use App\Services\QualityControlService;
use Filament\Forms\Components\Fieldset;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Enums\ActionsPosition;

class QualityControlResource extends Resource
{
    protected static ?string $model = QualityControl::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box-arrow-down';

    protected static ?string $navigationGroup = 'Warehouse';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Quality Control')
                    ->schema([
                        Select::make('warehouse_id')
                            ->required()
                            ->label('Warehouse')
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->relationship('warehouse', 'name'),
                        Select::make('rak_id')
                            ->label('Rak')
                            ->preload()
                            ->reactive()
                            ->searchable()
                            ->relationship('rak', 'name', function ($get, Builder $query) {
                                $query->where('warehouse_id', $get('warehouse_id'));
                            })
                            ->nullable(),
                        Select::make('product_id')
                            ->label('Product')
                            ->searchable()
                            ->preload()
                            ->relationship('product', 'name', function (Builder $query) {})
                            ->getOptionLabelFromRecordUsing(function ($record) {
                                return "({$record->sku}) {$record->name}";
                            })
                            ->required(),
                        Select::make('inspected_by')
                            ->label('Inspected By')
                            ->relationship('inspectedBy', 'name')
                            ->preload()
                            ->searchable()
                            ->default(null),
                        TextInput::make('passed_quantity')
                            ->required()
                            ->numeric()
                            ->default(0),
                        TextInput::make('rejected_quantity')
                            ->required()
                            ->numeric()
                            ->default(0),
                        Textarea::make('notes')
                            ->nullable(),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('warehouse.name')
                    ->searchable()
                    ->label('Warehouse'),
                TextColumn::make('purchaseReceiptItem.purchaseReceipt.purchaseOrder.po_number')
                    ->label('PO Number')
                    ->searchable(),
                TextColumn::make('product')
                    ->label('Product')
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('product', function ($query) use ($search) {
                            $query->where('sku', 'LIKE', '%' . $search . '%')
                                ->orWhere('name', 'LIKE', '%' . $search . '%');
                        });
                    })
                    ->formatStateUsing(function ($state) {
                        return "({$state->sku}) {$state->name}";
                    }),
                TextColumn::make('inspectedBy.name')
                    ->searchable()
                    ->label('Inspected By'),
                TextColumn::make('passed_quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('rejected_quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(function ($state) {
                        return $state == 1 ? 'success' : 'gray';
                    })
                    ->formatStateUsing(function ($state) {
                        return $state == 1 ? 'Sudah Proses' : 'Belum Proses';
                    }),

                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->searchable(),
                TextColumn::make('rak.name')
                    ->label("Rak")
                    ->searchable(),
                TextColumn::make('notes')
                    ->label('Notes'),
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
                TextColumn::make('date_send_stock')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                ActionGroup::make([
                    EditAction::make()
                        ->color('success'),
                    DeleteAction::make(),
                    Action::make('Complete')
                        ->color('success')
                        ->label('Complete')
                        ->requiresConfirmation()
                        ->hidden(function ($record) {
                            return $record->status == 1;
                        })
                        ->icon('heroicon-o-check-badge')
                        ->form(function ($record) {
                            if ($record->rejected_quantity > 0) {
                                return [
                                    TextInput::make('return_number')
                                        ->label('Return Number')
                                        ->string()
                                        ->required(),
                                    Select::make('warehouse_id')
                                        ->label('Warehouse')
                                        ->preload()
                                        ->reactive()
                                        ->searchable()
                                        ->options(function () {
                                            return Warehouse::select(['id', 'name'])->get()->pluck('name', 'id');
                                        })
                                        ->required(),
                                    Select::make('rak_id')
                                        ->label('Rak')
                                        ->preload()
                                        ->reactive()
                                        ->searchable()
                                        ->options(function ($get) {
                                            return Rak::where('warehouse_id', $get('warehouse_id'))->select(['id', 'name'])->get()->pluck('name', 'id');
                                        })
                                        ->nullable(),
                                    Textarea::make('reason')
                                        ->label('Reason')
                                        ->nullable()
                                        ->string()
                                        ->default($record->reason_reject)
                                ];
                            }

                            return null;
                        })
                        ->action(function (array $data, $record) {
                            $qualityControlService = app(QualityControlService::class);
                            $qualityControlService->completeQualityControl($record, $data);
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Quality Control Completed");
                        })
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
            'index' => Pages\ListQualityControls::route('/'),
            // 'create' => Pages\CreateQualityControl::route('/create'),
            // 'edit' => Pages\EditQualityControl::route('/{record}/edit'),
        ];
    }
}
