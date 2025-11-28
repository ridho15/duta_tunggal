<?php

namespace Tests\Browser;

use App\Models\Currency;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\OrderRequestService;
use App\Services\PurchaseReceiptService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ProcurementToAccountingDuskTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        // Minimal seed/data required
        Currency::factory()->create(['code' => 'IDR', 'to_rupiah' => 1]);
    }

    public function test_procurement_flow_creates_journal_entries_and_ledger_page_loads()
    {
        // Create basic data
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['supplier_id' => $supplier->id, 'cost_price' => 10000]);

        // Create an order request with one item
        $orderRequest = OrderRequest::factory()->create([
            'warehouse_id' => $warehouse->id,
            'created_by' => $user->id,
            'status' => 'draft'
        ]);

        OrderRequestItem::factory()->create([
            'order_request_id' => $orderRequest->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        // Approve order request (creates PurchaseOrder and PurchaseOrderItem)
        $orderRequestService = app(OrderRequestService::class);

        $poData = [
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-DUSK-1',
            'order_date' => now()->format('Y-m-d'),
            'expected_date' => now()->addWeek()->format('Y-m-d'),
            'note' => 'Dusk test PO'
        ];

        $orderRequestService->approve($orderRequest, $poData);

        $purchaseOrder = PurchaseOrder::where('po_number', 'PO-DUSK-1')->first();
        $this->assertNotNull($purchaseOrder, 'Purchase Order should have been created by approve service');

        // Create a purchase receipt for the purchase order
        // Create purchase receipt associated with the purchase order.
        // Avoid setting columns that may not exist in schema (e.g., supplier_id/warehouse_id)
        $purchaseReceipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'status' => 'draft',
        ]);

        // create receipt item based on PO items
        foreach ($purchaseOrder->purchaseOrderItem as $poi) {
            PurchaseReceiptItem::factory()->create([
                'purchase_receipt_id' => $purchaseReceipt->id,
                'purchase_order_item_id' => $poi->id,
                'product_id' => $poi->product_id,
                'qty_received' => $poi->quantity,
                'qty_accepted' => $poi->quantity,
            ]);
        }

        // Post the receipt to generate journal entries
        $purchaseReceiptService = app(PurchaseReceiptService::class);
        $result = $purchaseReceiptService->postPurchaseReceipt($purchaseReceipt);

        // Assert journal entries exist for posted receipt items
        $this->assertDatabaseHas('journal_entries', [
            'source_type' => PurchaseReceiptItem::class,
        ]);

        // Now open browser and visit Buku Besar page to ensure ledger UI loads
        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/buku-besar-page')
                ->assertSee('Buku Besar')
                ->assertSee('Tanggal')
                ->assertSee('Debit')
                ->assertSee('Kredit');
        });
    }
}
