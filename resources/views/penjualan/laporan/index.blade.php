@extends('layouts.app', ['title' => 'Laporan Penjualan'])

@inject('salesService', 'App\Services\Reports\SalesReportService')

@section('content')
<div class="space-y-6">

    {{-- Breadcrumb & Top Bar --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-gray-200">
        <div>
            <div class="flex items-center gap-2 text-xs text-gray-500 mb-1">
                <a href="{{ route('penjualan.so.index') }}" class="hover:text-gray-700">Penjualan</a>
                <span>&rsaquo;</span>
                <span class="text-gray-800 font-semibold">Laporan Penjualan</span>
            </div>
            <h1 class="text-2xl font-black text-gray-900 tracking-tight">Laporan Penjualan</h1>
            <p class="text-xs text-gray-500 mt-0.5">
                Pantau realisasi pendapatan, pengiriman barang, margin keuntungan, dan komitmen pesanan penjualan.
            </p>
        </div>

        {{-- Action Buttons (PDF & Excel) --}}
        <div class="flex items-center gap-2">
            <a
                href="{{ route('penjualan.laporan.pdf', request()->query()) }}"
                target="_blank"
                class="inline-flex items-center gap-2 px-3.5 py-2 bg-white hover:bg-gray-50 text-gray-700 text-xs font-semibold rounded-xl border border-gray-200 shadow-sm transition active:scale-[0.98]"
                title="Cetak Laporan PDF (A4 Landscape)"
            >
                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                <span>Cetak PDF</span>
            </a>

            <a
                href="{{ route('penjualan.laporan.excel', request()->query()) }}"
                class="inline-flex items-center gap-2 px-3.5 py-2 bg-[#15803d] hover:bg-[#166534] text-white text-xs font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
                title="Unduh Data ke Spreadsheet Excel (.xlsx)"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span>Ekspor Excel</span>
            </a>
        </div>
    </div>

    {{-- Mode Selector Tabs --}}
    @php
        $modes = [
            'invoice' => [
                'label' => 'Penjualan (Invoice)',
                'badge' => 'Akrual & Margin',
                'desc' => 'Pendapatan yang telah ditagih, HPP, margin kotor, dan status pembayaran.',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>',
            ],
            'delivery' => [
                'label' => 'Pengiriman (DO)',
                'badge' => 'Fisik Gudang',
                'desc' => 'Realisasi fisik barang keluar gudang, tanggal kirim, dan verifikasi barang.',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>',
            ],
            'order' => [
                'label' => 'Pesanan (SO)',
                'badge' => 'Komitmen Order',
                'desc' => 'Rekapitulasi pesanan penjualan (Sales Order) yang masuk dari customer.',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>',
            ],
        ];
    @endphp

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-1.5">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-1.5">
            @foreach ($modes as $key => $m)
                @php
                    $isActive = ($mode === $key);
                    $url = route('penjualan.laporan.index', array_merge(request()->query(), ['mode' => $key, 'status' => null, 'page' => 1]));
                @endphp
                <a
                    href="{{ $url }}"
                    class="flex items-start gap-3 p-3.5 rounded-xl transition {{ $isActive ? 'bg-[#f0fdf4] border border-[#86efac] shadow-xs' : 'hover:bg-gray-50 border border-transparent text-gray-700' }}"
                >
                    <span class="p-2 rounded-xl {{ $isActive ? 'bg-[#15803d] text-white' : 'bg-gray-100 text-gray-500' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            {!! $m['icon'] !!}
                        </svg>
                    </span>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold {{ $isActive ? 'text-[#15803d]' : 'text-gray-900' }}">
                                {{ $m['label'] }}
                            </span>
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded {{ $isActive ? 'bg-[#15803d] text-white' : 'bg-gray-100 text-gray-600' }}">
                                {{ $m['badge'] }}
                            </span>
                        </div>
                        <p class="text-[11px] mt-0.5 {{ $isActive ? 'text-emerald-800' : 'text-gray-400' }} leading-tight line-clamp-2">
                            {{ $m['desc'] }}
                        </p>
                    </div>
                </a>
            @endforeach
        </div>
    </div>

    {{-- Ringkasan KPI Cards --}}
    @if (!empty($summaryLines))
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            @foreach ($summaryLines as $label => $val)
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block mb-1">
                        {{ $label }}
                    </span>
                    <span class="text-base sm:text-lg font-black text-gray-900 font-mono tracking-tight block">
                        {{ $val }}
                    </span>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Filter Toolbar --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <form method="GET" action="{{ route('penjualan.laporan.index') }}" class="space-y-3">
            <input type="hidden" name="mode" value="{{ $mode }}">

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                {{-- Tanggal Mulai --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">
                        Tanggal Mulai ({{ $mode === 'order' ? 'Tgl SO' : ($mode === 'delivery' ? 'Tgl Kirim' : 'Tgl Invoice') }})
                    </label>
                    <input
                        type="date"
                        name="start_date"
                        value="{{ $startDate }}"
                        class="w-full text-xs rounded-xl border border-gray-200 px-3 py-2 bg-gray-50 focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] transition font-mono"
                    >
                </div>

                {{-- Tanggal Akhir --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">
                        Tanggal Akhir
                    </label>
                    <input
                        type="date"
                        name="end_date"
                        value="{{ $endDate }}"
                        class="w-full text-xs rounded-xl border border-gray-200 px-3 py-2 bg-gray-50 focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] transition font-mono"
                    >
                </div>

                {{-- Customer Filter --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">
                        Customer
                    </label>
                    <select
                        name="customer_id"
                        class="w-full text-xs rounded-xl border border-gray-200 px-3 py-2 bg-gray-50 focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] transition"
                    >
                        <option value="">-- Semua Customer --</option>
                        @foreach ($customers as $c)
                            <option value="{{ $c->id }}" {{ (string)$customerId === (string)$c->id ? 'selected' : '' }}>
                                {{ $c->code }} - {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Search No. Dokumen --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">
                        Cari Dokumen
                    </label>
                    <input
                        type="text"
                        name="so_number"
                        value="{{ $soNumber }}"
                        placeholder="{{ $mode === 'invoice' ? 'No. Invoice / No. SO' : ($mode === 'delivery' ? 'No. DO / No. SO' : 'No. SO') }}"
                        class="w-full text-xs rounded-xl border border-gray-200 px-3 py-2 bg-gray-50 focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] transition"
                    >
                </div>

                {{-- Status Filter --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">
                        {{ $statusLabel }}
                    </label>
                    <select
                        name="status"
                        class="w-full text-xs rounded-xl border border-gray-200 px-3 py-2 bg-gray-50 focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] transition"
                    >
                        <option value="">{{ $mode === 'delivery' ? 'Semua (Terkirim & Selesai)' : '-- Semua Status --' }}</option>
                        @foreach ($statusOptions as $sKey => $sVal)
                            <option value="{{ $sKey }}" {{ (string)$status === (string)$sKey ? 'selected' : '' }}>
                                {{ $sVal }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Filter Footer: Sort & Submit Buttons --}}
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-2 border-t border-gray-100">
                <div class="flex items-center gap-2">
                    <label class="text-xs text-gray-500 font-medium">Urutkan Nilai:</label>
                    <select
                        name="sort_by_total"
                        class="text-xs rounded-xl border border-gray-200 px-2.5 py-1.5 bg-gray-50 focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#15803d] transition"
                    >
                        <option value="">Default (Tanggal Terbaru)</option>
                        <option value="desc" {{ $sortByTotal === 'desc' ? 'selected' : '' }}>Tertinggi ke Terendah</option>
                        <option value="asc" {{ $sortByTotal === 'asc' ? 'selected' : '' }}>Terendah ke Tertinggi</option>
                    </select>
                </div>

                <div class="flex items-center gap-2 justify-end">
                    <a
                        href="{{ route('penjualan.laporan.index', ['mode' => $mode]) }}"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-xs font-semibold rounded-xl transition"
                        title="Reset Filter"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span>Reset</span>
                    </a>

                    <button
                        type="submit"
                        class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-[#15803d] hover:bg-[#166534] text-white text-xs font-bold rounded-xl shadow-sm transition active:scale-95"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        <span>Terapkan Filter</span>
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- Data Table --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            @if ($mode === 'invoice')
                {{-- TABEL MODE INVOICE --}}
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50/75 border-b border-gray-200 text-gray-600 font-bold uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="px-3.5 py-3">No. Invoice</th>
                            <th class="px-3.5 py-3">Tanggal</th>
                            <th class="px-3.5 py-3">Customer</th>
                            <th class="px-3.5 py-3">No. SO</th>
                            <th class="px-3.5 py-3 text-right">DPP</th>
                            <th class="px-3.5 py-3 text-right">PPN</th>
                            <th class="px-3.5 py-3 text-right font-black">Grand Total</th>
                            <th class="px-3.5 py-3 text-right">HPP</th>
                            <th class="px-3.5 py-3 text-right">Margin (Rp)</th>
                            <th class="px-3.5 py-3 text-right">Margin %</th>
                            <th class="px-3.5 py-3 text-center">Status Bayar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($records as $item)
                            @php
                                $r = $salesService->invoiceRow($item);
                                $isPositiveMargin = ($r['margin'] >= 0);
                            @endphp
                            <tr class="hover:bg-gray-50/80 transition">
                                <td class="px-3.5 py-2.5 font-mono font-bold text-gray-900">
                                    {{ $r['invoice_number'] }}
                                </td>
                                <td class="px-3.5 py-2.5 text-gray-600 font-mono">
                                    {{ \Carbon\Carbon::parse($r['invoice_date'])->format('d/m/Y') }}
                                </td>
                                <td class="px-3.5 py-2.5">
                                    <span class="font-bold text-gray-900 block">{{ $r['customer_name'] }}</span>
                                    <span class="text-[11px] font-mono text-gray-400">{{ $r['customer_code'] }}</span>
                                </td>
                                <td class="px-3.5 py-2.5 font-mono text-gray-600">
                                    {{ $r['so_number'] ?: '-' }}
                                </td>
                                <td class="px-3.5 py-2.5 text-right font-mono text-gray-700">
                                    Rp {{ number_format($r['dpp'], 0, ',', '.') }}
                                </td>
                                <td class="px-3.5 py-2.5 text-right font-mono text-gray-700">
                                    Rp {{ number_format($r['ppn'], 0, ',', '.') }}
                                </td>
                                <td class="px-3.5 py-2.5 text-right font-mono font-extrabold text-gray-900">
                                    Rp {{ number_format($r['total'], 0, ',', '.') }}
                                </td>
                                <td class="px-3.5 py-2.5 text-right font-mono text-gray-600">
                                    Rp {{ number_format($r['hpp'], 0, ',', '.') }}
                                    @if ($r['hpp_estimated'])
                                        <span class="text-amber-500 font-bold" title="Estimasi HPP">*</span>
                                    @endif
                                </td>
                                <td class="px-3.5 py-2.5 text-right font-mono font-bold {{ $isPositiveMargin ? 'text-[#15803d]' : 'text-red-600' }}">
                                    Rp {{ number_format($r['margin'], 0, ',', '.') }}
                                </td>
                                <td class="px-3.5 py-2.5 text-right font-mono font-bold {{ $isPositiveMargin ? 'text-[#15803d]' : 'text-red-600' }}">
                                    {{ number_format($r['margin_pct'], 1, ',', '.') }}%
                                </td>
                                <td class="px-3.5 py-2.5 text-center">
                                    @php
                                        $pStatus = $r['payment_status'];
                                        $badgeClass = match ($pStatus) {
                                            'lunas' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                            'sebagian' => 'bg-blue-50 text-blue-800 border-blue-200',
                                            'jatuh_tempo' => 'bg-red-50 text-red-800 border-red-200',
                                            default => 'bg-amber-50 text-amber-800 border-amber-200',
                                        };
                                        $pLabel = \App\Services\Reports\SalesReportService::PAYMENT_STATUS_LABELS[$pStatus] ?? ucfirst($pStatus);
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold border uppercase tracking-wider {{ $badgeClass }}">
                                        {{ $pLabel }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center py-12 text-gray-400">
                                    <svg class="w-12 h-12 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    Tidak ada transaksi invoice penjualan yang sesuai dengan filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

            @elseif ($mode === 'delivery')
                {{-- TABEL MODE PENGIRIMAN (DELIVERY ORDER) --}}
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50/75 border-b border-gray-200 text-gray-600 font-bold uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="px-3.5 py-3">No. DO</th>
                            <th class="px-3.5 py-3">Tgl Kirim</th>
                            <th class="px-3.5 py-3">Customer</th>
                            <th class="px-3.5 py-3">No. SO</th>
                            <th class="px-3.5 py-3">No. Invoice</th>
                            <th class="px-3.5 py-3 text-center">Status</th>
                            <th class="px-3.5 py-3 text-right">Kuantitas</th>
                            <th class="px-3.5 py-3 text-right">DPP Nilai Jual</th>
                            <th class="px-3.5 py-3 text-right">HPP Stok</th>
                            <th class="px-3.5 py-3 text-right">Margin</th>
                            <th class="px-3.5 py-3 text-right">Margin %</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($records as $item)
                            @php
                                $r = $salesService->deliveryRow($item);
                                $isPositiveMargin = ($r['margin'] >= 0);
                            @endphp
                            <tr class="hover:bg-gray-50/80 transition">
                                <td class="px-3.5 py-2.5 font-mono font-bold text-gray-900">
                                    {{ $r['do_number'] }}
                                </td>
                                <td class="px-3.5 py-2.5 text-gray-600 font-mono">
                                    {{ $r['delivery_date'] ? \Carbon\Carbon::parse($r['delivery_date'])->format('d/m/Y') : '-' }}
                                </td>
                                <td class="px-3.5 py-2.5 font-bold text-gray-900">
                                    {{ $r['customer_name'] }}
                                </td>
                                <td class="px-3.5 py-2.5 font-mono text-gray-600">
                                    {{ $r['so_numbers'] ?: '-' }}
                                </td>
                                <td class="px-3.5 py-2.5 font-mono text-gray-600">
                                    {{ implode(', ', $r['invoice_numbers']) ?: '-' }}
                                </td>
                                <td class="px-3.5 py-2.5 text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold border uppercase tracking-wider bg-emerald-50 text-[#15803d] border-emerald-200">
                                        {{ \App\Models\DeliveryOrder::statusLabel($r['status']) }}
                                    </span>
                                </td>
                                <td class="px-3.5 py-2.5 text-right font-mono font-bold text-gray-900">
                                    {{ number_format($r['quantity'], 0, ',', '.') }} pcs
                                </td>
                                <td class="px-3.5 py-2.5 text-right font-mono text-gray-700">
                                    Rp {{ number_format($r['dpp'], 0, ',', '.') }}
                                </td>
                                <td class="px-3.5 py-2.5 text-right font-mono text-gray-600">
                                    Rp {{ number_format($r['hpp'], 0, ',', '.') }}
                                </td>
                                <td class="px-3.5 py-2.5 text-right font-mono font-bold {{ $isPositiveMargin ? 'text-[#15803d]' : 'text-red-600' }}">
                                    Rp {{ number_format($r['margin'], 0, ',', '.') }}
                                </td>
                                <td class="px-3.5 py-2.5 text-right font-mono font-bold {{ $isPositiveMargin ? 'text-[#15803d]' : 'text-red-600' }}">
                                    {{ number_format($r['margin_pct'], 1, ',', '.') }}%
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center py-12 text-gray-400">
                                    <svg class="w-12 h-12 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                                    </svg>
                                    Tidak ada data pengiriman (DO) yang sesuai dengan filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

            @else
                {{-- TABEL MODE PESANAN (SALES ORDER) --}}
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50/75 border-b border-gray-200 text-gray-600 font-bold uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="px-3.5 py-3">No. SO</th>
                            <th class="px-3.5 py-3">Tanggal Order</th>
                            <th class="px-3.5 py-3">Customer</th>
                            <th class="px-3.5 py-3 text-center">Status</th>
                            <th class="px-3.5 py-3 text-right font-black">Total Nilai Pesanan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($records as $so)
                            <tr class="hover:bg-gray-50/80 transition">
                                <td class="px-3.5 py-2.5 font-mono font-bold text-gray-900">
                                    <a href="{{ route('penjualan.so.show', $so) }}" class="text-[#15803d] hover:underline">
                                        {{ $so->so_number }}
                                    </a>
                                </td>
                                <td class="px-3.5 py-2.5 text-gray-600 font-mono">
                                    {{ \Carbon\Carbon::parse($so->order_date)->format('d/m/Y') }}
                                </td>
                                <td class="px-3.5 py-2.5">
                                    <span class="font-bold text-gray-900 block">{{ $so->customer->name ?? 'Customer' }}</span>
                                    <span class="text-[11px] font-mono text-gray-400">{{ $so->customer->code ?? '-' }}</span>
                                </td>
                                <td class="px-3.5 py-2.5 text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold border uppercase tracking-wider bg-gray-50 text-gray-700 border-gray-200">
                                        {{ \App\Models\SaleOrder::statusLabel($so->status) }}
                                    </span>
                                </td>
                                <td class="px-3.5 py-2.5 text-right font-mono font-extrabold text-gray-900">
                                    Rp {{ number_format($so->total_amount, 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-12 text-gray-400">
                                    <svg class="w-12 h-12 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                    </svg>
                                    Tidak ada data pesanan (SO) yang sesuai dengan filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Footnote & Pagination Footer --}}
        <div class="px-4 py-3 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                @if ($footnote)
                    <p class="text-[11px] text-gray-400 italic">
                        {{ $footnote }}
                    </p>
                @endif
            </div>

            <div>
                {{ $records->links() }}
            </div>
        </div>
    </div>

</div>
@endsection
