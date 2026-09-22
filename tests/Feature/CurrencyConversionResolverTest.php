<?php

use App\Models\Currency;
use App\Support\CurrencyConversionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('currency conversion resolver converts amounts and formats symbols consistently', function () {
    $idr = Currency::create([
        'name' => 'Indonesian Rupiah',
        'symbol' => 'Rp',
        'code' => 'IDR',
        'to_rupiah' => 1,
    ]);

    $usd = Currency::create([
        'name' => 'US Dollar',
        'symbol' => '$',
        'code' => 'USD',
        'to_rupiah' => 16000,
    ]);

    expect(CurrencyConversionResolver::resolveSymbol($idr->id))->toBe('Rp');
    expect(CurrencyConversionResolver::resolveSymbol($usd->id))->toBe('$');
    expect(CurrencyConversionResolver::resolveRate($usd->id))->toBe(16000.0);
    expect(CurrencyConversionResolver::convertBetweenCurrencies(16000, $idr->id, $usd->id))->toBe(1.0);
    expect(CurrencyConversionResolver::convertBetweenCurrencies(1, $usd->id, $idr->id))->toBe(16000.0);
    expect(CurrencyConversionResolver::formatAmount($usd->id, 6250))->toBe('$ 6.250');
    expect(CurrencyConversionResolver::formatAmountFromIdr(16000, $usd->id))->toBe('$ 1');
});
