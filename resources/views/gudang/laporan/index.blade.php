@extends('layouts.app', ['title' => 'Laporan Stok & Mutasi'])

@inject('invService', 'App\Services\Reports\InventoryReportService')

@section('content')
<div class="space-y-6">

    {{-- Breadcrumb & Top Bar --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-gray-200">
        <div>
            <div class="flex items-center gap-2 text-xs text-gray-500 mb-1">
                <a href="{{ route('gudang.stok.index') }}" class="hover:text-gray-700">Gudang & Stok</a>
                <span>&rsaquo;</span>
                <span class="text-gray-800 font-semibold">Laporan Stok & Mutasi</span>
            </div>
            <h1 class="text-2xl font-black text-gray-900 tracking-tight">Laporan Persediaan & Mutasi Stok</h1>
            <p class="text-xs text-gray-500 mt-0.5">
                Pantau saldo fisik persediaan, pergerakan barang masuk/keluar, dan analisis perputaran umur stok.
            </p>
        </div>

        {{-- Action Buttons (PDF & Excel) --}}
        <div class="flex items-center gap-2">
            <a
                href="{{ route('gudang.laporan.pdf', request()->query()) }}"
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
                href="{{ route('gudang.laporan.excel', request()->query()) }}"
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
        $tabs = [
            'stock' => [
                'label' => 'Posisi Stok Fisik',
                'badge' => 'Saldo Real-Time',
                'desc' => 'Saldo fisik di gudang, kuantitas reserved, stok bebas, dan peringatan batas minimum.',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>',
            ],
            'movement' => [
                'label' => 'Riwayat Mutasi Stok',
                'badge' => 'Pergerakan Barang',
                'desc' => 'Pergerakan barang masuk (PO), keluar (DO), pindah antar gudang, dan hasil stok opname.',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>',
            ],
            'aging' => [
                'label' => 'Analisis Umur Stok',
                'badge' => 'Stock Aging',
                'desc' => 'Klasifikasi perputaran barang: Aktif (≤30h), Slow Moving (≤90h), Stagnan, & Dead Stock.',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>',
            ],
        ];
    @endphp

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-1.5">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-1.5">
            @foreach ($tabs as $key => $t)
                @php
                    $isActive = ($type === $key);
                    $url = route('gudang.laporan.index', array_merge(request()->query(), ['type' => $key, 'page' => 1]));
                @endphp
                <a
                    href="{{ $url }}"
                    class="flex items-start gap-3 p-3.5 rounded-xl transition {{ $isActive ? 'bg-[#f0fdf4] border border-[#86efac] shadow-sm' : 'hover:bg-gray-50 border border-transparent text-gray-700' }}"
                >
                    <span class="p-2 rounded-xl {{ $isActive ? 'bg-[#15803d] text-white' : 'bg-gray-100 text-gray-500' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            {!! $t['icon'] !!}
                        </svg>
                    </span>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold {{ $isActive ? 'text-[#15803d]' : 'text-gray-900' }}">
                                {{ $t['label'] }}
                            </span>
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded {{ $isActive ? 'bg-[#15803d] text-white' : 'bg-gray-100 text-gray-600' }}">
                                {{ $t['badge'] }}
                            </span>
                        </div>
                        <p class="text-[11px] mt-0.5 {{ $isActive ? 'text-[#166534]' : 'text-gray-400' }} leading-tight line-clamp-2">
                            {{ $t['desc'] }}
                        </p>
                    </div>
                </a>
            @endforeach
        </div>
    </div>

    {{-- Ringkasan KPI Cards --}}
    @if (!empty($kpi))
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block mb-1">
                    Total SKU Terdaftar
                </span>
                <span class="text-base sm:text-lg font-black text-gray-900 font-mono tracking-tight block">
                    {{ number_format($kpi['total_sku'], 0, ',', '.') }} SKU
                </span>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block mb-1">
                    Total Kuantitas Fisik
                </span>
                <span class="text-base sm:text-lg font-black text-[#15803d] font-mono tracking-tight block">
                    {{ number_format($kpi['total_qty'], 0, ',', '.') }} pcs
                </span>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block mb-1">
                    Stok Dipesan (Reserved)
                </span>
                <span class="text-base sm:text-lg font-black text-amber-600 font-mono tracking-tight block">
                    {{ number_format($kpi['total_reserved'], 0, ',', '.') }} pcs
                </span>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block mb-1">
                    Di Bawah Batas Minimum
                </span>
                <span class="text-base sm:text-lg font-black {{ $kpi['low_stock_count'] > 0 ? 'text-red-600' : 'text-gray-900' }} font-mono tracking-tight block">
                    {{ number_format($kpi['low_stock_count'], 0, ',', '.') }} Item
                </span>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block mb-1">
                    Gudang Aktif
                </span>
                <span class="text-base sm:text-lg font-black text-gray-900 font-mono tracking-tight block">
                    {{ number_format($kpi['total_warehouses'], 0, ',', '.') }} Lokasi
                </span>
            </div>
        </div>
    @endif

    {{-- Filter Toolbar --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <form method="GET" action="{{ route('gudang.laporan.index') }}" class="space-y-3">
            <input type="hidden" name="type" value="{{ $type }}">

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                {{-- Gudang Filter --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">
                        Pilih Gudang
                    </label>
                    <select
                        name="warehouse_id"
                        class="w-full text-xs rounded-xl border border-gray-200 px-3 py-2 bg-gray-50 focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] transition"
                    >
                        <option value="">-- Semua Gudang --</option>
                        @foreach ($warehouses as $w)
                            <option value="{{ $w->id }}" {{ (string)$warehouseId === (string)$w->id ? 'selected' : '' }}>
                                {{ $w->name }} ({{ $w->kode ?? $w->code }})
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Produk Filter --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">
                        Pilih Produk
                    </label>
                    <select
                        name="product_id"
                        class="w-full text-xs rounded-xl border border-gray-200 px-3 py-2 bg-gray-50 focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] transition"
                    >
                        <option value="">-- Semua Produk --</option>
                        @foreach ($products as $p)
                            <option value="{{ $p->id }}" {{ (string)$productId === (string)$p->id ? 'selected' : '' }}>
                                {{ $p->sku }} - {{ $p->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Tanggal Mulai (untuk mutasi) --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">
                        Tanggal Mulai {{ $type !== 'movement' ? '(As of Date)' : '' }}
                    </label>
                    <input
                        type="date"
                        name="start_date"
                        value="{{ $startDate }}"
                        class="w-full text-xs rounded-xl border border-gray-200 px-3 py-2 bg-gray-50 focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] transition font-mono"
                    >
                </div>

                {{-- Tanggal Akhir (untuk mutasi) --}}
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
            </div>

            {{-- Filter Footer --}}
            <div class="flex items-center justify-end gap-2 pt-2 border-t border-gray-100">
                <a
                    href="{{ route('gudang.laporan.index', ['type' => $type]) }}"
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
        </form>
    </div>

    {{-- Data Table --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            @if ($type === 'stock')
                {{-- TABEL MODE POSISI STOK --}}
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50/75 border-b border-gray-200 text-gray-600 font-bold uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="px-3.5 py-3">Gudang</th>
                            <th class="px-3.5 py-3">Kode SKU</th>
                            <th class="px-3.5 py-3">Nama Produk</th>
                            <th class="px-3.5 py-3">Rak</th>
                            <th class="px-3.5 py-3 text-right">Qty Fisik</th>
                            <th class="px-3.5 py-3 text-right">Qty Dipesan</th>
                            <th class="px-3.5 py-3 text-right">Qty Minimum</th>
                            <th class="px-3.5 py-3 text-right font-black">Qty Tersedia</th>
                            <th class="px-3.5 py-3 text-center">Status Stok</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($records as $item)
                            @php
                                $qtyOnHand = $invService->qtyOnHand($item);
                                $status = $invService->stockStatusForRecord($item);
                                $badgeClass = match ($status) {
                                    'Habis' => 'bg-red-50 text-red-800 border-red-200',
                                    'Minimum' => 'bg-amber-50 text-amber-800 border-amber-200',
                                    default => 'bg-emerald-50 text-[#15803d] border-emerald-200',
                                };
                            @endphp
                            <tr class="hover:bg-gray-50/80 transition">
                                <td class="px-3.5 py-2.5 font-semibold text-gray-900">
                                    {{ $item->warehouse->name ?? '-' }}
                                </td>
                                <td class="px-3.5 py-2.5 font-mono font-bold text-gray-900">
                                    {{ $item->product->sku ?? '-' }}
                                </td>
                                <td class="px-3.5 py-2.5 font-medium text-gray-800">
                                    {{ $item->product->name ?? '-' }}
                                </td>
                                <td class="px-3.5 py-2.5 text-gray-600 font-mono">
                                    {{ $item->rak->nama_rak ?? '-' }}
                                </td>
                                <td class="px-3.5 py-2.5 text-right font-mono text-gray-800">
                                    {{ number_format((float) $item->qty_available, 0, ',', '.') }}
                                </td>
                                <td class="px-3.5 py-2.5 text-right font-mono text-amber-600">
                                    {{ number_format((float) $item->qty_reserved, 0, ',', '.') }}
                                </td>
                                <td class="px-3.5 py-2.5 text-right font-mono text-gray-500">
                                    {{ number_format((float) $item->qty_min, 0, ',', '.') }}
                                </td>
                                <td class="px-3.5 py-2.5 text-right font-mono font-extrabold text-gray-900">
                                    {{ number_format($qtyOnHand, 0, ',', '.') }}
                                </td>
                                <td class="px-3.5 py-2.5 text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold border uppercase tracking-wider {{ $badgeClass }}">
                                        {{ $status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-12 text-gray-400">
                                    Tidak ada data stok barang yang sesuai dengan filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

            @elseif ($type === 'movement')
                {{-- TABEL MODE RIWAYAT MUTASI --}}
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50/75 border-b border-gray-200 text-gray-600 font-bold uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="px-3.5 py-3">Tanggal</th>
                            <th class="px-3.5 py-3">Kode SKU</th>
                            <th class="px-3.5 py-3">Nama Produk</th>
                            <th class="px-3.5 py-3">Gudang</th>
                            <th class="px-3.5 py-3">Rak</th>
                            <th class="px-3.5 py-3 text-center">Tipe Mutasi</th>
                            <th class="px-3.5 py-3 text-right font-black">Perubahan Qty</th>
                            <th class="px-3.5 py-3">Referensi</th>
                            <th class="px-3.5 py-3">Catatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($records as $m)
                            @php
                                $ref = $invService->movementReference($m);
                                $isPositive = ((float)$m->quantity > 0);
                            @endphp
                            <tr class="hover:bg-gray-50/80 transition">
                                <td class="px-3.5 py-2.5 font-mono text-gray-600">
                                    {{ \Carbon\Carbon::parse($m->date)->format('d/m/Y') }}
                                </td>
                                <td class="px-3.5 py-2.5 font-mono font-bold text-gray-900">
                                    {{ $m->product->sku ?? '-' }}
                                </td>
                                <td class="px-3.5 py-2.5 font-medium text-gray-800">
                                    {{ $m->product->name ?? '-' }}
                                </td>
                                <td class="px-3.5 py-2.5 text-gray-700">
                                    {{ $m->warehouse->name ?? '-' }}
                                </td>
                                <td class="px-3.5 py-2.5 text-gray-600 font-mono">
                                    {{ $m->rak->nama_rak ?? '-' }}
                                </td>
                                <td class="px-3.5 py-2.5 text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border uppercase tracking-wider bg-gray-50 text-gray-700 border-gray-200">
                                        {{ $m->type ?? 'Movement' }}
                                    </span>
                                </td>
                                <td class="px-3.5 py-2.5 text-right font-mono font-extrabold {{ $isPositive ? 'text-[#15803d]' : 'text-red-600' }}">
                                    {{ $isPositive ? '+' : '' }}{{ number_format((float) $m->quantity, 0, ',', '.') }}
                                </td>
                                <td class="px-3.5 py-2.5 font-mono text-[11px] text-gray-600">
                                    {{ $ref }}
                                </td>
                                <td class="px-3.5 py-2.5 text-gray-500 text-[11px]">
                                    {{ $m->notes ?: '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-12 text-gray-400">
                                    Tidak ada data mutasi stok pada periode ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

            @else
                {{-- TABEL MODE AGING STOK --}}
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50/75 border-b border-gray-200 text-gray-600 font-bold uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="px-3.5 py-3">Gudang</th>
                            <th class="px-3.5 py-3">Kode SKU</th>
                            <th class="px-3.5 py-3">Nama Produk</th>
                            <th class="px-3.5 py-3">Rak</th>
                            <th class="px-3.5 py-3 text-right">Qty Tersedia</th>
                            <th class="px-3.5 py-3 text-right">Qty Dipesan</th>
                            <th class="px-3.5 py-3 text-right font-black">Qty On Hand</th>
                            <th class="px-3.5 py-3 text-center">Terakhir Mutasi</th>
                            <th class="px-3.5 py-3 text-right">Hari di Gudang</th>
                            <th class="px-3.5 py-3 text-center">Kategori Aging</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($records as $item)
                            @php
                                $qtyOnHand = $invService->qtyOnHand($item);
                                $lastMove = $invService->lastMovementDateForRecord($item);
                                $days = $invService->agingDaysForRecord($item);
                                $cat = $invService->agingCategoryForRecord($item);
                                $badgeClass = match ($cat) {
                                    'Aktif' => 'bg-emerald-50 text-[#15803d] border-emerald-200',
                                    'Slow Moving' => 'bg-amber-50 text-amber-800 border-amber-200',
                                    'Stagnan' => 'bg-orange-50 text-orange-800 border-orange-200',
                                    'Dead Stock' => 'bg-red-50 text-red-800 border-red-200',
                                    default => 'bg-gray-50 text-gray-700 border-gray-200',
                                };
                            @endphp
                            <tr class="hover:bg-gray-50/80 transition">
                                <td class="px-3.5 py-2.5 font-semibold text-gray-900">
                                    {{ $item->warehouse->name ?? '-' }}
                                </td>
                                <td class="px-3.5 py-2.5 font-mono font-bold text-gray-900">
                                    {{ $item->product->sku ?? '-' }}
                                </td>
                                <td class="px-3.5 py-2.5 font-medium text-gray-800">
                                    {{ $item->product->name ?? '-' }}
                                </td>
                                <td class="px-3.5 py-2.5 text-gray-600 font-mono">
                                    {{ $item->rak->nama_rak ?? '-' }}
                                </td>
                                <td class="px-3.5 py-2.5 text-right font-mono text-gray-800">
                                    {{ number_format((float) $item->qty_available, 0, ',', '.') }}
                                </td>
                                <td class="px-3.5 py-2.5 text-right font-mono text-amber-600">
                                    {{ number_format((float) $item->qty_reserved, 0, ',', '.') }}
                                </td>
                                <td class="px-3.5 py-2.5 text-right font-mono font-extrabold text-gray-900">
                                    {{ number_format($qtyOnHand, 0, ',', '.') }}
                                </td>
                                <td class="px-3.5 py-2.5 text-center font-mono text-gray-600">
                                    {{ $lastMove ? $lastMove->format('d/m/Y') : '-' }}
                                </td>
                                <td class="px-3.5 py-2.5 text-right font-mono font-bold text-gray-900">
                                    {{ $days !== null ? $days . ' hari' : '-' }}
                                </td>
                                <td class="px-3.5 py-2.5 text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold border uppercase tracking-wider {{ $badgeClass }}">
                                        {{ $cat }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-12 text-gray-400">
                                    Tidak ada data inventori untuk analisis aging.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Pagination Footer --}}
        <div class="px-4 py-3 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <span class="text-xs text-gray-500 font-mono">
                Menampilkan {{ $records->firstItem() ?? 0 }} - {{ $records->lastItem() ?? 0 }} dari {{ $records->total() }} baris
            </span>

            <div>
                {{ $records->links() }}
            </div>
        </div>
    </div>

</div>
@endsection
