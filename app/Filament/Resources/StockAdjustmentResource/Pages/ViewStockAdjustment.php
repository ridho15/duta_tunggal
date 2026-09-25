<?php

namespace App\Filament\Resources\StockAdjustmentResource\Pages;

use App\Filament\Resources\StockAdjustmentResource;
use App\Http\Controllers\HelperController;
use App\Models\StockAdjustment;
use App\Services\StockAdjustmentService;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ViewStockAdjustment extends ViewRecord
{
    protected static string $resource = StockAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->status === 'draft')
                ->requiresConfirmation()
                ->modalHeading('Setujui Stock Adjustment')
                ->modalDescription('Approval akan membuat mutasi stok dan jurnal penyesuaian keuangan.')
                ->modalSubmitActionLabel('Ya, Setujui')
                ->action(function () {
                    try {
                        app(StockAdjustmentService::class)->approveStockAdjustment($this->record, Auth::id());

                        HelperController::sendNotification(
                            isSuccess: true,
                            title: 'Information',
                            message: 'Stock adjustment berhasil disetujui, mutasi stok dan jurnal penyesuaian sudah dicatat.'
                        );
                        $this->refreshFormData(['status', 'approved_by', 'approved_at']);
                    } catch (ValidationException $exception) {
                        HelperController::sendNotification(
                            isSuccess: false,
                            title: 'Validasi Stock Adjustment',
                            message: collect($exception->errors())->flatten()->implode("\n")
                        );
                    }
                }),

            Actions\Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $this->record->status === 'draft')
                ->requiresConfirmation()
                ->modalHeading('Tolak Stock Adjustment')
                ->modalDescription('Apakah Anda yakin ingin menolak stock adjustment ini?')
                ->modalSubmitActionLabel('Ya, Tolak')
                ->action(function () {
                    $this->record->update([
                        'status' => 'rejected',
                        'approved_by' => Auth::id(),
                        'approved_at' => now(),
                    ]);

                    HelperController::sendNotification(
                        isSuccess: true,
                        title: 'Information',
                        message: 'Stock adjustment berhasil ditolak.'
                    );
                    $this->refreshFormData(['status', 'approved_by', 'approved_at']);
                }),

            Actions\EditAction::make()
                ->icon('heroicon-o-pencil')
                ->visible(fn () => $this->record->status === 'draft'),
        ];
    }
}