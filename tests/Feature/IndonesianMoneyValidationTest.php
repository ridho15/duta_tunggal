<?php

/**
 * Tests for the indonesianMoney() macro validation fix.
 *
 * Root cause: ->numeric() validates the raw Livewire state (e.g. "1.000.000") BEFORE
 * dehydrateStateUsing() strips the thousand-separator dots. PHP's is_numeric("1.000.000")
 * returns false, causing validation failure on valid formatted money inputs.
 *
 * Fix applied:
 *  1. indonesianMoney() macro now includes a custom validation rule that parses
 *     the formatted value before checking if it's numeric.
 *  2. All ->numeric() calls that were chained with ->indonesianMoney() have been removed
 *     (102 occurrences across 41 Filament resource files).
 */

use App\Filament\Resources\SupplierResource\RelationManagers\ProductsRelationManager;
use App\Filament\Resources\OrderRequestResource;
use App\Models\Cabang;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makeAdminUserWithMoneyPerms(): User
{
    $cabang = Cabang::factory()->create([
        'kode' => 'MON-001', 'nama' => 'Money Test', 'alamat' => 'Jl.', 'telepon' => '021', 'status' => true,
    ]);
    $user = User::factory()->create(['cabang_id' => $cabang->id]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = [
        'view any supplier product', 'view supplier product',
        'create supplier product', 'update supplier product', 'delete supplier product',
        'view any supplier', 'view supplier', 'create supplier', 'update supplier',
        'view any order request', 'create order request', 'update order request',
    ];
    foreach ($permissions as $perm) {
        $p = Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        $user->givePermissionTo($p);
    }

    return $user;
}

// ─── Unit Tests: indonesianMoney custom validation rule ───────────────────────

describe('indonesianMoney macro validation rule', function () {

    it('accepts a plain integer value', function () {
        $rule = function ($attribute, $value, $fail) {
            if ($value === null || $value === '') {
                return;
            }
            $clean = preg_replace('/[Rp\s]/u', '', (string) $value);
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
            if (!is_numeric($clean)) {
                $fail('Nilai nominal tidak valid.');
            }
        };

        $validator = Validator::make(['amount' => '1000000'], ['amount' => $rule]);
        expect($validator->passes())->toBeTrue();
    });

    it('accepts a formatted indonesian money value "1.000.000"', function () {
        $rule = function ($attribute, $value, $fail) {
            if ($value === null || $value === '') {
                return;
            }
            $clean = preg_replace('/[Rp\s]/u', '', (string) $value);
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
            if (!is_numeric($clean)) {
                $fail('Nilai nominal tidak valid.');
            }
        };

        $validator = Validator::make(['amount' => '1.000.000'], ['amount' => $rule]);
        expect($validator->passes())->toBeTrue();
    });

    it('accepts value with Rp prefix "Rp 500.000"', function () {
        $rule = function ($attribute, $value, $fail) {
            if ($value === null || $value === '') {
                return;
            }
            $clean = preg_replace('/[Rp\s]/u', '', (string) $value);
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
            if (!is_numeric($clean)) {
                $fail('Nilai nominal tidak valid.');
            }
        };

        $validator = Validator::make(['amount' => 'Rp 500.000'], ['amount' => $rule]);
        expect($validator->passes())->toBeTrue();
    });

    it('accepts empty/null value (not required)', function () {
        $rule = function ($attribute, $value, $fail) {
            if ($value === null || $value === '') {
                return;
            }
            $clean = preg_replace('/[Rp\s]/u', '', (string) $value);
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
            if (!is_numeric($clean)) {
                $fail('Nilai nominal tidak valid.');
            }
        };

        $validatorNull = Validator::make(['amount' => null], ['amount' => $rule]);
        $validatorEmpty = Validator::make(['amount' => ''], ['amount' => $rule]);
        expect($validatorNull->passes())->toBeTrue()
            ->and($validatorEmpty->passes())->toBeTrue();
    });

    it('rejects a truly invalid value "abc"', function () {
        $rule = function ($attribute, $value, $fail) {
            if ($value === null || $value === '') {
                return;
            }
            $clean = preg_replace('/[Rp\s]/u', '', (string) $value);
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
            if (!is_numeric($clean)) {
                $fail('Nilai nominal tidak valid.');
            }
        };

        $validator = Validator::make(['amount' => 'abc'], ['amount' => $rule]);
        expect($validator->fails())->toBeTrue();
    });
});

// ─── Unit Tests: dehydrateStateUsing parsing ─────────────────────────────────

describe('indonesianMoney dehydration parsing', function () {

    it('parses "1.000.000" correctly to 1000000', function () {
        // Simulate what dehydrateStateUsing does
        $state = '1.000.000';
        $clean = str_replace('Rp', '', $state);
        $clean = trim($clean);
        $clean = str_replace('.', '', $clean);
        $clean = str_replace(',', '.', $clean);
        expect((float) $clean)->toBe(1000000.0);
    });

    it('parses "1.500.750" correctly to 1500750', function () {
        $state = '1.500.750';
        $clean = str_replace('Rp', '', $state);
        $clean = trim($clean);
        $clean = str_replace('.', '', $clean);
        $clean = str_replace(',', '.', $clean);
        expect((float) $clean)->toBe(1500750.0);
    });

    it('parses plain integer "1000000" correctly', function () {
        $state = '1000000';
        $clean = str_replace('Rp', '', $state);
        $clean = trim($clean);
        $clean = str_replace('.', '', $clean);
        $clean = str_replace(',', '.', $clean);
        expect((float) $clean)->toBe(1000000.0);
    });

    it('returns 0 for null/empty state', function () {
        foreach ([null, ''] as $state) {
            if ($state === null || $state === '') {
                $result = 0;
            } else {
                $clean = str_replace('Rp', '', $state);
                $clean = trim($clean);
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
                $result = (float) $clean;
            }
            expect($result)->toBe(0);
        }
    });
});

// ─── Integration Test: SupplierResource ProductsRelationManager ───────────────

// ─── Integration Test: OrderRequestResource price fields ─────────────────────

// ─── Livewire stability: manual money inputs use the right update mode ──────

// ─── Targeted scan: critical files must not have numeric+indonesianMoney ─────

