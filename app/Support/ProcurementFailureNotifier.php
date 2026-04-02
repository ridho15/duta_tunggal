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