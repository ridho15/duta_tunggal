<?php

use Illuminate\Support\Facades\Route;

uses(Tests\TestCase::class);

beforeEach(function () {
    test()->disableBaseSeeding();
});

test('all 15 legacy hub routes have 301 redirects configured', function () {
    $expectedRedirects = [
        'admin/sales-hub' => 'admin/sale-orders',
        'admin/purchase-hub' => 'admin/purchase-orders',
        'admin/delivery-hub' => 'admin/delivery-orders',
        'admin/accounting-hub' => 'admin/journal-entries',
        'admin/inventory-hub' => 'admin/inventory-stocks',
        'admin/master-data-hub' => 'admin/products',
        'admin/user-roles-management-hub' => 'admin/users',
        'admin/manufacturing-hub' => 'admin',
        'admin/finance-sales-hub' => 'admin/sales-invoices',
        'admin/finance-purchase-hub' => 'admin/purchase-invoices',
        'admin/payment-hub' => 'admin/customer-receipts',
        'admin/finance-reports' => 'admin/sales-report-page',
        'admin/asset-management-hub' => 'admin/assets',
        'admin/warehouse-hub' => 'admin/warehouse-confirmations',
        'admin/dashboard-hub' => 'admin',
    ];

    $routes = Route::getRoutes();

    foreach ($expectedRedirects as $uri => $target) {
        $route = $routes->getByAction('Illuminate\Routing\RedirectController');
        $matching = collect($routes->getRoutes())->first(function ($r) use ($uri) {
            return trim($r->uri(), '/') === trim($uri, '/');
        });

        expect($matching)->not->toBeNull("Route for {$uri} should exist");
        expect($matching->defaults['status'] ?? null)->toBe(301, "Redirect status for {$uri} should be 301");
        expect(trim($matching->defaults['destination'] ?? '', '/'))->toBe(trim($target, '/'));
    }
});
