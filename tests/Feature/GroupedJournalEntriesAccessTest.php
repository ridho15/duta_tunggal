<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
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

    #[Test]
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
