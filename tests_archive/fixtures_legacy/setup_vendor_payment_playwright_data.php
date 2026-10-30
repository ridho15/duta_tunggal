<?php
/**
 * Deterministic fixture for Playwright VendorPayment tests (C1/C2/C3/C4).
 *
 * Depends on purchase invoice fixture data and creates:
 * - 1 approved Payment Request with selected invoices
 * - Account Payable rows with positive remaining amounts
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$now = now();

$fixture = [
    'supplier_code' => 'SUPP001',
    'po_number' => 'PO-TEST-INV-B23',
    'receipt_open' => 'PR-TEST-INV-OPEN',
    'invoice_locked' => 'INV-TEST-INV-LOCKED',
    'invoice_open' => 'INV-TEST-VP-OPEN',
    'payment_request_number' => 'PR-TEST-VP-APPROVED',
];

DB::transaction(function () use ($now, $fixture) {
    $testUser = DB::table('users')->where('email', 'ralamzah@gmail.com')->first();
    $userId = $testUser?->id ?? DB::table('users')->value('id') ?? 1;
    $cabangId = $testUser?->cabang_id ?? DB::table('cabangs')->value('id') ?? 1;

    $supplier = DB::table('suppliers')->where('code', $fixture['supplier_code'])->first();
    if (!$supplier) {
        throw new RuntimeException('Supplier fixture not found: ' . $fixture['supplier_code']);
    }

    $po = DB::table('purchase_orders')->where('po_number', $fixture['po_number'])->first();
    if (!$po) {
        throw new RuntimeException('PO fixture not found: ' . $fixture['po_number'] . '. Run setup_purchase_invoice_playwright_data.php first.');
    }

    $lockedInvoice = DB::table('invoices')->where('invoice_number', $fixture['invoice_locked'])->first();
    if (!$lockedInvoice) {
        throw new RuntimeException('Locked invoice fixture not found: ' . $fixture['invoice_locked']);
    }

    $receiptOpen = DB::table('purchase_receipts')->where('receipt_number', $fixture['receipt_open'])->first();
    if (!$receiptOpen) {
        throw new RuntimeException('Open receipt fixture not found: ' . $fixture['receipt_open']);
    }

    // Create / refresh second invoice for vendor payment fixture
    $openInvoiceId = DB::table('invoices')->where('invoice_number', $fixture['invoice_open'])->value('id');
    $openInvoicePayload = [
        'from_model_type' => 'App\\Models\\PurchaseOrder',
        'from_model_id' => $po->id,
        'invoice_date' => now()->toDateString(),
        'subtotal' => 600000,
        'tax' => 0,
        'other_fee' => json_encode([]),
        'total' => 666000,
        'due_date' => now()->addDays(30)->toDateString(),
        'status' => 'sent',
        'ppn_rate' => 11,
        'dpp' => 600000,
        'supplier_name' => $supplier->perusahaan,
        'supplier_phone' => $supplier->phone,
        'purchase_receipts' => json_encode([$receiptOpen->id]),
        'purchase_order_ids' => json_encode([$po->id]),
        'cabang_id' => $cabangId,
        'updated_at' => $now,
    ];

    if ($openInvoiceId) {
        DB::table('invoices')->where('id', $openInvoiceId)->update($openInvoicePayload);
        DB::table('invoice_items')->where('invoice_id', $openInvoiceId)->delete();
    } else {
        $openInvoiceId = DB::table('invoices')->insertGetId(array_merge($openInvoicePayload, [
            'invoice_number' => $fixture['invoice_open'],
            'created_at' => $now,
        ]));
    }

    $productId = DB::table('products')->value('id') ?? 1;
    DB::table('invoice_items')->insert([
        'invoice_id' => $openInvoiceId,
        'product_id' => $productId,
        'quantity' => 6,
        'price' => 100000,
        'discount' => 0,
        'tax_rate' => 11,
        'tax_amount' => 66000,
        'subtotal' => 600000,
        'total' => 666000,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $invoiceIds = [(int) $lockedInvoice->id, (int) $openInvoiceId];

    // Ensure account payables exist with remaining > 0
    $apRows = [
        [
            'invoice_id' => (int) $lockedInvoice->id,
            'total' => (float) ($lockedInvoice->total ?? 444000),
            'paid' => 0,
            'remaining' => (float) ($lockedInvoice->total ?? 444000),
            'status' => 'Belum Lunas',
        ],
        [
            'invoice_id' => (int) $openInvoiceId,
            'total' => 666000,
            'paid' => 0,
            'remaining' => 666000,
            'status' => 'Belum Lunas',
        ],
    ];

    foreach ($apRows as $row) {
        $existingApId = DB::table('account_payables')->where('invoice_id', $row['invoice_id'])->value('id');
        $payload = [
            'supplier_id' => $supplier->id,
            'total' => $row['total'],
            'paid' => $row['paid'],
            'remaining' => $row['remaining'],
            'status' => $row['status'],
            'cabang_id' => $cabangId,
            'created_by' => $userId,
            'updated_at' => $now,
        ];

        if ($existingApId) {
            DB::table('account_payables')->where('id', $existingApId)->update($payload);
        } else {
            DB::table('account_payables')->insert(array_merge($payload, [
                'invoice_id' => $row['invoice_id'],
                'created_at' => $now,
            ]));
        }
    }

    // Cleanup old fixture payment requests
    DB::table('payment_requests')->where('request_number', 'like', 'PR-TEST-VP-%')->delete();

    $totalAmount = (float) DB::table('account_payables')->whereIn('invoice_id', $invoiceIds)->sum('remaining');

    DB::table('payment_requests')->insert([
        'request_number' => $fixture['payment_request_number'],
        'supplier_id' => $supplier->id,
        'cabang_id' => $cabangId,
        'requested_by' => $userId,
        'approved_by' => $userId,
        'request_date' => now()->toDateString(),
        'payment_date' => now()->addDays(7)->toDateString(),
        'total_amount' => $totalAmount,
        'selected_invoices' => json_encode($invoiceIds),
        'notes' => 'Fixture PR for Playwright VendorPayment tests',
        'approval_notes' => 'Approved fixture',
        'status' => 'approved',
        'approved_at' => $now,
        'vendor_payment_id' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    echo "✅ Vendor payment fixture ready\n";
    echo "   Payment Request : {$fixture['payment_request_number']}\n";
    echo "   Supplier        : ({$fixture['supplier_code']}) {$supplier->perusahaan}\n";
    echo "   Invoices        : {$fixture['invoice_locked']}, {$fixture['invoice_open']}\n";
    echo "   Total Remaining : Rp " . number_format($totalAmount, 0, ',', '.') . "\n";
});
