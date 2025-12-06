<?php

namespace App\Filament\Resources\AssetResource\Pages;

use App\Filament\Resources\AssetResource;
use App\Http\Controllers\HelperController;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Symfony\Component\Console\Helper\Helper;

class ViewAsset extends ViewRecord
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->icon('heroicon-o-pencil'),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['purchase_cost'] = HelperController::parseIndonesianMoney($data['purchase_cost']);
        $data['salvage_value'] = HelperController::parseIndonesianMoney($data['salvage_value']);
        $data['annual_depreciation'] = HelperController::parseIndonesianMoney($data['annual_depreciation']);
        $data['monthly_depreciation'] = HelperController::parseIndonesianMoney($data['monthly_depreciation']);
        $data['accumulated_depreciation'] = HelperController::parseIndonesianMoney($data['accumulated_depreciation']);
        $data['book_value'] = HelperController::parseIndonesianMoney($data['book_value']);
        return $data;
    }
}
