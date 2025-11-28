<?php

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Services\DeliveryOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('validates journal balance', function () {
    $service = app(DeliveryOrderService::class);

    $balancedEntries = [
        ['debit' => 100, 'credit' => 0],
        ['debit' => 0, 'credit' => 100]
    ];

    $unbalancedEntries = [
        ['debit' => 100, 'credit' => 0],
        ['debit' => 0, 'credit' => 50]
    ];

    expect($service->validateJournalBalance($balancedEntries))->toBeTrue();
    expect($service->validateJournalBalance($unbalancedEntries))->toBeFalse();
});