<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

app(PermissionRegistrar::class)->forgetCachedPermissions();

app(PermissionSeeder::class)->run();
app(RoleSeeder::class)->run();

$superAdminRole = Role::query()->where('name', 'Super Admin')->firstOrFail();

$user = User::query()->updateOrCreate(
    ['email' => 'superadmin@gmail.com'],
    [
        'email' => 'superadmin@gmail.com',
        'username' => 'super_admin',
        'manage_type' => 'all',
        'first_name' => 'Super Admin',
        'last_name' => '',
        'status' => true,
        'kode_user' => 'super_admin',
        'posisi' => 'Super Admin',
        'name' => 'Super Admin',
        'cabang_id' => null,
        'warehouse_id' => null,
        'email_verified_at' => now(),
        'password' => Hash::make('superadmin'),
    ],
);

$user->syncRoles([$superAdminRole->name]);

app(PermissionRegistrar::class)->forgetCachedPermissions();

echo json_encode([
    'user_id' => $user->id,
    'email' => $user->email,
    'roles' => $user->getRoleNames()->values()->all(),
    'counts' => [
        'users' => User::query()->count(),
        'roles' => Role::query()->count(),
        'permissions' => App\Models\Permission::query()->count(),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;