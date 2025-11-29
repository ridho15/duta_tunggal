<?php

namespace App\Filament\Resources\ManufacturingOrderResource\Pages;

use App\Filament\Resources\ManufacturingOrderResource;
use Filament\Actions;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;

class ViewManufacturingOrder extends ViewRecord
{
    protected static string $resource = ManufacturingOrderResource::class;

    protected function getActions(): array
    {
        return [
            Actions\Action::make('Produksi')
                ->label('Produksi')
                ->color('success')
                ->icon('heroicon-o-arrow-right-end-on-rectangle')
                ->requiresConfirmation()
                ->visible(function () {
                    $record = $this->getRecord();
                    return Auth::user()->hasPermissionTo('request manufacturing order') && $record->status == 'draft';
                })
                ->action(function () {
                    $record = $this->getRecord();
                    // Policy guard: transition draft -> in_progress
                    abort_unless(Gate::forUser(Auth::user())->allows('updateStatus', [$record, 'in_progress']), 403);
                    $manufacturingService = app(\App\Services\ManufacturingService::class);
                    $status = $manufacturingService->checkStockMaterial($record);
                    if ($status) {
                        $record->update([
                            'status' => 'in_progress'
                        ]);

                        // Create Production record automatically
                        $productionService = app(\App\Services\ProductionService::class);
                        \App\Models\Production::create([
                            'production_number' => $productionService->generateProductionNumber(),
                            'manufacturing_order_id' => $record->id,
                            'production_date' => now()->toDateString(),
                            'status' => 'draft',
                        ]);

                        \App\Http\Controllers\HelperController::sendNotification(isSuccess: true, title: "Information", message: "Manufacturing In Progress - Production record created");
                    } else {
                        \App\Http\Controllers\HelperController::sendNotification(isSuccess: false, title: "Information", message: "Stock material tidak mencukupi");
                    }
                }),
            EditAction::make()
                ->icon('heroicon-o-pencil-square'),
            DeleteAction::make()
                ->icon('heroicon-o-trash')
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord()->load('productionPlan.product.unitConversions');
        $product = $record->productionPlan->product;
        if (!$product) {
            return $data;
        }
        $listConversions = [];
        foreach ($product->unitConversions as $index => $conversion) {
            $listConversions[$index] = [
                'uom_id' => $conversion->uom_id,
                'nilai_konversi' => $conversion->nilai_konversi
            ];
        }

        $data['satuan_konversi'] = $listConversions;
        return $data;
    }
}
