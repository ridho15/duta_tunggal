<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Http\Controllers\HelperController;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->icon('heroicon-o-eye')->color('primary'),
            DeleteAction::make()
                ->icon('heroicon-o-trash'),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // $data['manage_type'] is already an array due to accessor
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (in_array('all', $data['manage_type'])) {
            $manage_type = "all";
        } else {
            $manage_type = "";
            foreach ($data['manage_type'] as $index => $item) {
                $manage_type .= $item;
                if (($index + 1) < count($data['manage_type'])) {
                    $manage_type .= ",";
                }
            }
        }
        $data['signature'] = HelperController::saveSignatureImage($data['signature']);
        if ($data['signature'] == null) {
            unset($data['signature']);
        }
        if ($data['last_name'] != null || $data['last_name'] != '') {
            $data['name'] = $data['first_name'] . ' ' . $data['last_name'];
        } else {
            $data['name'] = $data['first_name'];
        }
        $data['manage_type'] = $manage_type;
        return $data;
    }
}
