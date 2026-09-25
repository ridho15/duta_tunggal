<?php

use App\Models\Cabang;
use App\Models\Customer;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('runs legacy:remediate-data successfully in dry-run mode without modifying data', function () {
    test()->seed(\Database\Seeders\CurrencySeeder::class);
    test()->seed(\Database\Seeders\CabangSeeder::class);
    test()->seed(\Database\Seeders\CustomerSeeder::class);

    $customer = Customer::first();
    $cabang = Cabang::first();

    // Create a hanging test SO
    $so = SaleOrder::create([
        'so_number' => 'SO-00006',
        'customer_id' => $customer->id,
        'cabang_id' => $cabang->id,
        'order_date' => now()->toDateString(),
        'status' => 'draft',
        'subtotal' => 100000,
        'total_amount' => 111000,
    ]);

    $this->artisan('legacy:remediate-data --dry-run')
        ->assertSuccessful();

    // In dry-run, status remains draft
    expect($so->fresh()->status)->toBe('draft');
});

it('cancels hanging draft test SO in apply mode', function () {
    test()->seed(\Database\Seeders\CurrencySeeder::class);
    test()->seed(\Database\Seeders\CabangSeeder::class);
    test()->seed(\Database\Seeders\CustomerSeeder::class);

    $customer = Customer::first();
    $cabang = Cabang::first();

    // Create hanging test SOs
    $so6 = SaleOrder::create([
        'so_number' => 'SO-00006',
        'customer_id' => $customer->id,
        'cabang_id' => $cabang->id,
        'order_date' => now()->toDateString(),
        'status' => 'draft',
        'subtotal' => 100000,
        'total_amount' => 111000,
    ]);

    $so7 = SaleOrder::create([
        'so_number' => 'SO-00007',
        'customer_id' => $customer->id,
        'cabang_id' => $cabang->id,
        'order_date' => now()->toDateString(),
        'status' => 'draft',
        'subtotal' => 100000,
        'total_amount' => 111000,
    ]);

    $this->artisan('legacy:remediate-data')
        ->assertSuccessful();

    expect($so6->fresh()->status)->toBe('canceled')
        ->and($so7->fresh()->status)->toBe('canceled');
});
