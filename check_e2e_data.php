<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$u = \App\Models\User::where('email', 'ralamzah@gmail.com')->first();
echo "User: " . ($u ? $u->name . " | cabang_id: " . $u->cabang_id : "NOT FOUND") . "\n";

$s = \App\Models\Supplier::first();
echo "Supplier: id=" . $s->id . " name=" . $s->perusahaan . "\n";

$p = \App\Models\Product::where('is_manufacture', false)->first();
echo "Product: id=" . $p->id . " name=" . $p->name . " sku=" . $p->sku . "\n";

$w = \App\Models\Warehouse::first();
echo "Warehouse: id=" . $w->id . " name=" . $w->name . "\n";

$c = \App\Models\Cabang::first();
echo "Cabang: id=" . $c->id . " name=" . $c->nama . "\n";

$po = \App\Models\PurchaseOrder::latest()->first();
if ($po) echo "Latest PO: id=" . $po->id . " num=" . $po->po_number . " status=" . $po->status . "\n";

$pi = \App\Models\Invoice::latest()->first();
if ($pi) echo "Latest Invoice: id=" . $pi->id . " num=" . $pi->invoice_number . " status=" . $pi->status . "\n";
else echo "No invoices\n";

$coa_ap = \App\Models\ChartOfAccount::where('code', '2110')->first();
echo "AP COA: " . ($coa_ap ? $coa_ap->id . " - " . $coa_ap->perusahaan : "NOT FOUND") . "\n";

$coa_inv = \App\Models\ChartOfAccount::where('code', 'like', '1400%')->first();
echo "Inventory COA: " . ($coa_inv ? $coa_inv->id . " - " . $coa_inv->perusahaan . " code:" . $coa_inv->code : "NOT FOUND") . "\n";

$coa_ppn = \App\Models\ChartOfAccount::where('name', 'like', '%PPN%')->orWhere('perusahaan', 'like', '%PPN%')->first();
echo "PPN COA: " . ($coa_ppn ? $coa_ppn->id . " code:" . $coa_ppn->code . " - " . $coa_ppn->perusahaan : "NOT FOUND") . "\n";

echo "\nProducts count: " . \App\Models\Product::count() . "\n";
echo "OR count: " . \App\Models\OrderRequest::count() . "\n";
echo "PO count: " . \App\Models\PurchaseOrder::count() . "\n";
echo "PR count: " . \App\Models\PurchaseReceipt::count() . "\n";
echo "Invoice count: " . \App\Models\Invoice::count() . "\n";
echo "VendorPayment count: " . \App\Models\VendorPayment::count() . "\n";

// List all COA codes with relevant ranges
$coas = \App\Models\ChartOfAccount::whereIn('code', ['1400','1410','1411','2110','2100','5000','1121'])->get();
foreach ($coas as $coa) {
    echo "COA: " . $coa->code . " - " . $coa->perusahaan . " id=" . $coa->id . "\n";
}
