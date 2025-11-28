<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Database\Seeders\BukuBesarTestSeeder;
use Database\Seeders\ChartOfAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class BukuBesarJavaScriptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed test data
        $this->seed(ChartOfAccountSeeder::class);
        $this->seed(BukuBesarTestSeeder::class);
        
        // Create and authenticate a test user with super admin permissions
    /** @var \App\Models\User $user */
    $user = \App\Models\User::factory()->create();
        
        // Bypass Filament policy authorization for tests
        $this->actingAs($user);
        
        // Mock authorization to always return true for test environment
        Gate::before(function () {
            return true;
        });
    }

    /** @test */
    public function page_returns_correct_html_structure()
    {
        $response = $this->get('/admin/buku-besar-page');
        
        $response->assertStatus(200);
        $response->assertSee('Buku Besar', false);
        $response->assertSee('Filter & Pencarian', false);
    $response->assertSee('id="coa-search"', false);
    $response->assertSee('data-coa-multi-select', false);
    }

    /** @test */
    public function hidden_input_and_search_field_have_expected_attributes()
    {
        $response = $this->get('/admin/buku-besar-page');
        
    $response->assertSee('id="coa-select"', false);
    $response->assertSee('name="coa_ids"', false);
    $response->assertSee('dusk="coa-search-input"', false);
    }

    /** @test */
    public function select_dropdown_contains_coa_options()
    {
        $response = $this->get('/admin/buku-besar-page');
        
        $response->assertSee('KAS BESAR', false);
        $response->assertSee('BANK BCA', false);
    }

    /** @test */
    public function alpine_component_script_is_included()
    {
        $response = $this->get('/admin/buku-besar-page');
        
    $response->assertSee("Alpine.data('coaMultiSelect'", false);
        $response->assertSee('filteredOptions', false);
    }

    /** @test */
    public function date_inputs_have_correct_wire_model()
    {
        $response = $this->get('/admin/buku-besar-page');
        
    $response->assertSee('wire:model="start_date"', false);
    $response->assertSee('wire:model="end_date"', false);
    }

    /** @test */
    public function sample_button_exists()
    {
        $response = $this->get('/admin/buku-besar-page');
        
        $response->assertSee('Tampilkan Semua', false);
        $response->assertSee('wire:click="showAll"', false);
    }

    /** @test */
    public function livewire_component_is_initialized()
    {
        $response = $this->get('/admin/buku-besar-page');
        
        // Check for Livewire attributes
        $response->assertSee('wire:', false);
    }

    /** @test */
    public function javascript_console_logging_is_present()
    {
        $response = $this->get('/admin/buku-besar-page');
        
        // Check if our debug logging is present
    $response->assertSee('coaMultiSelect', false);
    $response->assertSee("toggle(option)", false);
    }

    /** @test */
    public function dropdown_configuration_is_correct()
    {
        $response = $this->get('/admin/buku-besar-page');
        $response->assertSee('selectFirst()', false);
        $response->assertSee('remove(', false);
    }

}
