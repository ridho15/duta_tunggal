<?php

namespace App\Filament\Resources\ReturnProductResource\Pages;

use App\Filament\Resources\ReturnProductResource;
use App\Http\Controllers\HelperController;
use App\Services\ReturnProductService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewReturnProduct extends ViewRecord
{
    protected static string $resource = ReturnProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->icon('heroicon-o-pencil-square')
                ->visible(fn () => $this->record->status === 'draft'),
            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(function () {
                    $user = Auth::user();
                    if (! $user) {
                        return false;
                    }

                    return ($user->hasRole(['Super Admin', 'Owner', 'Admin', 'Admin Inventory', 'Inventory Manager', 'Warehouse Staff'])
                        || $user->hasPermissionTo('approve return product'))
                        && $this->record->status === 'draft';
                })
                ->requiresConfirmation()
                ->action(function () {
                    if ($this->record->returnProductItem->isEmpty()) {
                        HelperController::sendNotification(
                            isSuccess: false,
                            title: 'Approval Failed',
                            message: 'Tidak dapat approve return product tanpa item. Silakan tambahkan minimal satu item retur.'
                        );

                        return;
                    }

                    try {
                        $returnProductService = app(ReturnProductService::class);
                        $returnProductService->updateQuantityFromModel($this->record);
                        $this->refreshFormData(['status']);

                        HelperController::sendNotification(
                            isSuccess: true,
                            title: 'Return Product Approved',
                            message: "Return product {$this->record->return_number} berhasil diapprove. Quantity telah diperbarui."
                        );
                    } catch (\Throwable $e) {
                        HelperController::sendNotification(
                            isSuccess: false,
                            title: 'Approval Failed',
                            message: 'Terjadi kesalahan saat approve return product: ' . $e->getMessage()
                        );
                    }
                }),
            DeleteAction::make()
                ->icon('heroicon-o-trash')
                ->color('danger'),
        ];
    }
}
