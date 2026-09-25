<x-filament-panels::page>
    <div
        x-data="{
            openPreview(url) {
                if (!url) return;
                try {
                    const win = window.open(url, '_blank', 'noopener,noreferrer');
                    if (!win || win.closed || typeof win.closed === 'undefined') {
                        // Popup terblokir oleh browser; navigasi langsung di window aktif sebagai fallback aman
                        window.location.href = url;
                    }
                } catch (e) {
                    window.location.href = url;
                }
            }
        }"
        x-on:open-inventory-card-preview.window="openPreview($event.detail?.url ?? $event.detail?.[0]?.url ?? $event.detail)"
        class="space-y-6"
    >
        {{-- Info Banner & Direct Links --}}
        <div class="flex items-start gap-3 p-4 bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800 rounded-xl">
            <x-heroicon-o-information-circle class="w-5 h-5 mt-0.5 text-blue-600 dark:text-blue-400 shrink-0" />
            <div class="flex-1">
                <p class="text-sm font-semibold text-blue-800 dark:text-blue-200">Cara Penggunaan</p>
                <p class="text-sm text-blue-700 dark:text-blue-300 mt-0.5">
                    Pilih <strong>item, gudang</strong>, dan <strong>rentang tanggal</strong> di bawah ini, lalu klik tombol
                    <strong>Preview Laporan</strong> untuk melihat kartu persediaan dalam tab baru.
                    Laporan juga dapat diunduh langsung dalam format <strong>PDF</strong> atau <strong>Excel</strong>.
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <a
                    href="{{ $this->getPreviewUrl() }}"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-primary-600 hover:bg-primary-500 rounded-lg shadow-sm transition"
                >
                    <x-heroicon-m-arrow-top-right-on-square class="w-4 h-4" />
                    Buka Langsung
                </a>
            </div>
        </div>

        {{-- Filter Section --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-funnel class="w-5 h-5 text-primary-600" />
                    Filter Kartu Persediaan
                </div>
            </x-slot>
            <x-slot name="description">
                Pilih item, gudang, dan rentang tanggal, lalu klik Preview untuk melihat data
            </x-slot>

            {{ $this->form }}
        </x-filament::section>
    </div>
</x-filament-panels::page>
