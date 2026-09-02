<?php

namespace Tests\Feature\Api;

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Tests\TestCase;

class PurchaseOrderApiTest extends TestCase
{
    public function test_can_fetch_purchase_order_dependencies()
    {
        $response = $this->getJson('/api/v1/purchase-orders/dependencies');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'next_po_number',
                    'default_order_date',
                    'default_expected_date',
                    'default_currency_id',
                    'cabangs',
                    'currencies',
                    'suppliers',
                    'products',
                    'tax_types',
                    'top_types',
                    'available_order_requests',
                    'available_sales_orders',
                ],
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertNotEmpty($response->json('data.next_po_number'));
    }

    public function test_can_generate_po_number()
    {
        $response = $this->getJson('/api/v1/purchase-orders/generate-number');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'po_number',
            ]);

        $this->assertStringStartsWith('PO-', $response->json('po_number'));
    }

    public function test_can_store_purchase_order_via_api()
    {
        $cabang = Cabang::first() ?? Cabang::create(['kode' => 'CB01', 'nama' => 'Cabang Utama']);
        $currency = Currency::first() ?? Currency::create(['name' => 'Rupiah', 'code' => 'IDR', 'symbol' => 'Rp', 'to_rupiah' => 1]);
        $product = Product::withoutGlobalScope('product_cabang')->first() ?? Product::factory()->create([
            'cost_price' => 50000,
        ]);
        $supplier = Supplier::first() ?? Supplier::create(['code' => 'SUP-01', 'perusahaan' => 'Supplier Test']);

        $poNumber = 'PO-TEST-' . time();

        $payload = [
            'header' => [
                'po_number' => $poNumber,
                'supplier_id' => $supplier->id,
                'cabang_id' => $cabang->id,
                'order_date' => now()->format('Y-m-d'),
                'expected_date' => now()->addDays(7)->format('Y-m-d'),
                'top_type' => 'credit_days',
                'tempo_hutang' => 30,
                'is_asset' => false,
                'is_import' => false,
                'note' => 'Automated Test PO',
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 5,
                    'unit_price' => 50000,
                    'discount' => 10,
                    'tax' => 11,
                    'tipe_pajak' => 'eklusif',
                    'currency_id' => $currency->id,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/purchase-orders', $payload);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'po_number',
                    'redirect_url',
                ],
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertDatabaseHas('purchase_orders', [
            'po_number' => $poNumber,
            'supplier_id' => $supplier->id,
            'status' => 'draft',
        ]);
    }

    public function test_can_show_and_update_purchase_order_via_api()
    {
        $supplier = Supplier::first() ?? Supplier::create(['code' => 'SUP-01', 'perusahaan' => 'Supplier Test']);
        $product = Product::withoutGlobalScope('product_cabang')->first() ?? Product::factory()->create();
        $currency = Currency::first() ?? Currency::create(['name' => 'Rupiah', 'code' => 'IDR', 'symbol' => 'Rp', 'to_rupiah' => 1]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-UPD-' . time(),
            'supplier_id' => $supplier->id,
            'order_date' => now()->format('Y-m-d'),
            'status' => 'draft',
            'top_type' => 'cod',
            'tempo_hutang' => 0,
            'total_amount' => 100000,
        ]);

        $item = $po->purchaseOrderItem()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 50000,
            'discount' => 0,
            'tax' => 11,
            'tipe_pajak' => 'eklusif',
            'currency_id' => $currency->id,
        ]);

        // 1. Show API
        $showRes = $this->getJson("/api/v1/purchase-orders/{$po->id}");
        $showRes->assertStatus(200)
            ->assertJsonPath('data.po_number', $po->po_number);

        // 2. Update API
        $updatePayload = [
            'header' => [
                'po_number' => $po->po_number,
                'supplier_id' => $supplier->id,
                'cabang_id' => $po->cabang_id,
                'order_date' => now()->format('Y-m-d'),
                'expected_date' => now()->addDays(5)->format('Y-m-d'),
                'top_type' => 'credit_days',
                'tempo_hutang' => 14,
                'is_asset' => false,
                'is_import' => false,
                'note' => 'Updated via Test',
            ],
            'items' => [
                [
                    'id' => $item->id,
                    'product_id' => $product->id,
                    'quantity' => 10,
                    'unit_price' => 45000,
                    'discount' => 5,
                    'tax' => 11,
                    'tipe_pajak' => 'eklusif',
                    'currency_id' => $currency->id,
                ],
            ],
        ];

        $updateRes = $this->putJson("/api/v1/purchase-orders/{$po->id}", $updatePayload);
        $updateRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'tempo_hutang' => 14,
            'note' => 'Updated via Test',
        ]);
    }

    public function test_order_request_reference_flow_mirrors_legacy_filament()
    {
        $supplierA = Supplier::factory()->create(['perusahaan' => 'Supplier Alpha', 'tempo_hutang' => 30]);
        $supplierB = Supplier::factory()->create(['perusahaan' => 'Supplier Beta', 'tempo_hutang' => 15]);
        $cabang = Cabang::first() ?? Cabang::create(['kode' => 'CB01', 'nama' => 'Cabang Utama']);
        $currency = Currency::first() ?? Currency::create(['name' => 'Rupiah', 'code' => 'IDR', 'symbol' => 'Rp', 'to_rupiah' => 1]);
        $product1 = Product::withoutGlobalScope('product_cabang')->first() ?? Product::factory()->create();
        $product2 = Product::withoutGlobalScope('product_cabang')->skip(1)->first() ?? Product::factory()->create();

        $userId = User::first()?->id ?? 1;

        // 1. Create a draft OR -> should NOT appear in PO dependencies
        $draftOr = OrderRequest::create([
            'request_number' => 'OR-DRAFT-' . time(),
            'request_date' => now()->format('Y-m-d'),
            'status' => 'draft',
            'currency_id' => $currency->id,
            'created_by' => $userId,
        ]);
        $draftOr->orderRequestItem()->create([
            'product_id' => $product1->id,
            'supplier_id' => $supplierA->id,
            'cabang_id' => $cabang->id,
            'quantity' => 10,
            'status' => 'draft',
            'unit_price' => 50000,
            'tax' => 11,
            'tipe_pajak' => 'inklusif',
            'currency_id' => $currency->id,
        ]);

        $depsRes = $this->getJson('/api/v1/purchase-orders/dependencies');
        $availableOrs = collect($depsRes->json('data.available_order_requests'));
        $this->assertFalse($availableOrs->contains('id', $draftOr->id));

        // 2. Create an approved OR with multi-supplier items
        $approvedOr = OrderRequest::create([
            'request_number' => 'OR-APPR-' . time(),
            'request_date' => now()->format('Y-m-d'),
            'status' => 'approved',
            'currency_id' => $currency->id,
            'created_by' => $userId,
        ]);
        $item1 = $approvedOr->orderRequestItem()->create([
            'product_id' => $product1->id,
            'supplier_id' => $supplierA->id,
            'cabang_id' => $cabang->id,
            'quantity' => 8,
            'status' => 'approved',
            'unit_price' => 50000,
            'discount' => 5,
            'tax' => 11,
            'tipe_pajak' => 'inklusif',
            'currency_id' => $currency->id,
        ]);
        $item2 = $approvedOr->orderRequestItem()->create([
            'product_id' => $product2->id,
            'supplier_id' => $supplierB->id,
            'cabang_id' => $cabang->id,
            'quantity' => 12,
            'status' => 'approved',
            'unit_price' => 75000,
            'discount' => 0,
            'tax' => 11,
            'tipe_pajak' => 'eklusif',
            'currency_id' => $currency->id,
        ]);

        $depsRes2 = $this->getJson('/api/v1/purchase-orders/dependencies');
        $availableOrs2 = collect($depsRes2->json('data.available_order_requests'));
        $found = $availableOrs2->firstWhere('id', $approvedOr->id);
        $this->assertNotNull($found);
        $this->assertEquals(2, $found['remaining_items']);
        $this->assertTrue(in_array($supplierA->id, $found['supplier_ids']));
        $this->assertTrue(in_array($supplierB->id, $found['supplier_ids']));

        // 3. Test referenceItems filtered by Supplier A
        $refResA = $this->getJson("/api/v1/purchase-orders/reference-items?type=OrderRequest&id={$approvedOr->id}&supplier_id={$supplierA->id}");
        $refResA->assertStatus(200);
        $this->assertCount(1, $refResA->json('items'));
        $this->assertEquals($product1->id, $refResA->json('items.0.product_id'));
        $this->assertEquals(8, $refResA->json('items.0.quantity'));
        $this->assertEquals($item1->id, $refResA->json('items.0.refer_item_model_id'));

        // 4. Test creating PO from approved OR -> status auto-approved
        $poNumber = 'PO-OR-AUTO-' . time();
        $storePayload = [
            'header' => [
                'po_number' => $poNumber,
                'supplier_id' => $supplierA->id,
                'cabang_id' => $cabang->id,
                'order_date' => now()->format('Y-m-d'),
                'refer_model_type' => 'OrderRequest',
                'refer_model_id' => $approvedOr->id,
                'top_type' => 'credit_days',
                'tempo_hutang' => 30,
            ],
            'items' => [
                [
                    'product_id' => $product1->id,
                    'quantity' => 8,
                    'unit_price' => 50000,
                    'discount' => 5,
                    'tax' => 11,
                    'tipe_pajak' => 'inklusif',
                    'currency_id' => $currency->id,
                    'refer_item_model_type' => OrderRequestItem::class,
                    'refer_item_model_id' => $item1->id,
                ],
            ],
        ];

        $storeRes = $this->postJson('/api/v1/purchase-orders', $storePayload);
        $storeRes->assertStatus(200);
        $this->assertDatabaseHas('purchase_orders', [
            'po_number' => $poNumber,
            'supplier_id' => $supplierA->id,
            'status' => 'approved',
        ]);
    }
}
