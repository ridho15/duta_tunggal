<?php

namespace Tests\Feature\Api;

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\OrderRequest;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderRequestApiTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::withoutGlobalScopes()->first() ?? User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'Super Admin']);
        if (! $this->user->hasRole('Super Admin')) {
            $this->user->assignRole($role);
        }
        $this->actingAs($this->user);
    }

    public function test_unauthenticated_request_is_blocked(): void
    {
        auth()->logout();
        $this->getJson('/api/v1/order-requests/dependencies')->assertStatus(401);
        $this->postJson('/api/v1/order-requests', [])->assertStatus(401);
    }

    public function test_user_without_permission_cannot_create_order_request(): void
    {
        $restrictedUser = User::factory()->create([
            'email' => 'restricted_' . uniqid() . '@example.com',
            'username' => 'restr_' . substr(uniqid(), 0, 7),
        ]);
        $this->actingAs($restrictedUser);
        $this->postJson('/api/v1/order-requests', [])->assertStatus(403);
    }

    public function test_can_fetch_order_request_dependencies()
    {
        $response = $this->getJson('/api/v1/order-requests/dependencies');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'next_request_number',
                    'default_request_date',
                    'default_currency_id',
                    'cabangs',
                    'currencies',
                    'suppliers',
                    'products',
                    'tax_types',
                ],
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertNotEmpty($response->json('data.next_request_number'));
    }

    public function test_can_generate_request_number()
    {
        $response = $this->getJson('/api/v1/order-requests/generate-number');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'request_number',
            ]);

        $this->assertStringStartsWith('OR-', $response->json('request_number'));
    }

    public function test_can_store_order_request_via_api()
    {
        $cabang = Cabang::first() ?? Cabang::create(['kode' => 'CB01', 'nama' => 'Cabang Utama']);
        $currency = Currency::first() ?? Currency::create(['name' => 'Rupiah', 'code' => 'IDR', 'symbol' => 'Rp', 'to_rupiah' => 1]);
        $product = Product::withoutGlobalScope('product_cabang')->first() ?? Product::factory()->create([
            'cost_price' => 25000,
        ]);
        $supplier = Supplier::first() ?? Supplier::create(['code' => 'SUP-01', 'perusahaan' => 'Supplier Test']);

        $requestNumber = 'OR-TEST-' . uniqid();

        $payload = [
            'request_number' => $requestNumber,
            'request_date' => now()->format('Y-m-d'),
            'note' => 'Order request created from Next.js test',
            'currency_id' => $currency->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 10,
                    'unit_price' => 25000,
                    'original_price' => 25000,
                    'cabang_id' => $cabang->id,
                    'supplier_id' => $supplier->id,
                    'currency_id' => $currency->id,
                    'discount' => 10, // 10%
                    'tipe_pajak' => 'eklusif',
                    'tax' => 11,
                    'note' => 'Item note',
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/order-requests', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'request_number',
                    'status',
                    'redirect_url',
                ],
            ]);

        $this->assertDatabaseHas('order_requests', [
            'request_number' => $requestNumber,
            'status' => 'draft',
        ]);
    }

    public function test_can_update_order_request_via_api()
    {
        $currency = Currency::first() ?? Currency::factory()->create();
        $orderRequest = OrderRequest::first() ?? OrderRequest::factory()->create(['currency_id' => $currency->id]);
        $product = Product::first() ?? Product::factory()->create();
        $cabang = Cabang::first() ?? Cabang::factory()->create();

        $payload = [
            'request_number' => $orderRequest->request_number,
            'request_date' => '2026-09-02',
            'note' => 'Updated note via API test',
            'currency_id' => $currency->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 10,
                    'unit_price' => 75000,
                    'original_price' => 75000,
                    'cabang_id' => $cabang->id,
                    'supplier_id' => null,
                    'currency_id' => $currency->id,
                    'discount' => 5,
                    'tipe_pajak' => 'eklusif',
                    'tax' => 11,
                    'note' => 'Updated item note',
                ],
            ],
        ];

        $response = $this->putJson("/api/v1/order-requests/{$orderRequest->id}", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('order_requests', [
            'id' => $orderRequest->id,
            'note' => 'Updated note via API test',
        ]);
    }

    public function test_legacy_filament_order_request_urls_redirect_to_dashboard_under_lockdown()
    {
        $user = User::first() ?? User::factory()->create();
        $orderRequest = OrderRequest::first() ?? OrderRequest::factory()->create();

        $this->actingAs($user)->get('/admin/order-requests/create')->assertRedirect(route('dashboard'));
        $this->actingAs($user)->get("/admin/order-requests/{$orderRequest->id}/edit")->assertRedirect(route('dashboard'));
    }
}
