<x-filament-panels::page>
    <div class="space-y-6">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-bold">Laporan Penjualan</h1>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-950 dark:text-white mb-4">Filter Laporan</h3>

            {{ $this->form }}
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
