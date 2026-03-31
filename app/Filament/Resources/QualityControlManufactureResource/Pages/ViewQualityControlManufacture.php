<?php

namespace App\Filament\Resources\QualityControlManufactureResource\Pages;

use App\Filament\Resources\QualityControlManufactureResource;
use App\Http\Controllers\HelperController;
use App\Services\QualityControlService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewQualityControlManufacture extends ViewRecord
{
    protected static string $resource = QualityControlManufactureResource::class;

    protected function getActions(): array
    {
        return [
            EditAction::make()
                ->icon('heroicon-o-pencil-square'),
            DeleteAction::make()
                ->icon('heroicon-o-trash'),
            Action::make('Complete')
                ->color('success')
                ->label('Complete')
                ->requiresConfirmation()
                ->hidden(function ($record) {
                    return $record->status == 1;
                })
                ->icon('heroicon-o-check-badge')
                ->form(fn ($record) => QualityControlManufactureResource::buildProcessingFormSchema($record))
                ->action(function (array $data, $record) {
                    $qualityControlService = app(QualityControlService::class);
                    $qualityControlService->completeQualityControl($record, $data);
                    HelperController::sendNotification(isSuccess: true, title: "Information", message: "Quality Control Manufacture Completed. Proses selanjutnya: Tim Gudang perlu memindahkan barang hasil produksi ke lokasi penyimpanan dan memperbarui stok inventori.");
                    $qualityControlService->checkPenerimaanBarang($record);
                })
        ];
    }
}