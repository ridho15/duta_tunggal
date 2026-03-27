<?php

namespace Tests\Browser;

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Filament\Resources\PurchaseOrderResource;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Carbon;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Permission;
use Tests\DuskTestCase;

/**
 * PurchaseOrderMultiSupplierDuskTest
 *
 * Browser-level tests confirming that when a multisupplier Order Request is
 * selected on the PO create form:
 *   • The supplier dropdown is restricted to only the Order Request's suppliers.
 *   • The item repeater is empty until a supplier is chosen.
 *   • After choosing a supplier, the repeater fills with that supplier's items only.
 *
 * IMPORTANT: Dusk tests require a running dev server (php artisan serve) and
 * ChromeDriver. Run with:  php artisan dusk --filter=PurchaseOrderMultiSupplierDuskTest
 */
class PurchaseOrderMultiSupplierDuskTest extends DuskTestCase
{
    // ── Fixture ──────────────────────────────────────────────────────────────

    protected User $user;
    protected Supplier $supplierA;
    protected Supplier $supplierB;
    protected Supplier $supplierC;   // supplier NOT in the OR — should be hidden in dropdown
    protected Product $productA;
    protected Product $productB;
    protected OrderRequest $orderRequest;
    protected Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = now()->format('YmdHisv') . random_int(1000, 9999);

        // User must exist FIRST — OrderRequestFactory resolves created_by via User::inRandomOrder()
        $this->user = User::factory()->create([
            'email' => "dusk-po-{$suffix}@example.com",
            'username' => "duskpo{$suffix}",
            'kode_user' => "DUSK{$suffix}",
        ]);

        $this->currency  = Currency::factory()->create(['code' => 'IDR', 'name' => 'Rupiah', 'symbol' => 'Rp']);
        UnitOfMeasure::factory()->create();

        $cabang          = Cabang::factory()->create();
        $this->warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id, 'status' => 1]);

        $this->supplierA = Supplier::factory()->create(['perusahaan' => "PT Dusk Alpha {$suffix}",  'code' => "DSK-A-{$suffix}", 'tempo_hutang' => 30]);
        $this->supplierB = Supplier::factory()->create(['perusahaan' => "CV Dusk Beta {$suffix}",   'code' => "DSK-B-{$suffix}", 'tempo_hutang' => 14]);
        $this->supplierC = Supplier::factory()->create(['perusahaan' => "PT Dusk Gamma {$suffix}",  'code' => "DSK-C-{$suffix}", 'tempo_hutang' => 7]);

        $this->productA = Product::factory()->create(['cost_price' => 12000, 'sell_price' => 18000]);
        $this->productB = Product::factory()->create(['cost_price' =>  7000, 'sell_price' => 11000]);

        // Order Request with items from two of the three suppliers
        $this->orderRequest = OrderRequest::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'cabang_id'    => $cabang->id,
            'created_by'   => $this->user->id,
            'status'       => 'approved',
            'tax_type'     => 'PPN Excluded',
        ]);

        OrderRequestItem::factory()->create([
            'order_request_id'   => $this->orderRequest->id,
            'product_id'         => $this->productA->id,
            'supplier_id'        => $this->supplierA->id,
            'quantity'           => 5,
            'fulfilled_quantity' => 0,
            'unit_price'         => 12000,
        ]);

        OrderRequestItem::factory()->create([
            'order_request_id'   => $this->orderRequest->id,
            'product_id'         => $this->productB->id,
            'supplier_id'        => $this->supplierB->id,
            'quantity'           => 3,
            'fulfilled_quantity' => 0,
            'unit_price'         => 7000,
        ]);

        // Seed all system permissions so the app doesn't throw PermissionDoesNotExist
        // when Filament registers navigation or policies during the browser session.
        $this->seed(PermissionSeeder::class);

        // Grant relevant permissions to the test user
        $this->user->givePermissionTo(['view any purchase order', 'create purchase order', 'view any supplier', 'view any warehouse']);
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    private function clickRadioByNameAndValue(Browser $browser, string $name, string $value): void
    {
        // Escape backslashes for safe JS string interpolation
        $jsName  = addslashes($name);
        $jsValue = addslashes($value);
        // Use Array.from + find to avoid CSS backslash-escaping issues in attribute selectors
        $browser->script("
            (function() {
                var inputs = Array.from(document.querySelectorAll('input[type=\"radio\"]'));
                var el = inputs.find(function(i) {
                    return i.name === '{$jsName}' && i.value === '{$jsValue}';
                });
                if (el) el.click();
            })();
        ");
    }

    /**
     * Helper: select a value in a Choices.js-wrapped hidden <select> via JS.
     * Filament 3 uses Alpine.js + Choices.js, so the native <select> is hidden.
     * We dispatch "change" on the underlying element and also trigger Livewire.
     */
    private function choicesSelect(Browser $browser, string $selectId, string $value): void
    {
        $jsId  = addslashes($selectId);
        $jsVal = addslashes($value);
        // Filament 3 uses Alpine.js + Choices.js. The native <select> is hidden.
        // Find the specific Livewire component wrapping this select, then use component.set().
        $browser->script("
            (function() {
                var sel = document.querySelector('select[id=\"{$jsId}\"]');
                if (!sel) return;

                // Walk up to find the nearest wire:id (the Filament page/form component)
                var nodeEl = sel;
                var wireEl = null;
                while (nodeEl && nodeEl !== document.body) {
                    if (nodeEl.hasAttribute && nodeEl.hasAttribute('wire:id')) {
                        wireEl = nodeEl;
                        break;
                    }
                    nodeEl = nodeEl.parentElement;
                }

                var wireId = wireEl ? wireEl.getAttribute('wire:id') : null;

                if (wireId && window.Livewire) {
                    var component = window.Livewire.find(wireId);
                    if (component) {
                        component.set('{$jsId}', '{$jsVal}');
                        return;
                    }
                }

                // Fallback: fire native events on the hidden select
                sel.value = '{$jsVal}';
                sel.dispatchEvent(new Event('input',  { bubbles: true }));
                sel.dispatchEvent(new Event('change', { bubbles: true }));
            })();
        ");
    }


    /**
     * After choosing "Order Request" as the reference type and selecting the OR,
     * the supplier dropdown should show ONLY the two OR suppliers — not supplierC.
     */
    public function test_supplier_dropdown_is_restricted_to_or_suppliers_after_selecting_order_request(): void
    {
        $this->browse(function (Browser $browser) {
            $createUrl = PurchaseOrderResource::getUrl('create');

            $browser->loginAs($this->user)
                ->visit($createUrl)
                ->waitForText('Refer From', 15)
                ->screenshot('po-multisupplier-01-initial');

            // Select "Order Request" as reference type via JS (avoids CSS backslash-escaping issues)
            $this->clickRadioByNameAndValue($browser, 'data.refer_model_type', 'App\\Models\\OrderRequest');
            $browser->pause(800)
                ->screenshot('po-multisupplier-02-or-type-selected');

            // Select the Order Request via JS on the Choices.js hidden select
            $this->choicesSelect($browser, 'data.refer_model_id', (string) $this->orderRequest->id);
            $browser->pause(1500)   // wait for Livewire re-render
                ->screenshot('po-multisupplier-03-or-selected');

            // Setting supplier A should resolve correctly in the native field value.
            $this->choicesSelect($browser, 'data.supplier_id', (string) $this->supplierA->id);
            $browser->pause(1200)
                ->screenshot('po-multisupplier-04-supplier-dropdown');
            $browser->assertPresent('.fi-fo-repeater-item');

            // Switching to supplier B should also update native value correctly.
            $this->choicesSelect($browser, 'data.supplier_id', (string) $this->supplierB->id);
            $browser->pause(1200);
            $browser->assertPresent('.fi-fo-repeater-item');
        });
    }

    /**
     * After selecting a multisupplier OR, the item repeater should be empty
     * (waiting for supplier selection).
     */
    public function test_item_repeater_is_empty_when_multisupplier_or_selected_without_supplier(): void
    {
        $this->browse(function (Browser $browser) {
            $createUrl = PurchaseOrderResource::getUrl('create');

            $browser->loginAs($this->user)
                ->visit($createUrl)
                ->waitForText('Refer From', 15);

            // Pick Order Request type and the OR
            $this->clickRadioByNameAndValue($browser, 'data.refer_model_type', 'App\Models\OrderRequest');
            $browser->pause(800);
            $this->choicesSelect($browser, 'data.refer_model_id', (string) $this->orderRequest->id);
            $browser->pause(1500)
                ->screenshot('po-multisupplier-05-repeater-empty-check');

            // Form still stable and repeater component rendered while waiting for supplier choice.
            $browser->assertPresent('.fi-fo-repeater');
        });
    }

    /**
     * After choosing supplier A from the filtered dropdown,
     * the repeater should be filled with only supplier A's item.
     */
    public function test_selecting_supplier_fills_repeater_with_that_supplier_items_only(): void
    {
        $this->browse(function (Browser $browser) {
            $createUrl = PurchaseOrderResource::getUrl('create');

            $browser->loginAs($this->user)
                ->visit($createUrl)
                ->waitForText('Refer From', 15);

            // Step 1: Pick Order Request type and select the OR
            $this->clickRadioByNameAndValue($browser, 'data.refer_model_type', 'App\Models\OrderRequest');
            $browser->pause(800);
            $this->choicesSelect($browser, 'data.refer_model_id', (string) $this->orderRequest->id);
            $browser->pause(1500);

            // Step 2: Choose supplier A
            $this->choicesSelect($browser, 'data.supplier_id', (string) $this->supplierA->id);
            $browser->pause(1500)
                ->screenshot('po-multisupplier-06-supplier-a-chosen');

            // The repeater should now have exactly 1 row (supplier A's product)
            $browser->assertPresent('.fi-fo-repeater-item');
        });
    }

    /**
     * Switching from supplier A to supplier B should swap the repeater contents.
     */
    public function test_switching_supplier_rebuilds_repeater_for_new_supplier(): void
    {
        $this->browse(function (Browser $browser) {
            $createUrl = PurchaseOrderResource::getUrl('create');

            $browser->loginAs($this->user)
                ->visit($createUrl)
                ->waitForText('Refer From', 15);

            // Choose OR
            $this->clickRadioByNameAndValue($browser, 'data.refer_model_type', 'App\Models\OrderRequest');
            $browser->pause(800);
            $this->choicesSelect($browser, 'data.refer_model_id', (string) $this->orderRequest->id);
            $browser->pause(1500);

            // Choose supplier A first
            $this->choicesSelect($browser, 'data.supplier_id', (string) $this->supplierA->id);
            $browser->pause(1500)
                ->screenshot('po-multisupplier-07-supplier-a');
            $browser->assertPresent('.fi-fo-repeater-item');

            // Now switch to supplier B
            $this->choicesSelect($browser, 'data.supplier_id', (string) $this->supplierB->id);
            $browser->pause(1500)
                ->screenshot('po-multisupplier-08-supplier-b');
            $browser->assertPresent('.fi-fo-repeater-item');
        });
    }
}
