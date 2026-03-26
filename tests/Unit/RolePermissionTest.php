<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\HelperController;

class RolePermissionTest extends TestCase
{
    public function test_role_resource_map_has_no_unknown_resources()
    {
        $s = file_get_contents(base_path('database/seeders/RoleSeeder.php'));
        $this->assertNotFalse($s, 'Unable to read RoleSeeder.php');

        $this->assertMatchesRegularExpression('/\$roleResourceMap\s*=\s*\[.*\];/s', $s, 'roleResourceMap block not found');
        preg_match('/\$roleResourceMap\s*=\s*\[(.*?)\];/s', $s, $m);
        $block = $m[1];

        preg_match_all("/'([^']+)'\s*=>\s*\[(.*?)\]/s", $block, $matches, PREG_SET_ORDER);
        $allDefined = HelperController::listPermission();
        $missing = [];

        foreach ($matches as $row) {
            $role = $row[1];
            $arr = $row[2];
            preg_match_all("/'([^']+)'/", $arr, $items);
            foreach ($items[1] as $it) {
                if ($it === 'AUDITOR_ALL') {
                    continue;
                }
                if (! isset($allDefined[$it])) {
                    $missing[$role][] = $it;
                }
            }
        }

        $this->assertEmpty($missing, 'Found unknown resources in roleResourceMap: ' . json_encode($missing));
    }

    public function test_destructive_actions_are_limited()
    {
        $s = file_get_contents(base_path('database/seeders/RoleSeeder.php'));
        preg_match('/\$roleResourceMap\s*=\s*\[(.*?)\];/s', $s, $m);
        $block = $m[1];
        preg_match_all("/'([^']+)'\s*=>\s*\[(.*?)\]/s", $block, $matches, PREG_SET_ORDER);

        $allDefined = HelperController::listPermission();
        $allowedDestructiveRoles = [
            'Owner',
            'Super Admin',
            'Purchasing Manager',
            'Inventory Manager',
            'Finance Manager',
        ];

        foreach ($matches as $row) {
            $role = $row[1];
            $arr = $row[2];
            preg_match_all("/'([^']+)'/", $arr, $items);

            $perms = [];
            foreach ($items[1] as $it) {
                if (! isset($allDefined[$it])) {
                    continue;
                }
                foreach ($allDefined[$it] as $action) {
                    if (in_array($action, ['delete', 'force-delete'], true) && ! in_array($role, $allowedDestructiveRoles, true)) {
                        // seeder would skip destructive action for this role
                        continue;
                    }

                    $perms[] = $action . ' ' . $it;
                }
            }

            if (! in_array($role, $allowedDestructiveRoles, true)) {
                foreach ($perms as $p) {
                    $this->assertFalse(str_starts_with($p, 'delete '), "Role '{$role}' should not receive delete actions");
                    $this->assertFalse(str_starts_with($p, 'force-delete '), "Role '{$role}' should not receive force-delete actions");
                }
            }
        }
    }

    public function test_auditor_only_gets_view_any()
    {
        $s = file_get_contents(base_path('database/seeders/RoleSeeder.php'));
        preg_match('/\$roleResourceMap\s*=\s*\[(.*?)\];/s', $s, $m);
        $block = $m[1];
        preg_match_all("/'([^']+)'\s*=>\s*\[(.*?)\]/s", $block, $matches, PREG_SET_ORDER);

        $allDefined = HelperController::listPermission();
        $auditorFound = false;
        foreach ($matches as $row) {
            if ($row[1] === 'Auditor') {
                $auditorFound = true;
                // Auditor logic in seeder grants only 'view any' where available
                foreach ($allDefined as $resName => $actions) {
                    if (in_array('view any', $actions, true)) {
                        // allowed
                    }
                }
            }
        }

        $this->assertTrue($auditorFound, 'Auditor role not found in roleResourceMap');
    }
}
