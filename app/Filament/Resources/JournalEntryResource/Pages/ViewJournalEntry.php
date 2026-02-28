<?php

namespace App\Filament\Resources\JournalEntryResource\Pages;

use App\Filament\Resources\JournalEntryResource;
use App\Models\JournalEntry;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewJournalEntry extends ViewRecord
{
    protected static string $resource = JournalEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->icon('heroicon-o-pencil'),

            Actions\Action::make('auto_reversal')
                ->label('Auto Reversal Jurnal')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Buat Jurnal Auto Reversal')
                ->modalDescription(fn () => 'Ini akan membuat jurnal reversal (membalik semua debit/kredit) untuk transaction group yang sama. Lanjutkan?')
                ->visible(fn () => !empty($this->record->transaction_id) && !$this->record->is_reversal)
                ->action(function () {
                    $record = $this->record;
                    $transactionId = $record->transaction_id;

                    // Check if reversal already exists
                    $alreadyReversed = JournalEntry::where('reversal_of_transaction_id', $transactionId)->exists();
                    if ($alreadyReversed) {
                        \Filament\Notifications\Notification::make()
                            ->title('Sudah Di-Reversal')
                            ->warning()
                            ->body("Jurnal dengan transaction_id {$transactionId} sudah pernah di-reversal sebelumnya.")
                            ->send();
                        return;
                    }

                    // Get all entries with same transaction_id
                    $originalEntries = JournalEntry::where('transaction_id', $transactionId)->get();

                    if ($originalEntries->isEmpty()) {
                        \Filament\Notifications\Notification::make()
                            ->title('Data Tidak Ditemukan')
                            ->danger()
                            ->body("Tidak ada entri dengan transaction_id {$transactionId}.")
                            ->send();
                        return;
                    }

                    $newTransactionId = 'REV-' . $transactionId;
                    $reversalDate = now()->format('Y-m-d');

                    foreach ($originalEntries as $entry) {
                        JournalEntry::create([
                            'coa_id'                     => $entry->coa_id,
                            'date'                       => $reversalDate,
                            'reference'                  => 'REV-' . $entry->reference,
                            'description'                => 'Auto Reversal: ' . $entry->description,
                            'debit'                      => $entry->credit,   // swapped
                            'credit'                     => $entry->debit,    // swapped
                            'journal_type'               => 'REV',
                            'cabang_id'                  => $entry->cabang_id,
                            'department_id'              => $entry->department_id,
                            'project_id'                 => $entry->project_id,
                            'transaction_id'             => $newTransactionId,
                            'is_reversal'                => true,
                            'reversal_of_transaction_id' => $transactionId,
                        ]);
                    }

                    \Filament\Notifications\Notification::make()
                        ->title('Auto Reversal Berhasil')
                        ->success()
                        ->body("Berhasil membuat {$originalEntries->count()} entri jurnal reversal dengan transaction_id: {$newTransactionId}.")
                        ->send();
                }),
        ];
    }
}
