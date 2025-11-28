<?php

namespace App\Filament\Resources\BankReconciliationResource\Pages;

use App\Filament\Resources\BankReconciliationResource;
use App\Models\JournalEntry;
use Filament\Resources\Pages\EditRecord;

class EditBankReconciliation extends EditRecord
{
    protected static string $resource = BankReconciliationResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['selected_entry_ids'] = [];
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Process selected entries for reconciliation
        if (isset($data['selected_entry_ids']) && is_array($data['selected_entry_ids'])) {
            foreach ($data['selected_entry_ids'] as $entryId) {
                JournalEntry::where('id', $entryId)->update([
                    'bank_recon_id' => $this->record->id,
                    'bank_recon_status' => 'cleared',
                    'bank_recon_date' => now()->toDateString(),
                ]);
            }
        }

        // Remove selected_entry_ids from data before saving
        unset($data['selected_entry_ids']);

        return $data;
    }
}
