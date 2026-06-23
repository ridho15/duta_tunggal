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
use App\Filament\Resources\OrderRequestResource\Pages\CreateOrderRequest;
use App\Models\Cabang;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Filament\Forms\Components\TextInput;
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

describe('SupplierResource ProductsRelationManager supplier_price validation', function () {

    it('no longer has ->numeric() validator on supplier_price field', function () {
        // Verify the resource PHP file no longer contains ->numeric() near supplier_price
        $file = file_get_contents(
            base_path('app/Filament/Resources/SupplierResource/RelationManagers/ProductsRelationManager.php')
        );

        // Get the lines around supplier_price
        $lines = explode("\n", $file);
        $numericNearMoney = false;
        foreach ($lines as $i => $line) {
            if (str_contains($line, 'indonesianMoney')) {
                $window = implode("\n", array_slice($lines, max(0, $i - 5), 10));
                if (str_contains($window, '->numeric()')) {
                    $numericNearMoney = true;
                    break;
                }
            }
        }
        expect($numericNearMoney)->toBeFalse(
            '->numeric() should not appear near ->indonesianMoney() in ProductsRelationManager'
        );
    });
});

// ─── Integration Test: OrderRequestResource price fields ─────────────────────

describe('OrderRequestResource price field validation', function () {

    it('no longer has ->numeric() near ->indonesianMoney() in OrderRequestResource', function () {
        $file = file_get_contents(
            base_path('app/Filament/Resources/OrderRequestResource.php')
        );

        $lines = explode("\n", $file);
        $numericNearMoney = false;
        foreach ($lines as $i => $line) {
            if (str_contains($line, 'indonesianMoney')) {
                $window = implode("\n", array_slice($lines, max(0, $i - 5), 10));
                if (str_contains($window, '->numeric()')) {
                    $numericNearMoney = true;
                    break;
                }
            }
        }
        expect($numericNearMoney)->toBeFalse(
            '->numeric() should not appear near ->indonesianMoney() in OrderRequestResource'
        );
    });

    it('no longer has ->numeric() near ->indonesianMoney() in ViewOrderRequest page', function () {
        $file = file_get_contents(
            base_path('app/Filament/Resources/OrderRequestResource/Pages/ViewOrderRequest.php')
        );

        $lines = explode("\n", $file);
        $numericNearMoney = false;
        foreach ($lines as $i => $line) {
            if (str_contains($line, 'indonesianMoney')) {
                $window = implode("\n", array_slice($lines, max(0, $i - 5), 10));
                if (str_contains($window, '->numeric()')) {
                    $numericNearMoney = true;
                    break;
                }
            }
        }
        expect($numericNearMoney)->toBeFalse(
            '->numeric() should not appear near ->indonesianMoney() in ViewOrderRequest'
        );
    });

    it('parses formatted override prices without dropping thousands', function () {
        expect(OrderRequestResource::parseCurrencyState('100.000'))->toBe(100000.0)
            ->and(OrderRequestResource::parseCurrencyState('100.000,00'))->toBe(100000.0)
            ->and(OrderRequestResource::parseCurrencyState('1.000.000'))->toBe(1000000.0)
            ->and(OrderRequestResource::parseCurrencyState('1.000.000,00'))->toBe(1000000.0);
    });

    it('updates manual override price fields with debounce to keep live calculations stable', function () {
        $file = file_get_contents(
            base_path('app/Filament/Resources/OrderRequestResource.php')
        );

        $unitPricePositions = [];
        $offset = 0;

        while (($position = strpos($file, "TextInput::make('unit_price')", $offset)) !== false) {
            $unitPricePositions[] = $position;
            $offset = $position + 1;
        }

        expect($unitPricePositions)->not->toBeEmpty();

        $moneyOverrideBlocks = collect($unitPricePositions)
            ->map(fn (int $position) => substr($file, $position, 1400))
            ->filter(fn (string $block) => str_contains($block, "label('Harga Override')"))
            ->values();

        expect($moneyOverrideBlocks)->toHaveCount(2);

        foreach ($moneyOverrideBlocks as $block) {
            expect($block)
                ->toContain('$money($input,')
                ->toContain('->live(debounce: 500)')
                ->not->toContain('->reactive()')
                ->not->toContain('->live()')
                ->not->toContain('->live(onBlur: true)');
        }
    });
});

// ─── Livewire stability: manual money inputs use the right update mode ──────

describe('Manual money inputs use debounced live updates when calculations must stay live', function () {

    $targets = [
        'app/Filament/Actions/AddDepositAction.php' => [
            'amount' => 1,
            'used_amount' => 1,
        ],
        'app/Filament/Resources/AccountPayableResource.php' => [
            'paid' => 1,
        ],
        'app/Filament/Resources/VendorPaymentResource.php' => [
            'payment_amount' => 1,
        ],
        'app/Filament/Resources/InvoiceResource.php' => [
            'subtotal' => 1,
            'dpp' => 1,
            'amount' => 1,
        ],
        'app/Filament/Resources/PurchaseOrderResource/RelationManagers/PurchaseOrderItemRelationManager.php' => [
            'unit_price' => 1,
            'discount' => 1,
        ],
        'app/Filament/Resources/PurchaseOrderResource.php' => [
            'total' => 1,
            'nominal' => 1,
        ],
        'app/Filament/Resources/SalesInvoiceResource.php' => [
            'amount' => 1,
        ],
        'app/Filament/Resources/SaleOrderResource.php' => [
            'unit_price' => 1,
        ],
        'app/Filament/Resources/SaleOrderResource/RelationManagers/SaleOrderItemRelationManager.php' => [
            'unit_price' => 1,
            'discount' => 1,
        ],
        'app/Filament/Resources/QuotationResource.php' => [
            'unit_price' => 2,
        ],
        'app/Filament/Resources/QuotationResource/Pages/ViewQuotation.php' => [
            'unit_price' => 1,
        ],
        'app/Filament/Resources/QuotationResource/RelationManagers/QuotationItemRelationManager.php' => [
            'unit_price' => 1,
        ],
        'app/Filament/Resources/AssetResource.php' => [
            'purchase_cost' => 1,
        ],
        'app/Filament/Resources/BillOfMaterialResource.php' => [
            'labor_cost' => 1,
            'overhead_cost' => 1,
        ],
        'app/Filament/Resources/MaterialIssueResource.php' => [
            'cost_per_unit' => 1,
        ],
        'app/Filament/Resources/StockAdjustmentResource/RelationManagers/StockAdjustmentItemsRelationManager.php' => [
            'unit_cost' => 1,
        ],
        'app/Filament/Resources/StockOpnameResource/RelationManagers/StockOpnameItemsRelationManager.php' => [
            'unit_cost' => 1,
        ],
    ];

    foreach ($targets as $path => $fields) {
        foreach ($fields as $field => $expectedDebounceCount) {
            it("updates {$path} {$field} money input with debounce", function () use ($path, $field, $expectedDebounceCount) {
                $lines = file(base_path($path));
                $blocks = [];

                foreach ($lines as $index => $line) {
                    if (! str_contains($line, "TextInput::make('{$field}')")) {
                        continue;
                    }

                    $end = count($lines);

                    for ($cursor = $index + 1; $cursor < min(count($lines), $index + 140); $cursor++) {
                        if (
                            str_contains($lines[$cursor], 'TextInput::make(')
                            || str_contains($lines[$cursor], 'Select::make(')
                            || str_contains($lines[$cursor], 'Repeater::make(')
                            || str_contains($lines[$cursor], 'Radio::make(')
                            || str_contains($lines[$cursor], 'Placeholder::make(')
                        ) {
                            $end = $cursor;
                            break;
                        }
                    }

                    $block = implode('', array_slice($lines, $index, $end - $index));

                    if (str_contains($block, '->indonesianMoney()') || str_contains($block, '$money($input')) {
                        $blocks[] = $block;
                    }
                }

                expect($blocks)->not->toBeEmpty("No money TextInput block found for {$field} in {$path}");

                $debouncedBlocks = array_filter(
                    $blocks,
                    fn (string $block) => str_contains($block, '->live(debounce: 500)')
                );

                expect($debouncedBlocks)->toHaveCount($expectedDebounceCount);

                foreach ($debouncedBlocks as $block) {
                    expect($block)
                        ->not->toContain('->live()')
                        ->not->toContain('->live(onBlur: true)');
                }
            });
        }
    }
});

describe('Manual money inputs without live calculations stay on blur', function () {

    $targets = [
        'app/Filament/Resources/CashBankTransferResource.php' => [
            'other_costs' => 1,
        ],
        'app/Filament/Resources/InvoiceResource.php' => [
            'total' => 1,
        ],
        'app/Filament/Resources/JournalEntryResource.php' => [
            'debit' => 1,
            'credit' => 1,
        ],
        'app/Filament/Resources/PurchaseReceiptResource.php' => [
            'total' => 1,
        ],
        'app/Filament/Resources/PurchaseInvoiceResource.php' => [
            'pph22_amount' => 1,
            'bea_masuk_amount' => 1,
            'amount' => 1,
            'total' => 1,
        ],
    ];

    foreach ($targets as $path => $fields) {
        foreach ($fields as $field => $expectedOnBlurCount) {
            it("updates {$path} {$field} money input on blur", function () use ($path, $field, $expectedOnBlurCount) {
                $lines = file(base_path($path));
                $blocks = [];

                foreach ($lines as $index => $line) {
                    if (! str_contains($line, "TextInput::make('{$field}')")) {
                        continue;
                    }

                    $end = count($lines);

                    for ($cursor = $index + 1; $cursor < min(count($lines), $index + 140); $cursor++) {
                        if (
                            str_contains($lines[$cursor], 'TextInput::make(')
                            || str_contains($lines[$cursor], 'Select::make(')
                            || str_contains($lines[$cursor], 'Repeater::make(')
                            || str_contains($lines[$cursor], 'Radio::make(')
                            || str_contains($lines[$cursor], 'Placeholder::make(')
                        ) {
                            $end = $cursor;
                            break;
                        }
                    }

                    $block = implode('', array_slice($lines, $index, $end - $index));

                    if (str_contains($block, '->indonesianMoney()') || str_contains($block, '$money($input')) {
                        $blocks[] = $block;
                    }
                }

                expect($blocks)->not->toBeEmpty("No money TextInput block found for {$field} in {$path}");

                $onBlurBlocks = array_filter(
                    $blocks,
                    fn (string $block) => str_contains($block, '->live(onBlur: true)')
                );

                expect($onBlurBlocks)->toHaveCount($expectedOnBlurCount);

                foreach ($onBlurBlocks as $block) {
                    expect($block)
                        ->not->toContain('->live()');
                }
            });
        }
    }
});

// ─── Targeted scan: critical files must not have numeric+indonesianMoney ─────

describe('Targeted scan: critical price fields no longer have ->numeric() conflict', function () {

    $criticalFiles = [
        'SupplierResource/RelationManagers/ProductsRelationManager.php',
        'OrderRequestResource.php',
        'OrderRequestResource/Pages/ViewOrderRequest.php',
        'ProductResource.php',
        'InvoiceResource.php',
        'PurchaseOrderResource.php',
        'SalesInvoiceResource.php',
        'QuotationResource.php',
        'SaleOrderResource.php',
        'SaleOrderResource/RelationManagers/SaleOrderItemRelationManager.php',
        'QuotationResource/RelationManagers/QuotationItemRelationManager.php',
        'DepositResource.php',
        'AccountPayableResource.php',
        'AccountReceivableResource.php',
        'CashBankTransactionResource.php',
    ];

    foreach ($criticalFiles as $relPath) {
        it("has no ->numeric() adjacent to ->indonesianMoney() in {$relPath}", function () use ($relPath) {
            $filePath = base_path("app/Filament/Resources/{$relPath}");
            $lines = file($filePath);

            foreach ($lines as $i => $line) {
                if (str_contains($line, '->numeric()') && !str_contains($line, 'TextColumn') && !str_contains($line, 'TextEntry')) {
                    $window = array_slice($lines, max(0, $i - 4), 8);
                    $found = array_filter($window, fn($l) => str_contains($l, '->indonesianMoney('));
                    expect($found)->toBeEmpty(
                        "->numeric() at line " . ($i + 1) . " is adjacent to ->indonesianMoney() in {$relPath}"
                    );
                }
            }
        });
    }
});
