<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    use \App\Filament\Concerns\HasMenuAccess;

    protected static ?string $navigationGroup = 'BERANDA';

    protected static ?string $navigationLabel = 'Tugas Saya';

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Tugas Saya';

    public function getHeading(): string
    {
        return 'Halo, ' . (auth()->user()?->name ?? '');
    }

    public function getSubheading(): ?string
    {
        return 'Pilih menu di samping untuk mulai bekerja.';
    }

    public function getWidgets(): array
    {
        // Ringkasan aset hanya relevan untuk Owner & Keuangan. Widget tugas per peran
        // ("Perlu Anda kerjakan") menyusul di Tahap 2 (Spesifikasi UI/UX 6.1).
        $codes = auth()->check() ? \App\Support\MenuAccess::codesFor(auth()->user()) : [];

        return array_intersect($codes, ['OWN', 'FIN'])
            ? [\App\Filament\Widgets\AssetStatsWidget::class]
            : [];
    }
}
