<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Services\Reports\SalesReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesReportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_sales_pdf_payload_summary_from_service(): void
    {
        $customer = Customer::factory()->create(['code' => 'CUS-SERVICE', 'name' => 'Customer Service']);
        $product = Product::factory()->create(['name' => 'Produk Sales Service']);

        $confirmedOrder = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'so_number' => 'SO-SERVICE-001',
            'status' => 'confirmed',
            'total_amount' => 1_008_000,
            'created_at' => '2026-04-03 10:00:00',
        ]);

        SaleOrderItem::factory()->create([
            'sale_order_id' => $confirmedOrder->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 450_000,
            'discount' => 10,
            'tax' => 12,
            'tipe_pajak' => 'Eksklusif',
        ]);

        $cancelledOrder = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'so_number' => 'SO-SERVICE-002',
            'status' => 'cancelled',
            'total_amount' => 250_000,
            'created_at' => '2026-04-04 11:00:00',
        ]);

        SaleOrderItem::factory()->create([
            'sale_order_id' => $cancelledOrder->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 250_000,
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'Eksklusif',
        ]);

        $payload = app(SalesReportService::class)->pdfPayload([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
        ]);

        $this->assertCount(2, $payload['rows']);
        $this->assertSame(2, $payload['summary']['total_orders']);
        $this->assertSame(1_258_000.0, $payload['summary']['total_amount']);
        $this->assertSame(629_000.0, $payload['summary']['average_amount']);
        $this->assertSame(3.0, $payload['summary']['total_quantity']);
        $this->assertSame(1, $payload['summary']['unique_products']);
        $this->assertSame(1, $payload['summary']['status_counts']['confirmed']);
        $this->assertSame(1, $payload['summary']['status_counts']['cancelled']);
        $this->assertSame('SO-SERVICE-001', $payload['rows']->first()['so_number']);
        $this->assertSame('Customer Service', $payload['rows']->first()['customer_name']);
    }
}