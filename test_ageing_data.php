<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Invoice;
use App\Models\AccountReceivable;
use App\Models\AccountPayable;
use App\Models\AgeingSchedule;
use Carbon\Carbon;

echo "Creating simplified test data for Aging Report...\n";

// Get existing cabang or skip
$cabang = \App\Models\Cabang::first();
if (!$cabang) {
    echo "No cabang found, skipping test data creation\n";
    exit(1);
}

// Create test invoices and AR/AP records with different aging scenarios
$testData = [
    // Current (0-30 days) - AR
    [
        'type' => 'receivable',
        'invoice_date' => Carbon::now()->subDays(15),
        'due_date' => Carbon::now()->addDays(15),
        'amount' => 1000000,
        'remaining' => 1000000,
        'bucket' => 'Current',
        'days_outstanding' => 15,
    ],
    // 31-60 days - AR
    [
        'type' => 'receivable',
        'invoice_date' => Carbon::now()->subDays(45),
        'due_date' => Carbon::now()->subDays(15),
        'amount' => 2000000,
        'remaining' => 2000000,
        'bucket' => '31–60',
        'days_outstanding' => 45,
    ],
    // 61-90 days - AR
    [
        'type' => 'receivable',
        'invoice_date' => Carbon::now()->subDays(75),
        'due_date' => Carbon::now()->subDays(45),
        'amount' => 1500000,
        'remaining' => 1500000,
        'bucket' => '61–90',
        'days_outstanding' => 75,
    ],
    // >90 days - AR
    [
        'type' => 'receivable',
        'invoice_date' => Carbon::now()->subDays(120),
        'due_date' => Carbon::now()->subDays(90),
        'amount' => 3000000,
        'remaining' => 3000000,
        'bucket' => '>90',
        'days_outstanding' => 120,
    ],
    // Current payable
    [
        'type' => 'payable',
        'invoice_date' => Carbon::now()->subDays(10),
        'due_date' => Carbon::now()->addDays(20),
        'amount' => 800000,
        'remaining' => 800000,
        'bucket' => 'Current',
        'days_outstanding' => 10,
    ],
    // 31-60 days payable
    [
        'type' => 'payable',
        'invoice_date' => Carbon::now()->subDays(50),
        'due_date' => Carbon::now()->subDays(20),
        'amount' => 1200000,
        'remaining' => 1200000,
        'bucket' => '31–60',
        'days_outstanding' => 50,
    ],
];

$createdAR = 0;
$createdAP = 0;

foreach ($testData as $index => $data) {
    try {
        // Create invoice with minimal required fields
        $invoice = Invoice::create([
            'invoice_number' => 'TEST-' . str_pad($index + 1, 3, '0', STR_PAD_LEFT),
            'from_model_type' => 'App\\Models\\SaleOrder', // Required field
            'from_model_id' => 1, // Dummy ID
            'invoice_date' => $data['invoice_date'],
            'due_date' => $data['due_date'],
            'total' => $data['amount'],
            'status' => 'sent', // Use valid status
            'subtotal' => $data['amount'],
        ]);

        // Create AR/AP record
        if ($data['type'] === 'receivable') {
            $ar = AccountReceivable::create([
                'invoice_id' => $invoice->id,
                'customer_id' => 1, // Dummy customer ID
                'cabang_id' => $cabang->id,
                'total' => $data['amount'],
                'amount' => $data['amount'],
                'remaining' => $data['remaining'],
                'status' => 'Belum Lunas',
            ]);

            // Create ageing schedule
            AgeingSchedule::create([
                'ageable_type' => AccountReceivable::class,
                'ageable_id' => $ar->id,
                'from_model_type' => 'App\\Models\\SaleOrder',
                'from_model_id' => 1,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'],
                'bucket' => $data['bucket'],
                'days_outstanding' => $data['days_outstanding'],
                'amount' => $data['remaining'],
            ]);

            $createdAR++;
            echo "Created AR invoice {$invoice->invoice_number} - {$data['bucket']} days\n";
        } else {
            $ap = AccountPayable::create([
                'invoice_id' => $invoice->id,
                'supplier_id' => 1, // Dummy supplier ID
                'total' => $data['amount'],
                'amount' => $data['amount'],
                'remaining' => $data['remaining'],
                'status' => 'Belum Lunas',
            ]);

            // Create ageing schedule
            AgeingSchedule::create([
                'ageable_type' => AccountPayable::class,
                'ageable_id' => $ap->id,
                'from_model_type' => 'App\\Models\\PurchaseOrder',
                'from_model_id' => 1,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'],
                'bucket' => $data['bucket'],
                'days_outstanding' => $data['days_outstanding'],
                'amount' => $data['remaining'],
            ]);

            $createdAP++;
            echo "Created AP invoice {$invoice->invoice_number} - {$data['bucket']} days\n";
        }
    } catch (\Exception $e) {
        echo "Error creating test data for index {$index}: " . $e->getMessage() . "\n";
    }
}

echo "\nTest data creation completed!\n";
echo "Summary:\n";
echo "- Account Receivables created: {$createdAR}\n";
echo "- Account Payables created: {$createdAP}\n";
echo "- Total Ageing Schedules: " . AgeingSchedule::count() . "\n";
echo "- Total Invoices: " . Invoice::count() . "\n";