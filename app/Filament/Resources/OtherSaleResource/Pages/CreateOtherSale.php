<?php

namespace App\Filament\Resources\OtherSaleResource\Pages;

use App\Filament\Resources\OtherSaleResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateOtherSale extends CreateRecord
{
    protected static string $resource = OtherSaleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set created_by to current authenticated user
        $data['created_by'] = Auth::id();

        return $data;
    }
}
