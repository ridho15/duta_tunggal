<?php

test('konversi Rp 109.859 ke USD menghasilkan 7,32 untuk tampilan 2 desimal', function () {
    $priceInIdr = 109859;
    $rateUsdToIdr = 15000;

    $priceInUsd = $priceInIdr / $rateUsdToIdr;

    expect(number_format($priceInUsd, 2, ',', '.'))->toBe('7,32');
});

test('simulasi perubahan mata uang item mengonversi nilai menggunakan rate lama dan baru', function () {
    $oldCurrencyRateToIdr = 1.0;      // IDR
    $newCurrencyRateToIdr = 15000.0;  // USD

    $currentOriginalPrice = 109859.0;
    $currentUnitPrice = 109859.0;

    $convertedOriginalPrice = ($currentOriginalPrice * $oldCurrencyRateToIdr) / $newCurrencyRateToIdr;
    $convertedUnitPrice = ($currentUnitPrice * $oldCurrencyRateToIdr) / $newCurrencyRateToIdr;

    expect(number_format($convertedOriginalPrice, 2, ',', '.'))->toBe('7,32');
    expect(number_format($convertedUnitPrice, 2, ',', '.'))->toBe('7,32');
});

test('round-trip USD ke IDR menjaga nilai pokok dalam batas presisi desimal', function () {
    $priceInIdr = 109859.0;
    $rateUsdToIdr = 15000.0;

    $priceInUsd = $priceInIdr / $rateUsdToIdr;
    $restoredIdr = $priceInUsd * $rateUsdToIdr;

    expect(abs($restoredIdr - $priceInIdr))->toBeLessThan(0.00001);
});
