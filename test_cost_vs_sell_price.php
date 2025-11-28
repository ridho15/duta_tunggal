<?php

require_once 'vendor/autoload.php';
require_once 'bootstrap/app.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\JournalEntry;
use App\Services\DeliveryOrderService;

// Create test product with cost_price and sell_price
$product = Product::factory()->create([
    'cost_price' => 100.00,
    'sell_price' => 150.00,
    'pajak' => 10.0, // PPn 10%
]);

// Create delivery order
$do = DeliveryOrder::factory()->create();
$doItem = DeliveryOrderItem::factory()->create([
    'delivery_order_id' => $do->id,
    'product_id' => $product->id,
    'quantity' => 2,
]);

// Post delivery order
$service = app(DeliveryOrderService::class);
$result = $service->postDeliveryOrder($do);

if ($result['status'] === 'posted') {
    // Check journal entries
    $entries = JournalEntry::where('source_type', DeliveryOrder::class)
        ->where('source_id', $do->id)
        ->get();

    $totalDebit = $entries->sum('debit');
    $totalCredit = $entries->sum('credit');

    echo "✅ Delivery Order posted successfully\n";
    echo "📊 Total Debit: $totalDebit, Total Credit: $totalCredit\n";
    echo "💰 Cost Price used: " . ($totalDebit / 2) . " per unit (should be 100.00)\n";
    echo "📈 Sell Price: " . $product->sell_price . " (not used in journal)\n";
    echo "🧾 PPn: " . $product->pajak . "% (not used in journal)\n";

    // Verify cost_price is used, not sell_price
    if (abs($totalDebit - 200.0) < 0.01) {
        echo "✅ CORRECT: Journal uses cost_price (100.00 x 2 = 200.00)\n";
    } else {
        echo "❌ ERROR: Journal does not use cost_price\n";
    }

    // Verify sell_price is NOT used
    if (abs($totalDebit - 300.0) > 0.01) {
        echo "✅ CORRECT: Journal does NOT use sell_price\n";
    } else {
        echo "❌ ERROR: Journal incorrectly uses sell_price\n";
    }
} else {
    echo "❌ Failed to post delivery order\n";
}