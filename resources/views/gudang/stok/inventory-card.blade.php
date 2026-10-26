@extends('layouts.app')

@section('title', 'Laporan Kartu Persediaan Konsolidasi – ERP Duta Tunggal')

@section('content')
<div class="space-y-6">
    {{-- Header & Breadcrumbs --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <nav class="flex items-center gap-2 text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">
                <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Dashboard</a>
                <span class="text-gray-300">/</span>
                <a href="{{ route('gudang.stok.index') }}" class="hover:text-gray-700">Gudang & Stok</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Laporan Kartu Persediaan</span>
            </nav>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Laporan Kartu Persediaan (Konsolidasi)</h1>
            <p class="text-xs text-gray-500 mt-1">
                Rekapitulasi pergerakan mutasi kuantitas dan nominal aset persediaan seluruh produk dalam periode pembukuan.
            </p>
        </div>
        <div class="flex items-center gap-2.5">
            <a href="{{ route('gudang.stok.inventory-card.pdf', request()->query()) }}"
               target="_blank"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M17 17h2a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h2m2 4h6a2 2 0 0 0 2-2v-4H7v4a2 2 0 0 0 2 2z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                    <path d="M17 9V5a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v4" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
                <span>Cetak PDF (Landscape)</span>
            </a>
            <a href="{{ route('inventory-card.excel', request()->query()) }}"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-[#15803d] bg-emerald-50 border border-emerald-200 rounded-xl hover:bg-emerald-100 transition shadow-sm">
                <svg class="w-4 h-4 text-[#15803d]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.5L17 7.5V19a2 2 0 0 1-2 2z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
                <span>Ekspor Excel</span>
            </a>
        </div>
    </div>

    {{-- Tabs Switcher --}}
    @include('gudang.stok.partials.tabs', ['activeTab' => 'inventory-card'])

    {{-- KPI Summary Cards --}}
    @php $totals = $reportData['totals']; @endphp
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Saldo Awal --}}
        <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm">
            <div class="flex items-center justify-between text-gray-500 mb-1.5">
                <span class="text-xs font-medium uppercase tracking-wider">Total Saldo Awal</span>
                <span class="text-[10px] text-gray-400">Sebelum {{ date('d/m/Y', strtotime($filters['start_date'])) }}</span>
            </div>
            <div class="text-2xl font-mono font-bold text-gray-900 tracking-tight">
                {{ number_format($totals['opening_qty'], 0, ',', '.') }} <span class="text-xs font-sans font-normal text-gray-500">unit</span>
            </div>
            <p class="text-[11px] text-gray-500 mt-1 font-mono">
                Rp {{ number_format($totals['opening_value'], 0, ',', '.') }}
            </p>
        </div>

        {{-- Total Masuk --}}
        <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm">
            <div class="flex items-center justify-between text-gray-500 mb-1.5">
                <span class="text-xs font-medium uppercase tracking-wider">Total Mutasi Masuk</span>
                <span class="w-6 h-6 rounded-full bg-emerald-50 text-[#15803d] flex items-center justify-center font-bold text-xs">&darr;</span>
            </div>
            <div class="text-2xl font-mono font-bold text-[#15803d] tracking-tight">
                +{{ number_format($totals['qty_in'], 0, ',', '.') }} <span class="text-xs font-sans font-normal text-emerald-600">unit</span>
            </div>
            <p class="text-[11px] text-emerald-600 mt-1 font-mono">
                +Rp {{ number_format($totals['value_in'], 0, ',', '.') }}
            </p>
        </div>

        {{-- Total Keluar --}}
        <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm">
            <div class="flex items-center justify-between text-gray-500 mb-1.5">
                <span class="text-xs font-medium uppercase tracking-wider">Total Mutasi Keluar</span>
                <span class="w-6 h-6 rounded-full bg-rose-50 text-rose-700 flex items-center justify-center font-bold text-xs">&uarr;</span>
            </div>
            <div class="text-2xl font-mono font-bold text-rose-700 tracking-tight">
                -{{ number_format($totals['qty_out'], 0, ',', '.') }} <span class="text-xs font-sans font-normal text-rose-600">unit</span>
            </div>
            <p class="text-[11px] text-rose-600 mt-1 font-mono">
                -Rp {{ number_format($totals['value_out'], 0, ',', '.') }}
            </p>
        </div>

        {{-- Total Saldo Akhir --}}
        <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm">
            <div class="flex items-center justify-between text-gray-500 mb-1.5">
                <span class="text-xs font-medium uppercase tracking-wider">Total Saldo Akhir</span>
                <span class="text-[10px] text-gray-400">Posisi {{ date('d/m/Y', strtotime($filters['end_date'])) }}</span>
            </div>
            <div class="text-2xl font-mono font-bold text-blue-700 tracking-tight">
                {{ number_format($totals['closing_qty'], 0, ',', '.') }} <span class="text-xs font-sans font-normal text-blue-600">unit</span>
            </div>
            <p class="text-[11px] text-blue-600 mt-1 font-mono">
                Rp {{ number_format($totals['closing_value'], 0, ',', '.') }}
            </p>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm">
        <form method="GET" action="{{ route('gudang.stok.inventory-card') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div>
                <label for="start_date" class="block text-xs font-medium text-gray-700 mb-1">Dari Tanggal *</label>
                <input type="date"
                       id="start_date"
                       name="start_date"
                       value="{{ $filters['start_date'] }}"
                       class="w-full py-2 px-3 text-xs border border-gray-200 rounded-xl focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] outline-none">
            </div>

            <div>
                <label for="end_date" class="block text-xs font-medium text-gray-700 mb-1">Sampai Tanggal *</label>
                <input type="date"
                       id="end_date"
                       name="end_date"
                       value="{{ $filters['end_date'] }}"
                       class="w-full py-2 px-3 text-xs border border-gray-200 rounded-xl focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] outline-none">
            </div>

            <div>
                <label for="warehouse_id" class="block text-xs font-medium text-gray-700 mb-1">Gudang</label>
                <x-searchable-select name="warehouse_id" :options="collect($warehouses)->map(fn ($wh) => ['id' => $wh->id, 'label' => $wh->name])->values()" :value="$filters['warehouse_id']" placeholder="Semua Gudang (Konsolidasi)" size="sm" id="warehouse_id" />
            </div>

            <div>
                <label for="product_id" class="block text-xs font-medium text-gray-700 mb-1">Spesifik Produk</label>
                <x-searchable-select name="product_id" :options="collect($products)->map(fn ($p) => ['id' => $p->id, 'label' => $p->name . ' (' . ($p->sku ?: '-') . ')'])->values()" :value="$filters['product_id']" placeholder="Semua Produk" size="sm" id="product_id" />
            </div>

            <div class="flex items-end gap-2">
                <button type="submit"
                        class="flex-1 py-2 px-3 text-xs font-semibold text-white bg-gray-900 hover:bg-black rounded-xl transition shadow-sm">
                    Tampilkan
                </button>
                <a href="{{ route('gudang.stok.inventory-card') }}"
                   class="px-3 py-2 text-xs font-medium text-gray-600 hover:text-gray-900 border border-gray-200 rounded-xl hover:bg-gray-50 transition"
                   title="Reset Filter">
                    Reset
                </a>
            </div>
        </form>
    </div>

    {{-- Consolidated Inventory Table --}}
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm">
        <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
            <div class="text-xs font-semibold text-gray-800">
                Rekapitulasi Kartu Persediaan Konsolidasi ({{ count($reportData['rows']) }} entri mutasi)
            </div>
            <div class="text-[11px] text-gray-500 font-mono">
                Periode: {{ date('d M Y', strtotime($filters['start_date'])) }} s/d {{ date('d M Y', strtotime($filters['end_date'])) }}
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-gray-600">
                <thead class="bg-gray-50 text-[11px] uppercase tracking-wider text-gray-500 font-semibold border-b border-gray-200">
                    <tr>
                        <th class="py-3 px-3 w-10 text-center">#</th>
                        <th class="py-3 px-4 min-w-[200px]">Nama Produk & SKU</th>
                        <th class="py-3 px-3">Gudang</th>
                        <th class="py-3 px-3 text-right">Saldo Awal (Qty)</th>
                        <th class="py-3 px-3 text-right">Masuk (Qty)</th>
                        <th class="py-3 px-3 text-right">Keluar (Qty)</th>
                        <th class="py-3 px-3 text-right bg-blue-50/30">Saldo Akhir (Qty)</th>
                        <th class="py-3 px-4 text-right">Nilai Akhir (Rp)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-xs text-gray-700">
                    @forelse ($reportData['rows'] as $i => $row)
                        <tr class="hover:bg-gray-50/80 transition-colors">
                            <td class="py-3 px-3 text-center text-gray-400 text-xs">{{ $i + 1 }}</td>
                            <td class="py-3 px-4">
                                <div class="font-bold text-gray-900 leading-snug">{{ $row['product_name'] }}</div>
                                <div class="text-[11px] text-gray-400 font-mono">{{ $row['product_sku'] ?: '-' }}</div>
                            </td>
                            <td class="py-3 px-3 text-xs text-gray-700">
                                {{ $row['warehouse_name'] }}
                            </td>
                            <td class="py-3 px-3 text-right font-mono text-gray-600">
                                {{ number_format($row['opening_qty'], 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-3 text-right font-mono font-bold text-emerald-700">
                                {{ $row['qty_in'] > 0 ? '+' . number_format($row['qty_in'], 0, ',', '.') : '-' }}
                            </td>
                            <td class="py-3 px-3 text-right font-mono font-bold text-rose-700">
                                {{ $row['qty_out'] > 0 ? '-' . number_format($row['qty_out'], 0, ',', '.') : '-' }}
                            </td>
                            <td class="py-3 px-3 text-right font-mono font-bold text-blue-800 bg-blue-50/30">
                                {{ number_format($row['closing_qty'], 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-gray-900">
                                Rp {{ number_format($row['closing_value'], 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-10 text-center text-gray-400">
                                Tidak ada mutasi persediaan tercatat pada periode tanggal ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-gray-50 border-t-2 border-gray-200 text-xs font-bold text-gray-900">
                    <tr>
                        <td colspan="3" class="py-3.5 px-4 text-right uppercase tracking-wider text-xs">
                            TOTAL KESELURUHAN:
                        </td>
                        <td class="py-3.5 px-3 text-right font-mono text-gray-700">
                            {{ number_format($totals['opening_qty'], 0, ',', '.') }}
                        </td>
                        <td class="py-3.5 px-3 text-right font-mono text-emerald-700">
                            +{{ number_format($totals['qty_in'], 0, ',', '.') }}
                        </td>
                        <td class="py-3.5 px-3 text-right font-mono text-rose-700">
                            -{{ number_format($totals['qty_out'], 0, ',', '.') }}
                        </td>
                        <td class="py-3.5 px-3 text-right font-mono text-blue-800 bg-blue-100/50">
                            {{ number_format($totals['closing_qty'], 0, ',', '.') }}
                        </td>
                        <td class="py-3.5 px-4 text-right font-mono text-gray-900">
                            Rp {{ number_format($totals['closing_value'], 0, ',', '.') }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
