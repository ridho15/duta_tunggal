<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Product;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Cabang;
use Illuminate\Support\Facades\DB;

DB::transaction(function () {
    $now = now();
    $user = User::query()->where('email', 'ralamzah@gmail.com')->first() ?? User::query()->orderBy('id')->first();
    if (! $user) {
        throw new RuntimeException('No user found for journal entry playwright fixture');
    }

    $cabang = Cabang::query()->find($user->cabang_id) ?? Cabang::query()->orderBy('id')->first();
    if (! $cabang) {
        throw new RuntimeException('No cabang found for journal entry playwright fixture');
    }

    User::query()->whereKey($user->id)->update([
        'manage_type' => 'all',
        'cabang_id' => $cabang->id,
    ]);

    $supplier = Supplier::query()->updateOrCreate(
        ['code' => 'SUP-JE-PW-001'],
        [
            'code' => 'SUP-JE-PW-001',
            'name' => 'PT Journal Entry Fixture',
            'cabang_id' => $cabang->id,
            'address' => 'Fixture Address',
            'phone' => '081234567890',
            'handphone' => '081234567891',
            'email' => 'journal-entry-fixture@example.test',
            'perusahaan' => 'PT Journal Entry Fixture',
            'fax' => '021123456',
            'npwp' => '12.345.678.9-012.345',
            'tempo_hutang' => 30,
            'kontak_person' => 'Fixture Contact',
        ]
    );

    $inventoryCoa = ChartOfAccount::query()->where('code', '1140.01')->first()
        ?? ChartOfAccount::query()->orderBy('id')->first();

    if (! $inventoryCoa) {
        throw new RuntimeException('No chart of account found for journal entry playwright fixture');
    }

    $purchaseOrder = PurchaseOrder::query()->updateOrCreate(
        ['po_number' => 'PO-PW-JE-001'],
        [
            'supplier_id' => $supplier->id,
            'order_date' => $now->toDateString(),
            'status' => 'approved',
            'warehouse_id' => DB::table('warehouses')->where('cabang_id', $cabang->id)->value('id') ?? DB::table('warehouses')->value('id'),
            'tempo_hutang' => 30,
            'created_by' => $user->id,
            'cabang_id' => $cabang->id,
        ]
    );

    $product = Product::query()->orderBy('id')->first() ?? Product::factory()->create([
        'supplier_id' => $supplier->id,
    ]);

    $currencyId = DB::table('currencies')->where('code', 'IDR')->value('id') ?? DB::table('currencies')->value('id');

    $purchaseOrderItem = PurchaseOrderItem::query()->updateOrCreate(
        [
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $product->id,
        ],
        [
            'quantity' => 10,
            'unit_price' => 25000,
            'discount' => 0,
            'tax' => 11,
            'tipe_pajak' => 'Non Pajak',
            'currency_id' => $currencyId,
        ]
    );

    $receipt = PurchaseReceipt::query()->updateOrCreate(
        ['receipt_number' => 'PR-PW-JE-001'],
        [
            'purchase_order_id' => $purchaseOrder->id,
            'receipt_date' => $now,
            'received_by' => $user->id,
            'status' => 'completed',
            'currency_id' => DB::table('currencies')->where('code', 'IDR')->value('id') ?? DB::table('currencies')->value('id'),
            'cabang_id' => $cabang->id,
            'notes' => 'Playwright fixture purchase receipt',
        ]
    );

    $receiptItem = PurchaseReceiptItem::query()->updateOrCreate(
        [
            'purchase_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $purchaseOrderItem->id,
        ],
        [
            'product_id' => $product->id,
            'qty_received' => 10,
            'qty_accepted' => 10,
            'qty_rejected' => 0,
            'warehouse_id' => $purchaseOrder->warehouse_id,
            'status' => 'completed',
        ]
    );

    JournalEntry::query()->where('reference', 'JE-PW-001')->delete();

    $journalEntry = JournalEntry::create([
        'coa_id' => $inventoryCoa->id,
        'date' => $now->toDateString(),
        'reference' => 'JE-PW-001',
        'description' => 'Playwright fixture journal for purchase receipt item source',
        'debit' => 250000,
        'credit' => 0,
        'journal_type' => 'purchase',
        'cabang_id' => $cabang->id,
        'source_type' => PurchaseReceiptItem::class,
        'source_id' => $receiptItem->id,
        'created_by' => $user->id,
    ]);

    echo "✅ Journal Entry fixture ready\n";
    echo "   Journal  : {$journalEntry->reference}\n";
    echo "   Receipt  : {$receipt->receipt_number}\n";
    echo "   Item ID  : {$receiptItem->id}\n";
    echo "   Supplier : {$supplier->perusahaan}\n";
});
