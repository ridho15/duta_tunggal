<?php

namespace App\Filament\Resources\ReturnProductResource\Pages;

use App\Filament\Resources\ReturnProductResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateReturnProduct extends CreateRecord
{
    protected static string $resource = ReturnProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = 'draft';
        return $data;
    }
}
