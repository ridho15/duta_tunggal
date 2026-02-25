<?php

namespace Tests;

use App\Models\Cabang;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

abstract class TestCase extends BaseTestCase
{
    protected static bool $seedBaseData = true;

    public static function disableBaseSeeding(): void
    {
        static::$seedBaseData = false;
    }

    public static function enableBaseSeeding(): void
    {
        static::$seedBaseData = true;
    }

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->shouldSeedBaseData()) {
            // Create default Cabang if not exists
            if (!Cabang::exists()) {
                Cabang::create([
                    'kode' => 'CB-001',
                    'nama' => 'Cabang Utama',
                    'alamat' => 'Jl. Test No. 1',
                    'telepon' => '021-123456',
                ]);
            }
            
            $this->seed(PermissionSeeder::class);
            $this->seed(RoleSeeder::class);
        }
    }

    protected function shouldSeedBaseData(): bool
    {
        return static::$seedBaseData;
    }

    protected function tearDown(): void
    {
        static::$seedBaseData = true;
        parent::tearDown();
    }
}
