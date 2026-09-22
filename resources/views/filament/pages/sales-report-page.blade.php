<x-filament-panels::page>
    <div class="space-y-6">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-bold">Laporan Penjualan</h1>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-950 dark:text-white mb-4">Filter Laporan</h3>

            {{ $this->form }}
        </div>

        {{-- Ringkasan periode: sumber sama dengan PDF dan ekspor --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6" data-report-summary>
            <h3 class="text-lg font-semibold text-gray-950 dark:text-white mb-3">Ringkasan</h3>
            <dl class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-3 text-sm">
                @foreach ($this->getSummaryLines() as $label => $value)
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                        <dd class="font-semibold text-gray-950 dark:text-white">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
            @if ($this->getFootnote())
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">{{ $this->getFootnote() }}</p>
            @endif
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
