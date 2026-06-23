<?php

namespace Tests;

use App\Models\Cabang;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

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

        if ($this->shouldSeedBaseData() && $this->canSeedBaseData()) {
            // Create default Cabang if not exists
            if (!Cabang::exists()) {
                Cabang::create([
                    'kode' => 'CB-001',
                    'nama' => 'Cabang Utama',
                    'alamat' => 'Jl. Test No. 1',
                    'telepon' => '021-123456',
                ]);
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $this->seed(PermissionSeeder::class);
            $this->seed(RoleSeeder::class);
        }
    }

    protected function canSeedBaseData(): bool
    {
        return Schema::hasTable('cabangs')
            && Schema::hasTable('permissions')
            && Schema::hasTable('roles');
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
