<?php

namespace App\Filament\Resources\ProductionPlanResource\Pages;

use App\Filament\Resources\ProductionPlanResource;
use App\Http\Controllers\HelperController;
use App\Services\ManufacturingService;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;

class ViewProductionPlan extends ViewRecord
{
    protected static string $resource = ProductionPlanResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->icon('heroicon-o-pencil'),
            Actions\DeleteAction::make()->icon('heroicon-o-trash'),
            Actions\Action::make('schedule')
                ->label('Jadwalkan')
                ->icon('heroicon-o-calendar-days')
                ->color('warning')
                ->visible(function () {
                    return $this->getRecord()->status === 'draft';
                })
                ->requiresConfirmation()
                ->modalHeading('Jadwalkan Rencana Produksi')
                ->modalDescription('Apakah Anda yakin ingin menjadwalkan rencana produksi ini? Status akan berubah menjadi SCHEDULED dan MaterialIssue akan dibuat otomatis.')
                ->modalSubmitActionLabel('Jadwalkan')
                ->action(function () {
                    $record = $this->getRecord();

                    if ($record->status !== 'draft') {
                        Notification::make()
                            ->title('Rencana sudah dijadwalkan')
                            ->info()
                            ->body('Rencana produksi ini tidak berada pada status draft.')
                            ->send();

                        return;
                    }

                    // Validate stock availability before scheduling
                    $stockValidation = \App\Filament\Resources\ProductionPlanResource::validateStockForProductionPlan($record);
                    if (!$stockValidation['valid']) {
                        HelperController::sendNotification(
                            isSuccess: false,
                            title: "Tidak Dapat Menjadwalkan",
                            message: $stockValidation['message']
                        );
                        return;
                    }

                    try {
                        DB::transaction(function () use ($record) {
                            $record->update(['status' => 'scheduled']);

                            HelperController::setLog(
                                message: 'Production plan dijadwalkan dan MaterialIssue dibuat otomatis.',
                                model: $record
                            );
                        });

                        HelperController::sendNotification(
                            isSuccess: true,
                            title: 'Berhasil',
                            message: 'Rencana produksi berhasil dijadwalkan dan MaterialIssue telah dibuat otomatis.'
                        );
                    } catch (\Throwable $exception) {
                        report($exception);

                        HelperController::sendNotification(
                            isSuccess: false,
                            title: 'Gagal menjadwalkan',
                            message: 'Terjadi kesalahan saat menjadwalkan rencana produksi: ' . $exception->getMessage()
                        );
                    }
                }),
        ];
    }
}