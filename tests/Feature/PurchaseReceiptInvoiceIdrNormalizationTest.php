<?php

use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderCurrency;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\PurchaseReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('normalizes purchase receipt invoice amounts to idr when po item is in usd', function () {
    $this->seed(\Database\Seeders\ChartOfAccountSeeder::class);

    $idr = Currency::create([
        'name' => 'IDR',
        'symbol' => 'Rp',
        'code' => 'IDR',
        'to_rupiah' => 1,
    ]);

    $usd = Currency::create([
        'name' => 'USD',
        'symbol' => '$',
        'code' => 'USD',
        'to_rupiah' => 16000,
    ]);

    \App\Models\User::factory()->create();
    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();

    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
    ]);

    PurchaseOrderCurrency::create([
        'purchase_order_id' => $po->id,
        'currency_id' => $usd->id,
        'nominal' => 16000,
    ]);

    $poItem = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 5,
        'discount' => 0,
        'tax' => 0,
        'tipe_pajak' => 'Non Pajak',
        'currency_id' => $usd->id,
    ]);

    $receipt = PurchaseReceipt::create([
        'receipt_number' => 'RN-USD-5',
        'purchase_order_id' => $po->id,
        'receipt_date' => now(),
        'received_by' => 1,
        'currency_id' => $usd->id,
        'status' => 'completed',
        'cabang_id' => $supplier->cabang_id,
    ]);

    PurchaseReceiptItem::create([
        'purchase_receipt_id' => $receipt->id,
        'purchase_order_item_id' => $poItem->id,
        'product_id' => $product->id,
        'qty_received' => 1,
        'qty_accepted' => 1,
        'qty_rejected' => 0,
        'warehouse_id' => $warehouse->id,
        'status' => 'completed',
    ]);

    $receipt->refresh();
    $receipt->load('purchaseReceiptItem.purchaseOrderItem', 'purchaseOrder');

    $result = app(PurchaseReceiptService::class)->createAutomaticInvoiceFromReceipt($receipt);

    $invoice = Invoice::where('from_model_type', PurchaseReceipt::class)
        ->where('from_model_id', $receipt->id)
        ->first();
    
    expect($invoice)->not->toBeNull();
    expect($result['status'])->toBeIn(['created', 'skipped']);

    expect((float) $invoice->subtotal)->toBe(80000.0)
        ->and((float) $invoice->total)->toBe(80000.0)
        ->and((float) $invoice->dpp)->toBe(80000.0);

    $invoiceItem = $invoice->invoiceItem()->first();
    expect((float) $invoiceItem->price)->toBe(80000.0)
        ->and((float) $invoiceItem->total)->toBe(80000.0);

    $journal = \App\Models\JournalEntry::where('source_type', Invoice::class)
        ->where('source_id', $invoice->id)
        ->first();

    expect($journal)->not->toBeNull();
    expect((float) $journal->debit)->toBe(80000.0);
});
