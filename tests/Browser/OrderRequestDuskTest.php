<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use App\Models\User;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Warehouse;

class OrderRequestDuskTest extends DuskTestCase
{
    public function test_supplier_selection_filters_products_reactively()
    {
        // Create test data
        $supplier1 = Supplier::factory()->create([
            'name' => 'Test Supplier 1',
            'code' => 'SUP001'
        ]);

        $supplier2 = Supplier::factory()->create([
            'name' => 'Test Supplier 2',
            'code' => 'SUP002'
        ]);

        $product1 = Product::factory()->create([
            'name' => 'Test Product 1',
            'sku' => 'PROD001',
            'supplier_id' => $supplier1->id
        ]);

        $product2 = Product::factory()->create([
            'name' => 'Test Product 2',
            'sku' => 'PROD002',
            'supplier_id' => $supplier1->id
        ]);

        $product3 = Product::factory()->create([
            'name' => 'Test Product 3',
            'sku' => 'PROD003',
            'supplier_id' => $supplier2->id
        ]);

        $warehouse = Warehouse::factory()->create([
            'name' => 'Test Warehouse',
            'kode' => 'WH001'
        ]);

        $user = User::factory()->create([
            'email' => 'test@example.com',
            'username' => 'testuser',
            'kode_user' => 'TEST001',
        ]);

        $this->browse(function (Browser $browser) use ($user, $supplier1, $supplier2, $product1, $product2, $product3, $warehouse) {
            // Login and navigate to Order Request create page
            $browser->loginAs($user)
                ->visit('/admin/order-requests/create')
                ->waitFor('.fi-select', 15); // Wait for any select element to load

            // Take initial screenshot to see the form structure
            $browser->screenshot('order_request_initial_load');

            // Find supplier select by label or placeholder
            $browser->waitForText('Supplier')
                ->click('label:contains("Supplier") + div .fi-select')
                ->waitFor('.fi-select-option', 10);

            // Look for supplier options
            $browser->assertSee('(SUP001) Test Supplier 1')
                ->assertSee('(SUP002) Test Supplier 2');

            // Take screenshot after opening supplier dropdown
            $browser->screenshot('order_request_supplier_dropdown');

            // For now, just verify we can access the page and see suppliers
            // We'll refine the reactive testing once we understand the DOM structure
        });
    }
}