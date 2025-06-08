<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Http\Controllers\HelperController;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->icon('heroicon-o-trash'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['signature'] = HelperController::saveSignatureImage($data['signature']);
        if ($data['signature'] == null) {
            unset($data['signature']);
        }
        return $data;
    }
}
