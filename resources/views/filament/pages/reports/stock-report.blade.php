<x-filament-panels::page>
    <div
        x-data="{}"
        x-on:open-stock-preview.window="window.open($event.detail.url, '_blank', 'width=1280,height=900,scrollbars=yes,resizable=yes')"
        class="space-y-6"
    >
        {{-- Info Banner --}}
        <div class="flex items-start gap-3 p-4 bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800 rounded-xl">
            <x-heroicon-o-information-circle class="w-5 h-5 mt-0.5 text-blue-600 dark:text-blue-400 shrink-0" />
            <div>
                <p class="text-sm font-semibold text-blue-800 dark:text-blue-200">Cara Penggunaan</p>
                <p class="text-sm text-blue-700 dark:text-blue-300 mt-0.5">
                    Atur <strong>filter periode, item</strong>, dan <strong>gudang</strong> di bawah ini, lalu klik tombol
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-green-100 text-green-800 rounded font-semibold text-xs">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        Preview Laporan
                    </span>
                    untuk melihat laporan di jendela baru.
                    Data <strong>tidak ditampilkan</strong> langsung di halaman ini.
                </p>
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
