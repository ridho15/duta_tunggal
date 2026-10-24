<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminLockdownRedirectTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    public function test_guest_visiting_admin_is_redirected_to_dashboard(): void
    {
        $response = $this->get('/admin');
        $response->assertRedirect(route('dashboard'));
    }

    public function test_guest_visiting_admin_with_trailing_slash_is_redirected(): void
    {
        $response = $this->get('/admin/');
        $response->assertRedirect(route('dashboard'));
    }

    public function test_authenticated_user_visiting_admin_is_redirected_to_dashboard(): void
    {
        $response = $this->actingAs($this->user)->get('/admin');
        $response->assertRedirect(route('dashboard'));
    }

    public function test_authenticated_user_visiting_various_filament_admin_urls_is_redirected(): void
    {
        $adminPaths = [
            '/admin/users',
            '/admin/sale-orders',
            '/admin/purchase-orders',
            '/admin/delivery-orders',
            '/admin/warehouses',
            '/admin/products',
            '/admin/journal-entries',
            '/admin/quality-controls',
            '/admin/arbitrary-random-subpath',
        ];

        foreach ($adminPaths as $path) {
            $response = $this->actingAs($this->user)->get($path);
            $response->assertRedirect(route('dashboard'));
        }
    }

    public function test_following_redirect_from_admin_lands_authenticated_user_on_dashboard(): void
    {
        $response = $this->actingAs($this->user)->get('/admin');
        $response->assertRedirect(route('dashboard'));

        $dashboardResponse = $this->actingAs($this->user)->get(route('dashboard'));
        $dashboardResponse->assertOk();
    }
}
