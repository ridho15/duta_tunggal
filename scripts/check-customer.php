<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\CustomerService;

$service = new CustomerService();
$data = [
    'code' => 'CUST-F-2026-001',
    'name' => 'Bpk. Hendra Gunawan',
    'perusahaan' => 'PT Maju Elektrik Persada',
    'nik_npwp' => '3273998877660001',
    'phone' => '081299887766',
    'address' => 'Jl. Riau No. 45 Bandung',
    'tipe' => 'PKP',
    'tipe_pembayaran' => 'Kredit',
    'tempo_kredit' => 30,
    'kredit_limit' => 75000000,
];

$dups = $service->findDuplicates($data);
echo "Duplicates count: " . $dups->count() . "\n";
foreach ($dups as $d) {
    echo "  - Reason: {$d['reason']} | Customer: {$d['customer']->code} {$d['customer']->name}\n";
}