<?php

namespace App\Filament\Resources\JournalEntryResource\Pages;

use App\Filament\Resources\JournalEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;

class ListJournalEntries extends ListRecords
{
    protected static string $resource = JournalEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('grouped_view')
                ->label('Grouped View')
                ->icon('heroicon-o-squares-2x2')
                ->url(fn (): string => JournalEntryResource::getUrl('grouped'))
                ->color('info'),
            Actions\CreateAction::make()
            ->icon('heroicon-o-plus'),
        ];
    }
}
