<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Filament\Facades\Filament;
use Filament\Navigation\NavigationManager;

Filament::setCurrentPanel(Filament::getPanel('admin'));
$app->call(\Filament\Http\Middleware\DispatchServingFilamentEvent::class.'@handle', [
    'request' => request(),
    'next' => fn($req) => response('ok')
]);

try {
    $navGroups = app(NavigationManager::class)->get();
    echo "Navigation Groups Count: " . count($navGroups) . PHP_EOL;
    foreach ($navGroups as $group) {
        echo "Group: " . ($group->getLabel() ?? 'ROOT') . PHP_EOL;
        foreach ($group->getItems() as $item) {
            echo " - Item: " . $item->getLabel() . PHP_EOL;
        }
    }
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
