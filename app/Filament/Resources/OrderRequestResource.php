<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderRequestResource\Pages;
use App\Filament\Resources\OrderRequestResource\Pages\ViewOrderRequest;
use App\Http\Controllers\HelperController;
use App\Models\OrderRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\OrderRequestService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Repeater;
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
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Support\Facades\Auth;

class OrderRequestResource extends Resource
{
    protected static ?string $model = OrderRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box-arrow-down';

    protected static ?string $navigationGroup = 'Pembelian';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Order Request')
                    ->schema([
                        TextInput::make('request_number')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Select::make('warehouse_id')
                            ->label('Warehouse')
                            ->preload()
                            ->searchable()
                            ->relationship('warehouse', 'name')
                            ->required(),
                        DatePicker::make('request_date')
                            ->required(),
                        Textarea::make('note')
                            ->label('Note')
                            ->nullable(),
                        Repeater::make('orderRequestItem')
                            ->relationship()
                            ->columnSpanFull()
                            ->columns(3)
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->preload()
                                    ->searchable()
                                    ->relationship('product', 'id')
                                    ->getOptionLabelFromRecordUsing(function (Product $product) {
                                        return "({$product->sku}) {$product->name}";
                                    })
                                    ->required(),
                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->default(0)
                                    ->required(),
                                Textarea::make('note')
                                    ->nullable()
                                    ->label('Note')
                            ])
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('request_number')
                    ->searchable(),
                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->searchable(),
                TextColumn::make('request_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(function ($state) {
                        return Str::upper($state);
                    })
                    ->color(function ($state) {
                        return match ($state) {
                            'draft' => 'gray',
                            'approved' => 'success',
                            'rejected' => 'danger'
                        };
                    })
                    ->badge(),
                TextColumn::make('orderRequestItem')
                    ->label('Items')
                    ->formatStateUsing(function ($state) {
                        return "({$state->product->sku}) {$state->product->name}";
                    })
                    ->searchable()
                    ->badge(),
                TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                ActionGroup::make([
                    ViewAction::make()
                        ->color('primary'),
                    EditAction::make()
                        ->color('success'),
                    DeleteAction::make(),
                    Action::make('download_pdf')
                        ->label('Download PDF')
                        ->icon('heroicon-o-document')
                        ->color('danger')
                        ->visible(function($record){
                            return $record->status == 'approved';
                        })
                        ->action(function ($record) {
                            $pdf = Pdf::loadView('pdf.order-request', [
                                'orderRequest' => $record
                            ])->setPaper('A4', 'potrait');

                            return response()->streamDownload(function () use ($pdf) {
                                echo $pdf->stream();
                            }, 'Order_Request_' . $record->request_number . '.pdf');
                        }),
                    Action::make('reject')
                        ->label('Reject')
                        ->color('danger')
                        ->icon('heroicon-o-x-circle')
                        ->requiresConfirmation()
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('approve order request') && $record->status == 'draft';
                        })
                        ->action(function ($record) {
                            $orderRequestService = app(OrderRequestService::class);
                            $orderRequestService->reject($record);
                            HelperController::sendNotification(isSuccess: true, title: 'Information', message: "Order request rejected");
                        }),
                    Action::make('approve')
                        ->label('Approve')
                        ->color('success')
                        ->icon('heroicon-o-check-badge')
                        ->requiresConfirmation()
                        ->form([
                            Select::make('supplier_id')
                                ->label('Supplier')
                                ->preload()
                                ->searchable()
                                ->options(function () {
                                    return Supplier::select(['id', 'name'])->get()->pluck('name', 'id');
                                })->required(),
                            TextInput::make('po_number')
                                ->label('PO Number')
                                ->string()
                                ->maxLength(255)
                                ->required(),
                            DatePicker::make('order_date')
                                ->label('Order Date')
                                ->required(),
                            DatePicker::make('expected_date')
                                ->label('Expected Date')
                                ->nullable(),
                            Textarea::make('note')
                                ->label('Note')
                                ->nullable()
                        ])
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('approve order request') && $record->status == 'draft';
                        })
                        ->action(function (array $data, $record) {
                            $orderRequestService = app(OrderRequestService::class);
                            // Check purchase order number
                            $purchaseOrder = PurchaseOrder::where('po_number', $data['po_number'])->first();
                            if ($purchaseOrder) {
                                HelperController::sendNotification(isSuccess: false, title: "Information", message: "PO Number sudah digunakan !");
                                return;
                            }

                            $orderRequestService->approve($record, $data);
                            HelperController::sendNotification(isSuccess: true, title: 'Information', message: "Order Request Approved");
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
            'index' => Pages\ListOrderRequests::route('/'),
            'create' => Pages\CreateOrderRequest::route('/create'),
            'view' => ViewOrderRequest::route('/{record}'),
            'edit' => Pages\EditOrderRequest::route('/{record}/edit'),
        ];
    }
}
