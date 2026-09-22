<?php

namespace Tests\Feature\Api;

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\SaleOrder;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class QuotationAndSaleOrderSessionAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function createCustomer(Cabang $cabang): Customer
    {
        return Customer::factory()->create([
            'cabang_id' => $cabang->id,
            'kredit_limit' => 100000000,
            'tempo_kredit' => 30,
            'tipe_pembayaran' => 'Kredit',
        ]);
    }

    private function createProduct(Cabang $cabang): Product
    {
        return Product::first() ?? Product::factory()->create([
            'sell_price' => 50000,
        ]);
    }

    public function test_unauthenticated_request_to_create_quotation_fails_auth(): void
    {
        $response = $this->postJson('/api/v1/quotations', [
            'header' => ['quotation_number' => 'QUO-GUEST-' . uniqid()],
            'items' => [],
        ]);

        $this->assertTrue(in_array($response->status(), [401, 403], true));
    }

    public function test_super_admin_can_create_quotation_without_403(): void
    {
        $cabang = Cabang::first() ?? Cabang::factory()->create();
        Role::findOrCreate('Super Admin', 'web');

        $superAdmin = User::factory()->create([
            'email' => 'sa_' . uniqid() . '@example.com',
            'username' => 'sa_' . uniqid(),
            'kode_user' => 'SA' . rand(100, 999),
            'cabang_id' => $cabang->id,
            'manage_type' => 'all',
        ]);
        $superAdmin->assignRole('Super Admin');

        $customer = $this->createCustomer($cabang);
        $product = $this->createProduct($cabang);
        $currency = Currency::where('code', 'IDR')->first() ?? Currency::factory()->create(['code' => 'IDR', 'to_rupiah' => 1]);

        $payload = [
            'header' => [
                'quotation_number' => 'QUO-SA-' . uniqid(),
                'customer_id' => $customer->id,
                'cabang_id' => $cabang->id,
                'date' => now()->toDateString(),
                'currency_id' => $currency->id,
                'status' => Quotation::STATUS_DRAFT,
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 5,
                    'unit_price' => 50000,
                    'tax_type' => 'None',
                    'tax' => 0,
                    'discount' => 0,
                ],
            ],
        ];

        $response = $this->actingAs($superAdmin)->postJson('/api/v1/quotations', $payload);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('quotations', [
            'quotation_number' => $payload['header']['quotation_number'],
            'created_by' => $superAdmin->id,
        ]);
    }

    public function test_sales_role_can_create_quotation_and_submit_for_approval(): void
    {
        $cabang = Cabang::first() ?? Cabang::factory()->create();
        Role::findOrCreate('Sales', 'web');

        foreach (['create quotation', 'request-approve quotation', 'view any quotation', 'view quotation'] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $salesUser = User::factory()->create([
            'email' => 'sales_' . uniqid() . '@example.com',
            'username' => 'sales_' . uniqid(),
            'kode_user' => 'SL' . rand(100, 999),
            'cabang_id' => $cabang->id,
            'manage_type' => 'all',
        ]);
        $salesUser->assignRole('Sales');
        $salesUser->givePermissionTo(['create quotation', 'request-approve quotation']);

        $customer = $this->createCustomer($cabang);
        $product = $this->createProduct($cabang);
        $currency = Currency::where('code', 'IDR')->first() ?? Currency::factory()->create(['code' => 'IDR', 'to_rupiah' => 1]);

        $payload = [
            'header' => [
                'quotation_number' => 'QUO-SL-' . uniqid(),
                'customer_id' => $customer->id,
                'cabang_id' => $cabang->id,
                'date' => now()->toDateString(),
                'currency_id' => $currency->id,
                'status' => Quotation::STATUS_REQUEST_APPROVE,
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 50000,
                    'tax_type' => 'None',
                    'tax' => 0,
                    'discount' => 0,
                ],
            ],
        ];

        $response = $this->actingAs($salesUser)->postJson('/api/v1/quotations', $payload);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('quotations', [
            'quotation_number' => $payload['header']['quotation_number'],
            'status' => Quotation::STATUS_REQUEST_APPROVE,
        ]);
    }

    public function test_super_admin_can_create_sales_order_without_403(): void
    {
        $cabang = Cabang::first() ?? Cabang::factory()->create();
        Role::findOrCreate('Super Admin', 'web');

        $superAdmin = User::factory()->create([
            'email' => 'sa_so_' . uniqid() . '@example.com',
            'username' => 'sa_so_' . uniqid(),
            'kode_user' => 'SO' . rand(100, 999),
            'cabang_id' => $cabang->id,
            'manage_type' => 'all',
        ]);
        $superAdmin->assignRole('Super Admin');

        $customer = $this->createCustomer($cabang);
        $product = $this->createProduct($cabang);
        $currency = Currency::where('code', 'IDR')->first() ?? Currency::factory()->create(['code' => 'IDR', 'to_rupiah' => 1]);

        $payload = [
            'header' => [
                'so_number' => 'SO-SA-' . uniqid(),
                'customer_id' => $customer->id,
                'cabang_id' => $cabang->id,
                'order_date' => now()->toDateString(),
                'tipe_pengiriman' => 'Ambil Sendiri',
                'currency_id' => $currency->id,
                'status' => 'draft',
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 10,
                    'unit_price' => 45000,
                    'discount' => 0,
                    'tax_type' => 'None',
                    'tax' => 0,
                ],
            ],
        ];

        $response = $this->actingAs($superAdmin)->postJson('/api/v1/sales-orders', $payload);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('sale_orders', [
            'so_number' => $payload['header']['so_number'],
            'created_by' => $superAdmin->id,
        ]);
    }
}
