<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Pesan untuk pengguna UI Blade. Ditulis ke kunci flash yang dibaca layouts/partials/flash.blade.php,
 * menggantikan Notification Filament yang tidak pernah dirender di layout Blade.
 */
class Flash
{
    public static function error(string $title, string $body = ''): void
    {
        self::put('error', $title, $body);
    }

    public static function warning(string $title, string $body = ''): void
    {
        self::put('warning', $title, $body);
    }

    private static function put(string $key, string $title, string $body): void
    {
        $message = trim($title.($body !== '' ? ': '.$body : ''));

        // Observer dan service juga jalan dari artisan/queue tanpa sesi: cukup masuk log.
        if (! request()->hasSession()) {
            Log::log($key === 'error' ? 'error' : 'warning', $message);

            return;
        }

        // Pesan kedua di request yang sama digabung, supaya tidak menimpa yang pertama.
        $sameRequest = in_array($key, session('_flash.new', []), true);
        session()->flash($key, $sameRequest ? session($key).' | '.$message : $message);
    }
}
