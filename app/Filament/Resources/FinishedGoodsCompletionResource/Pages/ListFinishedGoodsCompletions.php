<?php

namespace App\Filament\Resources\FinishedGoodsCompletionResource\Pages;

use App\Filament\Resources\FinishedGoodsCompletionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFinishedGoodsCompletions extends ListRecords
{
    protected static string $resource = FinishedGoodsCompletionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
