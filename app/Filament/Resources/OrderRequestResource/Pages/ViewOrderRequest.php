<?php

namespace App\Filament\Resources\OrderRequestResource\Pages;

use App\Filament\Resources\OrderRequestResource;
use App\Http\Controllers\HelperController;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\OrderRequestService;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
            DeleteAction::make(),
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
        ];
    }
}
