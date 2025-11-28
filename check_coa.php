<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "COA accounts with PPn in name:" . PHP_EOL;
$coas = App\Models\ChartOfAccount::where('name', 'like', '%PPn%')->orWhere('name', 'like', '%ppn%')->get(['code', 'name']);
foreach($coas as $coa) {
    echo $coa->code . ' - ' . $coa->name . PHP_EOL;
}

echo PHP_EOL . "COA accounts with PPN in name:" . PHP_EOL;
$coas = App\Models\ChartOfAccount::where('name', 'like', '%PPN%')->get(['code', 'name']);
foreach($coas as $coa) {
    echo $coa->code . ' - ' . $coa->name . PHP_EOL;
}

echo PHP_EOL . "COA accounts with Biaya Pengiriman in name:" . PHP_EOL;
$coas = App\Models\ChartOfAccount::where('name', 'like', '%Biaya Pengiriman%')->get(['code', 'name']);
foreach($coas as $coa) {
    echo $coa->code . ' - ' . $coa->name . PHP_EOL;
}