<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$invoiceId = DB::table('invoices')
    ->where('invoice_number', 'INV-TEST-INV-LOCKED')
    ->value('id');

echo json_encode([
    'invoice_id' => $invoiceId ? (int) $invoiceId : null,
]) . PHP_EOL;
