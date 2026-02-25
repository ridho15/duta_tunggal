<x-filament-panels::page>
    <div class="space-y-6">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-bold">Laporan Mutasi Barang</h1>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-950 dark:text-white mb-4">Filter Laporan</h3>

            {{ $this->form }}
        </div>

        @if($this->showPreview)
            {{ $this->table }}
        @else
            <div class="p-8 border rounded bg-gray-50 text-center text-gray-500">
                <x-heroicon-o-document-chart-bar class="w-12 h-12 mx-auto mb-3 text-gray-400" style="width: 100px; height: 100px;"/>
                <p class="text-lg font-medium" style="font-size: 11pt">Atur filter di atas, kemudian klik <strong>Tampilkan Laporan</strong> untuk melihat data.</p>
            </div>
        @endif
    </div>
</x-filament-panels::page>