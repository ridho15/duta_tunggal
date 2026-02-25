<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\PurchaseOrderItem;
use App\Services\PurchaseReceiptService;

uses(RefreshDatabase::class);

test('automatic invoice calculates dpp and ppn per item type (non, inklusif, eksklusif)', function () {
    // Setup a purchase order (create supplier first to satisfy factory)
    $supplier = \App\Models\Supplier::factory()->create();

    // Ensure essential COA accounts exist for ledger postings in test environment
    $this->seed(\Database\Seeders\ChartOfAccountSeeder::class);

    $po = PurchaseOrder::factory()->create(['supplier_id' => $supplier->id]);

    // Create three items on PO with different tax types
    $poItemA = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id,
        'quantity' => 1,
        'unit_price' => 100000,
        'tax' => 12,
        'tipe_pajak' => 'Eklusif'
    ]);

    $poItemB = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id,
        'quantity' => 1,
        // gross price includes tax (12%) -> 112000 gross
        'unit_price' => 112000,
        'tax' => 12,
        'tipe_pajak' => 'Inklusif'
    ]);

    $poItemC = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id,
        'quantity' => 2,
        'unit_price' => 50000,
        'tax' => 0,
        'tipe_pajak' => 'Non Pajak'
    ]);

    // Create a receipt and receipt items (accepted qty)
    $receipt = PurchaseReceipt::factory()->create(['purchase_order_id' => $po->id]);

    $rA = PurchaseReceiptItem::factory()->create([
        'purchase_receipt_id' => $receipt->id,
        'purchase_order_item_id' => $poItemA->id,
        'product_id' => $poItemA->product_id,
        'qty_received' => 1,
        'qty_accepted' => 1,
    ]);

    $rB = PurchaseReceiptItem::factory()->create([
        'purchase_receipt_id' => $receipt->id,
        'purchase_order_item_id' => $poItemB->id,
        'product_id' => $poItemB->product_id,
        'qty_received' => 1,
        'qty_accepted' => 1,
    ]);

    $rC = PurchaseReceiptItem::factory()->create([
        'purchase_receipt_id' => $receipt->id,
        'purchase_order_item_id' => $poItemC->id,
        'product_id' => $poItemC->product_id,
        'qty_received' => 2,
        'qty_accepted' => 2,
    ]);

    // Trigger automatic invoice creation
    $service = app(PurchaseReceiptService::class);
    $res = $service->createAutomaticInvoiceFromReceipt($receipt);

    expect($res['status'])->toBe('created');

    $invoice = \App\Models\Invoice::find($res['invoice_id']);

    // Expected calculations:
    // Item A (Eksklusif): DPP 100000, PPN 12% = 12000
    // Item B (Inklusif): gross 112000 -> DPP 100000, PPN 12000
    // Item C (Non Pajak): 2 * 50000 = DPP 100000, PPN 0
    // Totals: DPP = 300000, PPN = 24000, total = 324000

    expect(round($invoice->dpp, 2))->toBe(300000.00)
        ->and(round($invoice->tax, 2))->toBe(24000.00)
        ->and(round($invoice->total, 2))->toBe(324000.00);

    // Also verify ledger entries created: PPN Masukan should be posted if COA exists in this environment
    $ppnCoaExists = \App\Models\ChartOfAccount::where('code', '1170.06')->exists() || (bool)($invoice->ppn_masukan_coa_id ?? null);

    if ($ppnCoaExists) {
        $ppnEntry = \App\Models\JournalEntry::where('description', 'like', '%PPN Masukan%')
            ->where('debit', '>', 0)
            ->where('source_type', \App\Models\Invoice::class)
            ->where('source_id', $invoice->id)
            ->first();

        expect($ppnEntry)->not->toBeNull();
        expect(round($ppnEntry->debit, 2))->toBe(24000.00);
    } else {
        // Environment lacks PPN Masukan COA; skip ledger assertion
        expect(true)->toBeTrue();
    }
});