<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Filament\Facades\Filament;
use Filament\Navigation\NavigationManager;
use Filament\Navigation\NavigationGroup;

Filament::serving(function () {
    $panel = Filament::getCurrentPanel();
    if (! $panel) return;

    $manager = app(NavigationManager::class);
    $manager->get(); 
    
    $items = $panel->getNavigationItems();
    $groupedItems = collect($items)->groupBy(fn ($item) => $item->getGroup());
    
    foreach ($groupedItems as $groupLabel => $groupItems) {
        if ($groupLabel && $groupItems->count() === 1) {
            $singleItem = $groupItems->first();
            echo "Found single item for group {$groupLabel}: " . $singleItem->getLabel() . PHP_EOL;
            
            $singleItem->group(null);
            
            $groups = $panel->getNavigationGroups();
            foreach ($groups as $group) {
                if ($group instanceof NavigationGroup && $group->getLabel() === $groupLabel) {
                    if (! $singleItem->getIcon()) {
                        $singleItem->icon($group->getIcon());
                    }
                    break;
                }
            }
        }
    }
});

// simulate a request to trigger serving
Filament::setCurrentPanel(Filament::getPanel('admin'));
$app->call(Filament\Http\Middleware\DispatchServingFilamentEvent::class.'@handle', [
    'request' => request(),
    'next' => fn($req) => response('ok')
]);

$navGroups = app(NavigationManager::class)->get();
foreach ($navGroups as $group) {
    echo "Group: " . ($group->getLabel() ?? 'ROOT') . PHP_EOL;
    foreach ($group->getItems() as $item) {
        echo " - Item: " . $item->getLabel() . PHP_EOL;
    }
}
