<?php

use App\Enums\PaymentStatus;
use App\Models\AccountPayable;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderCurrency;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\LedgerPostingService;
use App\Services\PurchaseInvoiceAccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createReceiptBackedPurchaseInvoiceRepairFixture(): array
{
    ChartOfAccount::create([
        'code' => '2100.10',
        'name' => 'PENERIMAAN BARANG BELUM TERTAGIH',
        'type' => 'liability',
        'is_active' => 1,
    ]);
    ChartOfAccount::create([
        'code' => '2110',
        'name' => 'HUTANG DAGANG',
        'type' => 'liability',
        'is_active' => 1,
    ]);
    ChartOfAccount::create([
        'code' => '1170.06',
        'name' => 'PPN MASUKAN',
        'type' => 'asset',
        'is_active' => 1,
    ]);
    ChartOfAccount::create([
        'code' => '1140.01',
        'name' => 'PERSEDIAAN',
        'type' => 'asset',
        'is_active' => 1,
    ]);
    ChartOfAccount::create([
        'code' => '6100',
        'name' => 'BIAYA UMUM',
        'type' => 'expense',
        'is_active' => 1,
    ]);

    User::factory()->create();

    $cabang = Cabang::factory()->create();
    $supplier = Supplier::factory()->create(['cabang_id' => $cabang->id]);
    $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id]);
    $product = Product::factory()->create(['cabang_id' => $cabang->id]);

    Currency::create([
        'name' => 'Rupiah',
        'symbol' => 'Rp',
        'code' => 'IDR',
        'to_rupiah' => 1,
    ]);
    $usd = Currency::create([
        'name' => 'US Dollar',
        'symbol' => '$',
        'code' => 'USD',
        'to_rupiah' => 15000,
    ]);

    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'po_number' => 'PO-TEST-USD-001',
        'status' => 'completed',
        'cabang_id' => $cabang->id,
        'total_amount' => 8879400,
    ]);

    PurchaseOrderCurrency::create([
        'purchase_order_id' => $po->id,
        'currency_id' => $usd->id,
        'nominal' => 15000,
    ]);

    $poItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $product->id,
        'quantity' => 10,
        'unit_price' => 53.33,
        'discount' => 0,
        'tax' => 11,
        'tipe_pajak' => 'eklusif',
        'currency_id' => $usd->id,
    ]);

    $receipts = collect([1, 2])->map(function (int $index) use ($po, $poItem, $product, $warehouse, $usd, $cabang) {
        $receipt = PurchaseReceipt::factory()->create([
            'receipt_number' => 'PR-TEST-' . $index,
            'purchase_order_id' => $po->id,
            'currency_id' => $usd->id,
            'status' => 'completed',
            'cabang_id' => $cabang->id,
            'other_cost' => 0,
        ]);

        PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $product->id,
            'qty_received' => 5,
            'qty_accepted' => 5,
            'qty_rejected' => 0,
            'warehouse_id' => $warehouse->id,
            'status' => 'completed',
        ]);

        return $receipt;
    });

    $invoice = PurchaseInvoiceAccountingService::withoutObserverPosting(function () use ($po, $receipts, $usd, $cabang, $product) {
        return Invoice::factory()->create([
            'invoice_number' => 'PINV-TEST-USD-001',
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $po->id,
            'purchase_order_ids' => [$po->id],
            'purchase_receipts' => $receipts->pluck('id')->map(fn ($id) => (string) $id)->all(),
            'currency_id' => $usd->id,
            'exchange_rate' => 15000,
            'subtotal' => 26931.65,
            'dpp' => 26931.65,
            'tax' => 11,
            'ppn_rate' => 11,
            'other_fee' => [],
            'total' => 29894.13,
            'status' => Invoice::STATUS_DRAFT,
            'cabang_id' => $cabang->id,
        ]);
    });

    $invoice->invoiceItem()->create([
        'product_id' => $product->id,
        'quantity' => 5,
        'price' => 53.33,
        'subtotal' => 266.65,
        'total' => 266.65,
        'tax_rate' => 11,
        'tax_amount' => 29.33,
    ]);
    $invoice->invoiceItem()->create([
        'product_id' => $product->id,
        'quantity' => 5,
        'price' => 5333,
        'subtotal' => 26665,
        'total' => 26665,
        'tax_rate' => 11,
        'tax_amount' => 2933.15,
    ]);

    AccountPayable::create([
        'invoice_id' => $invoice->id,
        'supplier_id' => $supplier->id,
        'total' => 29894.13,
        'paid' => 0,
        'remaining' => 29894.13,
        'status' => PaymentStatus::UNPAID->value,
        'cabang_id' => $cabang->id,
    ]);

    app(LedgerPostingService::class)->postInvoice($invoice->fresh());

    return compact('invoice', 'po', 'poItem', 'receipts', 'product');
}

it('dry-runs receipt backed purchase invoice repair without changing data', function () {
    ['invoice' => $invoice] = createReceiptBackedPurchaseInvoiceRepairFixture();

    $before = $invoice->fresh('invoiceItem', 'accountPayable');

    $this->artisan('procurement:audit-repair-purchase-invoice', [
        '--invoice' => $invoice->id,
    ])
        ->expectsOutputToContain('MISMATCH')
        ->expectsOutputToContain('Dry-run only')
        ->assertExitCode(1);

    $after = $invoice->fresh('invoiceItem', 'accountPayable');

    expect((float) $after->subtotal)->toBe((float) $before->subtotal)
        ->and((float) $after->total)->toBe((float) $before->total)
        ->and((float) $after->accountPayable->total)->toBe((float) $before->accountPayable->total)
        ->and((float) $after->invoiceItem()->orderByDesc('price')->first()->price)->toBe(5333.0);
});

it('applies receipt backed purchase invoice repair and reposts journals in idr', function () {
    ['invoice' => $invoice] = createReceiptBackedPurchaseInvoiceRepairFixture();

    $this->artisan('procurement:audit-repair-purchase-invoice', [
        '--invoice' => $invoice->id,
        '--apply' => true,
    ])
        ->expectsOutputToContain('repaired successfully')
        ->assertExitCode(0);

    $invoice = $invoice->fresh('invoiceItem', 'accountPayable');

    expect((float) $invoice->subtotal)->toBe(533.3)
        ->and((float) $invoice->dpp)->toBe(533.3)
        ->and(round((float) $invoice->ppn_rate, 2))->toBe(11.0)
        ->and((float) $invoice->ppn_amount)->toBe(58.66)
        ->and((float) $invoice->total)->toBe(591.96)
        ->and((float) $invoice->accountPayable->total)->toBe(591.96)
        ->and((float) $invoice->accountPayable->remaining)->toBe(591.96);

    $items = $invoice->invoiceItem()->orderBy('id')->get();
    expect($items)->toHaveCount(2);
    expect($items->pluck('price')->map(fn ($value) => (float) $value)->all())->toBe([53.33, 53.33])
        ->and($items->pluck('total')->map(fn ($value) => (float) $value)->all())->toBe([266.65, 266.65]);

    $openJournals = JournalEntry::withoutGlobalScopes()
        ->where('source_type', Invoice::class)
        ->where('source_id', $invoice->id)
        ->where('is_reversal', false)
        ->whereNull('reversal_of_transaction_id')
        ->get();

    expect((float) $openJournals->sum('debit'))->toBe(8879400.0)
        ->and((float) $openJournals->sum('credit'))->toBe(8879400.0)
        ->and($openJournals->contains(fn (JournalEntry $entry) => (float) $entry->debit === 7999500.0))->toBeTrue()
        ->and($openJournals->contains(fn (JournalEntry $entry) => (float) $entry->debit === 879900.0))->toBeTrue()
        ->and($openJournals->contains(fn (JournalEntry $entry) => (float) $entry->credit === 8879400.0))->toBeTrue();

    expect(JournalEntry::withoutGlobalScopes()
        ->where('source_type', Invoice::class)
        ->where('source_id', $invoice->id)
        ->where('is_reversal', true)
        ->count())->toBe(3);
});

it('normalizes malformed receipt backed invoice item prices from purchase order items', function () {
    ['invoice' => $invoice] = createReceiptBackedPurchaseInvoiceRepairFixture();

    $data = app(PurchaseInvoiceAccountingService::class)->normalizeFormData([
        'from_model_type' => PurchaseOrder::class,
        'from_model_id' => $invoice->from_model_id,
        'purchase_receipts' => $invoice->purchase_receipts,
        'selected_purchase_receipts' => $invoice->purchase_receipts,
        'invoiceItem' => [
            [
                'product_id' => $invoice->invoiceItem()->first()->product_id,
                'quantity' => 5,
                'price' => '5.333,00',
                'total' => '26.665,00',
            ],
        ],
        'other_fees' => [],
        'receiptBiayaItems' => [],
        'subtotal' => '26.931,65',
        'ppn_rate' => 11,
    ]);

    expect($data['invoiceItem'])->toHaveCount(2)
        ->and((float) $data['invoiceItem'][0]['price'])->toBe(53.33)
        ->and((float) $data['invoiceItem'][1]['price'])->toBe(53.33)
        ->and((float) $data['subtotal'])->toBe(533.3)
        ->and((float) $data['total'])->toBe(591.96);
});
