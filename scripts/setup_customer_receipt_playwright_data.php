<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptItem;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Invoice;
use App\Models\SaleOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

DB::transaction(function () {
    $now = now();
    $customerName = 'PW Customer Receipt - PT Maju Bersama';

    $user = User::query()->where('email', 'ralamzah@gmail.com')->first() ?? User::query()->orderBy('id')->first();
    if (! $user) {
        throw new RuntimeException('No user found for Customer Receipt Playwright fixture');
    }

    $cabangId = $user->cabang_id ?? DB::table('cabangs')->orderBy('id')->value('id');
    if (! $cabangId) {
        throw new RuntimeException('No cabang found for Customer Receipt Playwright fixture');
    }

    $customer = Customer::query()->updateOrCreate(
        ['code' => 'PW-CR-CUST-001'],
        [
            'name' => $customerName,
            'perusahaan' => $customerName,
            'cabang_id' => $cabangId,
            'address' => 'Fixture Address',
            'telephone' => '081234567890',
            'phone' => '081234567890',
            'fax' => '081234567891',
            'email' => 'pw-customer-receipt@example.test',
            'tipe' => 'PKP',
            'isSpecial' => 0,
            'tempo_kredit' => 30,
            'kredit_limit' => 50000000,
            'tipe_pembayaran' => 'Kredit',
            'nik_npwp' => '1234567890123456',
            'keterangan' => 'Playwright fixture',
        ]
    );

    $existingReceiptIds = DB::table('customer_receipts')
        ->where('customer_id', $customer->id)
        ->pluck('id');

    $existingReceiptItemIds = DB::table('customer_receipt_items')
        ->whereIn('customer_receipt_id', $existingReceiptIds)
        ->pluck('id');

    if ($existingReceiptItemIds->isNotEmpty()) {
        DB::table('journal_entries')
            ->where('source_type', CustomerReceiptItem::class)
            ->whereIn('source_id', $existingReceiptItemIds)
            ->delete();
    }

    DB::table('customer_receipt_items')
        ->whereIn('customer_receipt_id', $existingReceiptIds)
        ->delete();

    DB::table('customer_receipts')
        ->whereIn('id', $existingReceiptIds)
        ->delete();

    $otherSaleOrderIds = DB::table('sale_orders')
        ->where('customer_id', $customer->id)
        ->where('so_number', '!=', 'SO-PW-CR-001')
        ->pluck('id');

    if ($otherSaleOrderIds->isNotEmpty()) {
        DB::table('invoices')
            ->where('from_model_type', SaleOrder::class)
            ->whereIn('from_model_id', $otherSaleOrderIds)
            ->update(['deleted_at' => $now, 'updated_at' => $now]);

        DB::table('sale_orders')
            ->whereIn('id', $otherSaleOrderIds)
            ->update(['deleted_at' => $now, 'updated_at' => $now]);
    }

    User::query()
        ->where('email', 'ralamzah@gmail.com')
        ->update([
            'manage_type' => 'all',
            'cabang_id' => $cabangId,
        ]);

    $invoiceCabang = Cabang::factory()->createOrUpdate([
        'kode' => 'PW-CR-BR-002',
        'nama' => 'Playwright Branch 002',
    ]);

    $saleOrder = SaleOrder::withoutEvents(function () use ($customer, $cabangId, $user, $now) {
        return SaleOrder::query()->updateOrCreate(
            ['so_number' => 'SO-PW-CR-001'],
            [
                'customer_id' => $customer->id,
                'status' => 'completed',
                'order_date' => $now->toDateString(),
                'delivery_date' => $now->toDateString(),
                'cabang_id' => $cabangId,
                'created_by' => $user->id,
            ]
        );
    });

    $invoice = Invoice::withoutEvents(function () use ($saleOrder, $customer, $invoiceCabang, $user, $now) {
        return Invoice::query()->updateOrCreate(
            ['invoice_number' => 'INV-PW-CR-001'],
            [
                'from_model_type' => SaleOrder::class,
                'from_model_id' => $saleOrder->id,
                'invoice_date' => $now->toDateString(),
                'due_date' => $now->copy()->addDays(14)->toDateString(),
                'customer_name' => $customer->name,
                'subtotal' => 250000,
                'tax' => 0,
                'ppn_rate' => 0,
                'total' => 250000,
                'status' => 'unpaid',
                'cabang_id' => $invoiceCabang->id,
                'created_by' => $user->id,
            ]
        );
    });

    DB::table('account_receivables')->where('invoice_id', $invoice->id)->delete();

    $cashCoaId = ChartOfAccount::query()
        ->where('is_active', true)
        ->where('code', 'LIKE', '111%')
        ->orderBy('code')
        ->value('id');

    $fixtureReceipt = CustomerReceipt::query()->create([
        'invoice_id' => $invoice->id,
        'customer_id' => $customer->id,
        'selected_invoices' => [$invoice->id],
        'invoice_receipts' => [(string) $invoice->id => 100000],
        'payment_date' => $now->toDateString(),
        'total_payment' => 100000,
        'coa_id' => $cashCoaId,
        'payment_method' => 'Cash',
        'notes' => 'Playwright fixture receipt',
        'status' => 'Partial',
        'created_by' => $user->id,
        'cabang_id' => $invoiceCabang->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    CustomerReceiptItem::query()->create([
        'customer_receipt_id' => $fixtureReceipt->id,
        'invoice_id' => $invoice->id,
        'method' => 'Cash',
        'amount' => 100000,
        'coa_id' => $cashCoaId,
        'payment_date' => $now->toDateString(),
        'selected_invoices' => [$invoice->id],
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    echo "✅ Customer Receipt fixture ready\n";
    echo "   Customer: {$customerName}\n";
    echo "   Invoice : INV-PW-CR-001\n";
    echo "   Cabang  : {$invoiceCabang->nama}\n";
    echo "   Receipt : Partial fixture (100000 / 250000)\n";
});
