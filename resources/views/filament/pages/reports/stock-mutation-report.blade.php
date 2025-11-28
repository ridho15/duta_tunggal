<x-filament-panels::page>
    @php($report = $this->getReportData())

    <div class="space-y-6">
        <!-- Custom Periode Filter Form (manual override, non-Filament) -->
        <form method="GET" class="mb-4 flex flex-wrap gap-2 items-end">
            <div>
                <label for="start" class="block text-xs font-medium text-gray-700 dark:text-gray-200">Tanggal Mulai</label>
                <input type="date" id="start" name="start" value="{{ request('start', \Carbon\Carbon::parse($report['period']['start'])->format('Y-m-d')) }}" class="filament-input rounded-md border-gray-300 dark:bg-gray-900 dark:border-gray-700" />
            </div>
            <div>
                <label for="end" class="block text-xs font-medium text-gray-700 dark:text-gray-200">Tanggal Selesai</label>
                <input type="date" id="end" name="end" value="{{ request('end', \Carbon\Carbon::parse($report['period']['end'])->format('Y-m-d')) }}" class="filament-input rounded-md border-gray-300 dark:bg-gray-900 dark:border-gray-700" />
            </div>
            <button type="submit" style="background: linear-gradient(to right, #2563eb, #1d4ed8);" class="inline-flex items-center px-6 py-2.5 hover:shadow-md transform hover:scale-105 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 text-white font-medium text-sm rounded-lg shadow-sm">
                <x-heroicon-o-magnifying-glass class="w-4 h-4 mr-2" />
                Terapkan Filter
            </button>
            <button wire:click="exportExcel" style="background: linear-gradient(to right, #16a34a, #15803d);" class="inline-flex items-center px-6 py-2.5 hover:shadow-md transform hover:scale-105 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 text-white font-medium text-sm rounded-lg shadow-sm">
                <x-heroicon-o-document-arrow-down class="w-4 h-4 mr-2" />
                Export Excel
            </button>
        </form>

        <!-- Header Section -->
        <x-filament::section>
            <x-slot name="heading">
                Laporan Mutasi Barang Per Gudang
            </x-slot>
            <x-slot name="description">
                Detail pergerakan stock barang per gudang dalam periode tertentu
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-blue-50 dark:bg-blue-950/50 p-4 rounded-lg">
                    <div class="text-sm font-medium text-blue-600 dark:text-blue-400">Periode</div>
                    <div class="text-lg font-semibold text-blue-900 dark:text-blue-100">
                        {{ \Carbon\Carbon::parse($report['period']['start'])->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($report['period']['end'])->format('d/m/Y') }}
                    </div>
                </div>

                <div class="bg-green-50 dark:bg-green-950/50 p-4 rounded-lg">
                    <div class="text-sm font-medium text-green-600 dark:text-green-400">Total Gudang</div>
                    <div class="text-lg font-semibold text-green-900 dark:text-green-100">
                        {{ count($report['warehouseData']) }} gudang
                    </div>
                </div>

                <div class="bg-purple-50 dark:bg-purple-950/50 p-4 rounded-lg">
                    <div class="text-sm font-medium text-purple-600 dark:text-purple-400">Total Transaksi</div>
                    <div class="text-lg font-semibold text-purple-900 dark:text-purple-100">
                        {{ number_format($report['totals']['total_movements']) }}
                    </div>
                </div>

                <div class="bg-orange-50 dark:bg-orange-950/50 p-4 rounded-lg">
                    <div class="text-sm font-medium text-orange-600 dark:text-orange-400">Net Quantity</div>
                    <div class="text-lg font-semibold text-orange-900 dark:text-orange-100">
                        {{ number_format($report['totals']['total_qty_in'] - $report['totals']['total_qty_out'], 2) }}
                    </div>
                </div>
            </div>
        </x-filament::section>

        <!-- Warehouse Data -->
        @if(empty($report['warehouseData']))
            <x-filament::section>
                <div class="text-center py-8">
                    <x-heroicon-o-exclamation-triangle class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Tidak ada data mutasi</h3>
                    <p class="text-gray-500 dark:text-gray-400">Tidak ditemukan transaksi stock movement dalam periode yang dipilih.</p>
                </div>
            </x-filament::section>
        @else
            @foreach($report['warehouseData'] as $warehouse)
                <x-filament::section>
                    <x-slot name="heading">
                        Gudang: {{ $warehouse['warehouse_name'] }}
                        @if($warehouse['warehouse_code'])
                            <span class="text-sm text-gray-500 dark:text-gray-400">({{ $warehouse['warehouse_code'] }})</span>
                        @endif
                    </x-slot>

                    <!-- Warehouse Summary -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div class="text-center">
                            <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Qty Masuk</div>
                            <div class="text-lg font-semibold text-green-600">{{ number_format($warehouse['summary']['qty_in'], 2) }}</div>
                        </div>
                        <div class="text-center">
                            <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Qty Keluar</div>
                            <div class="text-lg font-semibold text-red-600">{{ number_format($warehouse['summary']['qty_out'], 2) }}</div>
                        </div>
                        <div class="text-center">
                            <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Net Qty</div>
                            <div class="text-lg font-semibold {{ $warehouse['summary']['net_qty'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ number_format($warehouse['summary']['net_qty'], 2) }}
                            </div>
                        </div>
                        <div class="text-center">
                            <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Transaksi</div>
                            <div class="text-lg font-semibold text-blue-600">{{ count($warehouse['movements']) }}</div>
                        </div>
                    </div>

                    <!-- Movements Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                <tr>
                                    <th scope="col" class="px-6 py-3">Tanggal</th>
                                    <th scope="col" class="px-6 py-3">Produk</th>
                                    <th scope="col" class="px-6 py-3">Tipe</th>
                                    <th scope="col" class="px-6 py-3">Qty Masuk</th>
                                    <th scope="col" class="px-6 py-3">Qty Keluar</th>
                                    <th scope="col" class="px-6 py-3">Nilai</th>
                                    <th scope="col" class="px-6 py-3">Referensi</th>
                                    <th scope="col" class="px-6 py-3">Rak</th>
                                    <th scope="col" class="px-6 py-3">Catatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($warehouse['movements'] as $movement)
                                    <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                        <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">
                                            {{ \Carbon\Carbon::parse($movement['date'])->format('d/m/Y') }}
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="font-medium">{{ $movement['product_name'] }}</div>
                                            @if($movement['product_sku'])
                                                <div class="text-xs text-gray-500">{{ $movement['product_sku'] }}</div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="px-2 py-1 text-xs font-medium rounded-full
                                                @if(str_contains($movement['type'], 'Masuk'))
                                                    bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300
                                                @elseif(str_contains($movement['type'], 'Keluar'))
                                                    bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300
                                                @else
                                                    bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300
                                                @endif">
                                                {{ $movement['type'] }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-green-600 font-medium">
                                            {{ $movement['qty_in'] > 0 ? number_format($movement['qty_in'], 2) : '-' }}
                                        </td>
                                        <td class="px-6 py-4 text-red-600 font-medium">
                                            {{ $movement['qty_out'] > 0 ? number_format($movement['qty_out'], 2) : '-' }}
                                        </td>
                                        <td class="px-6 py-4">
                                            {{ $movement['value'] ? 'Rp ' . number_format($movement['value'], 0) : '-' }}
                                        </td>
                                        <td class="px-6 py-4 text-gray-500">
                                            {{ $movement['reference'] ?: '-' }}
                                        </td>
                                        <td class="px-6 py-4 text-gray-500">
                                            {{ $movement['rak_name'] ?: '-' }}
                                        </td>
                                        <td class="px-6 py-4 text-gray-500 max-w-xs truncate" title="{{ $movement['notes'] }}">
                                            {{ $movement['notes'] ?: '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>
            @endforeach

            <!-- Grand Total Summary -->
            <x-filament::section>
                <x-slot name="heading">
                    Ringkasan Total Keseluruhan
                </x-slot>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-green-50 dark:bg-green-950/50 p-4 rounded-lg text-center">
                        <div class="text-sm font-medium text-green-600 dark:text-green-400">Total Qty Masuk</div>
                        <div class="text-xl font-bold text-green-900 dark:text-green-100">
                            {{ number_format($report['totals']['total_qty_in'], 2) }}
                        </div>
                    </div>
                    <div class="bg-red-50 dark:bg-red-950/50 p-4 rounded-lg text-center">
                        <div class="text-sm font-medium text-red-600 dark:text-red-400">Total Qty Keluar</div>
                        <div class="text-xl font-bold text-red-900 dark:text-red-100">
                            {{ number_format($report['totals']['total_qty_out'], 2) }}
                        </div>
                    </div>
                    <div class="bg-blue-50 dark:bg-blue-950/50 p-4 rounded-lg text-center">
                        <div class="text-sm font-medium text-blue-600 dark:text-blue-400">Net Quantity</div>
                        <div class="text-xl font-bold text-blue-900 dark:text-blue-100">
                            {{ number_format($report['totals']['total_qty_in'] - $report['totals']['total_qty_out'], 2) }}
                        </div>
                    </div>
                    <div class="bg-purple-50 dark:bg-purple-950/50 p-4 rounded-lg text-center">
                        <div class="text-sm font-medium text-purple-600 dark:text-purple-400">Total Transaksi</div>
                        <div class="text-xl font-bold text-purple-900 dark:text-purple-100">
                            {{ number_format($report['totals']['total_movements']) }}
                        </div>
                    </div>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>