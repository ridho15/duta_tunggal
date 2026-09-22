<?php

/**
 * Fixture bersama untuk tes kontrol internal (T3/T4). Fungsi berawalan `ctl` agar tidak bentrok dengan helper lain.
 */

use App\Models\Quotation;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/** Pengguna dengan peran & izin tertentu (semua cabang). */
function ctlUser(array $ctx, string $role, array $permissions = ['response sales order', 'approve quotation'], array $attributes = []): User
{
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

    $user = User::factory()->create(array_merge(['cabang_id' => $ctx['cabang']->id, 'manage_type' => 'all'], $attributes));
    $user->givePermissionTo($permissions);
    $user->assignRole($role);

    return $user;
}

/** Quotation menunggu persetujuan bernilai $amount, dibuat oleh $creator. */
function ctlQuotation(array $ctx, float $amount, ?User $creator = null, string $status = 'request_approve'): Quotation
{
    return Quotation::create([
        'quotation_number' => 'QO-CTL-'.strtoupper(substr(uniqid(), -8)), 'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id,
        'date' => now(), 'valid_until' => now()->addDays(30), 'currency_id' => $ctx['idr']->id, 'exchange_rate' => 1, 'tempo_pembayaran' => 30,
        'status' => $status, 'created_by' => ($creator ?? $ctx['user'])->id, 'total_amount' => $amount,
    ]);
}

/** Invoice (tanpa observer) untuk SO $so bernilai $total + baris piutang (AR) $remaining; $dueDate opsional (untuk uji jatuh tempo). */
function ctlInvoiceWithAr(array $ctx, \App\Models\SaleOrder $so, float $total, ?float $remaining = null, string $status = 'unpaid', ?string $dueDate = null): \App\Models\Invoice
{
    $invoice = \App\Models\Invoice::withoutEvents(fn () => \App\Models\Invoice::factory()->create([
        'from_model_type' => \App\Models\SaleOrder::class, 'from_model_id' => $so->id, 'customer_name' => $ctx['customer']->name,
        'cabang_id' => $ctx['cabang']->id, 'total' => $total, 'subtotal' => $total, 'status' => $status,
        'due_date' => $dueDate ?? now()->addDays(30)->toDateString(),
    ]));

    \App\Models\AccountReceivable::create([
        'invoice_id' => $invoice->id, 'customer_id' => $ctx['customer']->id, 'total' => $total, 'paid' => $total - ($remaining ?? $total),
        'remaining' => $remaining ?? $total, 'status' => ($remaining ?? $total) > 0 ? 'Belum Lunas' : 'Lunas', 'cabang_id' => $ctx['cabang']->id,
    ]);

    return $invoice;
}

/**
 * Penerimaan customer Lunas ($amount) atas $invoice dalam keadaan SETELAH diposting (tanpa observer): baris AR terbayar penuh, status invoice `paid`,
 * item penerimaan, dan jurnal Dr Bank / Cr Piutang. Mengembalikan [receipt, bankCoa, piutangCoa].
 */
function ctlPaidReceipt(array $ctx, \App\Models\Invoice $invoice, float $amount, string $method = 'Transfer'): array
{
    $bank = \App\Models\ChartOfAccount::firstOrCreate(['code' => '1112.01'], ['name' => 'Bank', 'type' => 'Asset', 'is_active' => true, 'opening_balance' => 0]);
    $piutang = \App\Models\ChartOfAccount::firstOrCreate(['code' => '1120'], ['name' => 'Piutang Dagang', 'type' => 'Asset', 'is_active' => true, 'opening_balance' => 0]);

    $receipt = \App\Models\CustomerReceipt::withoutEvents(fn () => \App\Models\CustomerReceipt::create([
        'invoice_id' => $invoice->id, 'customer_id' => $ctx['customer']->id, 'selected_invoices' => [$invoice->id], 'payment_date' => now()->toDateString(),
        'total_payment' => $amount, 'total_payment_idr' => $amount, 'payment_method' => $method, 'payment_reference' => 'TRX-CTL-'.strtoupper(substr(uniqid(), -6)),
        'coa_id' => $bank->id, 'status' => 'Paid', 'created_by' => $ctx['user']->id, 'cabang_id' => $ctx['cabang']->id, 'exchange_rate' => 1,
    ]));

    \App\Models\CustomerReceiptItem::withoutEvents(fn () => \App\Models\CustomerReceiptItem::create([
        'customer_receipt_id' => $receipt->id, 'invoice_id' => $invoice->id, 'method' => $method, 'amount' => $amount, 'amount_idr' => $amount,
        'coa_id' => $bank->id, 'payment_date' => now()->toDateString(), 'selected_invoices' => [$invoice->id],
    ]));

    $ar = \App\Models\AccountReceivable::where('invoice_id', $invoice->id)->firstOrFail();
    $ar->forceFill(['paid' => $amount, 'remaining' => max(0, (float) $ar->total - $amount), 'status' => $amount >= (float) $ar->total ? 'Lunas' : 'Belum Lunas'])->save();
    $invoice->forceFill(['status' => $amount >= (float) $ar->total ? 'paid' : 'partially_paid'])->saveQuietly();

    foreach ([[$bank->id, $amount, 0], [$piutang->id, 0, $amount]] as [$coaId, $debit, $credit]) {
        \App\Models\JournalEntry::create([
            'coa_id' => $coaId, 'date' => now()->toDateString(), 'reference' => 'REC-'.$receipt->id, 'description' => 'Customer receipt for receipt id '.$receipt->id,
            'debit' => $debit, 'credit' => $credit, 'journal_type' => 'receipt', 'source_type' => \App\Models\CustomerReceipt::class, 'source_id' => $receipt->id,
            'currency_id' => $ctx['idr']->id, 'exchange_rate' => 1, 'cabang_id' => $ctx['cabang']->id,
        ]);
    }

    return [$receipt->fresh(), $bank, $piutang];
}

/**
 * Invoice PENJUALAN terbit bernilai: $qty pcs × Rp $price (bersih diskon) + PPN $taxRate% (+ biaya pengiriman $shipping) lengkap dengan baris piutang (AR)
 * dan akun yang dibutuhkan Nota Kredit (Retur Penjualan, Deposit). Mengembalikan [invoice, invoiceItem, customerSaleOrder].
 */
function ctlCreditInvoice(array $ctx, float $qty = 12, float $price = 100000, float $taxRate = 11, float $shipping = 0, string $status = 'unpaid', ?float $paid = null): array
{
    foreach ([['4120.10', 'Retur Penjualan', 'Expense'], ['2160.04', 'Deposit Pelanggan', 'Liability'], ['2120.06', 'PPN Keluaran', 'Liability'], ['1120', 'Piutang Dagang', 'Asset'], ['6100.02', 'Biaya Pengiriman', 'Expense']] as [$code, $name, $type]) {
        \App\Models\ChartOfAccount::firstOrCreate(['code' => $code], ['name' => $name, 'type' => $type, 'is_active' => true, 'opening_balance' => 0]);
    }

    [$so] = stkSaleOrder($ctx, 1, ['status' => 'completed', 'total_amount' => 1]);
    $subtotal = round($qty * $price, 2);
    $tax = round($subtotal * $taxRate / 100, 2);
    $total = round($subtotal + $tax + $shipping, 2);

    $invoice = \App\Models\Invoice::withoutEvents(fn () => \App\Models\Invoice::factory()->create([
        'from_model_type' => \App\Models\SaleOrder::class, 'from_model_id' => $so->id, 'customer_name' => $ctx['customer']->name, 'cabang_id' => $ctx['cabang']->id,
        'subtotal' => $subtotal, 'dpp' => $subtotal, 'tax' => $taxRate, 'ppn_rate' => $taxRate, 'total' => $total, 'status' => $status, 'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(), 'ar_coa_id' => null, 'revenue_coa_id' => null, 'ppn_keluaran_coa_id' => null, 'biaya_pengiriman_coa_id' => null,
        'other_fee' => $shipping > 0 ? [['amount' => $shipping, 'description' => 'Ongkir', 'type' => 'delivery_cost']] : null,
    ]));

    $item = \App\Models\InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id, 'product_id' => $ctx['product']->id, 'quantity' => $qty, 'price' => $price, 'discount' => 0, 'tax_rate' => $taxRate,
        'tax_amount' => $tax, 'subtotal' => $subtotal, 'total' => round($subtotal + $tax, 2),
    ]);

    $paid ??= $status === 'paid' ? $total : 0;
    \App\Models\AccountReceivable::create([
        'invoice_id' => $invoice->id, 'customer_id' => $ctx['customer']->id, 'total' => $total, 'paid' => $paid, 'remaining' => $total - $paid,
        'status' => $total - $paid > 0 ? 'Belum Lunas' : 'Lunas', 'cabang_id' => $ctx['cabang']->id, 'exchange_rate' => 1,
    ]);

    return [$invoice->fresh(), $item, $so];
}
