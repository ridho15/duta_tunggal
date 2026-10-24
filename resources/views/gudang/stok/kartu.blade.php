@extends('layouts.app')

@section('title', 'Kartu Persediaan: ' . $product->name . ' – ERP Duta Tunggal')

@section('content')
<div class="space-y-6">
    {{-- Header & Breadcrumbs --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <nav class="flex items-center gap-2 text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">
                <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Dashboard</a>
                <span class="text-gray-300">/</span>
                <a href="{{ route('gudang.stok.index') }}" class="hover:text-gray-700">Cek Stok</a>
                <span class="text-gray-300">/</span>
                <span class="text-emerald-700 font-bold">Kartu Persediaan</span>
            </nav>
            <div class="flex items-center gap-3">
                <a href="{{ route('gudang.stok.index') }}"
                   class="p-1.5 rounded-lg border border-gray-200 text-gray-500 hover:text-gray-900 hover:bg-gray-100 transition shadow-2xs"
                   title="Kembali ke Daftar Stok">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M10 19l-7-7m0 0l7-7m-7 7h18" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </a>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">
                    Kartu Persediaan: {{ $product->name }}
                </h1>
            </div>
            <p class="text-xs text-gray-500 mt-1">
                Audit trail mutasi keluar-masuk barang secara kronologis lengkap dengan saldo berjalan (running balance) dan rujukan dokumen transaksi.
            </p>
        </div>
        <div class="flex items-center gap-2.5">
            <a href="{{ route('gudang.stok.card.pdf', [$product->id] + request()->query()) }}"
               target="_blank"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition shadow-xs">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M17 17h2a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h2m2 4h6a2 2 0 0 0 2-2v-4H7v4a2 2 0 0 0 2 2z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                    <path d="M17 9V5a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v4" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
                <span>Cetak PDF (Landscape)</span>
            </a>
            <a href="{{ route('gudang.stok.card.excel', [$product->id] + request()->query()) }}"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-emerald-800 bg-emerald-50 border border-emerald-300 rounded-lg hover:bg-emerald-100 transition shadow-2xs">
                <svg class="w-4 h-4 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.5L17 7.5V19a2 2 0 0 1-2 2z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
                <span>Ekspor Excel</span>
            </a>
        </div>
    </div>

    {{-- Product Identity Card --}}
    <div class="bg-white rounded-xl p-4.5 border border-gray-200 shadow-2xs">
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-4 text-xs">
            <div>
                <span class="text-[11px] text-gray-400 uppercase tracking-wider block">Kode / SKU</span>
                <span class="font-mono font-bold text-gray-900 bg-gray-100 px-1.5 py-0.5 rounded text-[11px] inline-block mt-0.5">
                    {{ $product->sku ?: 'NO-SKU' }}
                </span>
            </div>
            <div>
                <span class="text-[11px] text-gray-400 uppercase tracking-wider block">Kategori</span>
                <span class="font-semibold text-gray-800 block mt-0.5">
                    {{ $product->productCategory?->name ?? '-' }}
                </span>
            </div>
            <div>
                <span class="text-[11px] text-gray-400 uppercase tracking-wider block">Satuan (UOM)</span>
                <span class="font-semibold text-gray-800 block mt-0.5">
                    {{ $product->uom?->name ?? 'Pcs' }}
                </span>
            </div>
            <div>
                <span class="text-[11px] text-gray-400 uppercase tracking-wider block">HPP Satuan</span>
                <span class="font-mono font-bold text-gray-900 block mt-0.5">
                    Rp {{ number_format($product->cost_price, 0, ',', '.') }}
                </span>
            </div>
            <div>
                <span class="text-[11px] text-gray-400 uppercase tracking-wider block">Harga Jual</span>
                <span class="font-mono font-bold text-emerald-700 block mt-0.5">
                    Rp {{ number_format($product->sell_price, 0, ',', '.') }}
                </span>
            </div>
            <div>
                <span class="text-[11px] text-gray-400 uppercase tracking-wider block">Cabang Kepemilikan</span>
                <span class="font-semibold text-gray-800 block mt-0.5">
                    {{ $product->cabang?->nama ?? 'Pusat' }}
                </span>
            </div>
        </div>
    </div>

    {{-- Filter Bar & Quick Presets --}}
    <div class="bg-white rounded-xl p-4 border border-gray-200 shadow-2xs space-y-3">
        <form method="GET" action="{{ route('gudang.stok.card', $product->id) }}" id="filterForm" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            {{-- Dari Tanggal --}}
            <div>
                <label for="start_date" class="block text-xs font-medium text-gray-700 mb-1">Dari Tanggal *</label>
                <input type="date"
                       id="start_date"
                       name="start_date"
                       value="{{ $filters['start_date'] }}"
                       class="w-full py-2 px-3 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
            </div>

            {{-- Sampai Tanggal --}}
            <div>
                <label for="end_date" class="block text-xs font-medium text-gray-700 mb-1">Sampai Tanggal *</label>
                <input type="date"
                       id="end_date"
                       name="end_date"
                       value="{{ $filters['end_date'] }}"
                       class="w-full py-2 px-3 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
            </div>

            {{-- Filter Gudang --}}
            <div>
                <label for="warehouse_id" class="block text-xs font-medium text-gray-700 mb-1">Gudang</label>
                <x-searchable-select name="warehouse_id" :options="collect($warehouses)->map(fn ($wh) => ['id' => $wh->id, 'label' => $wh->name])->values()" :value="$filters['warehouse_id']" placeholder="Semua Gudang (Konsolidasian)" size="sm" id="warehouse_id" />
            </div>

            {{-- Submit & Reset Buttons --}}
            <div class="flex items-end gap-2">
                <button type="submit"
                        class="flex-1 py-2 px-3 text-xs font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition shadow-xs">
                    Tampilkan Mutasi
                </button>
                <a href="{{ route('gudang.stok.card', $product->id) }}"
                   class="px-3 py-2 text-xs font-medium text-gray-600 hover:text-gray-900 border border-gray-300 rounded-lg hover:bg-gray-50 transition"
                   title="Reset Filter">
                    Reset
                </a>
            </div>
        </form>

        {{-- Quick Presets --}}
        <div class="pt-2 border-t border-gray-100 flex items-center gap-1.5 flex-wrap text-xs text-gray-500">
            <span class="text-[11px] font-medium text-gray-400 uppercase tracking-wider mr-1">Preset Cepat:</span>
            <button type="button" onclick="setPreset('today')" class="px-2.5 py-1 text-[11.5px] rounded-md bg-gray-100 hover:bg-emerald-100 hover:text-emerald-800 transition">Hari Ini</button>
            <button type="button" onclick="setPreset('this_month')" class="px-2.5 py-1 text-[11.5px] rounded-md bg-gray-100 hover:bg-emerald-100 hover:text-emerald-800 transition">Bulan Ini</button>
            <button type="button" onclick="setPreset('last_month')" class="px-2.5 py-1 text-[11.5px] rounded-md bg-gray-100 hover:bg-emerald-100 hover:text-emerald-800 transition">Bulan Lalu</button>
            <button type="button" onclick="setPreset('quarter')" class="px-2.5 py-1 text-[11.5px] rounded-md bg-gray-100 hover:bg-emerald-100 hover:text-emerald-800 transition">Triwulan Ini</button>
            <button type="button" onclick="setPreset('ytd')" class="px-2.5 py-1 text-[11.5px] rounded-md bg-gray-100 hover:bg-emerald-100 hover:text-emerald-800 transition">Tahun Ini (YTD)</button>
        </div>
    </div>

    {{-- Period KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Saldo Awal --}}
        <div class="bg-white rounded-xl p-4.5 border border-gray-200 shadow-2xs">
            <div class="flex items-center justify-between text-gray-500 mb-1.5">
                <span class="text-xs font-medium uppercase tracking-wider">Saldo Awal</span>
                <span class="text-[10px] text-gray-400">Sebelum {{ date('d/m/Y', strtotime($filters['start_date'])) }}</span>
            </div>
            <div class="text-2xl font-mono font-bold text-gray-900 tracking-tight">
                {{ number_format($openingQty, 0, ',', '.') }}
            </div>
            <p class="text-[11px] text-gray-500 mt-1">Stok bawaan periode sebelumnya</p>
        </div>

        {{-- Total Masuk --}}
        <div class="bg-white rounded-xl p-4.5 border border-gray-200 shadow-2xs">
            <div class="flex items-center justify-between text-gray-500 mb-1.5">
                <span class="text-xs font-medium uppercase tracking-wider">Total Masuk (+)</span>
                <span class="w-6 h-6 rounded-full bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold text-xs">
                    &darr;
                </span>
            </div>
            <div class="text-2xl font-mono font-bold text-emerald-700 tracking-tight">
                +{{ number_format($totalIn, 0, ',', '.') }}
            </div>
            <p class="text-[11px] text-emerald-600 mt-1">Penerimaan pembelian, retur, transfer</p>
        </div>

        {{-- Total Keluar --}}
        <div class="bg-white rounded-xl p-4.5 border border-gray-200 shadow-2xs">
            <div class="flex items-center justify-between text-gray-500 mb-1.5">
                <span class="text-xs font-medium uppercase tracking-wider">Total Keluar (-)</span>
                <span class="w-6 h-6 rounded-full bg-rose-50 text-rose-700 flex items-center justify-center font-bold text-xs">
                    &uarr;
                </span>
            </div>
            <div class="text-2xl font-mono font-bold text-rose-700 tracking-tight">
                -{{ number_format($totalOut, 0, ',', '.') }}
            </div>
            <p class="text-[11px] text-rose-600 mt-1">Pengiriman penjualan, retur vendor</p>
        </div>

        {{-- Saldo Akhir Berjalan --}}
        <div class="bg-white rounded-xl p-4.5 border border-gray-200 shadow-2xs">
            <div class="flex items-center justify-between text-gray-500 mb-1.5">
                <span class="text-xs font-medium uppercase tracking-wider">Saldo Akhir</span>
                <span class="text-[10px] text-gray-400">Posisi {{ date('d/m/Y', strtotime($filters['end_date'])) }}</span>
            </div>
            <div class="text-2xl font-mono font-bold text-blue-700 tracking-tight">
                {{ number_format($closingQty, 0, ',', '.') }}
            </div>
            <p class="text-[11px] text-gray-500 mt-1">Saldo fisik akhir pada rentang tanggal</p>
        </div>
    </div>

    {{-- Chronological Movement Ledger Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-2xs">
        <div class="px-5 py-3.5 border-b border-gray-200 flex items-center justify-between bg-gray-50/50">
            <div class="text-xs font-semibold text-gray-800">
                Histori Mutasi Kronologis ({{ count($movements) }} transaksi dalam periode)
            </div>
            <div class="text-[11px] text-gray-500 font-mono">
                Periode: {{ date('d M Y', strtotime($filters['start_date'])) }} s/d {{ date('d M Y', strtotime($filters['end_date'])) }}
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-gray-600">
                <thead class="bg-gray-50 text-[11px] uppercase tracking-wider text-gray-500 font-semibold border-b border-gray-200">
                    <tr>
                        <th class="py-3 px-4 w-12 text-center">#</th>
                        <th class="py-3 px-4 w-36">Tanggal & Jam</th>
                        <th class="py-3 px-4 min-w-[170px]">No. Dokumen / Referensi</th>
                        <th class="py-3 px-3">Tipe Mutasi</th>
                        <th class="py-3 px-3">Gudang & Rak</th>
                        <th class="py-3 px-4 text-right w-28">Masuk (+)</th>
                        <th class="py-3 px-4 text-right w-28">Keluar (-)</th>
                        <th class="py-3 px-4 text-right w-32 bg-blue-50/30">Saldo Berjalan</th>
                        <th class="py-3 px-4 min-w-[180px]">Catatan / Keterangan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 font-mono text-[12px]">
                    {{-- Baris Saldo Awal --}}
                    <tr class="bg-gray-50/60 font-semibold text-gray-700">
                        <td class="py-3 px-4 text-center text-gray-400 font-sans">•</td>
                        <td class="py-3 px-4">{{ date('d/m/Y', strtotime($filters['start_date'])) }}</td>
                        <td class="py-3 px-4 font-sans text-gray-500 italic">SALDO-AWAL</td>
                        <td class="py-3 px-3 font-sans">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10.5px] font-medium bg-gray-100 text-gray-700">
                                Saldo Awal Periode
                            </span>
                        </td>
                        <td class="py-3 px-3 font-sans text-gray-500">-</td>
                        <td class="py-3 px-4 text-right text-gray-400">-</td>
                        <td class="py-3 px-4 text-right text-gray-400">-</td>
                        <td class="py-3 px-4 text-right font-bold text-gray-900 bg-blue-50/40">
                            {{ number_format($openingQty, 0, ',', '.') }}
                        </td>
                        <td class="py-3 px-4 font-sans text-gray-500 text-xs italic">
                            Saldo bawaan per awal tanggal {{ date('d M Y', strtotime($filters['start_date'])) }}
                        </td>
                    </tr>

                    {{-- Baris Mutasi Transaksi --}}
                    @forelse ($movements as $idx => $m)
                        <tr class="hover:bg-gray-50/80 transition-colors">
                            <td class="py-3 px-4 text-center text-gray-400 font-sans text-xs">
                                {{ $idx + 1 }}
                            </td>
                            <td class="py-3 px-4 text-gray-900">
                                {{ date('d/m/Y H:i', strtotime($m['date'])) }}
                            </td>
                            <td class="py-3 px-4">
                                @if ($m['reference_url'])
                                    <a href="{{ $m['reference_url'] }}"
                                       class="font-bold text-emerald-700 hover:text-emerald-900 hover:underline flex items-center gap-1"
                                       target="_blank">
                                        <span>{{ $m['reference_number'] }}</span>
                                        <svg class="w-3 h-3 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path d="M10 6H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-4M14 4h6m0 0v6m0-6L10 14" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                        </svg>
                                    </a>
                                @else
                                    <span class="font-bold text-gray-800">{{ $m['reference_number'] }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-3 font-sans">
                                @php
                                    $typeBadge = match(true) {
                                        str_contains($m['type'], 'in') => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                        str_contains($m['type'], 'out') || $m['type'] === 'sales' => 'bg-rose-50 text-rose-800 border-rose-200',
                                        default => 'bg-blue-50 text-blue-800 border-blue-200',
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10.5px] font-medium border {{ $typeBadge }}">
                                    {{ $m['type_label'] }}
                                </span>
                            </td>
                            <td class="py-3 px-3 font-sans">
                                <div class="text-xs text-gray-900 font-medium">{{ $m['warehouse_name'] }}</div>
                                @if ($m['rak_name'] !== '-')
                                    <div class="text-[10px] text-gray-400">Rak: {{ $m['rak_name'] }}</div>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right font-bold text-emerald-700">
                                {{ $m['qty_in'] > 0 ? '+' . number_format($m['qty_in'], 0, ',', '.') : '-' }}
                            </td>
                            <td class="py-3 px-4 text-right font-bold text-rose-700">
                                {{ $m['qty_out'] > 0 ? '-' . number_format($m['qty_out'], 0, ',', '.') : '-' }}
                            </td>
                            <td class="py-3 px-4 text-right font-bold text-blue-800 bg-blue-50/30">
                                {{ number_format($m['running_balance'], 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 font-sans text-xs text-gray-600">
                                {{ $m['notes'] }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-8 text-center text-gray-400 font-sans">
                                Belum ada riwayat mutasi barang pada periode tanggal yang dipilih.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-gray-50 border-t-2 border-gray-300 font-mono text-[12px] font-bold text-gray-900">
                    <tr>
                        <td colspan="5" class="py-3.5 px-4 font-sans text-right uppercase tracking-wider text-xs">
                            TOTAL MUTASI PERIODE & SALDO AKHIR:
                        </td>
                        <td class="py-3.5 px-4 text-right text-emerald-700">
                            +{{ number_format($totalIn, 0, ',', '.') }}
                        </td>
                        <td class="py-3.5 px-4 text-right text-rose-700">
                            -{{ number_format($totalOut, 0, ',', '.') }}
                        </td>
                        <td class="py-3.5 px-4 text-right text-blue-800 bg-blue-100/50">
                            {{ number_format($closingQty, 0, ',', '.') }}
                        </td>
                        <td class="py-3.5 px-4 font-sans text-xs text-gray-500 font-normal">
                            Posisi akhir per {{ date('d M Y', strtotime($filters['end_date'])) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script>
    function setPreset(preset) {
        const now = new Date();
        const startInput = document.getElementById('start_date');
        const endInput = document.getElementById('end_date');

        let startDate, endDate;

        if (preset === 'today') {
            startDate = formatDate(now);
            endDate = formatDate(now);
        } else if (preset === 'this_month') {
            startDate = formatDate(new Date(now.getFullYear(), now.getMonth(), 1));
            endDate = formatDate(new Date(now.getFullYear(), now.getMonth() + 1, 0));
        } else if (preset === 'last_month') {
            startDate = formatDate(new Date(now.getFullYear(), now.getMonth() - 1, 1));
            endDate = formatDate(new Date(now.getFullYear(), now.getMonth(), 0));
        } else if (preset === 'quarter') {
            const quarter = Math.floor(now.getMonth() / 3);
            startDate = formatDate(new Date(now.getFullYear(), quarter * 3, 1));
            endDate = formatDate(new Date(now.getFullYear(), (quarter + 1) * 3, 0));
        } else if (preset === 'ytd') {
            startDate = formatDate(new Date(now.getFullYear(), 0, 1));
            endDate = formatDate(now);
        }

        if (startDate && endDate) {
            startInput.value = startDate;
            endInput.value = endDate;
            document.getElementById('filterForm').submit();
        }
    }

    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
</script>
@endsection
