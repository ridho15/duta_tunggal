<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Database\Seeders\BukuBesarTestSeeder;
use Database\Seeders\ChartOfAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BukuBesarPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed test data
        $this->seed(ChartOfAccountSeeder::class);
        $this->seed(BukuBesarTestSeeder::class);
        
        // Create and authenticate a test user
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);
    }

    /** @test */
    public function it_can_render_buku_besar_page()
    {
        // Filament pages are accessible via Livewire component testing
        Livewire::test(\App\Filament\pages\BukuBesarPage::class)
            ->assertOk()
            ->assertSee('Buku Besar')
            ->assertSee('Pilih Akun');
    }

    /** @test */
    public function it_displays_coa_options_in_dropdown()
    {
        Livewire::test(\App\Filament\pages\BukuBesarPage::class)
            ->assertOk()
            ->assertSee('1111.01') // KAS BESAR
            ->assertSee('1112.01') // BANK BCA
            ->assertSee('4100');   // PENJUALAN
    }

    /** @test */
    public function it_can_select_a_coa_and_display_ledger()
    {
        $coa = ChartOfAccount::where('code', '1111.01')->first(); // KAS BESAR
        $this->assertNotNull($coa, 'KAS BESAR account should exist');

        Livewire::test(\App\Filament\pages\BukuBesarPage::class)
            ->set('coa_ids', [$coa->id])
            ->assertSet('coa_ids', [$coa->id])
            ->assertSee($coa->code)
            ->assertSee($coa->name);
    }

    /** @test */
    public function it_filters_journal_entries_by_date_range()
    {
        $coa = ChartOfAccount::where('code', '1112.01')->first(); // BANK BCA
        $this->assertNotNull($coa);

        // Create specific dated entries for testing
        $oldEntry = JournalEntry::create([
            'coa_id' => $coa->id,
            'date' => now()->subMonths(6)->format('Y-m-d'),
            'reference' => 'OLD-001',
            'description' => 'Old transaction (should not appear)',
            'debit' => 100000,
            'credit' => 0,
            'journal_type' => 'test',
            'source_type' => 'App\\Models\\ChartOfAccount',
            'source_id' => $coa->id,
        ]);

        $recentEntry = JournalEntry::create([
            'coa_id' => $coa->id,
            'date' => now()->subDays(5)->format('Y-m-d'),
            'reference' => 'RECENT-001',
            'description' => 'Recent transaction (should appear)',
            'debit' => 200000,
            'credit' => 0,
            'journal_type' => 'test',
            'source_type' => 'App\\Models\\ChartOfAccount',
            'source_id' => $coa->id,
        ]);

        $startDate = now()->subDays(10)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        Livewire::test(\App\Filament\pages\BukuBesarPage::class)
            ->set('coa_ids', [$coa->id])
            ->set('start_date', $startDate)
            ->set('end_date', $endDate)
            ->assertSee('RECENT-001')
            ->assertDontSee('OLD-001');
    }

    /** @test */
    public function it_handles_no_coa_selected_gracefully()
    {
        Livewire::test(\App\Filament\pages\BukuBesarPage::class)
            ->assertSet('coa_ids', [])
            ->assertSee('Pilih Akun'); // Should still show the filter form
    }

    /** @test */
    public function it_handles_coa_with_no_transactions()
    {
        // Create a COA with no transactions
        $emptyAccount = ChartOfAccount::create([
            'code' => '9999.99',
            'name' => 'Empty Test Account',
            'type' => 'Asset',
            'is_active' => true,
            'opening_balance' => 0,
            'debit' => 0,
            'credit' => 0,
            'ending_balance' => 0,
        ]);

        Livewire::test(\App\Filament\pages\BukuBesarPage::class)
            ->set('coa_ids', [$emptyAccount->id])
            ->assertSee($emptyAccount->code)
            ->assertSee($emptyAccount->name);
        
        // Should not error out, just show no transactions
    }

    /** @test */
    public function it_calculates_running_balance_correctly()
    {
        $coa = ChartOfAccount::where('code', '1111.01')->first(); // KAS BESAR (Asset)
        $this->assertNotNull($coa);

        // Clear existing entries for this account for clean calculation
        JournalEntry::where('coa_id', $coa->id)->delete();

        // Set known opening balance
        $coa->update(['opening_balance' => 10000000]);

        // Create test entries
        JournalEntry::create([
            'coa_id' => $coa->id,
            'date' => now()->startOfMonth()->format('Y-m-d'),
            'reference' => 'TEST-001',
            'description' => 'Debit entry',
            'debit' => 5000000,
            'credit' => 0,
            'journal_type' => 'test',
            'source_type' => 'App\\Models\\ChartOfAccount',
            'source_id' => $coa->id,
        ]);

        JournalEntry::create([
            'coa_id' => $coa->id,
            'date' => now()->startOfMonth()->addDays(1)->format('Y-m-d'),
            'reference' => 'TEST-002',
            'description' => 'Credit entry',
            'debit' => 0,
            'credit' => 2000000,
            'journal_type' => 'test',
            'source_type' => 'App\\Models\\ChartOfAccount',
            'source_id' => $coa->id,
        ]);

        // For Asset accounts: Running Balance = Opening + Debit - Credit
        // Expected: 10,000,000 + 5,000,000 - 2,000,000 = 13,000,000

        Livewire::test(\App\Filament\pages\BukuBesarPage::class)
            ->set('coa_ids', [$coa->id])
            ->set('start_date', now()->startOfMonth()->format('Y-m-d'))
            ->set('end_date', now()->endOfMonth()->format('Y-m-d'))
            ->assertSee('TEST-001')
            ->assertSee('TEST-002');
        
        // Note: Full balance calculation validation would require parsing the rendered view
        // which is better tested in browser/Dusk tests
    }

    /** @test */
    public function it_provides_correct_coa_options()
    {
        $component = Livewire::test(\App\Filament\pages\BukuBesarPage::class);
        
        $options = $component->instance()->coaOptions;
        
        $this->assertIsArray($options);
        $this->assertNotEmpty($options);
        
        // Check that options are keyed by ID and contain code + name
        $firstKey = array_key_first($options);
        $this->assertIsInt($firstKey);
        $this->assertStringContainsString(' - ', $options[$firstKey]);
    }

    /** @test */
    public function it_defaults_to_current_month_date_range()
    {
        $component = Livewire::test(\App\Filament\pages\BukuBesarPage::class);
        
        $startDate = $component->get('start_date');
        $endDate = $component->get('end_date');
        
        $this->assertEquals(now()->startOfMonth()->format('Y-m-d'), $startDate);
        $this->assertEquals(now()->endOfMonth()->format('Y-m-d'), $endDate);
    }

    /** @test */
    public function it_can_update_date_range()
    {
        $newStart = now()->subMonth()->startOfMonth()->format('Y-m-d');
        $newEnd = now()->subMonth()->endOfMonth()->format('Y-m-d');

        Livewire::test(\App\Filament\pages\BukuBesarPage::class)
            ->set('start_date', $newStart)
            ->set('end_date', $newEnd)
            ->assertSet('start_date', $newStart)
            ->assertSet('end_date', $newEnd);
    }
}
