<?php

namespace App\Filament\Resources\JournalEntryResource\Pages;

use App\Filament\Resources\JournalEntryResource;
use App\Models\JournalEntry;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class CreateJournalEntry extends CreateRecord
{
    protected static string $resource = JournalEntryResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            // Generate reference if not provided
            if (empty($data['reference']) && !empty($data['reference_prefix'])) {
                $prefix = $data['reference_prefix'];
                $number = $data['reference_number'] ?? '001';
                $data['reference'] = $prefix . '-' . $number;
            }

            // Get journal entries data
            $journalEntries = $data['journal_entries'] ?? [];
            unset($data['journal_entries'], $data['reference_prefix'], $data['reference_number'], $data['balance_validation']);

            // Get common data for all entries
            $commonData = [
                'date' => $data['date'],
                'reference' => $data['reference'],
                'journal_type' => $data['journal_type'],
                'cabang_id' => $data['cabang_id'] ?? null,
                'source_type' => $data['source_type'] ?? null,
                'source_id' => $data['source_id'] ?? null,
            ];

            $createdEntries = [];

            // Create each journal entry
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

                $createdEntries[] = $entry;
            }

            // Return the first entry as the "main" record for Filament
            return $createdEntries[0] ?? throw new \Exception('No journal entries were created. Please check your input data.');
        } catch (\Exception $e) {
            // Log the error and re-throw to show debug info
            Log::error('Error creating journal entries: ' . $e->getMessage(), [
                'data' => $data,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
