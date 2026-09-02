<?php

namespace Tests\Feature\Api;

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\User;
use Tests\TestCase;

class SaleOrderApiTest extends TestCase
{
    public function test_dependencies_returns_complete_master_data_and_next_so_number()
    {
        $user = User::first() ?? User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson('/api/v1/sales-orders/dependencies');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'next_so_number',
                    'default_order_date',
                    'default_delivery_date',
                    'default_currency_id',
                    'cabangs',
                    'currencies',
                    'customers',
                    'approved_quotations',
                    'products',
                    'tax_types',
                    'user',
                ],
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertNotEmpty($response->json('data.next_so_number'));
        $this->assertStringStartsWith('SO-', $response->json('data.next_so_number'));
    }

    public function test_can_generate_new_so_number()
    {
        $user = User::first() ?? User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson('/api/v1/sales-orders/generate-number');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'so_number',
                ],
            ]);

        $this->assertStringStartsWith('SO-', $response->json('data.so_number'));
    }

    public function test_create_sale_order_standalone_and_calculates_total_amount()
    {
        $user = User::first() ?? User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::first() ?? Customer::create([
            'code' => 'CUST-TEST-SO-01',
            'name' => 'PT Test Customer SO',
            'perusahaan' => 'PT Test SO',
            'nik_npwp' => '123456789012345',
            'address' => 'Jakarta Barat',
            'telephone' => '021123456',
            'phone' => '08123456789',
            'email' => 'test_so@example.com',
            'fax' => '021123456',
            'tempo_kredit' => 30,
            'kredit_limit' => 100000000,
            'tipe_pembayaran' => 'Kredit',
            'tipe' => 'PKP',
        ]);

        $cabang = Cabang::first() ?? Cabang::create([
            'kode' => 'CBG-TEST-SO-01',
            'nama' => 'Cabang Test SO',
        ]);

        $currency = Currency::first() ?? Currency::create([
            'name' => 'Rupiah',
            'code' => 'IDR',
            'symbol' => 'Rp',
            'to_rupiah' => 1,
        ]);

        $product = Product::withoutGlobalScope('product_cabang')->first() ?? Product::create([
            'sku' => 'SKU-TEST-SO-01',
            'name' => 'Produk Test SO',
            'sell_price' => 200000,
        ]);

        $soNumber = 'SO-TEST-' . time() . '-' . rand(100, 999);

        $payload = [
            'header' => [
                'so_number' => $soNumber,
                'customer_id' => $customer->id,
                'cabang_id' => $cabang->id,
                'order_date' => now()->format('Y-m-d'),
                'delivery_date' => now()->addDays(7)->format('Y-m-d'),
                'tipe_pengiriman' => 'Kirim Langsung',
                'shipped_to' => 'Jl. Pengiriman No 10 Jakarta',
                'currency_id' => $currency->id,
                'tempo_pembayaran' => 30,
                'notes' => 'Test Automated SO Creation',
                'status' => 'draft',
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 200000,
                    'discount' => 5,
                    'tax_type' => 'Eksklusif',
                    'tax' => 11,
                    'notes' => 'Item 1 note',
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sales-orders', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $soId = $response->json('data.id');
        $this->assertNotNull($soId);

        $saleOrder = SaleOrder::with('saleOrderItem')->find($soId);
        $this->assertNotNull($saleOrder);
        $this->assertEquals($soNumber, $saleOrder->so_number);
        $this->assertCount(1, $saleOrder->saleOrderItem);

        // Subtotal calculation:
        // 2 * 200,000 = 400,000. Disc 5% = 20,000 => base 380,000.
        // PPN Eksklusif 11% = 41,800. Subtotal = 421,800.
        $this->assertEquals(421800, (float) $saleOrder->total_amount);
    }

    public function test_create_sale_order_from_approved_quotation()
    {
        $user = User::first() ?? User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::first();
        $cabang = Cabang::first();
        $currency = Currency::first();
        $product = Product::withoutGlobalScope('product_cabang')->first();

        // Create an approved quotation
        $quotation = Quotation::create([
            'quotation_number' => 'QO-SO-TEST-' . time(),
            'customer_id' => $customer->id,
            'cabang_id' => $cabang->id,
            'date' => now(),
            'valid_until' => now()->addDays(30),
            'currency_id' => $currency->id,
            'exchange_rate' => 1.0,
            'tempo_pembayaran' => 15,
            'notes' => 'Approved quotation for SO testing',
            'status' => 'approve',
            'created_by' => $user->id,
            'total_amount' => 500000,
        ]);

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 100000,
            'unit_price_idr' => 100000,
            'discount' => 0,
            'tax_type' => 'None',
            'tax' => 0,
            'total_price' => 500000,
        ]);

        // Fetch quotation via API
        $qResponse = $this->getJson("/api/v1/sales-orders/quotation/{$quotation->id}");
        $qResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $quotation->id,
                    'customer_id' => $customer->id,
                ],
            ]);

        $soNumber = 'SO-FROM-QO-' . time();
        $payload = [
            'header' => [
                'so_number' => $soNumber,
                'quotation_id' => $quotation->id,
                'customer_id' => $customer->id,
                'cabang_id' => $cabang->id,
                'order_date' => now()->format('Y-m-d'),
                'delivery_date' => now()->addDays(5)->format('Y-m-d'),
                'tipe_pengiriman' => 'Kirim Langsung',
                'shipped_to' => 'Alamat Pengiriman Quotation',
                'currency_id' => $currency->id,
                'tempo_pembayaran' => 15,
                'notes' => 'SO created from Quotation',
                'status' => 'draft',
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 5,
                    'unit_price' => 100000,
                    'discount' => 0,
                    'tax_type' => 'None',
                    'tax' => 0,
                    'notes' => 'From QO item',
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sales-orders', $payload);
        $response->assertStatus(200)->assertJson(['success' => true]);

        $soId = $response->json('data.id');
        $saleOrder = SaleOrder::find($soId);
        $this->assertNotNull($saleOrder);
        $this->assertEquals($quotation->id, $saleOrder->quotation_id);
        $this->assertEquals(500000, (float) $saleOrder->total_amount);
    }

    public function test_show_and_update_sale_order()
    {
        $user = User::first() ?? User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::first();
        $cabang = Cabang::first();
        $currency = Currency::first();
        $product = Product::withoutGlobalScope('product_cabang')->first();

        $saleOrder = SaleOrder::create([
            'so_number' => 'SO-UPDATE-TEST-' . time(),
            'customer_id' => $customer->id,
            'cabang_id' => $cabang->id,
            'order_date' => now(),
            'delivery_date' => now()->addDays(7),
            'tipe_pengiriman' => 'Ambil Sendiri',
            'shipped_to' => 'Customer Pickup',
            'currency_id' => $currency->id,
            'exchange_rate' => 1.0,
            'tempo_pembayaran' => 14,
            'notes' => 'Initial SO Note',
            'status' => 'draft',
            'created_by' => $user->id,
            'total_amount' => 100000,
        ]);

        SaleOrderItem::create([
            'sale_order_id' => $saleOrder->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100000,
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'None',
            'currency_id' => $currency->id,
        ]);

        // Test Show
        $showResponse = $this->getJson("/api/v1/sales-orders/{$saleOrder->id}");
        $showResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'header' => [
                        'id' => $saleOrder->id,
                        'customer_id' => $customer->id,
                    ],
                ],
            ]);

        // Test Update
        $updatePayload = [
            'header' => [
                'so_number' => $saleOrder->so_number,
                'customer_id' => $customer->id,
                'cabang_id' => $cabang->id,
                'order_date' => now()->format('Y-m-d'),
                'delivery_date' => now()->addDays(10)->format('Y-m-d'),
                'tipe_pengiriman' => 'Kirim Langsung',
                'shipped_to' => 'Alamat baru',
                'currency_id' => $currency->id,
                'tempo_pembayaran' => 20,
                'notes' => 'Updated SO Content',
                'status' => 'draft',
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 4,
                    'unit_price' => 100000,
                    'discount' => 0,
                    'tax_type' => 'None',
                    'tax' => 0,
                    'notes' => 'Updated qty to 4',
                ],
            ],
        ];

        $updateResponse = $this->putJson("/api/v1/sales-orders/{$saleOrder->id}", $updatePayload);
        $updateResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        $saleOrder->refresh();
        $this->assertEquals('Alamat baru', $saleOrder->shipped_to);
        $this->assertEquals('Kirim Langsung', $saleOrder->tipe_pengiriman);
        $this->assertEquals(400000, (float) $saleOrder->total_amount);
    }
}
