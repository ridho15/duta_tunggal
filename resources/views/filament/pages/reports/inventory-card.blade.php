<x-filament-panels::page>
    <div
        x-data="{}"
        x-on:open-inventory-card-preview.window="window.open($event.detail.url, '_blank', 'width=1280,height=900,scrollbars=yes,resizable=yes')"
        class="space-y-6"
    >
        {{-- Info Banner --}}
        <div class="flex items-start gap-3 p-4 bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800 rounded-xl">
            <x-heroicon-o-information-circle class="w-5 h-5 mt-0.5 text-blue-600 dark:text-blue-400 shrink-0" />
            <div>
                <p class="text-sm font-semibold text-blue-800 dark:text-blue-200">Cara Penggunaan</p>
                <p class="text-sm text-blue-700 dark:text-blue-300 mt-0.5">
                    Pilih <strong>item, gudang</strong>, dan <strong>rentang tanggal</strong> di bawah ini, lalu klik tombol
                    <strong>Preview Laporan</strong>
                    untuk melihat laporan di jendela baru.
                    Dari halaman preview Anda dapat melakukan <strong>Print</strong>, <strong>Download PDF</strong>, atau <strong>Download Excel</strong>.
                </p>
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
