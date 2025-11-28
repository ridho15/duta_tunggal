<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== CHECKING USER CREDENTIALS ===\n";

$user = \App\Models\User::where('email', 'ralamzah@gmail.com')->first();
if ($user) {
    echo "User found: {$user->name} ({$user->email})\n";
    echo "Password valid: " . (\Illuminate\Support\Facades\Hash::check('ridho123', $user->password) ? 'Yes' : 'No') . "\n";
} else {
    echo "User not found\n";
    $users = \App\Models\User::all();
    echo "Available users:\n";
    foreach ($users as $u) {
        echo "  - {$u->email}\n";
    }
}
