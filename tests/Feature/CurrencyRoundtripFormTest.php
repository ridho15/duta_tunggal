<?php

namespace Tests\Feature;

use App\Helpers\MoneyHelper;
use App\Support\CurrencyConversionResolver;
use Database\Factories\CurrencyFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyRoundtripFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_idr_to_usd_and_back_roundtrip_preserves_display_value()
    {
        // Arrange: ensure currencies exist
        $idr = \App\Models\Currency::factory()->create(['code' => 'IDR', 'to_rupiah' => 1]);
        $usd = \App\Models\Currency::factory()->create(['code' => 'USD', 'to_rupiah' => 15000]);

        // Use value known to be sensitive to rounding
        $originalInput = '68.000.000'; // user input format
        $originalNumeric = MoneyHelper::safeParse($originalInput);

        // Act: convert IDR -> USD as UI would display
        // UI should keep a high-precision internal value (round=false) for back-conversion
        $usdAmountHighPrecision = CurrencyConversionResolver::convertFromIdr($originalNumeric, $usd->id, false);

        // Convert back USD -> IDR using the high-precision internal value
        $roundtrip = CurrencyConversionResolver::convertBetweenCurrencies((float)$usdAmountHighPrecision, $usd->id, $idr->id, true);

        // Assert: displayed rupiah should be identical
        $displayOriginal = MoneyHelper::rupiah($originalNumeric);
        $displayRoundtrip = MoneyHelper::rupiah($roundtrip);

        $this->assertEquals($displayOriginal, $displayRoundtrip, "Roundtrip display mismatch: $displayOriginal != $displayRoundtrip");
    }

    public function test_live_input_parsing_formatting_and_persisted_value_consistent()
    {
        $idr = \App\Models\Currency::factory()->create(['code' => 'IDR', 'to_rupiah' => 1]);
        $usd = \App\Models\Currency::factory()->create(['code' => 'USD', 'to_rupiah' => 15000]);

        // Simulate user typing formatted value
        $liveInput = '1.000.000';
        $parsed = MoneyHelper::safeParse($liveInput);

        // Convert to USD for display in alternate currency
        // UI keeps high-precision internal value when switching currencies
        $usdDisplayedHighPrecision = CurrencyConversionResolver::convertFromIdr($parsed, $usd->id, false);

        // User switches back to IDR using high-precision internal value
        $back = CurrencyConversionResolver::convertBetweenCurrencies((float)$usdDisplayedHighPrecision, $usd->id, $idr->id, true);

        // Persist (simulate saving to DB)
        $model = \App\Models\OrderRequestItem::factory()->create([
            'unit_price' => $back,
            'original_price' => $back,
        ]);

        $fresh = $model->fresh();

        $this->assertEquals(MoneyHelper::rupiah($parsed), MoneyHelper::rupiah($back));
        $this->assertEquals(MoneyHelper::rupiah($back), MoneyHelper::rupiah($fresh->unit_price));
    }
}
