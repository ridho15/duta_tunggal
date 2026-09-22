<?php

namespace App\Support;

use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Throwable;

class ProcurementFailureNotifier
{
    public static function message(Throwable|string|null $error, string $fallback): string
    {
        $message = trim($error instanceof Throwable ? $error->getMessage() : (string) $error);

        if ($message === '') {
            return $fallback;
        }

        $normalized = Str::lower($message);

        $knownMessages = [
            'only draft purchase returns can be submitted for approval' => 'Retur pembelian hanya bisa diajukan saat masih berstatus draft.',
            'only pending purchase returns can be approved' => 'Retur pembelian hanya bisa disetujui saat statusnya masih menunggu persetujuan.',
            'only pending purchase returns can be rejected' => 'Retur pembelian hanya bisa ditolak saat statusnya masih menunggu persetujuan.',
            'merge_next_order requires a target purchase order' => 'Pilih purchase order pengganti sebelum melanjutkan retur dengan opsi gabung ke order berikutnya.',
            'cannot locate purchaseorderitem or its purchaseorder for this qc.' => 'Item pembelian yang terkait dengan QC tidak ditemukan. Muat ulang data lalu coba lagi.',
            'cannot create purchase return: qc has no rejected items.' => 'Retur pembelian tidak dapat dibuat karena belum ada item yang ditolak pada QC.',
            'invalid failed_qc_action' => 'Tindakan lanjutan untuk QC tidak valid. Pilih opsi penanganan yang tersedia lalu coba lagi.',
            'cannot create qc from receipt without associated purchaseorderitem' => 'QC pembelian belum dapat dibuat karena item purchase order yang terkait tidak ditemukan.',
            'cannot exceed ordered quantity' => 'Jumlah item QC tidak boleh melebihi quantity yang dipesan pada purchase order.',
            'cannot exceed received quantity' => 'Jumlah item QC tidak boleh melebihi quantity yang diterima.',
            'passed quantity dan rejected quantity tidak boleh bernilai negatif' => 'Jumlah lolos QC dan jumlah reject tidak boleh bernilai negatif.',
            'total passed dan rejected' => 'Total jumlah lolos dan reject QC melebihi quantity yang tersedia. Periksa kembali hasil QC.',
            'account payable not found for invoice' => 'Data hutang invoice belum tersedia. Periksa invoice terkait sebelum melanjutkan pembayaran vendor.',
            'overpayment is not allowed' => 'Jumlah pembayaran melebihi sisa hutang invoice. Sesuaikan nominal pembayaran lalu coba lagi.',
            'voucher request tidak dapat diajukan' => 'Voucher request belum dapat diajukan. Periksa status voucher lalu coba lagi.',
            'voucher request tidak dapat disetujui' => 'Voucher request belum dapat disetujui. Periksa status voucher lalu coba lagi.',
            'voucher request tidak dapat ditolak' => 'Voucher request belum dapat ditolak. Periksa status voucher lalu coba lagi.',
            'voucher request tidak dapat dibatalkan' => 'Voucher request belum dapat dibatalkan. Periksa status voucher lalu coba lagi.',
            'untuk membuat transaksi otomatis, mohon pilih akun coa' => 'Pilih akun COA kas/bank dan akun COA lawan sebelum membuat transaksi otomatis.',
            'alasan penolakan harus diisi' => 'Alasan penolakan harus diisi sebelum voucher request ditolak.',
            'hanya voucher yang sudah disetujui yang dapat dibuatkan transaksi kas/bank' => 'Voucher harus disetujui terlebih dahulu sebelum dibuatkan transaksi kas/bank.',
            'voucher ini sudah memiliki transaksi kas/bank terkait' => 'Voucher ini sudah memiliki transaksi kas/bank terkait.',
            'voucher belum disetujui' => 'Voucher belum disetujui sehingga belum bisa digunakan.',
            'voucher ini sudah digunakan sebelumnya' => 'Voucher ini sudah digunakan sebelumnya.',
            'jumlah transaksi melebihi sisa voucher' => 'Jumlah transaksi melebihi sisa voucher yang tersedia.',
            'retur pembelian hanya bisa diajukan saat masih berstatus draft' => 'Retur pembelian hanya bisa diajukan saat masih berstatus draft.',
            'retur pembelian hanya bisa disetujui saat statusnya masih menunggu persetujuan' => 'Retur pembelian hanya bisa disetujui saat statusnya masih menunggu persetujuan.',
            'retur pembelian hanya bisa ditolak saat statusnya masih menunggu persetujuan' => 'Retur pembelian hanya bisa ditolak saat statusnya masih menunggu persetujuan.',
            'akun coa persediaan tidak ditemukan untuk jurnal retur pembelian' => 'Konfigurasi akun persediaan untuk jurnal retur pembelian belum lengkap.',
            'akun coa tidak ditemukan untuk jurnal retur pembelian' => 'Konfigurasi akun untuk jurnal retur pembelian belum lengkap.',
            'gagal membuat jurnal akuntansi retur pembelian' => 'Jurnal akuntansi retur pembelian gagal dibuat. Periksa konfigurasi akun COA lalu coba lagi.',
            'gagal menyesuaikan stok untuk retur pembelian' => 'Penyesuaian stok retur pembelian gagal diproses. Silakan coba lagi.',
                'material issue must be of type "issue" and status "completed"' => 'Material issue harus bertipe issue dan berstatus completed.',
                'material issue must be of type "return" and status "completed"' => 'Material issue harus bertipe return dan berstatus completed.',
                'production must be in "finished" status' => 'Produksi harus berstatus finished sebelum jurnal dapat dibuat.',
                'production does not have a related manufacturing order' => 'Produksi tidak memiliki Manufacturing Order terkait.',
                'no active bom found for manufacturing order' => 'BOM aktif tidak ditemukan untuk Manufacturing Order.',
                'total bdp cost for mo is zero or negative' => 'Total biaya BDP bernilai nol atau negatif sehingga jurnal tidak bisa dibuat.',
                'total labor and overhead cost must be greater than 0' => 'Total biaya tenaga kerja dan overhead harus lebih besar dari 0.',
                'expense coa not found' => 'COA beban tidak ditemukan.',
                'coa persediaan barang dalam proses - wip (1-201) tidak ditemukan' => 'COA WIP tidak ditemukan.',
                'coa pos sementara produksi (1400.04) tidak ditemukan' => 'COA Pos Sementara Produksi tidak ditemukan.',
                'akun coa untuk metode pembayaran tidak ditemukan' => 'Akun COA untuk metode pembayaran tidak ditemukan.',
                'tidak ada jurnal ditemukan untuk transaction_id' => 'Tidak ada jurnal yang dapat dibalik untuk transaction ID tersebut.',
                'tidak ada jurnal ditemukan untuk invoice id' => 'Tidak ada jurnal yang dapat dibalik untuk invoice tersebut.',
        ];

        foreach ($knownMessages as $needle => $friendlyMessage) {
            if (Str::contains($normalized, $needle)) {
                return $friendlyMessage;
            }
        }

        if (Str::contains($normalized, ['sqlstate', 'integrity constraint', 'syntax error', 'badmethodcall', 'undefined'])) {
            return $fallback;
        }

        if (Str::contains($normalized, ['coa', 'akun coa'])) {
            return 'Konfigurasi akun pembelian belum lengkap. Periksa akun COA yang terkait lalu coba lagi.';
        }

        if (Str::contains($normalized, ['deposit tidak tersedia', 'saldo deposit tidak mencukupi', 'uang muka supplier'])) {
            return Str::limit($message, 220);
        }

        return Str::limit($message, 220);
    }

    public static function danger(string $title, Throwable|string|null $error, string $fallback): void
    {
        Notification::make()
            ->title($title)
            ->body(self::message($error, $fallback))
            ->danger()
            ->send();
    }

    public static function warning(string $title, Throwable|string|null $error, string $fallback): void
    {
        Notification::make()
            ->title($title)
            ->body(self::message($error, $fallback))
            ->warning()
            ->send();
    }
}