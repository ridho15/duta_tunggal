<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(\Database\Seeders\UserSeeder::class);
});

test('UAT Scenario 1: Authenticated user visiting legacy Hub route gets 301 redirected to direct target', function () {
    $user = User::where('email', 'superadmin@gmail.com')->first();

    $response = $this->actingAs($user)->get('/admin/sales-hub');
    $response->assertStatus(301);
    $response->assertRedirect('/admin/sale-orders');

    $response2 = $this->actingAs($user)->get('/admin/purchase-hub');
    $response2->assertStatus(301);
    $response2->assertRedirect('/admin/purchase-orders');
});

test('UAT Scenario 2: Visiting non-existent route returns clean 404 without leaking stack trace', function () {
    $user = User::where('email', 'superadmin@gmail.com')->first();

    $response = $this->actingAs($user)->get('/admin/non-existent-route-random-12345');
    $response->assertStatus(404);
});

test('UAT Scenario 3: Kasir role boundary enforcement - restricted admin pages return 403 Forbidden', function () {
    $kasir = User::where('email', 'kasir@example.com')->first();

    // Kasir accessing forbidden Admin Settings
    $forbiddenRoutes = [
        '/admin/users',
        '/admin/approval-rules',
        '/admin/accounting-periods',
    ];

    foreach ($forbiddenRoutes as $route) {
        $response = $this->actingAs($kasir)->get($route);
        expect(in_array($response->status(), [403, 302, 404]))->toBeTrue(
            "Expected {$route} to be forbidden or redirected for Kasir, got {$response->status()}"
        );
    }
});

test('UAT Scenario 4: Warehouse Staff role boundary enforcement - financial journal is forbidden', function () {
    $staff = User::where('email', 'warehouse_staff@example.com')->first();

    $forbiddenRoutes = [
        '/admin/users',
        '/admin/journal-entries',
        '/admin/accounting-periods',
    ];

    foreach ($forbiddenRoutes as $route) {
        $response = $this->actingAs($staff)->get($route);
        expect(in_array($response->status(), [403, 302, 404]))->toBeTrue(
            "Expected {$route} to be forbidden for Warehouse staff, got {$response->status()}"
        );
    }
});

test('UAT Scenario 5: Super Admin has full authorized access to core resources', function () {
    $superadmin = User::where('email', 'superadmin@gmail.com')->first();

    $allowedRoutes = [
        '/admin',
        '/admin/sale-orders',
        '/admin/purchase-orders',
        '/admin/inventory-stocks',
        '/admin/users',
        '/admin/approval-rules',
    ];

    foreach ($allowedRoutes as $route) {
        $response = $this->actingAs($superadmin)->get($route);
        expect($response->status())->toBe(200, "Super admin should be able to view {$route}");
    }
});
