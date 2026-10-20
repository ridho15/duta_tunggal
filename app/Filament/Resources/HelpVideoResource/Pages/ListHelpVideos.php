<?php

namespace App\Filament\Resources\HelpVideoResource\Pages;

use App\Filament\Resources\HelpVideoResource;
use Filament\Resources\Pages\ListRecords;

class ListHelpVideos extends ListRecords
{
    protected static string $resource = HelpVideoResource::class;

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\CreateAction::make()];
    }
}
