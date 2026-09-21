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
