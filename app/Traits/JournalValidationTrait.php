<?php

namespace App\Traits;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use InvalidArgumentException;

trait JournalValidationTrait
{
    /**
     * Validate that journal entries are balanced (total debit = total credit)
     * and do not use parent/header accounts.
     * 
     * @param array $entries Array of JournalEntry instances or arrays with 'debit' and 'credit' keys
     * @throws \Exception If entries are not balanced
     * @throws InvalidArgumentException If any entry uses a parent account
     */
    protected function validateJournalEntries(array $entries): void
    {
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($entries as $entry) {
            $coaId = null;
            if ($entry instanceof JournalEntry) {
                $totalDebit += (float) $entry->debit;
                $totalCredit += (float) $entry->credit;
                $coaId = $entry->coa_id;
            } elseif (is_array($entry)) {
                $totalDebit += (float) ($entry['debit'] ?? 0);
                $totalCredit += (float) ($entry['credit'] ?? 0);
                $coaId = $entry['coa_id'] ?? null;
            } else {
                throw new \Exception('Invalid entry format for validation');
            }

            if ($coaId) {
                $this->validateNonParentCoa($coaId);
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

    /**
     * Ensure the COA is a leaf/transactable account, not a parent account.
     *
     * @param int|string|null $coaId
     * @throws InvalidArgumentException
     */
    protected function validateNonParentCoa(int|string|null $coaId): void
    {
        if (! $coaId) {
            return;
        }

        $coa = ChartOfAccount::find($coaId);
        if ($coa && $coa->children()->where('id', '!=', $coa->id)->exists()) {
            throw new InvalidArgumentException(
                "Akun '{$coa->code} - {$coa->name}' merupakan akun induk dan tidak dapat digunakan untuk transaksi jurnal."
            );
        }
    }
}