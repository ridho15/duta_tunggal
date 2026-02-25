<?php

namespace Tests\Unit\Services;

use App\Models\Rak;
use App\Models\Warehouse;
use App\Services\RakService;
use Tests\TestCase;

class RakServiceTest extends TestCase
{
    protected RakService $rakService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rakService = new RakService();
    }

    public function test_generate_kode_rak_without_warehouse()
    {
        // generated code should start with RAK- and include today's date
        $code = $this->rakService->generateKodeRak();

        $this->assertStringStartsWith('RAK-', $code);
        // pattern RAK-YYYYMMDD-XXX (3 digit suffix)
        $this->assertMatchesRegularExpression('#^RAK-\d{8}-\d{3}$#', $code);
    }

    public function test_generate_kode_rak_logic()
    {
        // The service now uses a random suffix, so we only verify it returns a
        // string conforming to the expected format and is unique when stored.
        $first = $this->rakService->generateKodeRak();
        $second = $this->rakService->generateKodeRak();
        $this->assertNotEquals($first, $second);
        $this->assertMatchesRegularExpression('#^RAK-\d{8}-\d{3}$#', $first);
        $this->assertMatchesRegularExpression('#^RAK-\d{8}-\d{3}$#', $second);
    }
}