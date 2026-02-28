<?php

namespace App\Filament\Resources\WarehouseResource\Pages;

use App\Filament\Resources\WarehouseResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateWarehouse extends CreateRecord
{
    protected static string $resource = WarehouseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Auto-set cabang_id from logged-in user when field is hidden
        // (field is hidden for non-superadmin users via ->visible() condition)
        if (empty($data['cabang_id'])) {
            $data['cabang_id'] = Auth::user()?->cabang_id;
        }
        return $data;
    }
}
