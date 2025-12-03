<?php

namespace App\Filament\Resources\OrderRequestResource\Pages;

use App\Filament\Resources\OrderRequestResource;
use App\Http\Controllers\HelperController;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\OrderRequestService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewOrderRequest extends ViewRecord
{
    protected static string $resource = OrderRequestResource::class;

    protected function getActions(): array
    {
        return [
            EditAction::make()
                ->icon('heroicon-o-pencil-square'),
            DeleteAction::make()->icon('heroicon-o-trash')
                ->color('danger'),
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
                    Toggle::make('create_purchase_order')
                        ->label('Create Purchase Order')
                        ->default(true)
                        ->reactive(),
                    Select::make('supplier_id')
                        ->label('Supplier')
                        ->preload()
                        ->searchable()
                        ->default(fn () => $this->resolveDefaultSupplierId())
                        ->options(function () {
                            return Supplier::select(['id', 'name', 'code'])->get()->mapWithKeys(function ($supplier) {
                                return [$supplier->id => "({$supplier->code}) {$supplier->name}"];
                            });
                        })
                        ->getSearchResultsUsing(function (string $search) {
                            return Supplier::where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%")
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(function ($supplier) {
                                    return [$supplier->id => "({$supplier->code}) {$supplier->name}"];
                                });
                        })
                        ->required()
                        ->visible(fn ($get) => $get('create_purchase_order')),
                    TextInput::make('po_number')
                        ->label('PO Number')
                        ->string()
                        ->maxLength(255)
                        ->required()
                        ->suffixAction(
                            FormAction::make('generatePoNumber')
                                ->icon('heroicon-o-arrow-path')
                                ->action(function ($set) {
                                    $set('po_number', HelperController::generatePoNumber());
                                })
                        )
                        ->visible(fn ($get) => $get('create_purchase_order')),
                    DatePicker::make('order_date')
                        ->label('Order Date')
                        ->required()
                        ->visible(fn ($get) => $get('create_purchase_order')),
                    DatePicker::make('expected_date')
                        ->label('Expected Date')
                        ->nullable()
                        ->visible(fn ($get) => $get('create_purchase_order')),
                    Textarea::make('note')
                        ->label('Note')
                        ->nullable()
                        ->visible(fn ($get) => $get('create_purchase_order'))
                ])
                ->visible(function ($record) {
                    return Auth::user()->hasPermissionTo('approve order request') && $record->status == 'draft';
                })
                ->action(function (array $data, $record) {
                    $orderRequestService = app(OrderRequestService::class);
                    if ($data['create_purchase_order']) {
                        // Check purchase order number
                        $purchaseOrder = PurchaseOrder::where('po_number', $data['po_number'])->first();
                        if ($purchaseOrder) {
                            HelperController::sendNotification(isSuccess: false, title: "Information", message: "PO Number sudah digunakan !");
                            return;
                        }
                    }

                    $orderRequestService->approve($record, $data);
                    HelperController::sendNotification(isSuccess: true, title: 'Information', message: "Order Request Approved");
                }),
            Action::make('create_purchase_order')
                ->label('Create Purchase Order')
                ->color('info')
                ->icon('heroicon-o-document-plus')
                ->requiresConfirmation()
                ->form([
                    Select::make('supplier_id')
                        ->label('Supplier')
                        ->preload()
                        ->searchable()
                        ->default(fn () => $this->resolveDefaultSupplierId())
                        ->options(function () {
                            return Supplier::select(['id', 'name', 'code'])->get()->mapWithKeys(function ($supplier) {
                                return [$supplier->id => "({$supplier->code}) {$supplier->name}"];
                            });
                        })
                        ->getSearchResultsUsing(function (string $search) {
                            return Supplier::where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%")
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(function ($supplier) {
                                    return [$supplier->id => "({$supplier->code}) {$supplier->name}"];
                                });
                        })
                        ->required(),
                    TextInput::make('po_number')
                        ->label('PO Number')
                        ->string()
                        ->maxLength(255)
                        ->required()
                        ->suffixAction(
                            FormAction::make('generatePoNumber')
                                ->icon('heroicon-o-arrow-path')
                                ->action(function ($set) {
                                    $set('po_number', HelperController::generatePoNumber());
                                })
                        ),
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
                    return Auth::user()->hasPermissionTo('approve order request') && $record->status == 'approved' && !$record->purchaseOrder;
                })
                ->action(function (array $data, $record) {
                    $orderRequestService = app(OrderRequestService::class);
                    // Check purchase order number
                    $purchaseOrder = PurchaseOrder::where('po_number', $data['po_number'])->first();
                    if ($purchaseOrder) {
                        HelperController::sendNotification(isSuccess: false, title: "Information", message: "PO Number sudah digunakan !");
                        return;
                    }

                    $orderRequestService->createPurchaseOrder($record, $data);
                    HelperController::sendNotification(isSuccess: true, title: 'Information', message: "Purchase Order Created");
                })
        ];
    }

    /**
     * Resolve a default supplier id from the current order request's products.
     * If all products share the same supplier, return that supplier id, otherwise null.
     */
    private function resolveDefaultSupplierId(): ?int
    {
        if (!isset($this->record)) {
            return null;
        }

        $items = $this->record->orderRequestItem()->with('product')->get();

        $supplierIds = $items->map(function ($item) {
            return optional($item->product)->supplier_id;
        })->filter()->unique()->values();

        if ($supplierIds->count() === 1) {
            return $supplierIds->first();
        }

        return null;
    }
}
