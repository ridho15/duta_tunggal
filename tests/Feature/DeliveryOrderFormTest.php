<?php

use App\Models\Cabang;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Models\Driver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('delivery order warehouse sources only use active warehouses in the user branch', function () {
    $cabang = Cabang::factory()->create();
    $otherCabang = Cabang::factory()->create();
    $user = User::create([
        'name' => 'Test User 2',
        'email' => 'test2@example.com',
        'username' => 'testuser2',
        'password' => bcrypt('password'),
        'first_name' => 'Test',
        'kode_user' => 'TU002',
        'cabang_id' => $cabang->id,
    ]);

    $activeWarehouse = Warehouse::factory()->create([
        'cabang_id' => $user->cabang_id,
        'status' => 1,
    ]);
    $inactiveWarehouse = Warehouse::factory()->create([
        'cabang_id' => $user->cabang_id,
        'status' => 0,
    ]);
    $otherBranchWarehouse = Warehouse::factory()->create([
        'cabang_id' => $otherCabang->id,
        'status' => 1,
    ]);

    $eligibleWarehouseIds = Warehouse::where('status', 1)
        ->where('cabang_id', $user->cabang_id)
        ->pluck('id')
        ->all();

    expect($eligibleWarehouseIds)
        ->toContain($activeWarehouse->id)
        ->not->toContain($inactiveWarehouse->id)
        ->not->toContain($otherBranchWarehouse->id);
});
