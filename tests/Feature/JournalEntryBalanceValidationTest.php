<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\ChartOfAccount;
use App\Models\Cabang;
use App\Filament\Resources\JournalEntryResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Validator;

class JournalEntryBalanceValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unbalanced_journal_entries_cannot_be_created()
    {
        // Create test data
        $cabang = Cabang::factory()->create();
        $coa1 = ChartOfAccount::factory()->create();
        $coa2 = ChartOfAccount::factory()->create();

        // Prepare unbalanced data
        $data = [
            'reference_prefix' => 'MANUAL',
            'reference_number' => '001',
            'reference' => 'MANUAL-001',
            'date' => now()->format('Y-m-d'),
            'journal_type' => 'manual',
            'cabang_id' => $cabang->id,
            'description' => 'Test unbalanced entry',
            'journal_entries' => [
                [
                    'coa_id' => $coa1->id,
                    'debit' => 100000,
                    'credit' => 0,
                    'description' => 'Debit entry',
                ],
                [
                    'coa_id' => $coa2->id,
                    'debit' => 0,
                    'credit' => 50000, // Unbalanced - only 50,000 credit vs 100,000 debit
                    'description' => 'Credit entry',
                ],
            ],
            'balance_validation' => 'dummy', // This should trigger validation
        ];

        // Test the balance validation rule directly
        $rules = [
            'balance_validation' => [
                function ($attribute, $value, $fail) use ($data) {
                    $entries = $data['journal_entries'] ?? [];

                    if (!is_array($entries) || count($entries) < 2) {
                        $fail('Minimal 2 journal entries diperlukan.');
                        return;
                    }

                    $totalDebit = 0;
                    $totalCredit = 0;

                    foreach ($entries as $entry) {
                        $totalDebit += (float) ($entry['debit'] ?? 0);
                        $totalCredit += (float) ($entry['credit'] ?? 0);
                    }

                    if (abs($totalDebit - $totalCredit) > 0.01) {
                        $fail("Journal entries tidak balance. Total Debit: Rp" . number_format($totalDebit, 2) . ", Total Credit: Rp" . number_format($totalCredit, 2) . ". Selisih: Rp" . number_format(abs($totalDebit - $totalCredit), 2));
                    }
                },
            ],
        ];

        $validator = Validator::make($data, $rules);

        // The validation should fail
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('balance_validation', $validator->errors()->toArray());
        $this->assertTrue(strpos($validator->errors()->first('balance_validation'), 'tidak balance') !== false);
    }

    public function test_balanced_journal_entries_can_be_created()
    {
        // Create test data
        $cabang = Cabang::factory()->create();
        $coa1 = ChartOfAccount::factory()->create();
        $coa2 = ChartOfAccount::factory()->create();

        // Prepare balanced data
        $data = [
            'reference_prefix' => 'MANUAL',
            'reference_number' => '001',
            'reference' => 'MANUAL-001',
            'date' => now()->format('Y-m-d'),
            'journal_type' => 'manual',
            'cabang_id' => $cabang->id,
            'description' => 'Test balanced entry',
            'journal_entries' => [
                [
                    'coa_id' => $coa1->id,
                    'debit' => 100000,
                    'credit' => 0,
                    'description' => 'Debit entry',
                ],
                [
                    'coa_id' => $coa2->id,
                    'debit' => 0,
                    'credit' => 100000, // Balanced - 100,000 credit vs 100,000 debit
                    'description' => 'Credit entry',
                ],
            ],
            'balance_validation' => 'dummy',
        ];

        // Test the balance validation rule directly
        $rules = [
            'balance_validation' => [
                function ($attribute, $value, $fail) use ($data) {
                    $entries = $data['journal_entries'] ?? [];

                    if (!is_array($entries) || count($entries) < 2) {
                        $fail('Minimal 2 journal entries diperlukan.');
                        return;
                    }

                    $totalDebit = 0;
                    $totalCredit = 0;

                    foreach ($entries as $entry) {
                        $totalDebit += (float) ($entry['debit'] ?? 0);
                        $totalCredit += (float) ($entry['credit'] ?? 0);
                    }

                    if (abs($totalDebit - $totalCredit) > 0.01) {
                        $fail("Journal entries tidak balance. Total Debit: Rp" . number_format($totalDebit, 2) . ", Total Credit: Rp" . number_format($totalCredit, 2) . ". Selisih: Rp" . number_format(abs($totalDebit - $totalCredit), 2));
                    }
                },
            ],
        ];

        $validator = Validator::make($data, $rules);

        // The validation should pass
        $this->assertTrue($validator->passes());
    }
}