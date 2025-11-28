<?php

namespace Tests\Unit;

use App\Http\Controllers\HelperController;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PermissionsConsistencyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function helper_controller_permissions_match_database_permissions()
    {
        // Seed permissions from PermissionSeeder behavior
        foreach (HelperController::listPermission() as $resource => $actions) {
            foreach ($actions as $action) {
                $name = trim($action . ' ' . $resource);
                Permission::updateOrCreate([
                    'name' => $name,
                ], [
                    'name' => $name,
                    'guard_name' => 'web',
                ]);
            }
        }

        $defined = collect(HelperController::listPermission())->flatMap(function ($actions, $resource) {
            return collect($actions)->map(fn($a) => trim($a . ' ' . $resource));
        })->unique()->sort()->values()->all();

        $inDb = Permission::pluck('name')->unique()->sort()->values()->all();

        // If there are differences, fail and show them
        $missingInDb = array_diff($defined, $inDb);
        $extraInDb = array_diff($inDb, $defined);

        $this->assertEmpty($missingInDb, 'Permissions defined in HelperController::listPermission() missing from DB: ' . implode(', ', $missingInDb));
        $this->assertEmpty($extraInDb, 'Permissions present in DB but not defined in HelperController::listPermission(): ' . implode(', ', $extraInDb));

        $this->assertEquals($defined, $inDb);
    }
}
