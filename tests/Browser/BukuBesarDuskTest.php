<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use App\Models\User;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;

class BukuBesarDuskTest extends DuskTestCase
{
    public function test_custom_dropdown_and_livewire_updates_table()
    {
        // Create a user and seed two COAs + JournalEntries so we can select two times
        // Create a uniquely-named user to avoid duplicate key errors when tests run repeatedly
        $unique = uniqid();
        $user = User::factory()->create([
            'email' => 'dusk+' . $unique . '@example.com',
            'username' => 'duskuser_' . $unique,
            'kode_user' => 'DUSK' . strtoupper(substr($unique, 0, 6)),
        ]);

        $coa1 = ChartOfAccount::factory()->create([
            'type' => 'Asset',
            'opening_balance' => 100000,
        ]);

        $coa2 = ChartOfAccount::factory()->create([
            'type' => 'Asset',
            'opening_balance' => 50000,
        ]);

        JournalEntry::factory()->create([
            'coa_id' => $coa1->id,
            'date' => now()->format('Y-m-d'),
            'debit' => 50000,
            'credit' => 0,
            'description' => 'Dusk Test Entry 1',
        ]);

        JournalEntry::factory()->create([
            'coa_id' => $coa2->id,
            'date' => now()->format('Y-m-d'),
            'debit' => 0,
            'credit' => 30000,
            'description' => 'Dusk Test Entry 2',
        ]);

        $this->browse(function (Browser $browser) use ($user, $coa1, $coa2) {
            $browser->loginAs($user)
                ->visit('/buku-besar-page')
                ->waitFor('@coa-input', 15)
                ->click('@coa-input')
                ->waitFor('@coa-option-' . $coa1->id)
                ->click('@coa-option-' . $coa1->id);

            $browser->pause(1200);

            $browser->screenshot('buku_besar_after_select1');

            $browser->assertSee('Dusk Test Entry 1')
                ->assertSee('Saldo Awal');

            $browser->click('@coa-input')
                ->waitFor('@coa-option-' . $coa2->id)
                ->click('@coa-option-' . $coa2->id);
            $browser->pause(1200);

            // Debug: capture screenshot after second selection
            $browser->screenshot('buku_besar_after_select_custom_dropdown');

            // Assert second entry visible
            $browser->assertSee('Dusk Test Entry 2')
                ->assertSee('Saldo Awal');
        });
    }
}
