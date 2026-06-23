<?php

namespace App\Filament\Resources\AssetTransferResource\Pages;

use App\Filament\Resources\AssetTransferResource;
use App\Models\Asset;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateAssetTransfer extends CreateRecord
{
    protected static string $resource = AssetTransferResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Enforce source branch inheritance from selected asset
        if (!empty($data['asset_id'])) {
            $asset = Asset::find($data['asset_id']);
            if ($asset && !empty($asset->cabang_id)) {
                $data['from_cabang_id'] = $asset->cabang_id;
            }
        }

        return $data;
    }
}
