<?php

use App\Models\Cabang;
use App\Models\Customer;
use App\Models\Currency;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliverySchedule;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\SuratJalan;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Models\ChartOfAccount;
use App\Services\CustomerReceiptAllocator;
use App\Services\LedgerPostingService;
use App\Services\InvoiceService;
use App\Services\SuratJalanService;
use App\Services\SalesOrderService;
use App\Services\SequentialNumberGenerator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$admin = User::where('email', 'superadmin@gmail.com')->first();
Auth::login($admin);

$customer = Customer::where('name', 'PT CITRA TEKNIK MEDICA')->first();
$product = Product::where('sku', 'PROF025032')->first();
$warehouse = Warehouse::where('name', 'GUDANG DUTA-K01.2')->first();
$driver = Driver::where('name', 'UAT Driver Test')->first();
$vehicle = Vehicle::where('plate', 'B 1234 UAT')->first();
$currency = Currency::where('code', 'IDR')->first();

// Ensure clean stock baseline of 100 Batang in WH 3
\App\Models\InventoryStock::updateOrCreate(
    ['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'rak_id' => null],
    ['qty_available' => 100, 'qty_reserved' => 0]
);

echo "Starting UAT Flow with Super Admin...\n";

// 1. Create SO with #shipTo Project & PIC
$soNumber = app(SalesOrderService::class)->generateSoNumber($customer->cabang_id);
$so = SaleOrder::create([
    'so_number' => $soNumber,
    'customer_id' => $customer->id,
    'cabang_id' => $customer->cabang_id,
    'order_date' => now()->toDateString(),
    'delivery_date' => now()->addDays(7)->toDateString(),
    'tipe_pengiriman' => 'Kirim Langsung',
    'shipped_to' => 'Jl. Peta No. 226, Bandung',
    'shipping_recipient_name' => 'Proyek RSUD Santosa Kopo',
    'shipping_recipient_phone' => 'Pak Hendra (0812-3456-7890)',
    'currency_id' => $currency->id,
    'exchange_rate' => 1.0,
    'tempo_pembayaran' => 30,
    'shipping_cost' => 25000,
    'status' => 'draft',
    'created_by' => $admin->id,
    'total_amount' => 100000,
]);

$soItem = SaleOrderItem::create([
    'sale_order_id' => $so->id,
    'product_id' => $product->id,
    'quantity' => 10,
    'delivered_quantity' => 0,
    'unit_price' => 10000,
    'discount' => 0,
    'tax' => 0,
    'tipe_pajak' => 'None',
]);

echo "1. SO Created: ID {$so->id} | {$so->so_number}\n";

// 2. Approve SO
$so->update([
    'status' => 'approved',
    'approved_by' => $admin->id,
    'approved_at' => now(),
]);
echo "2. SO Approved: {$so->so_number}\n";

// 3. Create DO & Reserve Stock
$doNumber = app(SalesOrderService::class)->generateDoNumber($warehouse->id);
$do = DeliveryOrder::create([
    'do_number' => $doNumber,
    'cabang_id' => $customer->cabang_id,
    'warehouse_id' => $warehouse->id,
    'delivery_date' => now()->toDateString(),
    'status' => 'confirmed',
    'created_by' => $admin->id,
]);
$do->salesOrders()->attach($so->id);

$doItem = DeliveryOrderItem::create([
    'delivery_order_id' => $do->id,
    'sale_order_item_id' => $soItem->id,
    'product_id' => $product->id,
    'quantity' => 10,
    'checked_quantity' => 10,
]);

// Reserve stock in Warehouse
$invStock = \App\Models\InventoryStock::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->first();
$invStock->increment('qty_reserved', 10);
StockReservation::create([
    'product_id' => $product->id,
    'warehouse_id' => $warehouse->id,
    'sale_order_id' => $so->id,
    'quantity' => 10,
    'status' => 'reserved',
]);
echo "3. DO Created: ID {$do->id} | {$do->do_number} | Stock Reserved: +10\n";

// 4. Confirm Dispatch & Create Surat Jalan
$sjNumber = app(SuratJalanService::class)->generateCode($customer->cabang_id);
$sj = SuratJalan::create([
    'sj_number' => $sjNumber,
    'issued_at' => now(),
    'status' => SuratJalan::STATUS_ISSUED,
    'signed_by' => $admin->id,
    'created_by' => $admin->id,
    'cabang_id' => $customer->cabang_id,
]);
$sj->deliveryOrder()->attach($do->id);

$scheduleNum = SequentialNumberGenerator::generate('delivery_schedules', 'schedule_number', 'SCH-', 4, 'Ymd');
DeliverySchedule::create([
    'schedule_number' => $scheduleNum,
    'scheduled_date' => now()->toDateString(),
    'delivery_method' => 'internal',
    'driver_id' => $driver->id,
    'vehicle_id' => $vehicle->id,
    'driver_name' => $driver->name,
    'vehicle_info' => $vehicle->plate,
    'surat_jalan_id' => $sj->id,
    'status' => 'delivered',
]);

// Mark DO received & release reservation, decrement physical stock via StockMovement
$do->update(['status' => 'received', 'received_at' => now()]);
$invStock->decrement('qty_reserved', 10);
$soItem->update(['delivered_quantity' => 10]);
$so->update(['status' => 'received']);

StockMovement::create([
    'product_id' => $product->id,
    'warehouse_id' => $warehouse->id,
    'rak_id' => $invStock->rak_id,
    'type' => 'sales',
    'quantity' => 10,
    'reference_id' => $do->id,
    'date' => now(),
]);
echo "4. SJ Issued: ID {$sj->id} | {$sj->sj_number} | Physical Stock: -10, Reserved Released\n";

// 5. Create Sales Invoice
$invNumber = app(InvoiceService::class)->generateSalesInvoiceNumber($customer->cabang_id, false);
$arCoa = ChartOfAccount::where('code', '1121')->first() ?? ChartOfAccount::where('name', 'like', '%Piutang%')->where('type', 'Asset')->first();
$salesCoa = ChartOfAccount::where('code', '4110')->first() ?? ChartOfAccount::where('name', 'like', '%Penjualan%')->where('type', 'Revenue')->first();

$invoice = Invoice::create([
    'invoice_number' => $invNumber,
    'cabang_id' => $customer->cabang_id,
    'customer_name' => $customer->name,
    'customer_phone' => $customer->phone,
    'invoice_date' => now()->toDateString(),
    'due_date' => now()->addDays(30)->toDateString(),
    'subtotal' => 100000,
    'tax' => 0,
    'other_fee' => 25000, // Ongkir
    'total' => 125000, // 100000 items + 25000 ongkir
    'dpp' => 100000,
    'status' => Invoice::STATUS_SENT,
    'ar_coa_id' => $arCoa->id,
    'from_model_type' => SaleOrder::class,
    'from_model_id' => $so->id,
    'currency_id' => $currency->id,
    'exchange_rate' => 1.0,
    'surat_jalan_ids' => [$sj->id],
]);

InvoiceItem::create([
    'invoice_id' => $invoice->id,
    'product_id' => $product->id,
    'quantity' => 10,
    'price' => 10000,
    'gross_amount' => 100000,
    'subtotal' => 100000,
    'total' => 100000,
    'cost_price' => 7500,
    'coa_id' => $salesCoa->id,
]);

// Post Invoice Journal
$entry = app(LedgerPostingService::class)->postInvoice($invoice);
echo "5. Invoice Created & Posted: ID {$invoice->id} | {$invoice->invoice_number} | Total: Rp 125.000\n";

// 6. Process Customer Receipt
$kasCoa = ChartOfAccount::where('code', '1111.01')->first() ?? ChartOfAccount::where('name', 'like', '%KAS BESAR%')->first() ?? ChartOfAccount::where('name', 'like', '%Kas%')->first();

$receipt = DB::transaction(function () use ($admin, $customer, $invoice, $kasCoa) {
    $rcpt = \App\Models\CustomerReceipt::create([
        'customer_id' => $customer->id,
        'cabang_id' => $customer->cabang_id,
        'invoice_id' => $invoice->id,
        'selected_invoices' => [$invoice->id],
        'invoice_receipts' => [$invoice->id => 125000],
        'payment_date' => now()->toDateString(),
        'payment_method' => 'Cash',
        'coa_id' => $kasCoa->id,
        'total_payment' => 125000,
        'total_payment_idr' => 125000,
        'overpayment_amount' => 0,
        'notes' => 'Pelunasan Faktur ' . $invoice->invoice_number,
        'status' => 'Paid',
        'created_by' => $admin->id,
        'currency_id' => $invoice->currency_id,
        'exchange_rate' => 1.0,
    ]);

    \App\Models\CustomerReceiptItem::create([
        'customer_receipt_id' => $rcpt->id,
        'invoice_id' => $invoice->id,
        'currency_id' => $invoice->currency_id,
        'exchange_rate' => 1.0,
        'method' => 'Cash',
        'amount' => 125000,
        'amount_idr' => 125000,
        'coa_id' => $kasCoa->id,
        'payment_date' => now()->toDateString(),
        'selected_invoices' => [$invoice->id],
    ]);

    $invoice->update([
        'remaining_amount' => 0,
        'status' => Invoice::STATUS_PAID,
        'payment_status' => 'paid',
    ]);

    $ar = \App\Models\AccountReceivable::where('invoice_id', $invoice->id)->first();
    if ($ar) {
        $ar->update([
            'paid' => 125000,
            'remaining' => 0,
            'status' => \App\Enums\PaymentStatus::PAID->value,
        ]);
    }

    app(LedgerPostingService::class)->postCustomerReceipt($rcpt->fresh());

    return $rcpt;
});

echo "6. Customer Receipt Created: ID {$receipt->id} | Total Paid: Rp 125.000\n";

// Save JSON for Puppeteer
$out = [
    'so_id' => $so->id,
    'do_id' => $do->id,
    'sj_id' => $sj->id,
    'invoice_id' => $invoice->id,
    'receipt_id' => $receipt->id,
];
file_put_contents('/tmp/uat_doc_ids.json', json_encode($out, JSON_PRETTY_PRINT));
echo "IDs saved to /tmp/uat_doc_ids.json: " . json_encode($out) . "\n";
