<?php

namespace App\Filament\Resources\CreditNoteResource\Pages;

use App\Filament\Resources\CreditNoteResource;
use App\Filament\Support\CreditNoteActions;
use App\Models\CreditNote;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewCreditNote extends ViewRecord
{
    protected static string $resource = CreditNoteResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->record->loadMissing(['items.product', 'items.invoiceItem.product', 'invoice', 'customer', 'customerReturn']);
    }

    protected function getHeaderActions(): array
    {
        return \App\Filament\Support\DocumentActions::layout($this->headerActionList(), ['print_credit_note', 'view_journal_entries', 'delete_draft']);
    }

    /** Daftar lengkap aksi header; pengelompokan utama/"Lainnya" oleh DocumentActions (D13). */
    private function headerActionList(): array
    {
        return [
            CreditNoteActions::issue(Action::make('issue')),
            CreditNoteActions::taxDocumentNumber(Action::make('tax_document_number')),
            Action::make('view_journal_entries')
                ->label('Lihat Journal Entries')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->visible(fn (): bool => $this->record instanceof CreditNote && $this->record->isIssued())
                ->url(fn () => route('filament.admin.resources.journal-entries.index', [
                    'tableFilters[source_type][value]' => CreditNote::class,
                    'tableFilters[source_id][value]' => $this->record->id,
                ]))
                ->openUrlInNewTab(),
            Action::make('print_credit_note')
                ->label('Cetak Nota Kredit')
                ->icon('heroicon-o-printer')
                ->color('primary')
                ->url(fn () => route('pdf-stream', ['type' => 'credit-note', 'id' => $this->record->id]))
                ->openUrlInNewTab(),
            CreditNoteActions::deleteDraft(Action::make('delete_draft')),
        ];
    }
}
