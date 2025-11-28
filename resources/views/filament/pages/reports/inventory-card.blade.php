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
        </form>
        <!-- Header Section -->
        <x-filament::section>
            <x-slot name="heading">
                Kartu Persediaan
            </x-slot>
            <x-slot name="description">
                Laporan pergerakan persediaan per produk dan gudang
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-blue-50 dark:bg-blue-950/50 p-4 rounded-lg">
                    <div class="text-sm font-medium text-blue-600 dark:text-blue-400">Periode</div>
                    <div class="text-lg font-semibold text-blue-900 dark:text-blue-100">
                        {{ \Carbon\Carbon::parse($report['period']['start'])->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($report['period']['end'])->format('d/m/Y') }}
                    </div>
                </div>

                @php($selectedProducts = $this->getSelectedProductNames())
                @php($selectedWarehouses = $this->getSelectedWarehouseNames())

                @if($selectedProducts)
                    <div class="bg-green-50 dark:bg-green-950/50 p-4 rounded-lg">
                        <div class="text-sm font-medium text-green-600 dark:text-green-400">Produk Terpilih</div>
                        <div class="text-sm text-green-900 dark:text-green-100">
                            {{ count($selectedProducts) }} produk
                        </div>
                    </div>
                @endif

                @if($selectedWarehouses)
                    <div class="bg-purple-50 dark:bg-purple-950/50 p-4 rounded-lg">
                        <div class="text-sm font-medium text-purple-600 dark:text-purple-400">Gudang Terpilih</div>
                        <div class="text-sm text-purple-900 dark:text-purple-100">
                            {{ count($selectedWarehouses) }} gudang
                        </div>
                    </div>
                @endif

                <div class="bg-gray-50 dark:bg-gray-950/50 p-4 rounded-lg">
                    <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Baris</div>
                    <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ count($report['rows']) }}
                    </div>
                </div>
            </div>
        </x-filament::section>

        <!-- Data Table Section -->
        <x-filament::section>
            <x-slot name="heading">
                Detail Pergerakan Persediaan
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                            <th class="px-4 py-3 text-left font-semibold text-gray-900 dark:text-gray-100">Produk</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-900 dark:text-gray-100">Gudang</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-gray-100">Saldo Awal</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-gray-100">Masuk</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-gray-100">Keluar</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-gray-100">Saldo Akhir</th>
                        </tr>
                        <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-25 dark:bg-gray-850 text-xs">
                            <th class="px-4 py-2"></th>
                            <th class="px-4 py-2"></th>
                            <th class="px-4 py-2 text-right text-gray-600 dark:text-gray-400">Qty | Nilai</th>
                            <th class="px-4 py-2 text-right text-gray-600 dark:text-gray-400">Qty | Nilai</th>
                            <th class="px-4 py-2 text-right text-gray-600 dark:text-gray-400">Qty | Nilai</th>
                            <th class="px-4 py-2 text-right text-gray-600 dark:text-gray-400">Qty | Nilai</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if(count($report['rows']) > 0)
                            @foreach($report['rows'] as $row)
                                <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900/50">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ $row['product_name'] }}</div>
                                        @if($row['product_sku'])
                                            <div class="text-xs text-gray-500 dark:text-gray-400">SKU: {{ $row['product_sku'] }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ $row['warehouse_name'] }}</div>
                                        @if($row['warehouse_code'])
                                            <div class="text-xs text-gray-500 dark:text-gray-400">Kode: {{ $row['warehouse_code'] }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="font-medium">{{ number_format($row['opening_qty'], 2, ',', '.') }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ number_format($row['opening_value'], 0, ',', '.') }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="font-medium text-green-600 dark:text-green-400">{{ number_format($row['qty_in'], 2, ',', '.') }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ number_format($row['value_in'], 0, ',', '.') }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="font-medium text-red-600 dark:text-red-400">{{ number_format($row['qty_out'], 2, ',', '.') }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ number_format($row['value_out'], 0, ',', '.') }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="font-medium">{{ number_format($row['closing_qty'], 2, ',', '.') }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ number_format($row['closing_value'], 0, ',', '.') }}</div>
                                    </td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                    <x-heroicon-o-inbox class="w-6 h-6 mx-auto mb-4 text-gray-300 dark:text-gray-600" />
                                    Tidak ada data pergerakan pada periode ini.<br>
                                    Total saldo awal: 0
                                </td>
                            </tr>
                        @endif
                    </tbody>
                    @if($report['rows'])
                        <tfoot class="bg-gray-100 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
                            <tr class="font-semibold">
                                <td colspan="2" class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">TOTAL</td>
                                <td class="px-4 py-3 text-right">
                                    <div class="font-bold">{{ number_format($report['totals']['opening_qty'], 2, ',', '.') }}</div>
                                    <div class="text-xs text-gray-600 dark:text-gray-400">{{ number_format($report['totals']['opening_value'], 0, ',', '.') }}</div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="font-bold text-green-600 dark:text-green-400">{{ number_format($report['totals']['qty_in'], 2, ',', '.') }}</div>
                                    <div class="text-xs text-gray-600 dark:text-gray-400">{{ number_format($report['totals']['value_in'], 0, ',', '.') }}</div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="font-bold text-red-600 dark:text-red-400">{{ number_format($report['totals']['qty_out'], 2, ',', '.') }}</div>
                                    <div class="text-xs text-gray-600 dark:text-gray-400">{{ number_format($report['totals']['value_out'], 0, ',', '.') }}</div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="font-bold">{{ number_format($report['totals']['closing_qty'], 2, ',', '.') }}</div>
                                    <div class="text-xs text-gray-600 dark:text-gray-400">{{ number_format($report['totals']['closing_value'], 0, ',', '.') }}</div>
                                </td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </x-filament::section>

        <!-- Summary Cards -->
        @if($report['rows'])
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-gray-50 dark:bg-gray-950/50 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Saldo Awal</div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($report['totals']['opening_value'], 0, ',', '.') }}</div>
                        </div>
                        <x-heroicon-o-currency-dollar class="w-8 h-8 text-gray-400 dark:text-gray-600" />
                    </div>
                </div>

                <div class="bg-green-50 dark:bg-green-950/50 p-4 rounded-lg border border-green-200 dark:border-green-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-sm font-medium text-green-600 dark:text-green-400">Total Nilai Masuk</div>
                            <div class="text-2xl font-bold text-green-900 dark:text-green-100">{{ number_format($report['totals']['value_in'], 0, ',', '.') }}</div>
                        </div>
                        <x-heroicon-o-arrow-trending-up class="w-8 h-8 text-green-400 dark:text-green-600" />
                    </div>
                </div>

                <div class="bg-red-50 dark:bg-red-950/50 p-4 rounded-lg border border-red-200 dark:border-red-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-sm font-medium text-red-600 dark:text-red-400">Total Nilai Keluar</div>
                            <div class="text-2xl font-bold text-red-900 dark:text-red-100">{{ number_format($report['totals']['value_out'], 0, ',', '.') }}</div>
                        </div>
                        <x-heroicon-o-arrow-trending-down class="w-8 h-8 text-red-400 dark:text-red-600" />
                    </div>
                </div>

                <div class="bg-blue-50 dark:bg-blue-950/50 p-4 rounded-lg border border-blue-200 dark:border-blue-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-sm font-medium text-blue-600 dark:text-blue-400">Total Saldo Akhir</div>
                            <div class="text-2xl font-bold text-blue-900 dark:text-blue-100">{{ number_format($report['totals']['closing_value'], 0, ',', '.') }}</div>
                        </div>
                        <x-heroicon-o-scale class="w-8 h-8 text-blue-400 dark:text-blue-600" />
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
