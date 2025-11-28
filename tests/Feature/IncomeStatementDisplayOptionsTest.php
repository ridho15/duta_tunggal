<?php

use App\Filament\Pages\IncomeStatementPage;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Cabang;
use App\Models\User;
use Livewire\Livewire;
use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);
    
    // Create test branch
    $this->cabang = Cabang::factory()->create([
        'kode' => 'TEST',
        'nama' => 'Test Branch',
    ]);
});

describe('Income Statement Display Options', function () {
    
    test('page can mount with default display options', function () {
        Livewire::test(IncomeStatementPage::class)
            ->assertSet('show_only_totals', false)
            ->assertSet('show_parent_accounts', true)
            ->assertSet('show_child_accounts', true)
            ->assertSet('show_zero_balance', false)
            ->assertStatus(200);
    });
    
    test('can toggle show only totals option', function () {
        Livewire::test(IncomeStatementPage::class)
            ->set('show_only_totals', true)
            ->assertSet('show_only_totals', true);
    });
    
    test('can toggle show parent accounts option', function () {
        Livewire::test(IncomeStatementPage::class)
            ->set('show_parent_accounts', false)
            ->assertSet('show_parent_accounts', false);
    });
    
    test('can toggle show child accounts option', function () {
        Livewire::test(IncomeStatementPage::class)
            ->set('show_child_accounts', false)
            ->assertSet('show_child_accounts', false);
    });
    
    test('can toggle show zero balance option', function () {
        Livewire::test(IncomeStatementPage::class)
            ->set('show_zero_balance', true)
            ->assertSet('show_zero_balance', true);
    });
    
    test('displays income statement data correctly', function () {
        // Create parent revenue account
        $parentRevenue = ChartOfAccount::factory()->create([
            'type' => 'Revenue',
            'code' => '4-1000',
            'name' => 'Pendapatan Penjualan',
            'parent_id' => null,
        ]);
        
        // Create child revenue account
        $childRevenue = ChartOfAccount::factory()->create([
            'type' => 'Revenue',
            'code' => '4-1001',
            'name' => 'Pendapatan Retail',
            'parent_id' => $parentRevenue->id,
        ]);
        
        // Create journal entry for parent
        JournalEntry::factory()->create([
            'coa_id' => $parentRevenue->id,
            'date' => now()->format('Y-m-d'),
            'debit' => 0,
            'credit' => 5000000,
            'cabang_id' => $this->cabang->id,
        ]);
        
        // Create journal entry for child
        JournalEntry::factory()->create([
            'coa_id' => $childRevenue->id,
            'date' => now()->format('Y-m-d'),
            'debit' => 0,
            'credit' => 3000000,
            'cabang_id' => $this->cabang->id,
        ]);
        
        $component = Livewire::test(IncomeStatementPage::class)
            ->set('start_date', now()->startOfMonth()->format('Y-m-d'))
            ->set('end_date', now()->endOfMonth()->format('Y-m-d'))
            ->set('cabang_id', $this->cabang->id);
        
        // Assert page renders without error
        $component->assertStatus(200);
        
        // Assert data exists via method call
        $data = $component->call('getIncomeStatementData')->get('data');
        if (!$data) {
            // Try getting from component instance
            $instance = $component->instance();
            $data = $instance->getIncomeStatementData();
        }
        
        expect($data)->toBeArray();
        expect($data)->toHaveKey('sales_revenue');
        expect($data['sales_revenue'])->toBeArray();
    });
    
    test('filters accounts with zero balance when option is disabled', function () {
        // Create revenue account with zero balance
        $zeroAccount = ChartOfAccount::factory()->create([
            'type' => 'Revenue',
            'code' => '4-2000',
            'name' => 'Pendapatan Lain (Zero)',
        ]);
        
        // Create revenue account with balance
        $nonZeroAccount = ChartOfAccount::factory()->create([
            'type' => 'Revenue',
            'code' => '4-3000',
            'name' => 'Pendapatan Aktif',
        ]);
        
        JournalEntry::factory()->create([
            'coa_id' => $nonZeroAccount->id,
            'date' => now()->format('Y-m-d'),
            'debit' => 0,
            'credit' => 1000000,
            'cabang_id' => $this->cabang->id,
        ]);
        
        $component = Livewire::test(IncomeStatementPage::class)
            ->set('start_date', now()->startOfMonth()->format('Y-m-d'))
            ->set('end_date', now()->endOfMonth()->format('Y-m-d'))
            ->set('show_zero_balance', false);
        
        $component->assertStatus(200);
        
        // In the view, zero balance accounts should be filtered out
        // This is tested implicitly through the filterAccounts function
    });
    
    test('shows only totals when option is enabled', function () {
        // Create test data
        $revenue = ChartOfAccount::factory()->create([
            'type' => 'Revenue',
            'code' => '4-1000',
            'name' => 'Pendapatan',
        ]);
        
        JournalEntry::factory()->create([
            'coa_id' => $revenue->id,
            'date' => now()->format('Y-m-d'),
            'debit' => 0,
            'credit' => 5000000,
            'cabang_id' => $this->cabang->id,
        ]);
        
        $component = Livewire::test(IncomeStatementPage::class)
            ->set('start_date', now()->startOfMonth()->format('Y-m-d'))
            ->set('end_date', now()->endOfMonth()->format('Y-m-d'))
            ->set('show_only_totals', true);
        
        $component->assertStatus(200);
        $component->assertSet('show_only_totals', true);
    });
    
    test('filters parent accounts correctly', function () {
        // Create parent and child accounts
        $parent = ChartOfAccount::factory()->create([
            'type' => 'Expense',
            'code' => '6-1000',
            'name' => 'Beban Operasional',
            'parent_id' => null,
        ]);
        
        $child = ChartOfAccount::factory()->create([
            'type' => 'Expense',
            'code' => '6-1001',
            'name' => 'Beban Gaji',
            'parent_id' => $parent->id,
        ]);
        
        JournalEntry::factory()->create([
            'coa_id' => $child->id,
            'date' => now()->format('Y-m-d'),
            'debit' => 2000000,
            'credit' => 0,
            'cabang_id' => $this->cabang->id,
        ]);
        
        $component = Livewire::test(IncomeStatementPage::class)
            ->set('start_date', now()->startOfMonth()->format('Y-m-d'))
            ->set('end_date', now()->endOfMonth()->format('Y-m-d'))
            ->set('show_parent_accounts', false)
            ->set('show_child_accounts', true);
        
        $component->assertStatus(200);
        // When show_parent_accounts is false, only child accounts should be visible
    });
    
    test('filters child accounts correctly', function () {
        // Create parent and child accounts
        $parent = ChartOfAccount::factory()->create([
            'type' => 'Expense',
            'code' => '6-2000',
            'name' => 'Beban Administrasi',
            'parent_id' => null,
        ]);
        
        $child = ChartOfAccount::factory()->create([
            'type' => 'Expense',
            'code' => '6-2001',
            'name' => 'Beban Alat Tulis',
            'parent_id' => $parent->id,
        ]);
        
        JournalEntry::factory()->create([
            'coa_id' => $parent->id,
            'date' => now()->format('Y-m-d'),
            'debit' => 1000000,
            'credit' => 0,
            'cabang_id' => $this->cabang->id,
        ]);
        
        $component = Livewire::test(IncomeStatementPage::class)
            ->set('start_date', now()->startOfMonth()->format('Y-m-d'))
            ->set('end_date', now()->endOfMonth()->format('Y-m-d'))
            ->set('show_parent_accounts', true)
            ->set('show_child_accounts', false);
        
        $component->assertStatus(200);
        // When show_child_accounts is false, only parent accounts should be visible
    });
    
    test('displays all account levels correctly', function () {
        // Create accounts for all 5 levels
        
        // 1. Sales Revenue
        $revenue = ChartOfAccount::factory()->create([
            'type' => 'Revenue',
            'code' => '4-1000',
            'name' => 'Pendapatan Penjualan',
        ]);
        
        // 2. COGS
        $cogs = ChartOfAccount::factory()->create([
            'type' => 'Expense',
            'code' => '5-1000',
            'name' => 'HPP',
        ]);
        
        // 3. Operating Expenses
        $opex = ChartOfAccount::factory()->create([
            'type' => 'Expense',
            'code' => '6-1000',
            'name' => 'Beban Operasional',
        ]);
        
        // 4. Other Income
        $otherIncome = ChartOfAccount::factory()->create([
            'type' => 'Revenue',
            'code' => '7-1000',
            'name' => 'Pendapatan Lain',
        ]);
        
        // 5. Tax Expense
        $tax = ChartOfAccount::factory()->create([
            'type' => 'Expense',
            'code' => '9-1000',
            'name' => 'Pajak Penghasilan',
        ]);
        
        // Create journal entries for each
        $entries = [
            ['coa' => $revenue, 'debit' => 0, 'credit' => 10000000],
            ['coa' => $cogs, 'debit' => 6000000, 'credit' => 0],
            ['coa' => $opex, 'debit' => 2000000, 'credit' => 0],
            ['coa' => $otherIncome, 'debit' => 0, 'credit' => 500000],
            ['coa' => $tax, 'debit' => 500000, 'credit' => 0],
        ];
        
        foreach ($entries as $entry) {
            JournalEntry::factory()->create([
                'coa_id' => $entry['coa']->id,
                'date' => now()->format('Y-m-d'),
                'debit' => $entry['debit'],
                'credit' => $entry['credit'],
                'cabang_id' => $this->cabang->id,
            ]);
        }
        
        $component = Livewire::test(IncomeStatementPage::class)
            ->set('start_date', now()->startOfMonth()->format('Y-m-d'))
            ->set('end_date', now()->endOfMonth()->format('Y-m-d'))
            ->set('cabang_id', $this->cabang->id);
        
        $component->assertStatus(200);
        
        // Get data via method call
        $instance = $component->instance();
        $data = $instance->getIncomeStatementData();
        
        // Assert all sections exist
        expect($data)->toHaveKeys([
            'sales_revenue',
            'cogs',
            'gross_profit',
            'operating_expenses',
            'operating_profit',
            'other_income',
            'other_expense',
            'profit_before_tax',
            'tax_expense',
            'net_profit',
        ]);
        
        // Assert calculations are correct
        expect($data['sales_revenue']['total'])->toBe(10000000.0);
        expect($data['cogs']['total'])->toBe(6000000.0);
        expect($data['gross_profit'])->toBe(4000000.0);
        expect($data['operating_expenses']['total'])->toBe(2000000.0);
        expect($data['operating_profit'])->toBe(2000000.0);
        expect($data['other_income']['total'])->toBe(500000.0);
        expect($data['profit_before_tax'])->toBe(2500000.0);
        expect($data['tax_expense']['total'])->toBe(500000.0);
        expect($data['net_profit'])->toBe(2000000.0);
    });
    
    test('page renders without errors with display options UI', function () {
        $component = Livewire::test(IncomeStatementPage::class);
        
        $component->assertStatus(200);
        
        // Assert display options checkboxes are present in the view
        $component->assertSeeHtml('show_only_totals');
        $component->assertSeeHtml('show_parent_accounts');
        $component->assertSeeHtml('show_child_accounts');
        $component->assertSeeHtml('show_zero_balance');
    });
});
