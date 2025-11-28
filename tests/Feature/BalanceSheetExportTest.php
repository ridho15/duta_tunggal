<?php

use App\Filament\Pages\BalanceSheetPage;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Cabang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('BalanceSheetPage Export Tests', function () {

    beforeEach(function () {
        // Create test branch
        $this->cabang = Cabang::create([
            'kode' => 'TEST',
            'nama' => 'Test Branch',
            'alamat' => 'Test Address',
            'telepon' => '0123456789',
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

        // Create test journal entries
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'date' => '2025-11-05',
            'reference' => 'TEST-001',
            'description' => 'Test cash entry',
            'debit' => 1000000,
            'credit' => 0,
            'cabang_id' => $this->cabang->id,
            'source_type' => 'manual',
            'source_id' => 1,
        ]);

        JournalEntry::create([
            'coa_id' => $this->liabilityAccount->id,
            'date' => '2025-11-05',
            'reference' => 'TEST-002',
            'description' => 'Test liability entry',
            'debit' => 0,
            'credit' => 500000,
            'cabang_id' => $this->cabang->id,
            'source_type' => 'manual',
            'source_id' => 1,
        ]);
    });

    it('can export PDF with filters applied', function () {
        $component = Livewire::test(BalanceSheetPage::class)
            ->set('as_of_date', '2025-11-05')
            ->set('cabang_id', $this->cabang->id);

        // Generate report first
        $component->call('generateReport');

        // Test PDF export - should not throw exception
        expect(fn() => $component->call('exportPdf'))->not->toThrow(Exception::class);
    });

    it('can export Excel with filters applied', function () {
        $component = Livewire::test(BalanceSheetPage::class)
            ->set('as_of_date', '2025-11-05')
            ->set('cabang_id', $this->cabang->id);

        // Generate report first
        $component->call('generateReport');

        // Test Excel export - should not throw exception
        expect(fn() => $component->call('exportExcel'))->not->toThrow(Exception::class);
    });

    it('exports include filtered data correctly', function () {
        // Test with specific branch filter
        $component = Livewire::test(BalanceSheetPage::class)
            ->set('as_of_date', '2025-11-05')
            ->set('cabang_id', $this->cabang->id);

        $data = $component->instance()->getBalanceSheetData();

        // Should include data from the filtered branch
        expect($data['total_assets'])->toBe(1000000.0);
        expect($data['total_liabilities'])->toBe(500000.0);
    });

    it('exports work with all branches filter', function () {
        // Test with no branch filter (all branches)
        $component = Livewire::test(BalanceSheetPage::class)
            ->set('as_of_date', '2025-11-05')
            ->set('cabang_id', null);

        $data = $component->instance()->getBalanceSheetData();

        // Should include data from all branches
        expect($data['total_assets'])->toBe(1000000.0);
        expect($data['total_liabilities'])->toBe(500000.0);
    });
});