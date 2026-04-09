<?php

namespace Tests\Unit;

use App\Exports\PurchaseReportExport;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Services\Reports\PurchaseReportService;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseReportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_purchase_summary_and_service_backed_export_rows(): void
    {
        $supplier = Supplier::factory()->create([
            'code' => 'SUP-SERVICE',
            'perusahaan' => 'Supplier Service',
        ]);
        $product = Product::factory()->create(['name' => 'Produk Purchase Service']);

        $approvedOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-SERVICE-001',
            'status' => 'approved',
            'order_date' => '2026-04-02',
        ]);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $approvedOrder->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 200_000,
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'Non Pajak',
        ]);

        app(PurchaseOrderService::class)->updateTotalAmount($approvedOrder->fresh(['purchaseOrderItem', 'purchaseOrderBiaya']));
        $approvedOrder->refresh();

        $completedOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-SERVICE-002',
            'status' => 'completed',
            'order_date' => '2026-04-03',
        ]);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $completedOrder->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 150_000,
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'Non Pajak',
        ]);

        app(PurchaseOrderService::class)->updateTotalAmount($completedOrder->fresh(['purchaseOrderItem', 'purchaseOrderBiaya']));
        $completedOrder->refresh();

        $service = app(PurchaseReportService::class);
        $payload = $service->pdfPayload([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
        ]);

        $this->assertCount(2, $payload['rows']);
        $this->assertSame(2, $payload['summary']['total_orders']);
        $this->assertSame(750_000.0, $payload['summary']['total_amount']);
        $this->assertSame(375_000.0, $payload['summary']['average_amount']);
        $this->assertSame(4.0, $payload['summary']['total_quantity']);
        $this->assertSame(1, $payload['summary']['unique_products']);
        $this->assertSame(1, $payload['summary']['status_counts']['approved']);
        $this->assertSame(1, $payload['summary']['status_counts']['completed']);

        $collection = (new PurchaseReportExport(PurchaseOrder::query()))->collection();
        $headings = array_keys($collection->first());

        $this->assertSame([
            'No. PO',
            'Tanggal',
            'Kode Supplier',
            'Nama Supplier',
            'Alamat Supplier',
            'No. Telp',
            'Email',
            'Produk',
            'Qty',
            'Harga Satuan',
            'Subtotal',
            'Total PO',
            'Status',
        ], $headings);

        $itemRow = $collection->first(fn ($row) => ($row['Produk'] ?? null) === 'Produk Purchase Service');
        $summaryRow = $collection->first(fn ($row) => ($row['No. PO'] ?? null) === 'SUMMARY');

        $this->assertNotNull($itemRow);
        $this->assertSame(3, $itemRow['Qty']);
        $this->assertSame('Rp 200.000', $itemRow['Harga Satuan']);
        $this->assertSame('Rp 600.000', $itemRow['Subtotal']);
        $this->assertNotNull($summaryRow);
        $this->assertSame('Total: Rp 750.000', $summaryRow['Total PO']);
    }
}