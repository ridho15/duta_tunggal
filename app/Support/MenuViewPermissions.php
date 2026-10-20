<?php

namespace App\Support;

/**
 * Izin "lihat" (view any + view) per kode peran agar setiap peran bisa membuka
 * semua menu yang diizinkan matriks Spesifikasi UI/UX 5.2.
 *
 * Hanya izin MELIHAT. Izin membuat/mengubah/menyetujui tetap mengikuti RoleSeeder.
 * Dipakai oleh migrasi 2026_10_19_100000_grant_menu_view_permissions dan RoleSeeder.
 */
class MenuViewPermissions
{
    /** Role Spatie yang diberi izin per kode peran (Auditor, Delivery Driver, IT Support tidak diubah). */
    public const ROLES = [
        'SM'  => ['Sales Manager'],
        'SLS' => ['Sales', 'Customer Service'],
        'KSR' => ['Kasir'],
        'GDG' => ['Admin Inventory', 'Inventory Manager', 'Warehouse Staff', 'Checker'],
        'PUR' => ['Purchasing', 'Purchasing Manager'],
        'FIN' => ['Finance Manager', 'Accounting', 'Admin Keuangan'],
    ];

    /** Nama resource permission (tanpa awalan "view any"/"view") per kode peran. */
    public const RESOURCES = [
        // Cek Stok
        'SM' => ['inventory stock'],
        // Cek Stok, Perintah Kirim (DO), Permintaan Pembelian, Retur
        'SLS' => ['inventory stock', 'delivery order', 'order request', 'return product', 'customer return'],
        // Cek Stok, Customer, Perintah Kirim (DO), Piutang & Hutang
        'KSR' => ['inventory stock', 'customer', 'delivery order', 'account receivable'],
        // Siapkan & Kirim, Terima Barang (+QC), Cek Stok, Pindah Stok, Permintaan Pembelian,
        // Retur Pembelian, Retur, Laporan Stok
        'GDG' => [
            'warehouse confirmation', 'purchase receipt', 'purchase receipt item', 'quality control',
            'inventory stock', 'stock transfer', 'order request', 'purchase return',
            'return product', 'customer return', 'stock movement',
        ],
        // Terima Barang (+QC), Cek Stok, Invoice Pembelian, Pengajuan Dana, Laporan Stok
        'PUR' => [
            'purchase receipt', 'purchase receipt item', 'quality control', 'inventory stock',
            'invoice', 'voucher request', 'stock movement',
        ],
        // Akun & Pajak, Cek Stok, Customer, Supplier, Bayar Supplier, Pengajuan Dana, Retur,
        // Terima Pembayaran, Piutang & Hutang, Laporan Stok
        'FIN' => [
            'chart of account', 'currency', 'tax setting', 'inventory stock', 'customer', 'supplier',
            'vendor payment', 'voucher request', 'customer return', 'customer receipt',
            'account receivable', 'account payable', 'ageing schedule', 'stock movement', 'invoice',
        ],
    ];

    /**
     * @return array<string, array<int, string>> nama role => daftar nama permission
     */
    public static function permissionsByRole(): array
    {
        $result = [];

        foreach (self::ROLES as $code => $roles) {
            $permissions = [];
            foreach (self::RESOURCES[$code] ?? [] as $resource) {
                $permissions[] = 'view any ' . $resource;
                $permissions[] = 'view ' . $resource;
            }

            foreach ($roles as $role) {
                $result[$role] = array_values(array_unique(array_merge($result[$role] ?? [], $permissions)));
            }
        }

        return $result;
    }
}
