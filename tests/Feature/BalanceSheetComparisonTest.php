<?php

use App\Filament\Pages\BalanceSheetPage;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Cabang;
use App\Services\BalanceSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('Balance Sheet Comparison Functionality Tests', function () {

    beforeEach(function () {
        // Create test branches
        $this->cabang1 = Cabang::create([
            'kode' => 'CAB1',
            'nama' => 'Cabang 1',
            'alamat' => 'Address 1',
            'telepon' => '0123456789',
        ]);

        $this->cabang2 = Cabang::create([
            'kode' => 'CAB2',
            'nama' => 'Cabang 2',
            'alamat' => 'Address 2',
            'telepon' => '0987654321',
        ]);

        // Create Chart of Accounts
        $this->cashAccount = ChartOfAccount::create([
            'code' => '1-1001',
            'name' => 'Kas',
            'type' => 'Asset',
            'is_current' => true,
        ]);

        $this->liabilityAccount = ChartOfAccount::create([
            'code' => '2-2001',
            'name' => 'Utang Usaha',
            'type' => 'Liability',
            'is_current' => true,
        ]);

        $this->equityAccount = ChartOfAccount::create([
            'code' => '3-3001',
            'name' => 'Modal',
            'type' => 'Equity',
        ]);

        $this->service = app(BalanceSheetService::class);
    });

    it('enables comparison mode correctly', function () {
        $component = Livewire::test(BalanceSheetPage::class)
            ->set('show_comparison', true)
            ->set('comparison_date', '2025-10-31');

        expect($component->get('show_comparison'))->toBe(true);
        expect($component->get('comparison_date'))->toBe('2025-10-31');
    });

    it('disables comparison mode correctly', function () {
        $component = Livewire::test(BalanceSheetPage::class)
            ->set('show_comparison', false)
            ->set('comparison_date', null); // Explicitly set to null

        expect($component->get('show_comparison'))->toBe(false);
        expect($component->get('comparison_date'))->toBe(null);
    });

    it('returns null comparison data when comparison is disabled', function () {
        $component = Livewire::test(BalanceSheetPage::class)
            ->set('show_comparison', false);

        $comparisonData = $component->instance()->getComparisonData();

        expect($comparisonData)->toBe(null);
    });

    it('returns null comparison data when comparison date is not set', function () {
        $component = Livewire::test(BalanceSheetPage::class)
            ->set('show_comparison', true)
            ->set('comparison_date', null);

        $comparisonData = $component->instance()->getComparisonData();

        expect($comparisonData)->toBe(null);
    });

    it('calculates comparison data correctly for same period', function () {
        // Create transactions for current period
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang1->id,
            'date' => '2025-11-15',
            'reference' => 'TEST-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Cash transaction',
            'debit' => 10000000,
            'credit' => 0,
        ]);

        JournalEntry::create([
            'coa_id' => $this->liabilityAccount->id,
            'cabang_id' => $this->cabang1->id,
            'date' => '2025-11-15',
            'reference' => 'TEST-002',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Liability transaction',
            'debit' => 0,
            'credit' => 5000000,
        ]);

        $component = Livewire::test(BalanceSheetPage::class)
            ->set('as_of_date', '2025-11-15')
            ->set('comparison_date', '2025-11-15')
            ->set('show_comparison', true)
            ->set('cabang_id', $this->cabang1->id);

        $comparisonData = $component->instance()->getComparisonData();

        expect($comparisonData)->toBeArray();
        expect($comparisonData['total_assets']['current'])->toBe(10000000.0);
        expect($comparisonData['total_assets']['previous'])->toBe(10000000.0);
        expect($comparisonData['total_assets']['change'])->toBe(0.0);
        expect($comparisonData['total_assets']['percentage'])->toBe(0.0);
    });

    it('calculates comparison data correctly for different periods', function () {
        // Transactions for previous period (October)
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang1->id,
            'date' => '2025-10-15',
            'reference' => 'OCT-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'October cash',
            'debit' => 5000000,
            'credit' => 0,
        ]);

        // Additional transactions for current period (November)
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang1->id,
            'date' => '2025-11-01',
            'reference' => 'NOV-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'November cash',
            'debit' => 3000000,
            'credit' => 0,
        ]);

        JournalEntry::create([
            'coa_id' => $this->liabilityAccount->id,
            'cabang_id' => $this->cabang1->id,
            'date' => '2025-11-01',
            'reference' => 'NOV-002',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'November liability',
            'debit' => 0,
            'credit' => 2000000,
        ]);

        $component = Livewire::test(BalanceSheetPage::class)
            ->set('as_of_date', '2025-11-15')      // Current period (Nov 15)
            ->set('comparison_date', '2025-10-31') // Previous period (Oct 31)
            ->set('show_comparison', true)
            ->set('cabang_id', $this->cabang1->id);

        $comparisonData = $component->instance()->getComparisonData();

        expect($comparisonData)->toBeArray();

        // Current period should have: 5000000 (Oct) + 3000000 (Nov) = 8000000
        // Previous period should have: 5000000 (Oct only)
        expect($comparisonData['total_assets']['current'])->toBe(8000000.0);
        expect($comparisonData['total_assets']['previous'])->toBe(5000000.0);
        expect($comparisonData['total_assets']['change'])->toBe(3000000.0);
        expect($comparisonData['total_assets']['percentage'])->toBe(60.0); // (3000000/5000000)*100
    });

    it('handles comparison with zero previous values', function () {
        // Only current period transactions
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang1->id,
            'date' => '2025-11-15',
            'reference' => 'CUR-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Current cash',
            'debit' => 10000000,
            'credit' => 0,
        ]);

        $component = Livewire::test(BalanceSheetPage::class)
            ->set('as_of_date', '2025-11-15')
            ->set('comparison_date', '2025-10-31') // No transactions in this period
            ->set('show_comparison', true)
            ->set('cabang_id', $this->cabang1->id);

        $comparisonData = $component->instance()->getComparisonData();

        expect($comparisonData)->toBeArray();
        expect($comparisonData['total_assets']['current'])->toBe(10000000.0);
        expect($comparisonData['total_assets']['previous'])->toBe(0.0);
        expect($comparisonData['total_assets']['change'])->toBe(10000000.0);
        expect($comparisonData['total_assets']['percentage'])->toBe(0); // Division by zero handled
    });

    it('applies branch filter to comparison data', function () {
        // Transactions for cabang1
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang1->id,
            'date' => '2025-11-15',
            'reference' => 'CAB1-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Cabang1 cash',
            'debit' => 10000000,
            'credit' => 0,
        ]);

        // Transactions for cabang2 (should be filtered out)
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang2->id,
            'date' => '2025-11-15',
            'reference' => 'CAB2-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Cabang2 cash',
            'debit' => 5000000,
            'credit' => 0,
        ]);

        $component = Livewire::test(BalanceSheetPage::class)
            ->set('as_of_date', '2025-11-15')
            ->set('comparison_date', '2025-10-31')
            ->set('show_comparison', true)
            ->set('cabang_id', $this->cabang1->id); // Filter by cabang1

        $comparisonData = $component->instance()->getComparisonData();

        expect($comparisonData)->toBeArray();
        expect($comparisonData['total_assets']['current'])->toBe(10000000.0); // Only cabang1
        expect($comparisonData['total_assets']['previous'])->toBe(0.0);
    });

    it('calculates percentage changes correctly', function () {
        // Previous period: 2000000
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang1->id,
            'date' => '2025-10-15',
            'reference' => 'PREV-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Previous cash',
            'debit' => 2000000,
            'credit' => 0,
        ]);

        // Current period: 2000000 + 3000000 = 5000000
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang1->id,
            'date' => '2025-11-01',
            'reference' => 'CURR-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Current cash',
            'debit' => 3000000,
            'credit' => 0,
        ]);

        $component = Livewire::test(BalanceSheetPage::class)
            ->set('as_of_date', '2025-11-15')
            ->set('comparison_date', '2025-10-31')
            ->set('show_comparison', true)
            ->set('cabang_id', $this->cabang1->id);

        $comparisonData = $component->instance()->getComparisonData();

        expect($comparisonData)->toBeArray();
        expect($comparisonData['total_assets']['current'])->toBe(5000000.0);
        expect($comparisonData['total_assets']['previous'])->toBe(2000000.0);
        expect($comparisonData['total_assets']['change'])->toBe(3000000.0);
        expect($comparisonData['total_assets']['percentage'])->toBe(150.0); // (3000000/2000000)*100
    });

    it('includes all balance sheet sections in comparison', function () {
        // Create transactions for different account types
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id, // Asset
            'cabang_id' => $this->cabang1->id,
            'date' => '2025-11-15',
            'reference' => 'BS-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Asset transaction',
            'debit' => 10000000,
            'credit' => 0,
        ]);

        JournalEntry::create([
            'coa_id' => $this->liabilityAccount->id, // Liability
            'cabang_id' => $this->cabang1->id,
            'date' => '2025-11-15',
            'reference' => 'BS-002',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Liability transaction',
            'debit' => 0,
            'credit' => 5000000,
        ]);

        JournalEntry::create([
            'coa_id' => $this->equityAccount->id, // Equity
            'cabang_id' => $this->cabang1->id,
            'date' => '2025-11-15',
            'reference' => 'BS-003',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Equity transaction',
            'debit' => 0,
            'credit' => 2000000,
        ]);

        $component = Livewire::test(BalanceSheetPage::class)
            ->set('as_of_date', '2025-11-15')
            ->set('comparison_date', '2025-10-31')
            ->set('show_comparison', true)
            ->set('cabang_id', $this->cabang1->id);

        $comparisonData = $component->instance()->getComparisonData();

        expect($comparisonData)->toBeArray();

        // Check that all sections are present
        expect($comparisonData)->toHaveKey('current_assets');
        expect($comparisonData)->toHaveKey('fixed_assets');
        expect($comparisonData)->toHaveKey('total_assets');
        expect($comparisonData)->toHaveKey('current_liabilities');
        expect($comparisonData)->toHaveKey('long_term_liabilities');
        expect($comparisonData)->toHaveKey('total_liabilities');
        expect($comparisonData)->toHaveKey('equity');
        expect($comparisonData)->toHaveKey('total_equity');
        expect($comparisonData)->toHaveKey('total_liabilities_and_equity');

        // Check current values
        expect($comparisonData['total_assets']['current'])->toBe(10000000.0);
        expect($comparisonData['total_liabilities']['current'])->toBe(5000000.0);
        expect($comparisonData['total_equity']['current'])->toBe(2000000.0);
    });
});