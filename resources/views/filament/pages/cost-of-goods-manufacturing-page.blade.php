<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Usage note --}}
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 dark:border-emerald-700 dark:bg-emerald-900/20 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-200">
            <strong>&#128161; Cara Penggunaan:</strong>
            Pilih rentang tanggal (dan opsional cabang / produk), lalu klik
            <strong>Tampilkan Laporan</strong> untuk membuka laporan di tab baru.
        </div>

        {{-- Filter Form --}}
        {{ $this->form }}

        {{-- Empty state --}}
        <div class="bg-white dark:bg-gray-900 shadow rounded-xl p-10 text-center text-gray-500 dark:text-gray-400">
            <x-heroicon-o-cube class="mx-auto mb-3 h-10 w-10 text-gray-400" />
            <p class="text-base font-medium">Isi filter di atas, lalu klik <strong>Tampilkan Laporan</strong> untuk membuka laporan di tab baru.</p>
        </div>
    </div>

    <script>
    window.addEventListener('open-report-preview', event => {
        const url = event.detail?.url ?? event.detail?.[0]?.url;
        if (url) {
            window.open(url, '_blank', 'noopener');
        }
    });
    </script>
</x-filament-panels::page>
