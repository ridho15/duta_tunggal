<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('calculates total amount correctly with items and biayas', function () {
    // Mock livewire data structure
    $livewire = new class {
        public $data = [];
    };

    // Setup test data
    $livewire->data['purchaseOrderItem'] = [
        ['quantity' => 10, 'unit_price' => '100000', 'discount' => 5, 'tax' => 12, 'tipe_pajak' => 'Eksklusif'],
        ['quantity' => 5, 'unit_price' => '200000', 'discount' => 0, 'tax' => 12, 'tipe_pajak' => 'Inklusif']
    ];

    $livewire->data['purchaseOrderBiaya'] = [
        ['currency_id' => 7, 'total' => 50000],
        ['currency_id' => 8, 'total' => 100]
    ];

    $livewire->data['purchaseOrderCurrency'] = [
        ['currency_id' => 7, 'nominal' => 1],  // IDR
        ['currency_id' => 8, 'nominal' => 15000]  // USD to IDR rate
    ];

    // Execute the calculation logic
    $items = $livewire->data['purchaseOrderItem'] ?? [];
    $total = 0;
    foreach ($items as $item) {
        $total += \App\Http\Controllers\HelperController::hitungSubtotal(
            $item['quantity'] ?? 0,
            \App\Http\Controllers\HelperController::parseIndonesianMoney($item['unit_price'] ?? 0),
            $item['discount'] ?? 0,
            $item['tax'] ?? 0,
            $item['tipe_pajak'] ?? null
        );
    }

    $biayas = $livewire->data['purchaseOrderBiaya'] ?? [];
    $currencies = $livewire->data['purchaseOrderCurrency'] ?? [];
    foreach ($biayas as $biaya) {
        $nominal = 1.0;
        if (isset($biaya['currency_id'])) {
            foreach ($currencies as $c) {
                if (($c['currency_id'] ?? null) == $biaya['currency_id']) {
                    $nominal = (float)($c['nominal'] ?? $nominal);
                    break;
                }
            }
        }
        $total += ((float)$biaya['total'] ?? 0) * $nominal;
    }

    $livewire->data['total_amount'] = $total;

    // Expected calculation:
    // Item 1: 10 * 100000 = 1,000,000; discount 5% = 50,000; after discount = 950,000; tax 12% = 114,000; total = 1,064,000
    // Item 2: 5 * 200000 = 1,000,000; inclusive tax 12% = total remains 1,000,000
    // Biaya 1: 50,000 * 1 = 50,000
    // Biaya 2: 100 * 15000 = 1,500,000
    // Grand total: 1,064,000 + 1,000,000 + 50,000 + 1,500,000 = 3,614,000

    expect($livewire->data['total_amount'])->toBe(3614000.0);
});

test('handles decimal values in biaya correctly', function () {
    $livewire = new class {
        public $data = [];
    };

    $livewire->data['purchaseOrderItem'] = [];
    $livewire->data['purchaseOrderBiaya'] = [
        ['currency_id' => 7, 'total' => 50000.50],
        ['currency_id' => 8, 'total' => 100.75]
    ];
    $livewire->data['purchaseOrderCurrency'] = [
        ['currency_id' => 7, 'nominal' => 1],
        ['currency_id' => 8, 'nominal' => 15000]
    ];

    // Execute calculation
    $items = $livewire->data['purchaseOrderItem'] ?? [];
    $total = 0;
    foreach ($items as $item) {
        $total += \App\Http\Controllers\HelperController::hitungSubtotal(
            $item['quantity'] ?? 0,
            \App\Http\Controllers\HelperController::parseIndonesianMoney($item['unit_price'] ?? 0),
            $item['discount'] ?? 0,
            $item['tax'] ?? 0,
            $item['tipe_pajak'] ?? null
        );
    }

    $biayas = $livewire->data['purchaseOrderBiaya'] ?? [];
    $currencies = $livewire->data['purchaseOrderCurrency'] ?? [];
    foreach ($biayas as $biaya) {
        $nominal = 1.0;
        if (isset($biaya['currency_id'])) {
            foreach ($currencies as $c) {
                if (($c['currency_id'] ?? null) == $biaya['currency_id']) {
                    $nominal = (float)($c['nominal'] ?? $nominal);
                    break;
                }
            }
        }
        $total += ((float)$biaya['total'] ?? 0) * $nominal;
    }

    $livewire->data['total_amount'] = $total;

    expect($livewire->data['total_amount'])->toBe(1561250.5); // 50000.50 * 1 + 100.75 * 15000
});

test('handles missing currency gracefully', function () {
    $livewire = new class {
        public $data = [];
    };

    $livewire->data['purchaseOrderItem'] = [];
    $livewire->data['purchaseOrderBiaya'] = [
        ['currency_id' => 999, 'total' => 1000]  // Non-existent currency
    ];
    $livewire->data['purchaseOrderCurrency'] = [
        ['currency_id' => 7, 'nominal' => 1]
    ];

    // Execute calculation
    $items = $livewire->data['purchaseOrderItem'] ?? [];
    $total = 0;
    foreach ($items as $item) {
        $total += \App\Http\Controllers\HelperController::hitungSubtotal(
            $item['quantity'] ?? 0,
            \App\Http\Controllers\HelperController::parseIndonesianMoney($item['unit_price'] ?? 0),
            $item['discount'] ?? 0,
            $item['tax'] ?? 0,
            $item['tipe_pajak'] ?? null
        );
    }

    $biayas = $livewire->data['purchaseOrderBiaya'] ?? [];
    $currencies = $livewire->data['purchaseOrderCurrency'] ?? [];
    foreach ($biayas as $biaya) {
        $nominal = 1.0;
        if (isset($biaya['currency_id'])) {
            foreach ($currencies as $c) {
                if (($c['currency_id'] ?? null) == $biaya['currency_id']) {
                    $nominal = (float)($c['nominal'] ?? $nominal);
                    break;
                }
            }
        }
        $total += ((float)$biaya['total'] ?? 0) * $nominal;
    }

    $livewire->data['total_amount'] = $total;

    expect($livewire->data['total_amount'])->toBe(1000.0); // Uses default nominal 1.0
});

test('handles empty data gracefully', function () {
    $livewire = new class {
        public $data = [];
    };

    $livewire->data['purchaseOrderItem'] = [];
    $livewire->data['purchaseOrderBiaya'] = [];
    $livewire->data['purchaseOrderCurrency'] = [];

    // Execute calculation
    $items = $livewire->data['purchaseOrderItem'] ?? [];
    $total = 0;
    foreach ($items as $item) {
        $total += \App\Http\Controllers\HelperController::hitungSubtotal(
            $item['quantity'] ?? 0,
            \App\Http\Controllers\HelperController::parseIndonesianMoney($item['unit_price'] ?? 0),
            $item['discount'] ?? 0,
            $item['tax'] ?? 0,
            $item['tipe_pajak'] ?? null
        );
    }

    $biayas = $livewire->data['purchaseOrderBiaya'] ?? [];
    $currencies = $livewire->data['purchaseOrderCurrency'] ?? [];
    foreach ($biayas as $biaya) {
        $nominal = 1.0;
        if (isset($biaya['currency_id'])) {
            foreach ($currencies as $c) {
                if (($c['currency_id'] ?? null) == $biaya['currency_id']) {
                    $nominal = (float)($c['nominal'] ?? $nominal);
                    break;
                }
            }
        }
        $total += ((float)$biaya['total'] ?? 0) * $nominal;
    }

    $livewire->data['total_amount'] = $total;

    expect($livewire->data['total_amount'])->toBe(0);
});

test('calculates total with given subtotal and biaya values', function () {
    $livewire = new class {
        public $data = [];
    };

    // Setup test data with subtotal 779440 and biaya 100000 with nominal 16000
    $livewire->data['purchaseOrderItem'] = [
        ['quantity' => 1, 'unit_price' => '779440', 'discount' => 0, 'tax' => 0, 'tipe_pajak' => 'Non Pajak']
    ];

    $livewire->data['purchaseOrderBiaya'] = [
        ['currency_id' => 8, 'total' => 100000]
    ];

    $livewire->data['purchaseOrderCurrency'] = [
        ['currency_id' => 8, 'nominal' => 16000]
    ];

    // Execute the calculation logic
    $items = $livewire->data['purchaseOrderItem'] ?? [];
    $total = 0;
    foreach ($items as $item) {
        $total += \App\Http\Controllers\HelperController::hitungSubtotal(
            $item['quantity'] ?? 0,
            \App\Http\Controllers\HelperController::parseIndonesianMoney($item['unit_price'] ?? 0),
            $item['discount'] ?? 0,
            $item['tax'] ?? 0,
            $item['tipe_pajak'] ?? null
        );
    }

    $biayas = $livewire->data['purchaseOrderBiaya'] ?? [];
    $currencies = $livewire->data['purchaseOrderCurrency'] ?? [];
    foreach ($biayas as $biaya) {
        $nominal = 1.0;
        if (isset($biaya['currency_id'])) {
            foreach ($currencies as $c) {
                if (($c['currency_id'] ?? null) == $biaya['currency_id']) {
                    $nominal = (float)($c['nominal'] ?? $nominal);
                    break;
                }
            }
        }
        $total += ((float)$biaya['total'] ?? 0) * $nominal;
    }

    $livewire->data['total_amount'] = $total;

    // Expected: 779440 + (100000 * 16000) = 779440 + 1,600,000,000 = 1,600,779,440
    expect($livewire->data['total_amount'])->toBe(1600779440.0);
});
