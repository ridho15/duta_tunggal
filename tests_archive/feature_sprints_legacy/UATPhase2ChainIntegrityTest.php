<?php

use App\Models\User;
use App\Models\Cabang;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\Currency;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\InventoryStock;
use App\Models\AccountPayable;
use App\Models\Invoice;
use App\Models\Customer;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\SuratJalan;
use App\Services\PurchaseReturnService;
use App\Services\DeliveryOrderTransitions;
use App\Services\SuratJalanDocumentBuilder;
use App\Filament\Resources\DeliveryOrderResource;
use App\Filament\Resources\SalesInvoiceResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    test()->seed(\Database\Seeders\CabangSeeder::class);
    test()->seed(\Database\Seeders\WarehouseSeeder::class);
    test()->seed(\Database\Seeders\SupplierSeeder::class);
    test()->seed(\Database\Seeders\ChartOfAccountSeeder::class);
    test()->seed(\Database\Seeders\CurrencySeeder::class);
    test()->seed(\Database\Seeders\UnitOfMeasureSeeder::class);
    test()->seed(\Database\Seeders\ProductSeeder::class);
});

function findFilamentComponent(array $components, string $name) {
    foreach ($components as $component) {
        if (method_exists($component, 'getName') && $component->getName() === $name) {
            return $component;
        }
        if (method_exists($component, 'getChildComponents')) {
            $found = findFilamentComponent($component->getChildComponents(), $name);
            if ($found) {
                return $found;
            }
        }
    }
    return null;
}

it('Poin 2: Purchase return approval decrements stock, decrements AP, and creates balanced journals', function () {
    $cabang = Cabang::first();
    $warehouse = Warehouse::withoutGlobalScopes()->first();
    $supplier = Supplier::withoutGlobalScopes()->first();
    $currency = Currency::first();
    $user = User::factory()->create(['cabang_id' => $cabang->id]);
    test()->actingAs($user);

    $product = Product::factory()->create([
        'sku' => 'PR-TEST-' . Str::random(6),
        'cabang_id' => $cabang->id,
        'supplier_id' => $supplier->id,
        'cost_price' => 50000,
    ]);

    // Initial stock = 10
    InventoryStock::updateOrCreate(
        ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
        ['qty_available' => 10, 'qty_reserved' => 0]
    );

    $po = PurchaseOrder::create([
        'supplier_id' => $supplier->id,
        'po_number' => 'PO-' . strtoupper(Str::random(6)),
        'order_date' => now(),
        'status' => 'completed',
        'expected_date' => now()->addDays(7),
        'total_amount' => 500000,
        'currency_id' => $currency->id,
        'cabang_id' => $cabang->id,
        'warehouse_id' => $warehouse->id,
        'tempo_hutang' => 30,
        'created_by' => $user->id,
        'approved_by' => $user->id,
        'date_approved' => now(),
    ]);

    $poItem = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'product_id' => $product->id,
        'quantity' => 10,
        'unit_price' => 50000,
        'currency_id' => $currency->id,
    ]);

    $receipt = PurchaseReceipt::create([
        'purchase_order_id' => $po->id,
        'receipt_number' => 'RC-' . strtoupper(Str::random(6)),
        'receipt_date' => now(),
        'status' => 'completed',
        'received_by' => $user->id,
        'total_received' => 500000,
        'currency_id' => $currency->id,
        'cabang_id' => $cabang->id,
        'created_by' => $user->id,
    ]);

    $receiptItem = PurchaseReceiptItem::create([
        'purchase_receipt_id' => $receipt->id,
        'purchase_order_item_id' => $poItem->id,
        'product_id' => $product->id,
        'qty_received' => 10,
        'qty_accepted' => 10,
        'qty_rejected' => 0,
        'warehouse_id' => $warehouse->id,
    ]);

    // Active invoice and AP for this receipt
    $invoice = Invoice::create([
        'invoice_number' => 'INV-PR-' . strtoupper(Str::random(6)),
        'invoice_date' => now(),
        'due_date' => now()->addDays(30),
        'status' => 'unpaid',
        'total' => 500000,
        'subtotal' => 500000,
        'purchase_receipts' => [$receipt->id],
        'from_model_type' => PurchaseReceipt::class,
        'from_model_id' => $receipt->id,
        'currency_id' => $currency->id,
        'cabang_id' => $cabang->id,
        'created_by' => $user->id,
    ]);

    $ap = AccountPayable::create([
        'supplier_id' => $supplier->id,
        'invoice_id' => $invoice->id,
        'total' => 500000,
        'paid' => 0,
        'remaining' => 500000,
        'status' => 'Belum Lunas',
        'cabang_id' => $cabang->id,
    ]);

    // Create Purchase Return for 3 items
    $service = app(PurchaseReturnService::class);
    $return = $service->create([
        'purchase_receipt_id' => $receipt->id,
        'return_date' => now(),
        'nota_retur' => 'NR-TEST-' . strtoupper(Str::random(6)),
        'status' => 'draft',
        'cabang_id' => $cabang->id,
        'created_by' => $user->id,
    ]);

    PurchaseReturnItem::create([
        'purchase_return_id' => $return->id,
        'purchase_receipt_item_id' => $receiptItem->id,
        'product_id' => $product->id,
        'qty_returned' => 3,
        'unit_price' => 50000,
        'reason' => 'Defective',
    ]);

    // Approve the return directly from draft
    $approved = $service->approve($return);
    expect($approved)->toBeTrue();

    // Check stock was decremented from 10 to 7
    $stock = InventoryStock::where('product_id' , $product->id)->where('warehouse_id', $warehouse->id)->value('qty_available');
    expect((float) $stock)->toBe(7.0);

    // Check AP remaining decreased by 150.000 (from 500.000 to 350.000)
    $ap->refresh();
    expect((float) $ap->remaining)->toBe(350000.0);

    // Check balanced journal entry
    $journals = \App\Models\JournalEntry::where('source_type', PurchaseReturn::class)
        ->where('source_id', $return->id)
        ->get();
    expect($journals)->toHaveCount(2)
        ->and((float) $journals->sum('debit'))->toBe(150000.0)
        ->and((float) $journals->sum('credit'))->toBe(150000.0);
});

it('Poin 4: Delivery Order allows transition to completed and Surat Jalan PDF outputs complete quantity breakdown', function () {
    $cabang = Cabang::first();
    $warehouse = Warehouse::withoutGlobalScopes()->first();
    $currency = Currency::first();
    $customer = Customer::factory()->create(['cabang_id' => $cabang->id]);
    $user = User::factory()->create(['cabang_id' => $cabang->id]);
    test()->actingAs($user);

    $product = Product::factory()->create([
        'sku' => 'DO-TEST-' . Str::random(6),
        'cabang_id' => $cabang->id,
    ]);

    // Create SO for 10 units
    $so = SaleOrder::create([
        'so_number' => 'SO-DO-' . strtoupper(Str::random(6)),
        'customer_id' => $customer->id,
        'cabang_id' => $cabang->id,
        'order_date' => now(),
        'status' => 'approved',
        'created_by' => $user->id,
        'currency_id' => $currency->id,
        'total_amount' => 1000000,
    ]);

    $soItem = SaleOrderItem::create([
        'sale_order_id' => $so->id,
        'product_id' => $product->id,
        'quantity' => 10,
        'delivered_quantity' => 0,
        'unit_price' => 100000,
        'currency_id' => $currency->id,
    ]);

    // Create DO 1 for 4 units
    $do1 = DeliveryOrder::create([
        'do_number' => 'DO-01-' . strtoupper(Str::random(6)),
        'cabang_id' => $cabang->id,
        'status' => 'approved',
        'delivery_date' => now(),
        'created_by' => $user->id,
    ]);
    $do1->salesOrders()->attach($so->id);

    $do1Item = DeliveryOrderItem::create([
        'delivery_order_id' => $do1->id,
        'sale_order_item_id' => $soItem->id,
        'product_id' => $product->id,
        'quantity' => 4,
        'status' => 'confirmed',
    ]);

    // Verify DO transitions allow complete directly from approved
    expect(DeliveryOrderTransitions::allows('approved', 'completed'))->toBeTrue();

    // Complete DO 1
    $transitions = app(DeliveryOrderTransitions::class);
    $transitions->complete($do1, ['actor' => $user]);
    $do1->refresh();
    expect($do1->status)->toBe('completed');

    // Create Surat Jalan for DO 1
    $sj1 = SuratJalan::create([
        'sj_number' => 'SJ-01-' . strtoupper(Str::random(6)),
        'cabang_id' => $cabang->id,
        'status' => SuratJalan::STATUS_ISSUED,
        'issued_at' => now(),
        'created_by' => $user->id,
    ]);
    $sj1->deliveryOrder()->attach($do1->id);

    // Build document data
    $builder = app(SuratJalanDocumentBuilder::class);
    $doc1 = $builder->build($sj1);

    expect($doc1['groups'][0]['items'][0]['ordered_quantity'])->toBe('10')
        ->and($doc1['groups'][0]['items'][0]['delivered_quantity'])->toBe('0')
        ->and($doc1['groups'][0]['items'][0]['quantity'])->toBe('4')
        ->and($doc1['groups'][0]['items'][0]['remaining_quantity'])->toBe('6');

    // Render HTML and check headers and values
    $html1 = view('pdf.surat-jalan', ['suratJalan' => $sj1, 'doc' => $doc1])->render();
    expect($html1)
        ->toContain('Total Pesan')
        ->toContain('Sudah Dikirim')
        ->toContain('Kirim Sekarang')
        ->toContain('Sisa Pesanan');

    // Now create DO 2 for remaining 6 units
    $do2 = DeliveryOrder::create([
        'do_number' => 'DO-02-' . strtoupper(Str::random(6)),
        'cabang_id' => $cabang->id,
        'status' => 'approved',
        'delivery_date' => now(),
        'created_by' => $user->id,
    ]);
    $do2->salesOrders()->attach($so->id);

    $do2Item = DeliveryOrderItem::create([
        'delivery_order_id' => $do2->id,
        'sale_order_item_id' => $soItem->id,
        'product_id' => $product->id,
        'quantity' => 6,
        'status' => 'confirmed',
    ]);

    $sj2 = SuratJalan::create([
        'sj_number' => 'SJ-02-' . strtoupper(Str::random(6)),
        'cabang_id' => $cabang->id,
        'status' => SuratJalan::STATUS_ISSUED,
        'issued_at' => now(),
        'created_by' => $user->id,
    ]);
    $sj2->deliveryOrder()->attach($do2->id);

    $doc2 = $builder->build($sj2);
    expect($doc2['groups'][0]['items'][0]['ordered_quantity'])->toBe('10')
        ->and($doc2['groups'][0]['items'][0]['delivered_quantity'])->toBe('4')
        ->and($doc2['groups'][0]['items'][0]['quantity'])->toBe('6')
        ->and($doc2['groups'][0]['items'][0]['remaining_quantity'])->toBe('0');
});

it('Poin 5: Fully delivered SO is excluded from DO options, and fully invoiced SO is excluded from Sales Invoice options', function () {
    $cabang = Cabang::first();
    $currency = Currency::first();
    $customer = Customer::factory()->create(['cabang_id' => $cabang->id]);
    $user = User::factory()->create(['cabang_id' => $cabang->id]);
    test()->actingAs($user);

    $product = Product::factory()->create([
        'sku' => 'FLT-TEST-' . Str::random(6),
        'cabang_id' => $cabang->id,
    ]);

    // Create SO for 5 units with Kirim Langsung
    $so = SaleOrder::create([
        'so_number' => 'SO-FLT-' . strtoupper(Str::random(6)),
        'customer_id' => $customer->id,
        'cabang_id' => $cabang->id,
        'order_date' => now(),
        'tipe_pengiriman' => 'Kirim Langsung',
        'status' => 'approved',
        'currency_id' => $currency->id,
        'created_by' => $user->id,
        'total_amount' => 500000,
    ]);

    $soItem = SaleOrderItem::create([
        'sale_order_id' => $so->id,
        'product_id' => $product->id,
        'quantity' => 5,
        'delivered_quantity' => 0,
        'unit_price' => 100000,
        'currency_id' => $currency->id,
    ]);

    // Check deliverable SOs
    $deliverableQuery = SaleOrder::withoutGlobalScopes()->deliverable();
    $deliverableIds = $deliverableQuery->pluck('sale_orders.id')->all();
    expect($deliverableIds)->toContain($so->id);

    // Create DO delivering ALL 5 units
    $do = DeliveryOrder::create([
        'do_number' => 'DO-FLT-' . strtoupper(Str::random(6)),
        'cabang_id' => $cabang->id,
        'status' => 'approved',
        'delivery_date' => now(),
        'created_by' => $user->id,
    ]);
    $do->salesOrders()->attach($so->id);

    DeliveryOrderItem::create([
        'delivery_order_id' => $do->id,
        'sale_order_item_id' => $soItem->id,
        'product_id' => $product->id,
        'quantity' => 5,
    ]);

    app(DeliveryOrderTransitions::class)->complete($do, ['actor' => $user]);

    // After DO is completed, SO is completed and has 0 available quantity
    $deliverableAfter = SaleOrder::withoutGlobalScopes()->deliverable()->pluck('sale_orders.id')->all();
    expect($deliverableAfter)->not->toContain($so->id);

    // Ambil Sendiri SO is never deliverable via DO
    $soPickup = SaleOrder::create([
        'so_number' => 'SO-PICKUP-' . strtoupper(Str::random(6)),
        'customer_id' => $customer->id,
        'cabang_id' => $cabang->id,
        'order_date' => now(),
        'tipe_pengiriman' => 'Ambil Sendiri',
        'status' => 'approved',
        'currency_id' => $currency->id,
        'created_by' => $user->id,
        'total_amount' => 200000,
    ]);

    expect(SaleOrder::withoutGlobalScopes()->deliverable()->pluck('sale_orders.id')->all())->not->toContain($soPickup->id);
});

it('Poin 6: Sales invoice unit price is disabled and dehydrated', function () {
    $schema = SalesInvoiceResource::form(new \Filament\Forms\Form(new \App\Filament\Resources\SalesInvoiceResource\Pages\CreateSalesInvoice()))->getComponents();

    $deliveryOrderItemsRepeater = findFilamentComponent($schema, 'delivery_order_items');
    expect($deliveryOrderItemsRepeater)->not->toBeNull();

    $unitPriceField = findFilamentComponent($deliveryOrderItemsRepeater->getChildComponents(), 'unit_price');
    expect($unitPriceField)->not->toBeNull()
        ->and($unitPriceField->isDisabled())->toBeTrue()
        ->and($unitPriceField->isDehydrated())->toBeTrue();
});
