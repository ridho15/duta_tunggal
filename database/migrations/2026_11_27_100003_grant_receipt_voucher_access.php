<?php

use App\Support\Access;
use App\Support\AccessDefaults;
use App\Support\AccessManager;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

/**
 * Centang baru "Cetak Voucher Penerimaan" (Terima Pembayaran) untuk peran yang checklist-nya sudah tersimpan
 * dan sudah boleh Cetak Kwitansi; Finance Manager sekaligus mendapat Lihat & Cetak Kwitansi (cetak semua voucher). Pembatasan per penerimaan ada di CustomerReceipt::canPrintVoucher().
 */
return new class extends Migration
{
    public function up(): void
    {
        AccessDefaults::ensurePermissions();
        foreach (Role::query()->get() as $role) {
            if (! $role->permissions()->where('name', Access::CONFIGURED)->exists()) {
                continue;
            }
            $keys = AccessManager::roleKeys($role);
            $printAll = in_array($role->name, config('access.receipt_voucher_all_roles', []), true)
                || in_array($role->base_role, config('access.receipt_voucher_all_roles', []), true);
            $add = $printAll ? AccessDefaults::RECEIPT_VOUCHER_KEYS
                : (in_array('penjualan.receipts.print', $keys, true) ? ['penjualan.receipts.voucher'] : []);
            $add = array_values(array_diff($add, $keys));
            if ($add !== []) {
                AccessManager::saveRoleKeys($role, [...$keys, ...$add], 'update',
                    'Centang baru Cetak Voucher Penerimaan (keputusan Ko Handy 27 Nov 2026)');
            }
        }
        Access::flush();
    }

    public function down(): void
    {
        // Centang dibiarkan; lepas lewat Pengaturan > Peran & Hak Akses bila perlu.
    }
};
