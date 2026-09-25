<x-filament-panels::page>
    <div
        x-data="{
            openPreview(url) {
                if (!url) return;
                try {
                    const win = window.open(url, '_blank', 'noopener,noreferrer');
                    if (!win || win.closed || typeof win.closed === 'undefined') {
                        window.location.href = url;
                    }
                } catch (e) {
                    window.location.href = url;
                }
            }
        }"
        x-on:open-stock-preview.window="openPreview($event.detail?.url ?? $event.detail?.[0]?.url ?? $event.detail)"
        class="space-y-6"
    >
        {{-- Info Banner --}}
        <div class="flex items-start gap-3 p-4 bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800 rounded-xl">
            <x-heroicon-o-information-circle class="w-5 h-5 mt-0.5 text-blue-600 dark:text-blue-400 shrink-0" />
            <div class="flex-1">
                <p class="text-sm font-semibold text-blue-800 dark:text-blue-200">Laporan Stok Persediaan</p>
                <p class="text-sm text-blue-700 dark:text-blue-300 mt-0.5">
                    Atur <strong>filter periode, item</strong>, dan <strong>gudang</strong> di bawah ini, lalu klik tombol
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-green-100 text-green-800 rounded font-semibold text-xs">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        Preview Laporan
                    </span>
                    untuk mencetak / melihat laporan. Anda juga dapat menggunakan <strong>Laporan Interaktif</strong> untuk analisis langsung di layar.
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <a
                    href="{{ $this->getPreviewUrl() }}"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-success-600 hover:bg-success-500 rounded-lg shadow-sm transition"
                >
                    <x-heroicon-m-arrow-top-right-on-square class="w-4 h-4" />
                    Buka Langsung
                </a>
                <a
                    href="{{ \App\Filament\Pages\InventoryReportPage::getUrl() }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-primary-700 bg-primary-50 hover:bg-primary-100 border border-primary-200 rounded-lg shadow-sm transition"
                >
                    <x-heroicon-m-chart-bar-square class="w-4 h-4" />
                    Laporan Interaktif
                </a>
            </div>
        </div>

        {{-- Filter Section --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-funnel class="w-5 h-5 text-primary-600" />
                    Filter Laporan Stok
                </div>
            </x-slot>
            <x-slot name="description">
                Pilih periode, item, dan gudang untuk laporan stok
            </x-slot>

            {{ $this->form }}
        </x-filament::section>
    </div>
</x-filament-panels::page>
