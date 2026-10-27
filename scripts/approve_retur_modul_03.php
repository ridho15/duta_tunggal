<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ReturnProduct;
use App\Services\ReturnProductService;

$ret = ReturnProduct::where('return_number', 'DO-TEST-CHK-RETURR')->first();
if ($ret) {
    app(ReturnProductService::class)->updateQuantityFromModel($ret);
    echo "Retur {$ret->return_number} berhasil disetujui, stok karantina & DO pengganti dibuat!\n";
} else {
    echo "Dokumen retur DO-TEST-CHK-RETURR tidak ditemukan!\n";
}
