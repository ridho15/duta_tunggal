<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\QualityControlService;
use App\Services\PurchaseReceiptService;
use Database\Seeders\ChartOfAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebugPurchaseReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_debug_auto_receipt_posting()
    {
        $this->seed(ChartOfAccountSeeder::class);

        $user = User::factory()->create();
        $supplier = Supplier::factory()->create(['tempo_hutang' => 30]);
        $currency = Currency::factory()->create(['code' => 'IDR']);
        UnitOfMeasure::factory()->create();

        $inventoryCoa = ChartOfAccount::where('code', '1140.01')->first();
        $unbilledPurchaseCoa = ChartOfAccount::where('code', '2100.10')->first();
        $temporaryProcurementCoa = ChartOfAccount::where('code', '1400.01')->first();

        $product = \App\Models\Product::factory()->create([
            'cost_price' => 10000,
            'is_active' => true,
            'uom_id' => UnitOfMeasure::first()->id,
        ]);

        $product->update([
            'inventory_coa_id' => $inventoryCoa?->id,
            'unbilled_purchase_coa_id' => $unbilledPurchaseCoa?->id,
            'temporary_procurement_coa_id' => $temporaryProcurementCoa?->id,
        ]);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'warehouse_id' => \App\Models\Warehouse::factory()->create(['status' => 1])->id,
            'status' => 'approved',
            'created_by' => $user->id,
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 10000,
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'Eklusif',
            'currency_id' => $currency->id,
        ]);

        $qcService = app(QualityControlService::class);
        $qc = $qcService->createQCFromPurchaseOrderItem($poItem, [
            'inspected_by' => $user->id,
            'passed_quantity' => 10,
            'rejected_quantity' => 0,
        ]);

        $this->assertNotNull($qc);

        // Complete QC - this should auto-create receipt and post it
        $qcService->completeQualityControl($qc, []);

        $purchaseReceipt = PurchaseReceipt::where('purchase_order_id', $po->id)->first();
        $this->assertNotNull($purchaseReceipt, 'No purchase receipt created');

        $receiptItem = $purchaseReceipt->purchaseReceiptItem->first();
        $this->assertNotNull($receiptItem, 'No receipt item created');

        // Call posting again to inspect returned result (idempotent)
        $receiptService = app(PurchaseReceiptService::class);
        $result = $receiptService->postPurchaseReceipt($purchaseReceipt);

        // Output diagnostic info to stdout
        fwrite(STDOUT, "postPurchaseReceipt result: " . json_encode($result) . "\n");

        $count = \App\Models\JournalEntry::where('source_type', PurchaseReceiptItem::class)
            ->where('source_id', $receiptItem->id)
            ->count();

        fwrite(STDOUT, "Journal entries for receipt item {$receiptItem->id}: {$count}\n");

        $this->assertGreaterThan(0, $count, 'Expected at least one JournalEntry linked to PurchaseReceiptItem');
    }
}
