<?php

namespace App\Filament\Resources\ManufacturingOrderResource\Pages;

use App\Filament\Resources\ManufacturingOrderResource;
use Filament\Actions;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewManufacturingOrder extends ViewRecord
{
    protected static string $resource = ManufacturingOrderResource::class;

    protected function getActions(): array
    {
        return [
            EditAction::make()
                ->icon('heroicon-o-pencil-square'),
            DeleteAction::make()
                ->icon('heroicon-o-trash')
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $product = $this->getRecord()->product;
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
