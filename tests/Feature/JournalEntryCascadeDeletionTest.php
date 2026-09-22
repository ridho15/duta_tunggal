<?php

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Rak;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Auth::login($this->user);

    $this->seed(\Database\Seeders\CabangSeeder::class);
});

function makePurchaseReceiptItemWithJournals(): array
{
    $cabang = Cabang::query()->firstOrFail();
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id]);
    $rak = Rak::factory()->create(['warehouse_id' => $warehouse->id]);
    $purchaseOrder = PurchaseOrder::factory()->create(['cabang_id' => $cabang->id]);
    $purchaseOrderItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'product_id' => $product->id,
    ]);
    $purchaseReceipt = PurchaseReceipt::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'cabang_id' => $cabang->id,
    ]);

    $purchaseReceiptItem = PurchaseReceiptItem::factory()->create([
        'purchase_receipt_id' => $purchaseReceipt->id,
        'purchase_order_item_id' => $purchaseOrderItem->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
        'status' => 'pending',
    ]);

    $debitCoa = ChartOfAccount::factory()->create();
    $creditCoa = ChartOfAccount::factory()->create();

    $debitEntry = JournalEntry::withoutEvents(function () use ($purchaseReceiptItem, $debitCoa, $cabang) {
        return JournalEntry::create([
            'coa_id' => $debitCoa->id,
            'date' => now(),
            'reference' => 'PRI-TEST-' . $purchaseReceiptItem->id,
            'description' => 'Receipt item debit test',
            'debit' => 1000,
            'credit' => 0,
            'journal_type' => 'inventory',
            'cabang_id' => $cabang->id,
            'source_type' => PurchaseReceiptItem::class,
            'source_id' => $purchaseReceiptItem->id,
        ]);
    });

    $creditEntry = JournalEntry::withoutEvents(function () use ($purchaseReceiptItem, $creditCoa, $cabang) {
        return JournalEntry::create([
            'coa_id' => $creditCoa->id,
            'date' => now(),
            'reference' => 'PRI-TEST-' . $purchaseReceiptItem->id,
            'description' => 'Receipt item credit test',
            'debit' => 0,
            'credit' => 1000,
            'journal_type' => 'inventory',
            'cabang_id' => $cabang->id,
            'source_type' => PurchaseReceiptItem::class,
            'source_id' => $purchaseReceiptItem->id,
        ]);
    });

    return [$purchaseReceiptItem, $debitEntry, $creditEntry];
}

it('soft deletes journal entries when a purchase receipt item source is deleted', function () {
    [$purchaseReceiptItem, $debitEntry, $creditEntry] = makePurchaseReceiptItemWithJournals();

    $purchaseReceiptItem->delete();

    $this->assertSoftDeleted('purchase_receipt_items', ['id' => $purchaseReceiptItem->id]);
    $this->assertSoftDeleted('journal_entries', ['id' => $debitEntry->id]);
    $this->assertSoftDeleted('journal_entries', ['id' => $creditEntry->id]);
});

it('force deletes journal entries when a purchase receipt item source is force deleted', function () {
    [$purchaseReceiptItem, $debitEntry, $creditEntry] = makePurchaseReceiptItemWithJournals();

    $purchaseReceiptItem->forceDelete();

    $this->assertDatabaseMissing('purchase_receipt_items', ['id' => $purchaseReceiptItem->id]);
    $this->assertDatabaseMissing('journal_entries', ['id' => $debitEntry->id]);
    $this->assertDatabaseMissing('journal_entries', ['id' => $creditEntry->id]);
});