<?php

namespace App\Filament\Resources\CustomerReceiptResource\Pages;

use App\Filament\Resources\CustomerReceiptResource;
use Filament\Actions;
use Filament\Actions\EditAction;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\Action;

class ViewCustomerReceipt extends ViewRecord
{
    protected static string $resource = CustomerReceiptResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->record->loadMissing(['customerReceiptItem.invoice', 'journalEntries.coa']);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return CustomerReceiptResource::infolist($infolist);
    }

    protected function getHeaderActions(): array
    {
        return \App\Filament\Support\DocumentActions::layout($this->headerActionList(), ['edit', 'view_journal_entries', 'cancel_receipt']);
    }

    /** Daftar lengkap aksi header; pengelompokan utama/"Lainnya" oleh DocumentActions (D13). */
    private function headerActionList(): array
    {
        return [
            Actions\EditAction::make()->icon('heroicon-o-pencil')->color('warning'),
            \App\Filament\Support\CustomerReceiptActions::cancel(Action::make('cancel_receipt')),
            Action::make('print_receipt')
                ->label('Cetak Kwitansi')
                ->icon('heroicon-o-printer')
                ->color('primary')
                ->url(fn () => route('pdf-stream', ['type' => 'customer-receipt', 'id' => $this->record->id]))
                ->openUrlInNewTab(),
            Action::make('view_journal_entries')
                ->label('Lihat Journal Entries')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->url(fn () => route('filament.admin.resources.journal-entries.index', [
                    'tableFilters[source_type][value]' => 'App\Models\CustomerReceipt',
                    'tableFilters[source_id][source_id]' => $this->record->id
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
