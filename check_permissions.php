<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$config = config();
$dbConfig = $config['database']['connections']['mysql'];
$dbConfig['database'] = 'duta_tunggal_test';

$config['database']['connections']['testing'] = $dbConfig;

$capsule = new Illuminate\Database\Capsule\Manager();
$capsule->addConnection($config['database']['connections']['testing']);
$capsule->setAsGlobal();
$capsule->bootEloquent();

$permissions = \Spatie\Permission\Models\Permission::where('name', 'like', '%warehouse%')->pluck('name')->toArray();
echo "Warehouse permissions: " . implode(', ', $permissions) . PHP_EOL;

$allPermissions = \Spatie\Permission\Models\Permission::all()->pluck('name')->toArray();
echo "Total permissions: " . count($allPermissions) . PHP_EOL;
echo "All permissions: " . implode(', ', $allPermissions) . PHP_EOL;