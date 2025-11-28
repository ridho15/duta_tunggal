<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_posts_redirects_to_filament_dashboard()
    {
        // Create a user with a known password
        /** @var User $user */
        $user = User::factory()->create([
            'password' => Hash::make('secret-password'),
        ]);

        // Give the user necessary permissions to access Filament resources
        $user->givePermissionTo([
            'view any account payable',
            'view any account receivable',
            'view any chart of account',
            'view any user',
            'view any role',
            'view any permission',
        ]);

        // Test that unauthenticated users are redirected to login
        $response = $this->get('/admin');
        $response->assertRedirect('/admin/login');

        // Test that authenticated users can access the dashboard
        $this->actingAs($user);
        $response = $this->get('/admin');
        $response->assertStatus(200);
    }
}
