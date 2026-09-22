<?php

namespace App\Support;

/**
 * Menaikkan batas memori PHP tanpa pernah MENURUNKANNYA.
 *
 * Sebelumnya beberapa titik memanggil ini_set('memory_limit', '512M') langsung; bila php.ini (atau
 * `php -d memory_limit=-1` pada proses uji) lebih besar, batas itu justru diturunkan ke 512M.
 */
class MemoryLimit
{
    /** Naikkan batas ke $target hanya bila batas saat ini lebih rendah (batas tak terbatas -1 tidak disentuh). */
    public static function raiseTo(string $target = '512M'): void
    {
        $current = self::toBytes((string) ini_get('memory_limit'));

        if ($current === -1) {
            return;
        }

        $wanted = self::toBytes($target);

        if ($wanted === -1 || $current < $wanted) {
            ini_set('memory_limit', $target);
        }
    }

    /** Ubah notasi php.ini ("512M", "1G", "256K", "-1", "134217728") menjadi byte; -1 = tak terbatas. */
    public static function toBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return -1;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
