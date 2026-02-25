<x-filament-panels::page>
    @php
        $report = $this->reportData;
    @endphp

    <div class="space-y-6">

        {{-- =====================================================================
             PANEL FILTER
             ===================================================================== --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-funnel class="w-5 h-5 text-primary-500" />
                    Filter Kartu Persediaan
                </div>
            </x-slot>
            <x-slot name="description">
                Isi filter di bawah, kemudian klik <strong>Preview</strong> untuk melihat data.
            </x-slot>

            {{ $this->form }}
        </x-filament::section>

        {{-- =====================================================================
             PANEL PREVIEW (hanya tampil setelah klik Preview)
             ===================================================================== --}}
        @if ($this->showPreview)

            {{-- Toolbar: tombol ekspor --}}
            <div
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 px-5 py-3" style="padding-right: 0.25rem; padding-left: 0.25rem">
                <div class="text-sm text-gray-600 dark:text-gray-300">
                    <span class="font-semibold text-gray-900 dark:text-white">{{ count($report['rows'] ?? []) }}</span>
                    baris ditemukan
                    &nbsp;·&nbsp;
                    Periode <span
                        class="font-semibold">{{ \Carbon\Carbon::parse($report['period']['start'])->format('d/m/Y') }}</span>
                    &nbsp;–&nbsp;
                    <span
                        class="font-semibold">{{ \Carbon\Carbon::parse($report['period']['end'])->format('d/m/Y') }}</span>
                </div>

                <div class="flex flex-wrap gap-2">
                    {{-- Print --}}
                    <a href="{{ $this->getPrintUrl() }}" target="_blank"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-medium shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        <x-heroicon-o-printer class="w-4 h-4" />
                        Print
                    </a>

                    {{-- Download PDF --}}
                    <x-filament::button wire:click="export('pdf')" color="danger" icon="heroicon-m-document-arrow-down">
                        Download PDF
                    </x-filament::button>

                    {{-- Download Excel --}}
                    <x-filament::button wire:click="export('excel')" color="success" icon="heroicon-m-table-cells">
                        Download Excel
                    </x-filament::button>
                </div>
            </div>

            {{-- Tabel Data --}}
            <x-filament::section>
                <x-slot name="heading">Detail Pergerakan Persediaan</x-slot>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr
                                class="border-b-2 border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-xs uppercase tracking-wide">
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Produk
                                </th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Gudang
                                </th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Saldo
                                    Awal<br><span class="font-normal normal-case">Qty&nbsp;|&nbsp;Nilai</span></th>
                                <th class="px-4 py-3 text-right font-semibold text-green-600 dark:text-green-400">
                                    Masuk<br><span
                                        class="font-normal normal-case text-gray-500">Qty&nbsp;|&nbsp;Nilai</span></th>
                                <th class="px-4 py-3 text-right font-semibold text-red-600 dark:text-red-400">
                                    Keluar<br><span
                                        class="font-normal normal-case text-gray-500">Qty&nbsp;|&nbsp;Nilai</span></th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Saldo
                                    Akhir<br><span class="font-normal normal-case">Qty&nbsp;|&nbsp;Nilai</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($report['rows'] ?? [] as $row)
                                <tr
                                    class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900/50 transition">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900 dark:text-gray-100">
                                            {{ $row['product_name'] }}</div>
                                        @if ($row['product_sku'])
                                            <div class="text-xs text-gray-400 dark:text-gray-500">SKU:
                                                {{ $row['product_sku'] }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900 dark:text-gray-100">
                                            {{ $row['warehouse_name'] }}</div>
                                        @if ($row['warehouse_code'])
                                            <div class="text-xs text-gray-400 dark:text-gray-500">
                                                {{ $row['warehouse_code'] }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="font-semibold text-gray-800 dark:text-gray-200">
                                            {{ number_format($row['opening_qty'], 2, ',', '.') }}</div>
                                        <div class="text-xs text-gray-400 dark:text-gray-500">Rp
                                            {{ number_format($row['opening_value'], 0, ',', '.') }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="font-semibold text-green-600 dark:text-green-400">
                                            {{ number_format($row['qty_in'], 2, ',', '.') }}</div>
                                        <div class="text-xs text-gray-400 dark:text-gray-500">Rp
                                            {{ number_format($row['value_in'], 0, ',', '.') }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="font-semibold text-red-600 dark:text-red-400">
                                            {{ number_format($row['qty_out'], 2, ',', '.') }}</div>
                                        <div class="text-xs text-gray-400 dark:text-gray-500">Rp
                                            {{ number_format($row['value_out'], 0, ',', '.') }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="font-semibold text-gray-800 dark:text-gray-200">
                                            {{ number_format($row['closing_qty'], 2, ',', '.') }}</div>
                                        <div class="text-xs text-gray-400 dark:text-gray-500">Rp
                                            {{ number_format($row['closing_value'], 0, ',', '.') }}</div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-12 text-center text-gray-400 dark:text-gray-500">
                                        <x-heroicon-o-inbox
                                            class="w-10 h-10 mx-auto mb-3 text-gray-300 dark:text-gray-600" />
                                        <p class="font-medium">Tidak ada data pergerakan pada periode ini</p>
                                        <p class="text-xs mt-1">Coba ubah filter atau perluas rentang tanggal</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if (count($report['rows'] ?? []) > 0)
                            <tfoot class="border-t-2 border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-900">
                                <tr class="font-bold">
                                    <td colspan="2"
                                        class="px-4 py-3 text-right text-gray-700 dark:text-gray-200 uppercase text-xs tracking-wide">
                                        Total Keseluruhan</td>
                                    <td class="px-4 py-3 text-right">
                                        <div>{{ number_format($report['totals']['opening_qty'], 2, ',', '.') }}</div>
                                        <div class="text-xs font-normal text-gray-500">Rp
                                            {{ number_format($report['totals']['opening_value'], 0, ',', '.') }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right text-green-700 dark:text-green-400">
                                        <div>{{ number_format($report['totals']['qty_in'], 2, ',', '.') }}</div>
                                        <div class="text-xs font-normal text-gray-500">Rp
                                            {{ number_format($report['totals']['value_in'], 0, ',', '.') }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right text-red-700 dark:text-red-400">
                                        <div>{{ number_format($report['totals']['qty_out'], 2, ',', '.') }}</div>
                                        <div class="text-xs font-normal text-gray-500">Rp
                                            {{ number_format($report['totals']['value_out'], 0, ',', '.') }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div>{{ number_format($report['totals']['closing_qty'], 2, ',', '.') }}</div>
                                        <div class="text-xs font-normal text-gray-500">Rp
                                            {{ number_format($report['totals']['closing_value'], 0, ',', '.') }}</div>
                                    </td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </x-filament::section>

            {{-- Summary Cards --}}
            @if (count($report['rows'] ?? []) > 0)
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div
                        class="bg-gray-50 dark:bg-gray-950/50 rounded-xl border border-gray-200 dark:border-gray-700 flex items-center justify-between" style="padding: 1.25rem;">
                        <div>
                            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                Saldo Awal</div>
                            <div class="text-xl font-bold text-gray-900 dark:text-gray-100 mt-1">Rp
                                {{ number_format($report['totals']['opening_value'], 0, ',', '.') }}</div>
                        </div>
                        <x-heroicon-o-scale class="w-8 h-8 text-gray-300 dark:text-gray-600 shrink-0" />
                    </div>
                    <div
                        class="bg-green-50 dark:bg-green-950/50 rounded-xl border border-green-200 dark:border-green-800 flex items-center justify-between" style="padding: 1.25rem;">
                        <div>
                            <div class="text-xs font-medium text-green-600 dark:text-green-400 uppercase tracking-wide">
                                Nilai Masuk</div>
                            <div class="text-xl font-bold text-green-900 dark:text-green-100 mt-1">Rp
                                {{ number_format($report['totals']['value_in'], 0, ',', '.') }}</div>
                        </div>
                        <x-heroicon-o-arrow-trending-up class="w-8 h-8 text-green-300 dark:text-green-700 shrink-0" />
                    </div>
                    <div
                        class="bg-red-50 dark:bg-red-950/50 rounded-xl border border-red-200 dark:border-red-800 flex items-center justify-between" style="padding: 1.25rem;">
                        <div>
                            <div class="text-xs font-medium text-red-600 dark:text-red-400 uppercase tracking-wide">
                                Nilai Keluar</div>
                            <div class="text-xl font-bold text-red-900 dark:text-red-100 mt-1">Rp
                                {{ number_format($report['totals']['value_out'], 0, ',', '.') }}</div>
                        </div>
                        <x-heroicon-o-arrow-trending-down class="w-8 h-8 text-red-300 dark:text-red-700 shrink-0" />
                    </div>
                    <div
                        class="bg-blue-50 dark:bg-blue-950/50 rounded-xl border border-blue-200 dark:border-blue-800 flex items-center justify-between" style="padding: 1.25rem;">
                        <div>
                            <div class="text-xs font-medium text-blue-600 dark:text-blue-400 uppercase tracking-wide">
                                Saldo Akhir</div>
                            <div class="text-xl font-bold text-blue-900 dark:text-blue-100 mt-1">Rp
                                {{ number_format($report['totals']['closing_value'], 0, ',', '.') }}</div>
                        </div>
                        <x-heroicon-o-currency-dollar class="w-8 h-8 text-blue-300 dark:text-blue-700 shrink-0" />
                    </div>
                </div>
            @endif

        @endif {{-- end showPreview --}}

    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Avoid jQuery conflict with Alpine.js
        window.jQuery = window.$ = jQuery.noConflict();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <style>
        .select2-container--default .select2-selection--single {
            background-color: rgb(249 250 251 / var(--tw-bg-opacity));
            border-color: rgb(156 163 175 / var(--tw-border-opacity));
            border-radius: 0.5rem;
            height: 2.5rem;
            border-width: 1px;
            padding: 0.5rem;
            font-size: 0.875rem;
            line-height: 1.25rem;
            color: rgb(17 24 39 / var(--tw-text-opacity));
        }

        .dark .select2-container--default .select2-selection--single {
            background-color: rgb(31 41 55 / var(--tw-bg-opacity));
            border-color: rgb(75 85 99 / var(--tw-border-opacity));
            color: rgb(243 244 246 / var(--tw-text-opacity));
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: inherit;
            line-height: 1.5;
            padding-left: 0;
        }

        .select2-dropdown {
            border-color: rgb(156 163 175 / var(--tw-border-opacity));
            border-radius: 0.5rem;
        }

        .dark .select2-dropdown {
            background-color: rgb(31 41 55 / var(--tw-bg-opacity));
            border-color: rgb(75 85 99 / var(--tw-border-opacity));
        }

        .select2-container--default .select2-results__option {
            color: rgb(17 24 39 / var(--tw-text-opacity));
            background-color: transparent;
        }

        .dark .select2-container--default .select2-results__option {
            color: rgb(243 244 246 / var(--tw-text-opacity));
        }

        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: rgb(59 130 246 / var(--tw-bg-opacity));
            color: white;
        }

        /* Ensure labels remain visible */
        label {
            color: rgb(55 65 81 / var(--tw-text-opacity)) !important;
        }

        .dark label {
            color: rgb(209 213 219 / var(--tw-text-opacity)) !important;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize select2 after a short delay to ensure DOM is ready
            setTimeout(function() {
                if (typeof $ !== 'undefined' && typeof $.fn.select2 !== 'undefined') {
                    $('#product-select').select2({
                        placeholder: '— Semua Produk —',
                        allowClear: true,
                        width: '100%',
                        theme: 'default'
                    });
                    $('#warehouse-select').select2({
                        placeholder: '— Semua Gudang —',
                        allowClear: true,
                        width: '100%',
                        theme: 'default'
                    });

                    // Set initial values
                    $('#product-select').val('{{ $this->productId }}').trigger('change');
                    $('#warehouse-select').val('{{ $this->warehouseId }}').trigger('change');

                    // Update Livewire when select2 changes
                    $('#product-select').on('change', function() {
                        $wire.set('productId', $(this).val());
                    });
                    $('#warehouse-select').on('change', function() {
                        $wire.set('warehouseId', $(this).val());
                    });
                } else {
                    console.error('jQuery or select2 not loaded');
                }
            }, 500); // Increased delay
        });
    </script>
</x-filament-panels::page>
