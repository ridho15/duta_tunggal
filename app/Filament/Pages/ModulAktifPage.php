<?php

namespace App\Filament\Pages;

use App\Support\MenuAccess;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * PENGATURAN → Modul Aktif (Spesifikasi UI/UX 4.2 & 8.8).
 *
 * Menyalakan/mematikan menu modul yang jarang dipakai. Mematikan modul hanya
 * menyembunyikan menunya; data dan halaman tetap ada.
 */
class ModulAktifPage extends Page
{
    use \App\Filament\Concerns\HasMenuAccess;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $navigationGroup = 'PENGATURAN';

    protected static ?string $navigationLabel = 'Modul Aktif';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'modul-aktif';

    protected static ?string $title = 'Modul Aktif';

    protected static string $view = 'filament.pages.modul-aktif';

    /** @var array<string, bool> */
    public array $modules = [];

    public function mount(): void
    {
        foreach (array_keys((array) config('menu_access.modules', [])) as $module) {
            $this->modules[$module] = MenuAccess::moduleEnabled($module);
        }
    }

    /**
     * @return array<string, array{label: string, description: string}>
     */
    public function getModuleDefinitionsProperty(): array
    {
        return collect((array) config('menu_access.modules', []))
            ->map(fn (array $definition) => [
                'label' => (string) ($definition['label'] ?? ''),
                'description' => (string) ($definition['description'] ?? ''),
            ])
            ->all();
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        foreach (array_keys((array) config('menu_access.modules', [])) as $module) {
            MenuAccess::setModule($module, (bool) ($this->modules[$module] ?? false));
        }

        Notification::make()
            ->title('Modul Aktif disimpan')
            ->body('Menu akan berubah setelah halaman dimuat ulang.')
            ->success()
            ->duration(6000)
            ->send();

        $this->redirect(static::getUrl());
    }

    public function getSubheading(): ?string
    {
        return 'Mematikan modul hanya menyembunyikan menunya. Data yang sudah ada tidak dihapus.';
    }
}
