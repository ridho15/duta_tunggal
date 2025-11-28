<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_page_redirects_to_filament_login_for_guests()
    {
        // Filament directly redirects guests to the admin login page
        $response = $this->get('/admin');
        $response->assertRedirect('/admin/login');
    }
}
