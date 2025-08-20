<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Customer;
use App\Services\CreditValidationService;

try {
    echo "Testing CreditValidationService...\n";
    
    $customer = Customer::find(2);
    if (!$customer) {
        echo "Customer with ID 2 not found\n";
        exit(1);
    }
    
    echo "Customer found: {$customer->name}\n";
    
    $creditService = new CreditValidationService();
    
    echo "Testing getOverdueInvoices...\n";
    $overdueInvoices = $creditService->getOverdueInvoices($customer);
    echo "Success! Found " . $overdueInvoices->count() . " overdue invoices\n";
    
    echo "Testing getCreditSummary...\n";
    $creditSummary = $creditService->getCreditSummary($customer);
    echo "Success! Credit summary retrieved\n";
    echo "Credit Limit: " . number_format($creditSummary['credit_limit'], 0, ',', '.') . "\n";
    echo "Current Usage: " . number_format($creditSummary['current_usage'], 0, ',', '.') . "\n";
    
    echo "All tests passed!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    exit(1);
}
