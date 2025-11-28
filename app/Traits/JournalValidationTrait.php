<?php

namespace App\Traits;

trait JournalValidationTrait
{
    /**
     * Validate that journal entries are balanced (total debit = total credit)
     * 
     * @param array $entries Array of JournalEntry instances or arrays with 'debit' and 'credit' keys
     * @throws \Exception If entries are not balanced
     */
    protected function validateJournalEntries(array $entries): void
    {
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($entries as $entry) {
            if ($entry instanceof \App\Models\JournalEntry) {
                $totalDebit += (float) $entry->debit;
                $totalCredit += (float) $entry->credit;
            } elseif (is_array($entry)) {
                $totalDebit += (float) ($entry['debit'] ?? 0);
                $totalCredit += (float) ($entry['credit'] ?? 0);
            } else {
                throw new \Exception('Invalid entry format for validation');
            }
        }

        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new \Exception(
                sprintf(
                    'Journal entries are not balanced. Total Debit: %.2f, Total Credit: %.2f, Difference: %.2f',
                    $totalDebit,
                    $totalCredit,
                    $totalDebit - $totalCredit
                )
            );
        }
    }
}