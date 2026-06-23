<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->infolist }}

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Jurnal Hasil</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    Baris jurnal yang dibuat saat Material Issue diselesaikan.
                </p>
            </div>

            <div class="p-6">
                {{ $this->table }}
            </div>
        </div>
    </div>
</x-filament-panels::page>