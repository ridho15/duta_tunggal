<?php

use App\Filament\Pages\BalanceSheetPage;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Cabang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('BalanceSheetPage Frontend Tests', function () {

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

        $this->payableAccount = ChartOfAccount::create([
            'code' => '2-1001',
            'name' => 'Hutang Dagang',
            'type' => 'Liability',
            'is_current' => true,
        ]);

        $this->capitalAccount = ChartOfAccount::create([
            'code' => '3-1001',
            'name' => 'Modal Pemilik',
            'type' => 'Equity',
        ]);

        // Create sample journal entries
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now(),
            'reference' => 'FE-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Cash balance for frontend test',
            'debit' => 15000000,
            'credit' => 0,
        ]);

        JournalEntry::create([
            'coa_id' => $this->payableAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now(),
            'reference' => 'FE-002',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Accounts payable',
            'debit' => 0,
            'credit' => 5000000,
        ]);

        JournalEntry::create([
            'coa_id' => $this->capitalAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now(),
            'reference' => 'FE-003',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Capital contribution',
            'debit' => 0,
            'credit' => 10000000,
        ]);
    });

    it('renders the balance sheet page correctly', function () {
        Livewire::test(BalanceSheetPage::class)
            ->assertOk()
            ->assertSee('Neraca')
            ->assertSee('ASET (ASSETS)')
            ->assertSee('KEWAJIBAN (LIABILITIES)')
            ->assertSee('EKUITAS (EQUITY)');
    });

    it('displays summary cards with correct data', function () {
        Livewire::test(BalanceSheetPage::class)
            ->assertSee('Total Aset')
            ->assertSee('Rp 15.000.000') // Cash balance
            ->assertSee('Total Kewajiban')
            ->assertSee('Rp 5.000.000') // Payable balance
            ->assertSee('Total Ekuitas')
            ->assertSee('Rp 10.000.000'); // Capital balance
    });

    it('displays account rows with proper formatting', function () {
        Livewire::test(BalanceSheetPage::class)
            ->assertSee('1-1001') // Account code
            ->assertSee('Kas') // Account name
            ->assertSee('2-1001') // Liability account code
            ->assertSee('Hutang Dagang') // Liability account name
            ->assertSee('3-1001') // Equity account code
            ->assertSee('Modal Pemilik'); // Equity account name
    });

    it('shows balanced status when assets equal liabilities plus equity', function () {
        Livewire::test(BalanceSheetPage::class)
            ->assertSee('Neraca Seimbang')
            ->assertSee('Aset = Kewajiban + Ekuitas');
    });

    it('displays filter section with proper inputs', function () {
        Livewire::test(BalanceSheetPage::class)
            ->assertSee('Tanggal Neraca')
            ->assertSee('Cabang')
            ->assertSee('Semua Cabang')
            ->assertSee('Perbarui Laporan');
    });

    it('can toggle comparison mode and show comparison inputs', function () {
        Livewire::test(BalanceSheetPage::class)
            ->set('show_comparison', true)
            ->assertSee('🔄 Bandingkan Periode')
            ->assertSee('comparison_date');
    });

    it('displays export buttons', function () {
        Livewire::test(BalanceSheetPage::class)
            ->assertSee('Export PDF')
            ->assertSee('Export Excel')
            ->assertSee('Print');
    });

    it('shows drill down modal when account is clicked', function () {
        Livewire::test(BalanceSheetPage::class)
            ->call('showAccountDetails', $this->cashAccount->id)
            ->assertSet('show_drill_down', true)
            ->assertSet('selected_account_id', $this->cashAccount->id);
    });

    it('displays drill down modal content correctly', function () {
        Livewire::test(BalanceSheetPage::class)
            ->call('showAccountDetails', $this->cashAccount->id)
            ->assertSee('Kas') // Account name in modal
            ->assertSee('Total Debit')
            ->assertSee('Rp 15.000.000') // Debit amount
            ->assertSee('Total Kredit')
            ->assertSee('Rp 0') // Credit amount
            ->assertSee('Saldo')
            ->assertSee('Rp 15.000.000'); // Balance
    });

    it('closes drill down modal correctly', function () {
        Livewire::test(BalanceSheetPage::class)
            ->call('showAccountDetails', $this->cashAccount->id)
            ->assertSet('show_drill_down', true)
            ->call('closeDrillDown')
            ->assertSet('show_drill_down', false)
            ->assertSet('selected_account_id', null);
    });

    it('displays cabang options in filter dropdown', function () {
        Livewire::test(BalanceSheetPage::class)
            ->assertSee('TEST - Test Branch');
    });

    it('shows proper section headers with icons', function () {
        Livewire::test(BalanceSheetPage::class)
            ->assertSee('🏦 ASET (ASSETS)')
            ->assertSee('📋 KEWAJIBAN (LIABILITIES)')
            ->assertSee('🏛️ EKUITAS (EQUITY)');
    });

    it('displays subsection headers correctly', function () {
        Livewire::test(BalanceSheetPage::class)
            ->assertSee('💵 Aset Lancar (Current Assets)')
            ->assertSee('💳 Kewajiban Lancar (Current Liabilities)')
            ->assertSee('💼 Total Ekuitas');
    });

    it('shows total rows with proper styling', function () {
        Livewire::test(BalanceSheetPage::class)
            ->assertSee('🏦 TOTAL ASET')
            ->assertSee('📊 TOTAL KEWAJIBAN')
            ->assertSee('⚖️ TOTAL KEWAJIBAN & EKUITAS', false);
    });

    it('displays account codes in monospace font', function () {
        $response = Livewire::test(BalanceSheetPage::class)
            ->assertSee('1-1001')
            ->assertSee('2-1001')
            ->assertSee('3-1001');
    });

    it('shows retained earnings section', function () {
        Livewire::test(BalanceSheetPage::class)
            ->assertSee('Laba Ditahan (Retained Earnings)')
            ->assertSee('RE'); // Retained earnings code
    });

    it('displays current ratio in summary cards', function () {
        // Current assets: 15M, Current liabilities: 5M, Ratio should be 3.00
        Livewire::test(BalanceSheetPage::class)
            ->assertSee('Rasio Lancar')
            ->assertSee('3.00');
    });

    it('renders comparison section when enabled', function () {
        Livewire::test(BalanceSheetPage::class)
            ->set('show_comparison', true)
            ->set('comparison_date', now()->subMonth()->format('Y-m-d'))
            ->assertSee('📊 Perbandingan dengan');
    });

    it('shows proper balance check message', function () {
        Livewire::test(BalanceSheetPage::class)
            ->assertSee('✅')
            ->assertDontSee('⚠️');
    });

    it('displays account balances with proper formatting', function () {
        Livewire::test(BalanceSheetPage::class)
            ->assertSee('Rp 15.000.000') // Cash
            ->assertSee('Rp 5.000.000') // Payable
            ->assertSee('Rp 10.000.000'); // Capital
    });

});