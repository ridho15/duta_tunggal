<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 dark:border-slate-800 dark:bg-slate-900/40 dark:text-slate-200">
            Financial Statement memakai preview printable yang sama dengan route laporan, jadi angka admin dan standalone preview tetap sinkron.
        </div>

        <div class="bg-white dark:bg-gray-900 shadow rounded-xl p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Filter Laporan Keuangan</h2>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Jenis Laporan</label>
                    <select wire:model="statement_type" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                        <option value="all">Semua (P&amp;L + Balance Sheet + COGM)</option>
                        <option value="pl">Laba Rugi (P&amp;L)</option>
                        <option value="bs">Neraca (Balance Sheet)</option>
                        <option value="cogm">Harga Pokok Produksi (COGM)</option>
                    </select>
                </div>
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
            <div class="bg-white dark:bg-gray-900 shadow rounded-xl overflow-hidden">
                <div class="flex flex-col gap-3 bg-slate-900 px-6 py-4 md:flex-row md:items-center md:justify-between dark:bg-slate-950">
                    <div>
                        <h3 class="text-lg font-bold text-white">Preview Financial Statement</h3>
                        <p class="text-sm text-slate-300">Admin page menanam preview printable yang sama dengan route standalone.</p>
                    </div>
                    <a href="{{ $this->getPreviewUrl() }}" target="_blank" rel="noopener" class="inline-flex items-center rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-400">
                        Buka di Tab Baru
                    </a>
                </div>

                <iframe
                    src="{{ $this->getPreviewUrl() }}"
                    title="Financial Statement Preview"
                    class="block h-[1600px] w-full border-0 bg-slate-100 dark:bg-slate-950"
                ></iframe>
            </div>
        @else
            <div class="bg-white dark:bg-gray-900 shadow rounded-xl p-10 text-center text-gray-500 dark:text-gray-400">
                <x-heroicon-o-funnel class="mx-auto mb-3 h-10 w-10 text-gray-400" />
                <p class="text-base font-medium">Set filter terlebih dahulu, lalu klik <strong>Tampilkan Laporan</strong> untuk membuka preview financial statement.</p>
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
