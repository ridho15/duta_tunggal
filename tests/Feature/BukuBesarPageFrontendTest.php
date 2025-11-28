<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Database\Seeders\BukuBesarTestSeeder;
use Database\Seeders\ChartOfAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BukuBesarPageFrontendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed test data
        $this->seed(ChartOfAccountSeeder::class);
        $this->seed(BukuBesarTestSeeder::class);
        
        // Authenticate - create user with required permissions
        $user = \App\Models\User::first() ?? \App\Models\User::factory()->create([
            'email' => 'test@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($user);
    }

    /** @test */
    public function it_shows_dynamic_buttons_when_no_coa_selected()
    {
        Livewire::test(\App\Filament\pages\BukuBesarPage::class)
            ->assertSee('Tampilkan Semua')
            ->assertSee('Buku Besar berdasarkan Journal Entry');
    }

    /** @test */
    public function show_all_button_clears_coa_selection()
    {
        Livewire::test(\App\Filament\pages\BukuBesarPage::class)
            ->assertSet('coa_ids', [])
            ->call('showAll')
            ->assertSet('view_mode', 'all')
            ->assertSet('coa_ids', []);
    }

    /** @test */
    public function show_by_journal_entry_button_clears_coa_selection()
    {
        Livewire::test(\App\Filament\pages\BukuBesarPage::class)
            ->assertSet('coa_ids', [])
            ->call('showByJournalEntry')
            ->assertSet('view_mode', 'by_journal_entry')
            ->assertSet('coa_ids', []);
    }

    /** @test */
    public function selecting_coa_displays_ledger()
    {
        $coa = ChartOfAccount::where('code', '1111.01')->first(); // KAS BESAR
        
        Livewire::test(\App\Filament\pages\BukuBesarPage::class)
            ->set('coa_ids', [$coa->id])
            ->assertSee($coa->code)
            ->assertSee($coa->name)
            ->assertSee('Tanggal')
            ->assertSee('Debit')
            ->assertSee('Kredit');
    }

    /** @test */
    public function changing_start_date_updates_component()
    {
        $newDate = now()->subMonth()->startOfMonth()->format('Y-m-d');
        
        Livewire::test(\App\Filament\pages\BukuBesarPage::class)
            ->set('start_date', $newDate)
            ->assertSet('start_date', $newDate);
    }

    /** @test */
    public function changing_end_date_updates_component()
    {
        $newDate = now()->subMonth()->endOfMonth()->format('Y-m-d');
        
        Livewire::test(\App\Filament\pages\BukuBesarPage::class)
            ->set('end_date', $newDate)
            ->assertSet('end_date', $newDate);
    }

    /** @test */
    public function refresh_button_reloads_component()
    {
        Livewire::test(\App\Filament\pages\BukuBesarPage::class)
            ->call('$refresh')
            ->assertOk();
    }

    /** @test */
    public function coa_options_are_sorted_by_code()
    {
        $component = Livewire::test(\App\Filament\pages\BukuBesarPage::class);
        
        $options = $component->instance()->coaOptions;
        $labels = array_values($options);
        
        // Extract codes from labels (format: "CODE - NAME")
        $codes = array_map(function($label) {
            return explode(' - ', $label)[0];
        }, $labels);
        
        // Verify they're sorted
        $sortedCodes = $codes;
        sort($sortedCodes);
        
        $this->assertEquals($sortedCodes, $codes, 'COA options should be sorted by code');
    }

    /** @test */
    public function ledger_displays_journal_entries_within_date_range()
    {
        $coa = ChartOfAccount::where('code', '1112.01')->first(); // BANK BCA
        
        // Create entries in specific date ranges
        $oldEntry = JournalEntry::create([
            'coa_id' => $coa->id,
            'date' => now()->subMonths(2)->format('Y-m-d'),
            'reference' => 'OLD-TEST',
            'description' => 'Old entry outside range',
            'debit' => 1000,
            'credit' => 0,
            'journal_type' => 'test',
            'source_type' => 'App\\Models\\ChartOfAccount',
            'source_id' => $coa->id,
        ]);

        $currentEntry = JournalEntry::create([
            'coa_id' => $coa->id,
            'date' => now()->format('Y-m-d'),
            'reference' => 'CURRENT-TEST',
            'description' => 'Current entry in range',
            'debit' => 2000,
            'credit' => 0,
            'journal_type' => 'test',
            'source_type' => 'App\\Models\\ChartOfAccount',
            'source_id' => $coa->id,
        ]);

        Livewire::test(\App\Filament\pages\BukuBesarPage::class)
            ->set('coa_ids', [$coa->id])
            ->set('start_date', now()->startOfMonth()->format('Y-m-d'))
            ->set('end_date', now()->endOfMonth()->format('Y-m-d'))
            ->assertSee('CURRENT-TEST')
            ->assertDontSee('OLD-TEST');
    }

    /** @test */
    public function multiple_coa_selections_work_correctly()
    {
        $coa1 = ChartOfAccount::where('code', '1111.01')->first(); // KAS BESAR
        $coa2 = ChartOfAccount::where('code', '1112.01')->first(); // BANK BCA
        
        $component = Livewire::test(\App\Filament\pages\BukuBesarPage::class);

        // Select multiple COAs
        $component->set('coa_ids', [$coa1->id, $coa2->id])
            ->assertSet('coa_ids', [$coa1->id, $coa2->id]);

        // Verify selectedCoas contains both accounts
        $selectedCoas = $component->instance()->selectedCoas;
        $this->assertCount(2, $selectedCoas);
        $this->assertEquals($coa1->id, $selectedCoas[0]->id);
        $this->assertEquals($coa2->id, $selectedCoas[1]->id);
    }

    /** @test */
    public function computed_properties_work_correctly()
    {
        $coa = ChartOfAccount::where('code', '1111.01')->first();
        
        $component = Livewire::test(\App\Filament\pages\BukuBesarPage::class);
        
        // Test coaOptions computed property
        $this->assertIsArray($component->instance()->coaOptions);
        $this->assertNotEmpty($component->instance()->coaOptions);
        
        // Test selectedCoas computed property (initially empty array)
        $this->assertEmpty($component->instance()->selectedCoas);

        // Set COA and verify selectedCoas updates
        $component->set('coa_ids', [$coa->id]);
        $this->assertNotEmpty($component->instance()->selectedCoas);
        $this->assertCount(1, $component->instance()->selectedCoas);
        $this->assertEquals($coa->id, $component->instance()->selectedCoas[0]->id);
    }
}
