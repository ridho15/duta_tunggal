<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 dark:border-slate-800 dark:bg-slate-900/40 dark:text-slate-200">
            Profit Loss Multi Division sekarang memakai preview printable yang sama dengan laporan standalone, sehingga angka untuk audit, export, dan tampilan admin berasal dari payload yang sama.
        </div>

        <div class="rounded-xl bg-white p-6 shadow dark:bg-gray-900">
            <form wire:submit.prevent>
                {{ $this->form }}
            </form>
        </div>

        @if($this->showReport)
            <div class="overflow-hidden rounded-xl bg-white shadow dark:bg-gray-900">
                <div class="flex flex-col gap-3 bg-slate-900 px-6 py-4 md:flex-row md:items-center md:justify-between dark:bg-slate-950">
                    <div>
                        <h3 class="text-lg font-bold text-white">Preview Profit Loss Multi Division</h3>
                        <p class="text-sm text-slate-300">Preview admin menanam renderer yang sama dengan route standalone untuk kebutuhan review dan audit.</p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ $this->getPreviewUrl() }}" target="_blank" rel="noopener" class="inline-flex items-center rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-400">
                            Buka di Tab Baru
                        </a>
                        <a href="{{ $this->getExportUrl() }}" target="_blank" rel="noopener" class="inline-flex items-center rounded-lg bg-sky-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-sky-400">
                            Download Excel
                        </a>
                    </div>
                </div>

                <iframe
                    src="{{ $this->getPreviewUrl() }}"
                    title="Profit Loss Multi Division Preview"
                    class="block h-[1600px] w-full border-0 bg-slate-100 dark:bg-slate-950"
                ></iframe>
            </div>
        @else
            <div class="rounded-xl bg-white p-10 text-center text-gray-500 shadow dark:bg-gray-900 dark:text-gray-400">
                <x-heroicon-o-document-chart-bar class="mx-auto mb-3 h-10 w-10 text-gray-400" />
                <p class="text-base font-medium">Atur filter lalu klik <strong>Tampilkan Laporan</strong> untuk membuka preview Profit Loss Multi Division.</p>
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
