<?php

$projectRoot = dirname(__DIR__);
require $projectRoot . '/vendor/autoload.php';
$app = require_once $projectRoot . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$inv = \App\Models\Invoice::where('from_model_type','App\\Models\\PurchaseOrder')->orderBy('id','desc')->first();
if (! $inv) {
    echo "NO_INVOICE\n";
    exit(0);
}

echo "INV: {$inv->id} NUM: " . ($inv->invoice_number ?? 'N/A') . " TAX: " . ($inv->tax ?? 0) . "\n";

$entries = \App\Models\JournalEntry::where('source_type', \App\Models\Invoice::class)->where('source_id', $inv->id)->get();
if ($entries->isEmpty()) {
    echo "NO_JOURNAL_ENTRIES_FOR_INVOICE\n";
    exit(0);
}

foreach ($entries as $e) {
    echo sprintf("%d\tCOA:%s\tDEBIT:%s\tCREDIT:%s\tDESC:%s\n", $e->id, $e->coa_id, $e->debit, $e->credit, substr($e->description ?? '', 0, 120));
}
