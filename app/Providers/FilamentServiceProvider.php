<?php

namespace App\Providers;

use Filament\Actions\ActionGroup;
use Filament\Actions\StaticAction;
use Filament\Forms\Components\Component as FormComponent;
use Filament\Infolists\Components\Component as InfolistComponent;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;
use Illuminate\Support\ServiceProvider;

class FilamentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /*
         * Spesifikasi UI/UX 7.1 — semua teks UI Bahasa Indonesia.
         * Label tombol, kolom, field, filter, dan entri dilewatkan ke __() sehingga label
         * berbahasa Inggris (baik yang ditulis eksplisit maupun yang otomatis dari nama
         * field, mis. "Created at") diterjemahkan lewat lang/id.json. Label yang sudah
         * berbahasa Indonesia tidak ada di kamus sehingga tampil apa adanya.
         */
        foreach ([
            StaticAction::class,
            ActionGroup::class,
            FormComponent::class,
            InfolistComponent::class,
            Column::class,
            BaseFilter::class,
        ] as $class) {
            $class::configureUsing(fn ($component) => $component->translateLabel());
        }
    }
}
