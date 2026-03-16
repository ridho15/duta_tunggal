<?php

namespace App\Filament\Resources\CustomerReturnResource\Pages;

use App\Filament\Resources\CustomerReturnResource;
use App\Models\CustomerReturn;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateCustomerReturn extends CreateRecord
{
    protected static string $resource = CustomerReturnResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Auto-generate the return number before saving
        $data['return_number'] = CustomerReturn::generateReturnNumber();

        // Assign branch from logged-in user if not explicitly set
        if (empty($data['cabang_id'])) {
            $data['cabang_id'] = Auth::user()?->cabang_id;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
