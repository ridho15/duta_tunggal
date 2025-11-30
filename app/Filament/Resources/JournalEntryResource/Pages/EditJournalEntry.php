<?php

namespace App\Filament\Resources\JournalEntryResource\Pages;

use App\Filament\Resources\JournalEntryResource;
use App\Models\JournalEntry;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditJournalEntry extends EditRecord
{
    protected static string $resource = JournalEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalDescription('This will delete all journal entries with the same reference.')
                ->action(function () {
                    $record = $this->getRecord();
                    JournalEntry::where('reference', $record->reference)->delete();
                    return redirect()->to(JournalEntryResource::getUrl('index'));
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Get all entries with the same reference
        $record = $this->getRecord();
        $relatedEntries = JournalEntry::where('reference', $record->reference)->get();

        // Convert to repeater format
        $data['journal_entries'] = $relatedEntries->map(function ($entry) {
            return [
                'coa_id' => $entry->coa_id,
                'debit' => $entry->debit,
                'credit' => $entry->credit,
                'description' => $entry->description,
            ];
        })->toArray();

        // Fill common data from the first entry
        $firstEntry = $relatedEntries->first();
        if ($firstEntry) {
            $data['date'] = $firstEntry->date;
            $data['journal_type'] = $firstEntry->journal_type;
            $data['cabang_id'] = $firstEntry->cabang_id;
            $data['description'] = $firstEntry->description;
            $data['source_type'] = $firstEntry->source_type;
            $data['source_id'] = $firstEntry->source_id;
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Get journal entries data
        $journalEntries = $data['journal_entries'] ?? [];
        unset($data['journal_entries'], $data['balance_validation']);

        // Get all existing entries with the same reference
        $existingEntries = JournalEntry::where('reference', $record->reference)->get();

        // Delete all existing entries
        foreach ($existingEntries as $existingEntry) {
            $existingEntry->delete();
        }

        // Get common data
        $commonData = [
            'date' => $data['date'],
            'reference' => $record->reference, // Keep the same reference
            'journal_type' => $data['journal_type'],
            'cabang_id' => $data['cabang_id'] ?? null,
            'source_type' => $data['source_type'] ?? null,
            'source_id' => $data['source_id'] ?? null,
        ];

        $updatedEntries = [];

        // Create new entries
        foreach ($journalEntries as $entryData) {
            $entry = JournalEntry::create([
                'coa_id' => $entryData['coa_id'],
                'date' => $commonData['date'],
                'reference' => $commonData['reference'],
                'description' => $entryData['description'] ?? $data['description'],
                'debit' => (float) ($entryData['debit'] ?? 0),
                'credit' => (float) ($entryData['credit'] ?? 0),
                'journal_type' => $commonData['journal_type'],
                'cabang_id' => $commonData['cabang_id'],
                'source_type' => $commonData['source_type'],
                'source_id' => $commonData['source_id'],
            ]);

            $updatedEntries[] = $entry;
        }

        // Return the first entry as the "main" record
        return $updatedEntries[0] ?? $record;
    }
}
