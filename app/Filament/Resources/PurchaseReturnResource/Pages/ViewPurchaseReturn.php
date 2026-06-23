<?php

namespace App\Filament\Resources\PurchaseReturnResource\Pages;

use App\Filament\Resources\PurchaseReturnResource;
use App\Services\PurchaseReturnService;
use App\Support\ProcurementFailureNotifier;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Log;
use Throwable;

class ViewPurchaseReturn extends ViewRecord
{
    protected static string $resource = PurchaseReturnResource::class;

    protected function getActions(): array
    {
        return [
            EditAction::make()
                ->icon('heroicon-o-pencil-square'),
            Action::make('submit_for_approval')
                ->label('Submit for Approval')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->visible(fn($record) => $record->status === 'draft')
                ->action(function ($record) {
                    try {
                        $service = app(PurchaseReturnService::class);
                        $service->submitForApproval($record);

                        \Filament\Notifications\Notification::make()
                            ->title('Retur pembelian berhasil diajukan untuk persetujuan')
                            ->success()
                            ->send();
                    } catch (Throwable $exception) {
                        Log::error('ViewPurchaseReturn submit_for_approval failed', [
                            'purchase_return_id' => $record->id,
                            'error' => $exception->getMessage(),
                        ]);

                        ProcurementFailureNotifier::danger(
                            'Gagal Mengajukan Retur Pembelian',
                            $exception,
                            'Retur pembelian belum berhasil diajukan untuk persetujuan. Silakan coba lagi.'
                        );
                    }
                }),
            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn($record) => $record->status === 'pending_approval')
                ->form([
                    \Filament\Forms\Components\Textarea::make('approval_notes')
                        ->label('Approval Notes')
                        ->nullable(),
                ])
                ->action(function ($record, array $data) {
                    try {
                        $service = app(PurchaseReturnService::class);
                        $service->approve($record, $data);

                        \Filament\Notifications\Notification::make()
                            ->title('Retur pembelian berhasil disetujui')
                            ->success()
                            ->send();
                    } catch (Throwable $exception) {
                        Log::error('ViewPurchaseReturn approve failed', [
                            'purchase_return_id' => $record->id,
                            'error' => $exception->getMessage(),
                        ]);

                        ProcurementFailureNotifier::danger(
                            'Gagal Menyetujui Retur Pembelian',
                            $exception,
                            'Retur pembelian belum dapat disetujui. Silakan periksa data retur lalu coba lagi.'
                        );
                    }
                }),
            Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn($record) => $record->status === 'pending_approval')
                ->form([
                    \Filament\Forms\Components\Textarea::make('rejection_notes')
                        ->label('Rejection Notes')
                        ->required(),
                ])
                ->action(function ($record, array $data) {
                    try {
                        $service = app(PurchaseReturnService::class);
                        $service->reject($record, $data);

                        \Filament\Notifications\Notification::make()
                            ->title('Retur pembelian berhasil ditolak')
                            ->danger()
                            ->send();
                    } catch (Throwable $exception) {
                        Log::error('ViewPurchaseReturn reject failed', [
                            'purchase_return_id' => $record->id,
                            'error' => $exception->getMessage(),
                        ]);

                        ProcurementFailureNotifier::danger(
                            'Gagal Menolak Retur Pembelian',
                            $exception,
                            'Retur pembelian belum dapat ditolak. Silakan coba lagi.'
                        );
                    }
                }),
        ];
    }
}
