<?php

namespace App\Filament\Resources\MaterialIssueResource\Pages;

use App\Filament\Resources\MaterialIssueResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMaterialIssues extends ListRecords
{
    protected static string $resource = MaterialIssueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
