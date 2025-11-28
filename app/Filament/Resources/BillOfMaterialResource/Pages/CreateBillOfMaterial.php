<?php

namespace App\Filament\Resources\BillOfMaterialResource\Pages;

use App\Filament\Resources\BillOfMaterialResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateBillOfMaterial extends CreateRecord
{
    protected static string $resource = BillOfMaterialResource::class;

    protected function afterCreate(): void
    {
        // Sync invoice items
        $this->record->total_cost = $this->record->items->sum('subtotal') + $this->record->labor_cost + $this->record->overhead_cost;
        $this->record->save();
    }
}
