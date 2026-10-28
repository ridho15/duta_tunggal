<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Cabang;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\SaleOrder;
use App\Models\SuratJalan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

echo "=== MENYIAPKAN FIXTURES UAT MODUL 05 (SALES INVOICE & PIUTANG TERKUNCI) ===\n";

// 1. Set user Keuangan & Finance Manager agar memiliki akses penuh ke Cabang 27
User::where('email', 'admin_keuangan@example.com')->update([
    'manage_type' => ['all'],
    'cabang_id' => 27,
]);
User::where('email', 'finance_manager@example.com')->update([
    'manage_type' => ['all'],
    'cabang_id' => 27,
]);

// 2. Pastikan TaxSetting PPN 11% aktif & SO-TEST-SJ-01 memiliki pengaturan pajak Eksklusif 11%
\App\Models\TaxSetting::updateOrCreate(
    ['name' => 'PPN'],
    [
        'rate' => 11,
        'effective_date' => '2020-01-01',
        'status' => 1,
        'type' => 'PPN',
    ]
);

$so = SaleOrder::with('saleOrderItem')->where('so_number', 'SO-TEST-SJ-01')->first();
if ($so) {
    $so->update([
        'tipe_pajak' => 'Eksklusif',
        'ppn_rate' => 11,
    ]);
    foreach ($so->saleOrderItem as $item) {
        $item->update([
            'tax' => 11,
            'tipe_pajak' => 'Eksklusif',
        ]);
    }
}

// 3. Bersihkan invoice sebelumnya yang mungkin pernah dibuat untuk SJ-20261028-0001
$sj = SuratJalan::where('sj_number', 'SJ-20261028-0001')->first();
if ($sj) {
    $invoices = Invoice::where('from_model_type', SuratJalan::class)
        ->where('from_model_id', $sj->id)
        ->orWhereJsonContains('surat_jalan_ids', $sj->id)
        ->get();

    foreach ($invoices as $inv) {
        JournalEntry::where('source_type', Invoice::class)->where('source_id', $inv->id)->delete();
        $inv->invoiceItem()->delete();
        $inv->forceDelete();
    }
}

echo "Fixtures UAT Modul 05 berhasil disiapkan!\n";
if ($sj && $so) {
    echo "Surat Jalan Siap Difakturkan: {$sj->sj_number} (ID: {$sj->id}) dari SO: {$so->so_number} (ID: {$so->id})\n";
}
