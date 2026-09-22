<?php

use App\Services\QualityControlService;

class QualityControlServiceTest extends \PHPUnit\Framework\TestCase
{
    public function test_resolve_qc_journal_unit_price_idr_uses_stubbed_historical_rate(): void
    {
        $service = new QualityControlService();

        $resolved = $service->resolveQcJournalUnitPriceIdr('5.1251', null, 16000);

        $this->assertSame(5.1251, $resolved['amount_original_currency']);
        $this->assertSame(16000.0, $resolved['exchange_rate']);
        $this->assertSame(82001.6, $resolved['amount_idr']);
    }

    public function test_resolve_qc_journal_unit_price_idr_rounds_only_final_amount(): void
    {
        $service = new QualityControlService();

        $resolved = $service->resolveQcJournalUnitPriceIdr('5.12519', null, 16000);

        $this->assertSame(5.1252, $resolved['amount_original_currency']);
        $this->assertSame(82003.04, $resolved['amount_idr']);
    }
}