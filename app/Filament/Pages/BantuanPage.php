<?php

namespace App\Filament\Pages;

use App\Filament\Resources\HelpVideoResource;
use App\Models\HelpVideo;
use App\Support\MenuAccess;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

/** Bantuan: video tutorial sesuai peran user (Tahap 3). Dibuka dari ikon "?" di header. */
class BantuanPage extends Page
{
    protected static ?string $slug = 'bantuan';

    protected static ?string $title = 'Bantuan';

    protected static string $view = 'filament.pages.bantuan';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getSubheading(): ?string
    {
        return 'Video singkat cara memakai sistem sesuai pekerjaan Anda.';
    }

    protected function getViewData(): array
    {
        $codes = MenuAccess::codesFor(Auth::user());
        $isSuper = MenuAccess::isSuper(Auth::user());

        $videos = HelpVideo::query()
            ->where('is_active', true)
            ->when(! $isSuper, fn ($q) => $q->where(fn ($w) => $w->whereNull('role_code')->orWhereIn('role_code', $codes)))
            ->orderBy('sort')
            ->orderBy('title')
            ->get();

        return [
            'groups' => $videos->groupBy(fn (HelpVideo $v) => $v->role_code ? (HelpVideo::ROLE_LABELS[$v->role_code] ?? $v->role_code) : 'Untuk semua'),
            'manageUrl' => HelpVideoResource::canAccess() ? HelpVideoResource::getUrl('index') : null,
        ];
    }
}
