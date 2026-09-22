<?php
// Demo script: convert Rp 680,000,000 -> USD -> IDR with and without intermediate rounding
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Currency;
use App\Support\CurrencyConversionResolver;

$idrId = Currency::where('code', 'IDR')->value('id');
$usdId = Currency::where('code', 'USD')->value('id');

$amountIdr = 680000000; // Rp 680,000,000

echo "Currency IDs: IDR={$idrId}, USD={$usdId}\n";

// With intermediate rounding (current default behavior)
$toUsdRounded = CurrencyConversionResolver::convertFromIdr($amountIdr, $usdId); // rounded
$backToIdrRounded = CurrencyConversionResolver::convertToIdr((float) $toUsdRounded, $usdId);

// Without intermediate rounding
$toUsdHigh = CurrencyConversionResolver::convertFromIdr($amountIdr, $usdId, false); // string high-precision
$backToIdrHigh = CurrencyConversionResolver::convertToIdr((float) $toUsdHigh, $usdId, false);

echo "With intermediate rounding:\n";
echo "  IDR -> USD: {$toUsdRounded}\n";
echo "  USD -> IDR: {$backToIdrRounded}\n";
echo "  Loss: " . (int)($amountIdr - (float)$backToIdrRounded) . "\n\n";

echo "Without intermediate rounding (high-precision):\n";
echo "  IDR -> USD (high-precision): {$toUsdHigh}\n";
echo "  USD -> IDR (high-precision): {$backToIdrHigh}\n";
echo "  Loss: " . ((int)$amountIdr - (int) ((float)$backToIdrHigh)) . "\n";

return 0;
