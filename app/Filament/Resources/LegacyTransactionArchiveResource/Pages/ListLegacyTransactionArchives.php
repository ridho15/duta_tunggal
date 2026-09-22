<?php

namespace App\Filament\Resources\LegacyTransactionArchiveResource\Pages;

use App\Filament\Resources\LegacyTransactionArchiveResource;
use Filament\Resources\Pages\ListRecords;

class ListLegacyTransactionArchives extends ListRecords
{
    protected static string $resource = LegacyTransactionArchiveResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}