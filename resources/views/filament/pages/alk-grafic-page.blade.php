<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 dark:border-slate-800 dark:bg-slate-900/40 dark:text-slate-200">
            ALK Grafik memakai preview printable dan export yang sama dengan route laporan, sehingga angka di halaman admin, preview, PDF, dan Excel tetap berasal dari payload yang sama.
        </div>

        <div class="rounded-xl bg-white p-6 shadow dark:bg-gray-900">
            <div class="space-y-1">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Filter Analisis Laporan Keuangan</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Atur periode dan cabang untuk membuka laporan ALK Grafik dengan layout yang sama seperti laporan keuangan lainnya.</p>
            </div>

            <div class="mt-4 grid gap-4 md:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Tanggal Mulai</label>
                    <input type="date" wire:model="start_date" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Tanggal Akhir</label>
                    <input type="date" wire:model="end_date" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Cabang</label>
                    <select wire:model="cabang_id" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                        <option value="">-- Semua Cabang --</option>
                        @foreach(\App\Models\Cabang::query()->orderBy('nama')->get() as $cabang)
                            <option value="{{ $cabang->id }}">{{ $cabang->nama }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        @if($this->showPreview)
            <div class="overflow-hidden rounded-xl bg-white shadow dark:bg-gray-900">
                <div class="flex flex-col gap-3 bg-slate-900 px-6 py-4 md:flex-row md:items-center md:justify-between dark:bg-slate-950">
                    <div>
                        <h3 class="text-lg font-bold text-white">Preview ALK Grafik</h3>
                        <p class="text-sm text-slate-300">Iframe admin memakai mode embedded agar fokus tetap ke isi laporan, sementara toolbar penuh tetap tersedia di tab standalone.</p>
                    </div>
                    <a href="{{ $this->getPreviewUrl() }}" target="_blank" rel="noopener" class="inline-flex items-center rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-400">
                        Buka di Tab Baru
                    </a>
                </div>

                <iframe
                    src="{{ $this->getPreviewUrl(true) }}"
                    title="ALK Grafik Preview"
                    class="block h-[1620px] w-full border-0 bg-slate-100 dark:bg-slate-950"
                ></iframe>
            </div>
        @else
            <div class="rounded-xl bg-white p-10 text-center text-gray-500 shadow dark:bg-gray-900 dark:text-gray-400">
                <x-heroicon-o-presentation-chart-line class="mx-auto mb-3 h-10 w-10 text-gray-400" />
                <p class="text-base font-medium">Set filter terlebih dahulu, lalu klik <strong>Tampilkan Laporan</strong> untuk membuka preview ALK Grafik.</p>
            </div>
        @endif
    </div>
</x-filament-panels::page>

<script>
window.addEventListener('open-report-preview', event => {
    const url = event.detail?.url ?? event.detail?.[0]?.url;

    if (url) {
        window.open(url, '_blank', 'noopener');
    }
});
</script>
