<?php

namespace App\Filament\Concerns;

use App\Support\MenuAccess;

/**
 * Batasi menu DAN URL Resource/Page per peran (config/menu_access.php),
 * dan tampilkan menu modul opsional hanya bila modulnya aktif.
 *
 * Filament memanggil canAccess() saat mendaftarkan navigasi dan saat halaman
 * dibuka (403 bila false), jadi satu titik ini mengunci keduanya. Policy /
 * permission yang sudah ada tetap dicek lewat parent::canAccess().
 */
trait HasMenuAccess
{
    public static function canAccess(array $parameters = []): bool
    {
        if (! MenuAccess::allows(static::class)) {
            return false;
        }

        return parent::canAccess($parameters);
    }

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        $module = MenuAccess::moduleFor(static::class);

        if ($module !== null) {
            return MenuAccess::moduleEnabled($module);
        }

        return parent::shouldRegisterNavigation($parameters);
    }
}
