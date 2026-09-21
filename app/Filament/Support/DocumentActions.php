<?php

namespace App\Filament\Support;

use Filament\Actions\ActionGroup;

/**
 * Pola aksi dokumen yang seragam (T7.3, usulan 14 / D13): aksi UTAMA (sesuai status) langsung terlihat, aksi SEKUNDER
 * (Cetak, Ubah, Hapus, Jurnal, Batalkan…) dikelompokkan di menu "Lainnya". Halaman View cukup menyebut nama aksi sekundernya.
 */
class DocumentActions
{
    /**
     * @param  array<int, mixed>  $actions  daftar aksi header (Filament\Actions\Action / EditAction / DeleteAction …)
     * @param  array<int, string>  $secondaryNames  nama aksi yang masuk menu "Lainnya"
     * @return array<int, mixed>
     */
    public static function layout(array $actions, array $secondaryNames): array
    {
        $primary = [];
        $secondary = [];
        foreach ($actions as $action) {
            if (in_array($action->getName(), $secondaryNames, true)) {
                $secondary[] = $action;
            } else {
                $primary[] = $action;
            }
        }

        if ($secondary === []) {
            return $primary;
        }

        return [
            ...$primary,
            ActionGroup::make($secondary)->label('Lainnya')->icon('heroicon-m-ellipsis-vertical')->color('gray')->button(),
        ];
    }

    /** Penjaga klik ganda sisi tampilan: tombol dinonaktifkan selama permintaan berjalan (server tetap idempoten). */
    public static function guard($action)
    {
        return $action->extraAttributes(['wire:loading.attr' => 'disabled', 'wire:loading.class' => 'opacity-50 cursor-wait'], merge: true);
    }
}
