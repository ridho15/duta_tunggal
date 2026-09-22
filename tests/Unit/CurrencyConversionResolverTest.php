<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Currency;
use App\Support\CurrencyConversionResolver;

class CurrencyConversionResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_convert_to_idr_and_back_high_precision()
    {
        // Create a USD currency with rate 15000
        $usd = Currency::create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'to_rupiah' => 15000,
        ]);

        $amount = '10'; // 10 USD

        $idr = CurrencyConversionResolver::convertToIdrHighPrecision($amount, $usd->id);
        $this->assertEquals('150000.00', $idr);

        $back = CurrencyConversionResolver::convertFromIdrHighPrecision($idr, $usd->id);
        // convertFromIdrHighPrecision returns a bcmath string with scale, assert approximate
        $this->assertEquals(10.0, round((float) $back, 6));
    }
}
