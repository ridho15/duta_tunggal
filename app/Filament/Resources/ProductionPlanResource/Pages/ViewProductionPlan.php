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

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
            Actions\Action::make('schedule')
                ->label('Jadwalkan')
                ->icon('heroicon-o-calendar-days')
                ->color('warning')
                ->visible(function () {
                    return $this->getRecord()->status === 'draft';
                })
                ->requiresConfirmation()
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

                    try {
                        DB::transaction(function () use ($record) {
                            $record->update(['status' => 'scheduled']);

                            // Refresh fulfillment snapshot so material availability is up to date.
                            app(ManufacturingService::class)->updateMaterialFulfillment($record);

                            HelperController::setLog(
                                message: 'Production plan dijadwalkan untuk proses selanjutnya.',
                                model: $record
                            );
                        });

                        HelperController::sendNotification(
                            isSuccess: true,
                            title: 'Berhasil',
                            message: 'Rencana produksi berhasil dijadwalkan dan material fulfillment diperbarui.'
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