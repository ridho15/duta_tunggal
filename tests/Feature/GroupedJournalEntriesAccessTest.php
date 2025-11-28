<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GroupedJournalEntriesAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Run seeders if needed
        $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
        $this->artisan('db:seed', ['--class' => 'UserSeeder']);
        $this->artisan('db:seed', ['--class' => 'ChartOfAccountSeeder']);
    }

    /** @test */
    public function unauthenticated_users_cannot_access_grouped_journal_entries()
    {
        $response = $this->get('/admin/journal-entries/grouped');
        
        $response->assertStatus(302);
        $response->assertRedirect('/admin/login');
    }

    /** @test */
    public function authenticated_users_can_access_grouped_journal_entries()
    {
        // Find or create a user with appropriate role
        $user = User::where('email', 'ralamzah@gmail.com')->first();
        
        if (!$user) {
            $user = User::factory()->create([
                'name' => 'Test Admin',
                'email' => 'ralamzah@gmail.com',
                'password' => bcrypt('ridho123'),
            ]);
            
            // Assign appropriate role
            if (class_exists(\Spatie\Permission\Models\Role::class)) {
                $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Super Admin']);
                $user->assignRole($role);
            }
        }

        $this->actingAs($user);
        
        Livewire::test(\App\Filament\Resources\JournalEntryResource\Pages\GroupedJournalEntries::class)
            ->assertOk()
            ->assertSee('Grouped');
    }

    /** @test */
    public function grouped_journal_entries_page_loads_with_filters()
    {
        $user = User::where('email', 'ralamzah@gmail.com')->first();
        
        if (!$user) {
            $user = User::factory()->create([
                'name' => 'Test Admin',
                'email' => 'ralamzah@gmail.com',
                'password' => bcrypt('ridho123'),
            ]);
            
            if (class_exists(\Spatie\Permission\Models\Role::class)) {
                $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Super Admin']);
                $user->assignRole($role);
            }
        }

        $this->actingAs($user);
        
        Livewire::test(\App\Filament\Resources\JournalEntryResource\Pages\GroupedJournalEntries::class)
            ->assertOk()
            ->assertSee('Start Date')
            ->assertSee('End Date')
            ->assertSee('Journal Type')
            ->assertSee('Branch');
    }

    /** @test */
    public function grouped_journal_entries_route_is_registered()
    {
        $routes = collect(\Route::getRoutes())->map(function ($route) {
            return [
                'uri' => $route->uri(),
                'name' => $route->getName(),
            ];
        });

        $this->assertTrue(
            $routes->contains(function ($route) {
                return $route['uri'] === 'admin/journal-entries/grouped';
            }),
            'Route admin/journal-entries/grouped is not registered'
        );
    }
}
