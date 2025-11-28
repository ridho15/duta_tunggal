<?php

namespace Tests\Browser;

use App\Models\ChartOfAccount;
use App\Models\User;
use Database\Seeders\BukuBesarTestSeeder;
use Database\Seeders\ChartOfAccountSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class BukuBesarBrowserTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed test data
        $this->seed(ChartOfAccountSeeder::class);
        $this->seed(BukuBesarTestSeeder::class);
    }

    public function test_page_loads_successfully()
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/buku-besar-page')
                    ->assertSee('Buku Besar')
                    ->assertSee('Filter & Pencarian');
        });
    }

    public function test_coa_dropdown_displays_options()
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/buku-besar-page')
                    ->waitFor('#coa-search')
                    ->assertVisible('#coa-search')
                    ->assertAttribute('#coa-search', 'placeholder', 'Cari kode atau nama akun...');
        });
    }

    public function test_can_select_coa_and_view_ledger()
    {
        $user = User::factory()->create();
        $coa = ChartOfAccount::where('code', '1111.01')->first();

        $this->browse(function (Browser $browser) use ($user, $coa) {
            $browser->loginAs($user)
                    ->visit('/buku-besar-page')
                    ->waitFor('#coa-search')
                    ->click('#coa-search')
                    ->waitFor("button[data-option-id='{$coa->id}']")
                    ->click("button[data-option-id='{$coa->id}']")
                    ->pause(1000)
                    ->assertSee($coa->name)
                    ->assertSee('Tanggal')
                    ->assertSee('Debit')
                    ->assertSee('Kredit');
        });
    }

    public function test_date_filtering_works()
    {
        $user = User::factory()->create();
        $coa = ChartOfAccount::where('code', '1112.01')->first();

        $this->browse(function (Browser $browser) use ($user, $coa) {
            $startDate = now()->subMonth()->startOfMonth()->format('Y-m-d');
            $endDate = now()->format('Y-m-d');

            $browser->loginAs($user)
                    ->visit('/buku-besar-page')
                    ->waitFor('#coa-search')
                    ->click('#coa-search')
                    ->waitFor("button[data-option-id='{$coa->id}']")
                    ->click("button[data-option-id='{$coa->id}']")
                    ->pause(500)
                    ->type('input[wire\\:model="start_date"]', $startDate)
                    ->type('input[wire\\:model="end_date"]', $endDate)
                    ->pause(1000)
                    ->assertSee('Tanggal');
        });
    }

    public function test_custom_dropdown_script_is_present()
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
            ->visit('/buku-besar-page')
            ->waitFor('#coa-search')
            ->assertSourceHas('coaDropdown');
        });
    }

    public function test_livewire_reactivity_works()
    {
        $user = User::factory()->create();
        $coa1 = ChartOfAccount::where('code', '1111.01')->first();
        $coa2 = ChartOfAccount::where('code', '1112.01')->first();

        $this->browse(function (Browser $browser) use ($user, $coa1, $coa2) {
            $browser->loginAs($user)
                    ->visit('/buku-besar-page')
                    ->waitFor('#coa-search')
                    ->click('#coa-search')
                    ->waitFor("button[data-option-id='{$coa1->id}']")
                    ->click("button[data-option-id='{$coa1->id}']")
                    ->pause(1000)
                    ->assertSee($coa1->name)
                    ->click('#coa-search')
                    ->waitFor("button[data-option-id='{$coa2->id}']")
                    ->click("button[data-option-id='{$coa2->id}']")
                    ->pause(1000)
                    ->assertSee($coa2->name);
        });
    }

    public function test_sample_button_works()
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/buku-besar-page')
                    ->waitFor('#coa-search')
                    ->click('button:contains("Tampilkan Contoh")')
                    ->pause(1000)
                    ->assertSee('Tanggal')
                    ->assertSee('Debit')
                    ->assertSee('Kredit');
        });
    }
}
