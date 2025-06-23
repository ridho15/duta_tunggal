<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Http\Controllers\HelperController;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
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
