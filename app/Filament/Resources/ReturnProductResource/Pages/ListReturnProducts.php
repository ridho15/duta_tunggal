<?php

namespace App\Filament\Resources\ReturnProductResource\Pages;

use App\Filament\Resources\ReturnProductResource;
use Filament\Actions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReturnProducts extends ListRecords
{
    protected static string $resource = ReturnProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-plus-circle'),
        ];
    }
}
