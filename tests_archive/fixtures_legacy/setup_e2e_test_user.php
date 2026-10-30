<?php
/**
 * Setup E2E test user for Playwright tests.
 * Creates a non-superadmin user with cabinet ID 1,
 * so cabang_id is auto-filled in Filament forms (field is hidden).
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Cabang;
use Spatie\Permission\Models\Permission;

$cabang = Cabang::find(1);
if (!$cabang) {
    echo "ERROR: Cabang id=1 not found! Run seeder first.\n";
    exit(1);
}
echo "Using Cabang: {$cabang->nama} (id={$cabang->id})\n";

// Create or update E2E test user
$user = User::where('email', 'e2e-test@duta-tunggal.test')->first();
if (!$user) {
    $user = User::create([
        'name'        => 'E2E Test User',
        'first_name'  => 'E2E',
        'last_name'   => 'Test',
        'username'    => 'e2e_test_user',
        'kode_user'   => 'E2E-001',
        'email'       => 'e2e-test@duta-tunggal.test',
        'password'    => bcrypt('e2e-password-123'),
        'cabang_id'   => $cabang->id,
        'manage_type' => 'cabang',
        'status'      => 1,
    ]);
    echo "Created user id={$user->id}\n";
} else {
    $user->update([
        'cabang_id'   => $cabang->id,
        'manage_type' => 'cabang',
    ]);
    echo "Updated existing user id={$user->id}\n";
}

// Get all permissions related to data master modules
$keywords = ['cabang', 'customer', 'supplier', 'product', 'warehouse',
             'driver', 'vehicle', 'rak', 'unit of measure', 'currency',
             'tax setting', 'product category'];

$query = Permission::query();
foreach ($keywords as $kw) {
    $query->orWhere('name', 'LIKE', '%' . $kw . '%');
}
$perms = $query->pluck('name');

echo "Assigning " . $perms->count() . " permissions\n";
$user->syncPermissions($perms);

echo "Done! cabang_id={$user->cabang_id}, manage_type=" . json_encode($user->manage_type) . "\n";
echo "Login: e2e-test@duta-tunggal.test / e2e-password-123\n";
