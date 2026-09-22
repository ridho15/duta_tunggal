<?php

namespace Tests;

use App\Models\Cabang;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected static bool $seedBaseData = true;

    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $this->guardAgainstUnsafeTestingDatabase();

        return $app;
    }

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

    protected function guardAgainstUnsafeTestingDatabase(): void
    {
        if (! app()->runningUnitTests() && ! app()->environment('testing')) {
            return;
        }

        $database = (string) DB::connection()->getDatabaseName();

        if ($database !== '' && str_ends_with($database, '_test')) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Unsafe testing database [%s]. Automated tests must use a dedicated *_test database. Run php artisan config:clear and configure DB_DATABASE=duta_tunggal_test before running tests.',
            $database !== '' ? $database : '(empty)'
        ));
    }

    protected function tearDown(): void
    {
        static::$seedBaseData = true;
        parent::tearDown();
    }
}
