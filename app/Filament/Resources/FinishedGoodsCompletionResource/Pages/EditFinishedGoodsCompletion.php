<?php

namespace App\Filament\Resources\FinishedGoodsCompletionResource\Pages;

use App\Filament\Resources\FinishedGoodsCompletionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFinishedGoodsCompletion extends EditRecord
{
    protected static string $resource = FinishedGoodsCompletionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
