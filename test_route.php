<?php

// Simple test script to check if the grouped journal entries page loads after login
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Testing GroupedJournalEntries page access...\n";

// Simulate a simple HTTP request to check if the route exists
$router = app('router');
$routes = $router->getRoutes();

echo "Total routes: " . count($routes) . "\n";

$groupedRoute = null;
foreach ($routes as $route) {
    if (strpos($route->uri(), 'journal-entries/grouped') !== false) {
        $groupedRoute = $route;
        echo "Found grouped route: " . $route->uri() . "\n";
        break;
    }
}

if ($groupedRoute) {
    echo "Route found: " . $groupedRoute->uri() . "\n";
    echo "Route methods: " . implode(', ', $groupedRoute->methods()) . "\n";
    echo "Route action: " . $groupedRoute->getActionName() . "\n";
} else {
    echo "Route not found!\n";
}

// Try to resolve the controller
try {
    $controller = app(\App\Filament\Resources\JournalEntryResource\Pages\GroupedJournalEntries::class);
    echo "Controller resolved successfully\n";
} catch (Exception $e) {
    echo "Controller resolution failed: " . $e->getMessage() . "\n";
}