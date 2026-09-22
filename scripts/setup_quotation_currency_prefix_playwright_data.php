<?php

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\OrderRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cabang = Cabang::firstOrCreate(
    ['kode' => 'QT-USD'],
    ['nama' => 'Cabang Quotation USD', 'alamat' => 'Test']
);

$usd = Currency::updateOrCreate(
    ['code' => 'USD'],
    ['name' => 'US Dollar', 'symbol' => '$', 'to_rupiah' => 16000]
);

Currency::firstOrCreate(
    ['code' => 'IDR'],
    ['name' => 'Rupiah', 'symbol' => 'Rp', 'to_rupiah' => 1]
);

$user = User::firstOrCreate(
    ['email' => 'ralamzah@gmail.com'],
    [
        'name' => 'Ralamzah',
        'password' => Hash::make('ridho123'),
        'cabang_id' => $cabang->id,
        'manage_type' => ['all'],
    ]
);

$user->forceFill([
    'name' => 'Ralamzah',
])->save();

$customer = Customer::firstOrCreate(
    ['code' => 'QT-USD-CUST'],
    [
        'name' => 'Customer Quotation USD',
        'perusahaan' => 'PT Customer Quotation USD',
        'address' => 'Test',
        'telephone' => '021',
        'phone' => '081',
        'email' => 'qt-usd@example.test',
        'tipe' => 'PKP',
        'fax' => '021',
        'nik_npwp' => '1234567890123456',
        'tempo_kredit' => 0,
        'kredit_limit' => 0,
        'cabang_id' => $cabang->id,
    ]
);

$supplier = Supplier::firstOrCreate(
    ['code' => 'QT-USD-SUPP'],
    [
        'perusahaan' => 'PT Supplier Quotation USD',
        'address' => 'Test',
        'phone' => '021',
        'email' => 'supplier-qt-usd@example.test',
        'handphone' => '081',
        'fax' => '021',
        'npwp' => '00.000.000.0-000.000',
        'tempo_hutang' => 0,
        'kontak_person' => 'Tester',
        'cabang_id' => $cabang->id,
    ]
);

$uom = UnitOfMeasure::firstOrCreate(['name' => 'Pieces'], ['abbreviation' => 'PCS']);
$category = ProductCategory::firstOrCreate(
    ['kode' => 'QT-USD-CAT'],
    ['name' => 'Quotation USD Category', 'cabang_id' => $cabang->id]
);

$product = Product::updateOrCreate(
    ['sku' => 'QT-USD-PROD'],
    [
        'name' => 'Quotation USD Product',
        'cabang_id' => $cabang->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'supplier_id' => $supplier->id,
        'cost_price' => 160000,
        'sell_price' => 160000,
        'kode_merk' => 'QT',
        'is_active' => true,
        'is_manufacture' => false,
        'is_raw_material' => false,
    ]
);

$orderRequest = OrderRequest::updateOrCreate(
    ['request_number' => 'OR-QT-USD-PREFIX'],
    [
        'request_date' => now()->toDateString(),
        'needed_date' => now()->addDays(7)->toDateString(),
        'note' => 'Quotation USD prefix fixture',
        'status' => 'approved',
        'priority' => 'medium',
        'currency_id' => $usd->id,
        'cabang_id' => $cabang->id,
        'requested_by' => $user->id,
        'created_by' => $user->id,
        'customer_id' => $customer->id,
    ]
);

$orderRequest->orderRequestItem()->updateOrCreate(
    ['product_id' => $product->id],
    [
        'supplier_id' => $supplier->id,
        'cabang_id' => $cabang->id,
        'quantity' => 1,
        'unit_price' => 10,
        'original_price' => 10,
        'tax' => 0,
        'tipe_pajak' => 'none',
        'currency_id' => $usd->id,
    ]
);

echo json_encode([
    'order_request_id' => $orderRequest->id,
    'currency_id' => $usd->id,
    'currency_symbol' => $usd->symbol,
]) . PHP_EOL;
