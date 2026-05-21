<?php

use App\Support\JournalCurrencyAmountResolver;

class JournalCurrencyAmountResolverTest extends \PHPUnit\Framework\TestCase
{
    public function test_resolve_preserves_high_precision_amounts_for_qc_valuation(): void
    {
        $resolved = JournalCurrencyAmountResolver::resolve('5.1251', null, 16000);

        $this->assertSame(5.1251, $resolved['amount_original_currency']);
        $this->assertSame(16000.0, $resolved['exchange_rate']);
        $this->assertSame(82001.6, $resolved['amount_idr']);
    }

    public function test_resolve_rounds_idr_to_two_decimals_only_at_final_amount(): void
    {
        $resolved = JournalCurrencyAmountResolver::resolve('5.12519', null, 16000);

        $this->assertSame(5.1252, $resolved['amount_original_currency']);
        $this->assertSame(82003.04, $resolved['amount_idr']);
    }
}