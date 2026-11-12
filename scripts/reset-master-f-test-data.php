<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\ChartOfAccount;
use App\Models\Rak;
use App\Models\Vehicle;
use App\Models\Driver;
use App\Models\User;
use App\Models\ApprovalRule;

echo "=== [RESET MASTER TEST DATA (SIKLUS F)] ===\n";

// 1. Hapus Produk Uji F
$delProducts = Product::withTrashed()->where('sku', 'like', 'PRD-F-%')->forceDelete();
echo "Dihapus: {$delProducts} Product (PRD-F-%)\n";

// 2. Hapus Customer Uji F
$delCust = Customer::withTrashed()->where('code', 'like', 'CUST-F-%')->forceDelete();
echo "Dihapus: {$delCust} Customer (CUST-F-%)\n";

// 3. Hapus Supplier Uji F
$delSup = Supplier::withTrashed()->where('code', 'like', 'SUP-F-%')->forceDelete();
echo "Dihapus: {$delSup} Supplier (SUP-F-%)\n";

// 4. Hapus COA Uji F
$delCoa = ChartOfAccount::withTrashed()->where('code', '6313')->forceDelete();
echo "Dihapus: {$delCoa} ChartOfAccount (6313)\n";

// 5. Hapus Rak Uji F
$delRak = Rak::withTrashed()->where('code', 'like', 'RAK-F-%')->forceDelete();
echo "Dihapus: {$delRak} Rak (RAK-F-%)\n";

// 6. Hapus Kendaraan Uji F
$delVeh = Vehicle::withTrashed()->where('plate', 'B 9901 DT')->forceDelete();
echo "Dihapus: {$delVeh} Vehicle (B 9901 DT)\n";

// 7. Hapus Driver Uji F
$delDri = Driver::withTrashed()->where('name', 'Pak Joko Santoso')->forceDelete();
echo "Dihapus: {$delDri} Driver (Pak Joko Santoso)\n";

// 8. Hapus User Uji F
$user = User::where('email', 'staf.gudang.f@dutanggal.test')->first();
if ($user) {
    $user->roles()->detach();
    $user->delete();
    echo "Dihapus: 1 User (staf.gudang.f@dutanggal.test)\n";
} else {
    echo "Dihapus: 0 User (staf.gudang.f@dutanggal.test)\n";
}

// 9. Hapus Approval Rule Uji F
$delRule = ApprovalRule::where('label', 'like', '%di atas Rp 20.000.000%')->delete();
echo "Dihapus: {$delRule} ApprovalRule\n";

echo "=== [RESET DATA MASTER SELESAI DENGAN SUKSES] ===\n";

