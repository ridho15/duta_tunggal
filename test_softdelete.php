<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\AccountPayable;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Testing AccountPayable query for soft deletes\n";

// Query normal (tanpa withTrashed)
$query = AccountPayable::query();
$totals = $query->selectRaw('
    SUM(total) as total_amount,
    SUM(paid) as paid_amount,
    SUM(remaining) as remaining_amount,
    COUNT(*) as record_count
')->first();

echo "Normal query results:\n";
echo "Total Amount: " . ($totals->total_amount ?? 0) . "\n";
echo "Paid Amount: " . ($totals->paid_amount ?? 0) . "\n";
echo "Remaining Amount: " . ($totals->remaining_amount ?? 0) . "\n";
echo "Record Count: " . ($totals->record_count ?? 0) . "\n\n";

// Query dengan withTrashed
$queryWithTrashed = AccountPayable::withTrashed();
$totalsWithTrashed = $queryWithTrashed->selectRaw('
    SUM(total) as total_amount,
    SUM(paid) as paid_amount,
    SUM(remaining) as remaining_amount,
    COUNT(*) as record_count
')->first();

echo "Query with withTrashed() results:\n";
echo "Total Amount: " . ($totalsWithTrashed->total_amount ?? 0) . "\n";
echo "Paid Amount: " . ($totalsWithTrashed->paid_amount ?? 0) . "\n";
echo "Remaining Amount: " . ($totalsWithTrashed->remaining_amount ?? 0) . "\n";
echo "Record Count: " . ($totalsWithTrashed->record_count ?? 0) . "\n\n";

// Hitung jumlah soft deleted
$softDeletedCount = AccountPayable::onlyTrashed()->count();
echo "Number of soft deleted records: $softDeletedCount\n";

?>