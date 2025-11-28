<?php

namespace App\Filament\Resources\AccountReceivableResource\Pages;

use App\Filament\Resources\AccountReceivableResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateAccountReceivable extends CreateRecord
{
    protected static string $resource = AccountReceivableResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();
        return $data;
    }
}
