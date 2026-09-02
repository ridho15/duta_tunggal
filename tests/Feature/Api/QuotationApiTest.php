<?php

namespace Tests\Feature\Api;

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationApiTest extends TestCase
{
    public function test_dependencies_returns_complete_master_data_and_next_quotation_number()
    {
        $user = User::first() ?? User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson('/api/v1/quotations/dependencies');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'next_quotation_number',
                    'default_date',
                    'default_valid_until',
                    'default_currency_id',
                    'cabangs',
                    'currencies',
                    'customers',
                    'products',
                    'tax_types',
                    'user',
                ],
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertNotEmpty($response->json('data.next_quotation_number'));
        $this->assertStringStartsWith('QO-', $response->json('data.next_quotation_number'));
    }

    public function test_can_generate_new_quotation_number()
    {
        $user = User::first() ?? User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson('/api/v1/quotations/generate-number');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'quotation_number',
                ],
            ]);

        $this->assertStringStartsWith('QO-', $response->json('data.quotation_number'));
    }

    public function test_create_quotation_stores_record_and_calculates_total_amount()
    {
        $user = User::first() ?? User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::first() ?? Customer::create([
            'code' => 'CUST-TEST-01',
            'name' => 'PT Test Customer',
            'perusahaan' => 'PT Test',
            'nik_npwp' => '123456789012345',
            'address' => 'Jakarta',
            'telephone' => '021123456',
            'phone' => '08123456789',
            'email' => 'test@example.com',
            'fax' => '021123456',
            'tempo_kredit' => 30,
            'kredit_limit' => 100000000,
            'tipe_pembayaran' => 'Kredit',
            'tipe' => 'PKP',
        ]);

        $cabang = Cabang::first() ?? Cabang::create([
            'kode' => 'CBG-TEST-01',
            'nama' => 'Cabang Test',
        ]);

        $currency = Currency::first() ?? Currency::create([
            'name' => 'Rupiah',
            'code' => 'IDR',
            'symbol' => 'Rp',
            'to_rupiah' => 1,
        ]);

        $product = Product::withoutGlobalScope('product_cabang')->first() ?? Product::create([
            'sku' => 'SKU-TEST-01',
            'name' => 'Produk Test Quotation',
            'sell_price' => 100000,
        ]);

        $quotationNumber = 'QO-TEST-' . time() . '-' . rand(100, 999);

        $payload = [
            'header' => [
                'quotation_number' => $quotationNumber,
                'customer_id' => $customer->id,
                'cabang_id' => $cabang->id,
                'date' => now()->format('Y-m-d'),
                'valid_until' => now()->addDays(30)->format('Y-m-d'),
                'currency_id' => $currency->id,
                'tempo_pembayaran' => 30,
                'notes' => 'Test Automated Quotation Creation',
                'status' => 'draft',
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 100000,
                    'discount' => 10,
                    'tax_type' => 'Eksklusif',
                    'tax' => 11,
                    'notes' => 'Item 1 note',
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/quotations', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $quotationId = $response->json('data.id');
        $this->assertNotNull($quotationId);

        $quotation = Quotation::with('quotationItem')->find($quotationId);
        $this->assertNotNull($quotation);
        $this->assertEquals($quotationNumber, $quotation->quotation_number);
        $this->assertCount(1, $quotation->quotationItem);

        // Subtotal calculation:
        // 2 * 100,000 = 200,000. Disc 10% = 20,000 => base 180,000.
        // PPN Eksklusif 11% = 19,800. Subtotal = 199,800.
        $this->assertEquals(199800, (float) $quotation->total_amount);
    }

    public function test_show_and_update_quotation()
    {
        $user = User::first() ?? User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::first();
        $cabang = Cabang::first();
        $currency = Currency::first();
        $product = Product::withoutGlobalScope('product_cabang')->first();

        $quotation = Quotation::create([
            'quotation_number' => 'QO-TEST-UPDATE-' . time(),
            'customer_id' => $customer->id,
            'cabang_id' => $cabang->id,
            'date' => now(),
            'valid_until' => now()->addDays(14),
            'currency_id' => $currency->id,
            'exchange_rate' => 1.0,
            'tempo_pembayaran' => 14,
            'notes' => 'Initial Note',
            'status' => 'draft',
            'created_by' => $user->id,
            'total_amount' => 100000,
        ]);

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100000,
            'unit_price_idr' => 100000,
            'discount' => 0,
            'tax_type' => 'None',
            'tax' => 0,
            'total_price' => 100000,
        ]);

        // Test Show
        $showResponse = $this->getJson("/api/v1/quotations/{$quotation->id}");
        $showResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'header' => [
                        'id' => $quotation->id,
                        'customer_id' => $customer->id,
                    ],
                ],
            ]);

        // Test Update
        $updatePayload = [
            'header' => [
                'quotation_number' => $quotation->quotation_number,
                'customer_id' => $customer->id,
                'cabang_id' => $cabang->id,
                'date' => now()->format('Y-m-d'),
                'valid_until' => now()->addDays(20)->format('Y-m-d'),
                'currency_id' => $currency->id,
                'tempo_pembayaran' => 20,
                'notes' => 'Updated Note Content',
                'status' => 'draft',
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 3,
                    'unit_price' => 100000,
                    'discount' => 0,
                    'tax_type' => 'None',
                    'tax' => 0,
                    'notes' => 'Updated item qty to 3',
                ],
            ],
        ];

        $updateResponse = $this->putJson("/api/v1/quotations/{$quotation->id}", $updatePayload);
        $updateResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        $quotation->refresh();
        $this->assertEquals('Updated Note Content', $quotation->notes);
        $this->assertEquals(300000, (float) $quotation->total_amount);
    }
}
