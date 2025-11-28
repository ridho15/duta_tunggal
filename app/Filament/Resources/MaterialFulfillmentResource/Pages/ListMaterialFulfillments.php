<?php

namespace App\Filament\Resources\MaterialFulfillmentResource\Pages;

use App\Filament\Resources\MaterialFulfillmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMaterialFulfillments extends ListRecords
{
    protected static string $resource = MaterialFulfillmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refresh_fulfillment_data')
                ->label('Perbarui Data')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function () {
                    // Update fulfillment data for all production plans
                    $plans = \App\Models\ProductionPlan::with('billOfMaterial.items')->get();

                    foreach ($plans as $plan) {
                        \App\Models\MaterialFulfillment::updateFulfillmentData($plan);
                    }

                    \App\Http\Controllers\HelperController::sendNotification(
                        isSuccess: true,
                        title: "Berhasil",
                        message: "Data pemenuhan bahan berhasil diperbarui"
                    );
                }),
        ];
    }
}
