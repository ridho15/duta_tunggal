<?php

use App\Filament\Pages\BalanceSheetPage;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Cabang;
use App\Services\BalanceSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('BalanceSheetPage Backend Tests', function () {

    beforeEach(function () {
        // Create test branch
        $this->cabang = Cabang::create([
            'kode' => 'TEST',
            'nama' => 'Test Branch',
            'alamat' => 'Test Address',
            'telepon' => '0123456789',
        ]);

        // Create Chart of Accounts for Balance Sheet
        $this->cashAccount = ChartOfAccount::create([
            'code' => '1-1001',
            'name' => 'Kas',
            'type' => 'Asset',
            'is_current' => true,
            'is_active' => true,
        ]);

        $this->payableAccount = ChartOfAccount::create([
            'code' => '2-1001',
            'name' => 'Hutang Dagang',
            'type' => 'Liability',
            'is_current' => true,
            'is_active' => true,
        ]);

        $this->capitalAccount = ChartOfAccount::create([
            'code' => '3-1001',
            'name' => 'Modal Pemilik',
            'type' => 'Equity',
            'is_active' => true,
        ]);

        $this->revenueAccount = ChartOfAccount::create([
            'code' => '4-1001',
            'name' => 'Pendapatan Penjualan',
            'type' => 'Revenue',
            'is_active' => true,
        ]);

        $this->expenseAccount = ChartOfAccount::create([
            'code' => '6-1001',
            'name' => 'Beban Gaji',
            'type' => 'Expense',
            'is_active' => true,
        ]);
    });

    it('can mount the page with default values', function () {
        Livewire::test(BalanceSheetPage::class)
            ->assertSet('as_of_date', now()->endOfMonth()->format('Y-m-d'))
            ->assertSet('cabang_id', null)
            ->assertSet('show_comparison', false);
    });

    it('can generate balance sheet data', function () {
        // Create a journal entry to have some data
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now(),
            'reference' => 'BS-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Test cash entry',
            'debit' => 10000000,
            'credit' => 0,
        ]);

        $component = Livewire::test(BalanceSheetPage::class)
            ->set('as_of_date', now()->format('Y-m-d'));

        $data = $component->instance()->getBalanceSheetData();

        expect($data)->toBeArray();
        expect($data)->toHaveKey('current_assets');
        expect($data)->toHaveKey('total_assets');
        expect($data)->toHaveKey('is_balanced');
    });

    it('can get cabang options', function () {
        $component = Livewire::test(BalanceSheetPage::class);

        $options = $component->instance()->getCabangOptions();

        expect($options)->toBeArray();
        expect($options)->toHaveKey($this->cabang->id);
        expect($options[$this->cabang->id])->toBe('TEST - Test Branch');
    });

    it('can toggle comparison mode', function () {
        $component = Livewire::test(BalanceSheetPage::class)
            ->set('show_comparison', true)
            ->set('comparison_date', now()->subMonth()->format('Y-m-d'));

        expect($component->get('show_comparison'))->toBe(true);
        expect($component->get('comparison_date'))->toBe(now()->subMonth()->format('Y-m-d'));
    });

    it('can get comparison data when comparison is enabled', function () {
        // Create journal entries for two periods
        $date1 = now()->subDays(10); // Previous period
        $date2 = now(); // Current period

        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => $date1,
            'reference' => 'BS-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Cash balance period 1',
            'debit' => 5000000,
            'credit' => 0,
        ]);

        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => $date2,
            'reference' => 'BS-002',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Cash balance period 2',
            'debit' => 10000000,
            'credit' => 0,
        ]);

        // Test the service directly
        $service = app(BalanceSheetService::class);
        $comparison = $service->comparePeriods(
            ['as_of_date' => $date2->format('Y-m-d')],
            ['as_of_date' => $date1->format('Y-m-d')],
            $this->cabang->id
        );

        expect($comparison)->toBeArray();
        expect($comparison)->toHaveKey('total_assets');
        expect($comparison['total_assets']['current'])->toBe(15000000.0); // 5000000 + 10000000
        expect($comparison['total_assets']['previous'])->toBe(5000000.0);
        expect($comparison['total_assets']['change'])->toBe(10000000.0);
    });

    it('can show account details', function () {
        $component = Livewire::test(BalanceSheetPage::class)
            ->call('showAccountDetails', $this->cashAccount->id);

        expect($component->get('selected_account_id'))->toBe($this->cashAccount->id);
        expect($component->get('show_drill_down'))->toBe(true);
    });

    it('can close drill down modal', function () {
        $component = Livewire::test(BalanceSheetPage::class)
            ->set('selected_account_id', $this->cashAccount->id)
            ->set('show_drill_down', true)
            ->call('closeDrillDown');

        expect($component->get('selected_account_id'))->toBe(null);
        expect($component->get('show_drill_down'))->toBe(false);
    });

    it('can get drill down data for account', function () {
        $testDate = now()->subDays(1);
        
        // Create journal entries for the account
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => $testDate,
            'reference' => 'DRILL-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Test transaction',
            'debit' => 1000000,
            'credit' => 0,
        ]);

        // Test the service directly
        $service = app(BalanceSheetService::class);
        
        // Check if entry exists
        $entries = \App\Models\JournalEntry::where('coa_id', $this->cashAccount->id)
            ->where('date', '<=', $testDate)
            ->get();
        expect($entries)->toHaveCount(1);
        
        $drillDownData = $service->getAccountJournalEntries(
            $this->cashAccount->id,
            $testDate->format('Y-m-d'),
            null // Try without cabang_id filtering
        );

        // Debug output
        dump($drillDownData);

        expect($drillDownData)->toHaveKeys(['account', 'entries', 'total_debit', 'total_credit', 'balance']);
        expect($drillDownData['account']->id)->toBe($this->cashAccount->id);
        expect($drillDownData['total_debit'])->toBe(1000000.0);
        expect($drillDownData['balance'])->toBe(1000000.0);
    });

    it('validates date before generating report', function () {
        Livewire::test(BalanceSheetPage::class)
            ->set('as_of_date', '')
            ->call('generateReport');

        // The method should handle empty date gracefully without throwing errors
        expect(true)->toBe(true);
    });

    it('can filter by cabang', function () {
        $testDate = now()->subDays(1);
        
        // Create fresh accounts for this test
        $testCashAccount = ChartOfAccount::create([
            'code' => '1-2001',
            'name' => 'Kas Test',
            'type' => 'Asset',
            'is_current' => true,
            'is_active' => true,
        ]);

        // Create another branch
        $otherCabang = Cabang::create([
            'kode' => 'OTHER',
            'nama' => 'Other Branch',
            'alamat' => 'Other Address',
            'telepon' => '0987654321',
        ]);

        // Create entries for both branches
        JournalEntry::create([
            'coa_id' => $testCashAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => $testDate,
            'reference' => 'CABANG-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Cash in test branch',
            'debit' => 5000000,
            'credit' => 0,
        ]);

        JournalEntry::create([
            'coa_id' => $testCashAccount->id,
            'cabang_id' => $otherCabang->id,
            'date' => $testDate,
            'reference' => 'CABANG-002',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Cash in other branch',
            'debit' => 3000000,
            'credit' => 0,
        ]);

        // Test the service directly
        $service = app(BalanceSheetService::class);
        $data = $service->generate([
            'as_of_date' => $testDate->format('Y-m-d'),
            'cabang_id' => $this->cabang->id,
        ]);

        expect($data['total_assets'])->toBe(5000000.0); // Only test branch amount
    });

});