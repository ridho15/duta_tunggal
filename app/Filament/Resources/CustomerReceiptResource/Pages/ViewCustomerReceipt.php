<?php

namespace App\Filament\Resources\CustomerReceiptResource\Pages;

use App\Filament\Resources\CustomerReceiptResource;
use Filament\Actions;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\Action;

class ViewCustomerReceipt extends ViewRecord
{
    protected static string $resource = CustomerReceiptResource::class;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        // Load journal entries with COA relationship
        $this->record->load(['journalEntries.coa']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->icon('heroicon-o-pencil')->color('warning'),
            Action::make('view_journal_entries')
                ->label('Lihat Journal Entries')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->url(fn () => route('filament.admin.resources.journal-entries.index', [
                    'tableFilters[source_type][value]' => 'App\Models\CustomerReceipt',
                    'tableFilters[source_id][value]' => $this->record->id
                ]))
                ->openUrlInNewTab(),
        ];
    }

    protected function getActions(): array
    {
        return [
            EditAction::make()
                ->icon('heroicon-o-pencil-square')
        ];
    }
}
