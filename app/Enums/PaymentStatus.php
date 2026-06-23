<?php

namespace App\Enums;

/**
 * Status pembayaran untuk AccountPayable dan AccountReceivable.
 *
 * Nilai string ini disimpan langsung di database — jangan ubah nilai enum
 * tanpa bersamaan menjalankan migration untuk update data yang sudah ada.
 */
enum PaymentStatus: string
{
    case PAID   = 'Lunas';
    case UNPAID = 'Belum Lunas';

    /** Label tampilan dalam bahasa Indonesia (alias dari nilai). */
    public function label(): string
    {
        return $this->value;
    }

    /** Warna badge Filament untuk kolom TernaryColumn / BadgeColumn. */
    public function color(): string
    {
        return match ($this) {
            self::PAID   => 'success',
            self::UNPAID => 'warning',
        };
    }
}
