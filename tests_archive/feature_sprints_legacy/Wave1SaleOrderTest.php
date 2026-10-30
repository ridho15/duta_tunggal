<?php

namespace Tests\Feature\Sales;

use App\Models\Cabang;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\User;
use App\Services\ApprovalControlService;
use App\Services\DeliveryOrderService;
use App\Services\SalesOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Gelombang 1 laporan UAT 13 Okt 2026 (Sales Order): B1 ongkos kirim, B2 approve Kasir (harga sesuai batas), B3 limit kredit.
 */
class Wave1SaleOrderTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;
    private User $creator;
    private Customer $customer;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sales.controls.approval_rules' => true, 'sales.controls.credit_policy' => false]);

        Permission::findOrCreate('response sales order', 'web');
        foreach (['Kasir', 'Sales Manager', 'Owner'] as $role) {
            Role::findOrCreate($role, 'web')->givePermissionTo('response sales order');
        }

        $this->cabang = Cabang::factory()->create();
        $this->creator = User::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->customer = Customer::factory()->create(['cabang_id' => $this->cabang->id, 'tipe_pembayaran' => 'Bebas', 'kredit_limit' => 0]);
        // harga jual 100.000, batas 10% → lantai 90.000, modal 50.000
        $this->product = Product::factory()->create(['sell_price' => 100000, 'cost_price' => 50000, 'harga_batas' => 10]);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['cabang_id' => $this->cabang->id]);
        $user->assignRole($role);

        return $user;
    }

    private function saleOrder(float $unitPrice = 100000, float $qty = 10, float $discount = 0, array $attributes = []): SaleOrder
    {
        $so = SaleOrder::create(array_merge([
            'so_number' => 'SO-W1-' . uniqid(),
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'request_approve',
            'tipe_pengiriman' => 'Kirim Langsung',
            'created_by' => $this->creator->id,
            'total_amount' => $unitPrice * $qty * (1 - $discount / 100),
        ], $attributes));

        SaleOrderItem::create([
            'sale_order_id' => $so->id, 'product_id' => $this->product->id, 'quantity' => $qty,
            'unit_price' => $unitPrice, 'discount' => $discount, 'tax' => 0, 'tipe_pajak' => 'none',
        ]);

        return $so->fresh();
    }

    // ------------------------------------------------------------ B1 ongkos kirim

    public function test_shipping_cost_defaults_to_zero_and_grand_total_adds_it_to_item_total(): void
    {
        $so = $this->saleOrder();
        $this->assertEquals(0.0, (float) $so->shipping_cost);
        $this->assertEquals(1000000.0, $so->grandTotal());

        $so->update(['shipping_cost' => 150000]);
        $this->assertEquals(1000000.0, (float) $so->fresh()->total_amount);   // total barang tidak berubah
        $this->assertEquals(1150000.0, $so->fresh()->grandTotal());

        $so->update(['shipping_cost' => null]);                                // null dinormalkan ke 0
        $this->assertEquals(0.0, (float) $so->fresh()->shipping_cost);
    }

    public function test_shipping_cost_is_billed_once_on_the_first_delivery_order(): void
    {
        $so = $this->saleOrder(attributes: ['status' => 'approved', 'shipping_cost' => 150000]);

        $first = DeliveryOrderService::shippingCostToBill([$so->id]);
        $this->assertEquals(150000.0, $first['amount']);
        $this->assertStringContainsString($so->so_number, $first['description']);

        // DO pertama menagih ongkir
        $do = DeliveryOrder::create(['do_number' => 'DO-W1-1', 'cabang_id' => $this->cabang->id, 'delivery_date' => now(), 'status' => 'approved', 'additional_cost' => 150000]);
        DB::table('delivery_sales_orders')->insert(['delivery_order_id' => $do->id, 'sales_order_id' => $so->id]);

        // DO kedua tidak menagih lagi
        $this->assertEquals(0.0, DeliveryOrderService::shippingCostToBill([$so->id])['amount']);

        // Bila DO pertama dibatalkan, ongkir kembali bisa ditagihkan
        $do->update(['status' => 'closed']);
        $this->assertEquals(150000.0, DeliveryOrderService::shippingCostToBill([$so->id])['amount']);

        // Mengedit DO itu sendiri (dikecualikan) selalu melihat nilai penuh
        $do->update(['status' => 'approved']);
        $this->assertEquals(150000.0, DeliveryOrderService::shippingCostToBill([$so->id], $do->id)['amount']);
    }

    public function test_so_without_shipping_cost_bills_nothing(): void
    {
        $so = $this->saleOrder(attributes: ['status' => 'approved']);
        $this->assertEquals(['amount' => 0.0, 'description' => null], DeliveryOrderService::shippingCostToBill([$so->id]));
    }

    // ------------------------------------------------------------ B2/B5 approve Kasir

    public function test_cashier_can_approve_so_when_prices_respect_product_limit(): void
    {
        $so = $this->saleOrder(unitPrice: 95000, qty: 10);   // 950.000, di atas lantai 90.000, di bawah Rp10 juta

        $check = app(ApprovalControlService::class)->canApproveSaleOrder($this->user('Kasir'), $so);

        $this->assertTrue($check['allowed'], (string) $check['reason']);
    }

    public function test_cashier_cannot_approve_when_price_is_below_limit_but_sales_manager_can(): void
    {
        // Harga satuan 95.000 lolos, tetapi diskon 10% membuat harga efektif 85.500 < 90.000 (celah B4)
        $so = $this->saleOrder(unitPrice: 95000, qty: 10, discount: 10);
        $service = app(ApprovalControlService::class);

        $cashier = $service->canApproveSaleOrder($this->user('Kasir'), $so);
        $this->assertFalse($cashier['allowed']);
        $this->assertStringContainsString('di bawah batas harga', $cashier['reason']);
        $this->assertStringContainsString('atasan', $cashier['reason']);

        // Eskalasi: atasan tetap dapat menyetujui
        $this->assertTrue($service->canApproveSaleOrder($this->user('Sales Manager'), $so)['allowed']);
    }

    public function test_cashier_cannot_approve_above_the_first_tier_amount(): void
    {
        $so = $this->saleOrder(unitPrice: 100000, qty: 150);   // 15 juta > 10 juta

        $check = app(ApprovalControlService::class)->canApproveSaleOrder($this->user('Kasir'), $so);

        $this->assertFalse($check['allowed']);
        $this->assertStringContainsString('Persetujuan bertingkat', $check['reason']);
    }

    public function test_cashier_cannot_approve_own_sale_order(): void
    {
        $cashier = $this->user('Kasir');
        $so = $this->saleOrder(attributes: ['created_by' => $cashier->id]);

        $check = app(ApprovalControlService::class)->canApproveSaleOrder($cashier, $so);

        $this->assertFalse($check['allowed']);
        $this->assertStringContainsString('Segregation of Duties', $check['reason']);
    }

    public function test_cashier_approval_goes_through_the_service_and_sets_approved(): void
    {
        $so = $this->saleOrder(unitPrice: 95000, qty: 10);
        Auth::login($this->user('Kasir'));

        app(SalesOrderService::class)->approve($so);

        $this->assertSame('approved', $so->fresh()->status);
    }

    // ------------------------------------------------------------ B3 limit kredit (termasuk ongkir)

    public function test_approval_is_blocked_when_goods_plus_shipping_exceed_credit_limit(): void
    {
        $this->customer->update(['tipe_pembayaran' => 'Kredit', 'kredit_limit' => 1100000]);
        $so = $this->saleOrder(unitPrice: 100000, qty: 10, attributes: ['shipping_cost' => 150000]);   // 1.000.000 + 150.000 = 1.150.000 > 1.100.000
        Auth::login($this->user('Owner'));

        try {
            app(SalesOrderService::class)->approve($so);
            $this->fail('seharusnya ditolak karena melewati limit kredit');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Kredit limit tidak mencukupi', collect($e->errors())->flatten()->implode(' '));
        }

        $this->assertSame('request_approve', $so->fresh()->status);
    }

    public function test_approval_passes_when_goods_plus_shipping_fit_within_credit_limit(): void
    {
        $this->customer->update(['tipe_pembayaran' => 'Kredit', 'kredit_limit' => 1200000]);
        $so = $this->saleOrder(unitPrice: 100000, qty: 10, attributes: ['shipping_cost' => 150000]);
        Auth::login($this->user('Owner'));

        app(SalesOrderService::class)->approve($so);

        $this->assertSame('approved', $so->fresh()->status);
    }

    // ------------------------------------------------------------ UI (Filament)

    public function test_sale_order_form_has_shipping_cost_field(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = $this->user('Owner');
        $user->update(['manage_type' => 'all']);
        Permission::findOrCreate('create sales order', 'web');
        $user->givePermissionTo('create sales order');
        $this->actingAs($user);

        Livewire::test(\App\Filament\Resources\SaleOrderResource\Pages\CreateSaleOrder::class)
            ->assertFormFieldExists('shipping_cost');
    }

    public function test_cashier_sees_and_runs_the_approve_action_for_a_compliant_so_and_the_modal_renders(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $cashier = $this->user('Kasir');
        $cashier->update(['manage_type' => 'all']);
        foreach (['view any sales order', 'view sales order'] as $name) {
            Permission::findOrCreate($name, 'web');
            Role::findByName('Kasir', 'web')->givePermissionTo($name);
        }
        $so = $this->saleOrder(unitPrice: 95000, qty: 10);
        $this->actingAs($cashier->fresh());

        // Membuka modal memanggil deskripsi modal (dulu 500: SaleOrderResource::formatMoneyPreviewState tidak ada)
        Livewire::test(\App\Filament\Resources\SaleOrderResource\Pages\ListSaleOrders::class)
            ->assertTableActionVisible('approve', $so)
            ->mountTableAction('approve', $so)
            ->assertSuccessful()
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame('approved', $so->fresh()->status);
    }

    public function test_cashier_does_not_see_the_approve_action_when_price_violates_the_limit(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $cashier = $this->user('Kasir');
        $cashier->update(['manage_type' => 'all']);
        foreach (['view any sales order', 'view sales order'] as $name) {
            Permission::findOrCreate($name, 'web');
            Role::findByName('Kasir', 'web')->givePermissionTo($name);
        }
        $so = $this->saleOrder(unitPrice: 95000, qty: 10, discount: 10);
        $this->actingAs($cashier->fresh());

        Livewire::test(\App\Filament\Resources\SaleOrderResource\Pages\ListSaleOrders::class)
            ->assertTableActionHidden('approve', $so);
    }

    public function test_cashier_sees_needs_supervisor_action_instead_of_approve_for_price_violation(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $cashier = $this->user('Kasir');
        $cashier->update(['manage_type' => 'all']);
        foreach (['view any sales order', 'view sales order'] as $name) {
            Permission::findOrCreate($name, 'web');
            Role::findByName('Kasir', 'web')->givePermissionTo($name);
        }
        $violating = $this->saleOrder(unitPrice: 95000, qty: 10, discount: 10);
        $compliant = $this->saleOrder(unitPrice: 95000, qty: 10);
        $this->actingAs($cashier->fresh());

        Livewire::test(\App\Filament\Resources\SaleOrderResource\Pages\ListSaleOrders::class)
            ->assertTableActionVisible('needs_supervisor', $violating)
            ->assertTableActionHidden('needs_supervisor', $compliant)
            ->callTableAction('needs_supervisor', $violating)
            ->assertNotified('Perlu Persetujuan Atasan');

        $this->assertSame('request_approve', $violating->fresh()->status);
    }

    // ------------------------------------------------------------ PDF SO

    public function test_sales_order_pdf_shows_shipping_cost_and_grand_total_including_it(): void
    {
        $so = $this->saleOrder(unitPrice: 100000, qty: 10, attributes: ['shipping_cost' => 150000]);
        $html = view('pdf.sales-order', ['saleOrder' => $so->load(['customer', 'saleOrderItem.product.uom', 'cabang'])])->render();

        $this->assertStringContainsString('Ongkos Kirim', $html);
        $this->assertStringContainsString('termasuk Ongkos Kirim', $html);
        $this->assertStringContainsString('1.150.000', $html);
    }

    public function test_sales_order_pdf_without_shipping_cost_has_no_shipping_row(): void
    {
        $so = $this->saleOrder(unitPrice: 100000, qty: 10);
        $html = view('pdf.sales-order', ['saleOrder' => $so->load(['customer', 'saleOrderItem.product.uom', 'cabang'])])->render();

        $this->assertStringNotContainsString('termasuk Ongkos Kirim', $html);
        $this->assertStringNotContainsString('<strong>Ongkos Kirim</strong>', $html);
    }
}
