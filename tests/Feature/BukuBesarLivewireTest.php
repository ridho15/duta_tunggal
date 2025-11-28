<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use App\Filament\pages\BukuBesarPage;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;

class BukuBesarLivewireTest extends TestCase
{
    use RefreshDatabase;

    public function test_livewire_renders_entries_when_coa_selected()
    {
        // Create a COA and a journal entry within the current month
        $coa = ChartOfAccount::factory()->create([
            'type' => 'Asset',
            'opening_balance' => 100000,
        ]);

        $entry = JournalEntry::factory()->create([
            'coa_id' => $coa->id,
            'date' => now()->format('Y-m-d'),
            'debit' => 50000,
            'credit' => 0,
            'description' => 'Integration Test Entry',
        ]);

        // Test the Filament page Livewire component
        Livewire::test(BukuBesarPage::class)
            ->set('start_date', now()->startOfMonth()->format('Y-m-d'))
            ->set('end_date', now()->endOfMonth()->format('Y-m-d'))
            ->set('coa_ids', [$coa->id])
            ->assertSee('Integration Test Entry')
            ->assertSee('Saldo Awal');
    }
}
