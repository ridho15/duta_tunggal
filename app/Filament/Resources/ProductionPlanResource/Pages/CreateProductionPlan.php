<?php

namespace App\Filament\Resources\ProductionPlanResource\Pages;

use App\Filament\Resources\ProductionPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateProductionPlan extends CreateRecord
{
    protected static string $resource = ProductionPlanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();

        // Auto schedule option: if checked, set status to scheduled else default draft
        if (!empty($data['auto_schedule'])) {
            $data['status'] = 'scheduled';
        } else {
            $data['status'] = $data['status'] ?? 'draft';
        }

        // Remove transient form-only field
        unset($data['auto_schedule']);

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->record->status === 'scheduled') {
            try {
                app(\App\Services\ManufacturingService::class)->updateMaterialFulfillment($this->record);
                \App\Http\Controllers\HelperController::sendNotification(
                    isSuccess: true,
                    title: 'Berhasil',
                    message: 'Rencana produksi dijadwalkan langsung dan material fulfillment diperbarui.'
                );
            } catch (\Throwable $e) {
                report($e);
                \App\Http\Controllers\HelperController::sendNotification(
                    isSuccess: false,
                    title: 'Gagal Update Material',
                    message: 'Terjadi kesalahan saat memperbarui fulfillment: ' . $e->getMessage()
                );
            }
        }
    }
}
