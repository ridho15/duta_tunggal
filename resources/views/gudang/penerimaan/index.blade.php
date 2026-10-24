@extends('layouts.app')

@section('title', 'Penerimaan Barang (Goods Receipt) – Duta Tunggal ERP')

@section('content')
<div class="space-y-6" x-data="{ 
    deleteModalOpen: false, 
    deleteActionUrl: '', 
    deleteReceiptNumber: '',
    confirmDelete(url, number) {
        this.deleteActionUrl = url;
        this.deleteReceiptNumber = number;
        this.deleteModalOpen = true;
    }
}">
    {{-- Header Halaman --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <span>Gudang & Stok</span>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Penerimaan Barang (GRN)</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Penerimaan Barang Pembelian</h1>
            <p class="text-sm text-gray-500 mt-0.5">Pencatatan surat jalan masuk dari supplier, verifikasi fisik barang gudang, dan pembaruan stok persediaan.</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('gudang.penerimaan.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-xs sm:text-sm font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                + Terima Barang Baru
            </a>
        </div>
    </div>

    {{-- 4 KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Penerimaan --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-4 shadow-sm hover:border-gray-200 transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Total Penerimaan</span>
                <span class="p-2 bg-blue-50 text-blue-600 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-gray-900">{{ number_format($counts['total'] ?? 0) }}</span>
                <span class="text-xs font-semibold text-gray-500">Dokumen</span>
            </div>
            <span class="text-xs text-gray-400 mt-1 block">Seluruh riwayat surat jalan masuk</span>
        </div>

        {{-- Draf Penerimaan --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-4 shadow-sm hover:border-gray-200 transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Draf Penerimaan</span>
                <span class="p-2 bg-amber-50 text-amber-600 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-gray-900">{{ number_format($counts['draft'] ?? 0) }}</span>
                <span class="text-xs font-semibold text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded">Draf</span>
            </div>
            <span class="text-xs text-gray-400 mt-1 block">Menunggu verifikasi fisik gudang</span>
        </div>

        {{-- Diterima Sebagian --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-4 shadow-sm hover:border-gray-200 transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Diterima Sebagian</span>
                <span class="p-2 bg-indigo-50 text-indigo-600 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-gray-900">{{ number_format($counts['partial'] ?? 0) }}</span>
                <span class="text-xs font-semibold text-indigo-700 bg-indigo-100 px-1.5 py-0.5 rounded">Parsial</span>
            </div>
            <span class="text-xs text-gray-400 mt-1 block">PO terpenuhi bertahap</span>
        </div>

        {{-- Selesai Masuk Stok --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-4 shadow-sm hover:border-gray-200 transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Selesai Masuk Stok</span>
                <span class="p-2 bg-emerald-50 text-emerald-600 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-gray-900">{{ number_format($counts['completed'] ?? 0) }}</span>
                <span class="text-xs font-semibold text-emerald-700 bg-emerald-100 px-1.5 py-0.5 rounded">Selesai</span>
            </div>
            <span class="text-xs text-gray-400 mt-1 block">Stok persediaan resmi masuk</span>
        </div>
    </div>

    {{-- Filter & Tab Bar --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 space-y-4">
        {{-- Status Tabs --}}
        <div class="flex items-center gap-1 border-b border-gray-100 pb-3 overflow-x-auto text-sm font-semibold">
            @php
                $tabUrl = function($tabStatus) use ($search, $warehouseId, $startDate, $endDate) {
                    return route('gudang.penerimaan.index', array_filter([
                        'status' => $tabStatus !== 'all' ? $tabStatus : null,
                        'q' => $search,
                        'warehouse_id' => $warehouseId,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                    ]));
                };
            @endphp
            <a
                href="{{ $tabUrl('all') }}"
                class="px-3.5 py-1.5 rounded-lg transition {{ $status === 'all' ? 'bg-[#f0fdf4] text-[#15803d] font-bold border border-[#bbf7d0]' : 'text-gray-500 hover:text-gray-800 hover:bg-gray-50' }}"
            >
                Semua
                <span class="ml-1 px-1.5 py-0.2 bg-gray-100 text-gray-600 rounded-full text-xs">{{ $counts['total'] ?? 0 }}</span>
            </a>
            <a
                href="{{ $tabUrl('draft') }}"
                class="px-3.5 py-1.5 rounded-lg transition {{ $status === 'draft' ? 'bg-amber-50 text-amber-800 font-bold' : 'text-gray-500 hover:text-gray-800 hover:bg-gray-50' }}"
            >
                Draf
                <span class="ml-1 px-1.5 py-0.2 bg-amber-100 text-amber-800 rounded-full text-xs">{{ $counts['draft'] ?? 0 }}</span>
            </a>
            <a
                href="{{ $tabUrl('partial') }}"
                class="px-3.5 py-1.5 rounded-lg transition {{ $status === 'partial' ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-gray-500 hover:text-gray-800 hover:bg-gray-50' }}"
            >
                Sebagian
                <span class="ml-1 px-1.5 py-0.2 bg-indigo-100 text-indigo-800 rounded-full text-xs">{{ $counts['partial'] ?? 0 }}</span>
            </a>
            <a
                href="{{ $tabUrl('completed') }}"
                class="px-3.5 py-1.5 rounded-lg transition {{ $status === 'completed' ? 'bg-emerald-50 text-emerald-700 font-bold' : 'text-gray-500 hover:text-gray-800 hover:bg-gray-50' }}"
            >
                Selesai
                <span class="ml-1 px-1.5 py-0.2 bg-emerald-100 text-emerald-800 rounded-full text-xs">{{ $counts['completed'] ?? 0 }}</span>
            </a>
        </div>

        {{-- Filter Inputs Form --}}
        <form method="GET" action="{{ route('gudang.penerimaan.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
            <input type="hidden" name="status" value="{{ $status }}">

            {{-- Search Bar --}}
            <div class="lg:col-span-4">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Cari Penerimaan</label>
                <div class="relative">
                    <input
                        type="text"
                        name="q"
                        value="{{ $search }}"
                        placeholder="No. GRN, PO, Supplier, No. SJ..."
                        class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d]"
                    />
                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>

            {{-- Gudang --}}
            <div class="lg:col-span-3">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Gudang Penyimpanan</label>
                <x-searchable-select name="warehouse_id" :options="collect($warehouses)->map(fn ($wh) => ['id' => $wh->id, 'label' => $wh->name . ' (' . $wh->kode . ')'])->values()" :value="$warehouseId" placeholder="Semua Gudang" size="sm" :auto-submit="true" />
            </div>

            {{-- Tanggal Mulai --}}
            <div class="lg:col-span-2">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Tanggal Mulai</label>
                <input
                    type="date"
                    name="start_date"
                    value="{{ $startDate }}"
                    class="w-full py-2 px-3 text-sm rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] bg-white"
                />
            </div>

            {{-- Tanggal Selesai --}}
            <div class="lg:col-span-2">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Tanggal Selesai</label>
                <input
                    type="date"
                    name="end_date"
                    value="{{ $endDate }}"
                    class="w-full py-2 px-3 text-sm rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] bg-white"
                />
            </div>

            {{-- Tombol Aksi Filter --}}
            <div class="lg:col-span-1 flex items-center gap-1">
                <button
                    type="submit"
                    class="w-full py-2 px-3 bg-gray-900 hover:bg-gray-800 text-white text-sm font-semibold rounded-lg shadow-sm transition"
                    title="Terapkan Filter"
                >
                    Filter
                </button>
                @if ($search || $warehouseId || $startDate || $endDate || $status !== 'all')
                    <a
                        href="{{ route('gudang.penerimaan.index') }}"
                        class="p-2 text-gray-500 hover:text-red-600 rounded-lg border border-gray-200 hover:bg-gray-50 transition"
                        title="Reset Filter"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Tabel Penerimaan Barang --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-600">
                <thead class="bg-gray-50/80 text-[11px] uppercase font-semibold text-gray-500 border-b border-gray-100">
                    <tr>
                        <th class="py-3 px-4">No. Penerimaan</th>
                        <th class="py-3 px-4">Tanggal</th>
                        <th class="py-3 px-4">Pesanan Pembelian (PO)</th>
                        <th class="py-3 px-4">Supplier</th>
                        <th class="py-3 px-4">No. SJ Vendor</th>
                        <th class="py-3 px-4 text-center">Total Item</th>
                        <th class="py-3 px-4">Penerima</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($receipts as $receipt)
                        @php
                            $totalReceivedQty = $receipt->purchaseReceiptItem->sum('qty_received');
                            $totalAcceptedQty = $receipt->purchaseReceiptItem->sum('qty_accepted');
                            $totalRejectedQty = $receipt->purchaseReceiptItem->sum('qty_rejected');
                            $po = $receipt->purchaseOrder;
                        @endphp
                        <tr class="hover:bg-gray-50/60 transition">
                            {{-- No. Penerimaan --}}
                            <td class="py-3.5 px-4 font-bold text-gray-900 whitespace-nowrap">
                                <a href="{{ route('gudang.penerimaan.show', $receipt) }}" class="text-[#15803d] hover:text-[#14532d] hover:underline">
                                    {{ $receipt->receipt_number ?? ('GRN-' . $receipt->id) }}
                                </a>
                            </td>

                            {{-- Tanggal --}}
                            <td class="py-3.5 px-4 whitespace-nowrap text-gray-600">
                                {{ optional($receipt->receipt_date)->format('d/m/Y') ?? '-' }}
                            </td>

                            {{-- PO Terkait --}}
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                @if ($po && $po->exists)
                                    <a href="{{ route('pembelian.po.show', $po) }}" class="font-medium text-gray-900 hover:text-[#15803d]">
                                        {{ $po->po_number }}
                                    </a>
                                @else
                                    <span class="text-gray-400 italic">Penerimaan Langsung</span>
                                @endif
                            </td>

                            {{-- Supplier --}}
                            <td class="py-3.5 px-4">
                                <div class="font-medium text-gray-900 truncate max-w-[200px]">
                                    {{ $po->supplier->name ?? 'Supplier' }}
                                </div>
                                <div class="text-xs text-gray-400">
                                    {{ $po->supplier->kode ?? '' }}
                                </div>
                            </td>

                            {{-- No. SJ Vendor --}}
                            <td class="py-3.5 px-4 whitespace-nowrap font-mono text-xs text-gray-700">
                                {{ $receipt->supplier_delivery_note_number ?: '-' }}
                            </td>

                            {{-- Total Item & Kuantitas --}}
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                <span class="font-bold text-gray-900">{{ number_format($totalReceivedQty) }}</span>
                                <span class="text-xs text-gray-500 block">
                                    ({{ $receipt->purchaseReceiptItem->count() }} item)
                                </span>
                                @if ($totalRejectedQty > 0)
                                    <span class="inline-block mt-0.5 px-1.5 py-0.2 bg-red-100 text-red-700 text-[10px] font-bold rounded">
                                        {{ number_format($totalRejectedQty) }} ditolak
                                    </span>
                                @endif
                            </td>

                            {{-- Petugas Penerima --}}
                            <td class="py-3.5 px-4 whitespace-nowrap text-xs text-gray-600">
                                {{ $receipt->receivedBy->name ?? '-' }}
                            </td>

                            {{-- Status Badge --}}
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                @if ($receipt->status === 'completed')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Selesai
                                    </span>
                                @elseif ($receipt->status === 'partial')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                                        Sebagian
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-gray-50 text-gray-700 border border-gray-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                                        Draf
                                    </span>
                                @endif
                            </td>

                            {{-- Aksi --}}
                            <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1">
                                    {{-- Detail --}}
                                    <a
                                        href="{{ route('gudang.penerimaan.show', $receipt) }}"
                                        class="p-1.5 text-gray-500 hover:text-[#15803d] hover:bg-emerald-50 rounded-lg transition"
                                        title="Lihat Detail Penerimaan"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>

                                    {{-- Cetak PDF --}}
                                    <a
                                        href="{{ route('gudang.penerimaan.pdf', $receipt) }}"
                                        target="_blank"
                                        class="p-1.5 text-gray-500 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition"
                                        title="Cetak Surat Jalan Masuk PDF"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                        </svg>
                                    </a>

                                    {{-- Edit (Hanya Draf) --}}
                                    @if ($receipt->status === 'draft')
                                        <a
                                            href="{{ route('gudang.penerimaan.edit', $receipt) }}"
                                            class="p-1.5 text-gray-500 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition"
                                            title="Edit Draf Penerimaan"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </a>

                                        {{-- Hapus (Hanya Draf) --}}
                                        <button
                                            type="button"
                                            @click="confirmDelete('{{ route('gudang.penerimaan.destroy', $receipt) }}', '{{ $receipt->receipt_number }}')"
                                            class="p-1.5 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition"
                                            title="Hapus Draf"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-12 text-center text-gray-400">
                                <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                </svg>
                                <div class="text-base font-semibold text-gray-700">Tidak Ada Data Penerimaan Barang</div>
                                <p class="text-xs text-gray-500 mt-1">Belum ada dokumen penerimaan barang yang cocok dengan filter pencarian ini.</p>
                                <div class="mt-4">
                                    <a
                                        href="{{ route('gudang.penerimaan.create') }}"
                                        class="inline-flex items-center gap-2 px-4 py-2 bg-[#15803d] hover:bg-[#14532d] text-white text-xs font-semibold rounded-lg shadow-sm transition"
                                    >
                                        + Terima Barang Baru
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($receipts->hasPages())
            <div class="p-4 border-t border-gray-100 bg-gray-50/50">
                {{ $receipts->links() }}
            </div>
        @endif
    </div>

    {{-- Modal Konfirmasi Hapus Draf --}}
    <div
        x-show="deleteModalOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/50 backdrop-blur-sm flex items-center justify-center p-4"
    >
        <div
            @click.away="deleteModalOpen = false"
            class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100 transform transition-all"
        >
            <div class="flex items-center gap-3 text-red-600 mb-3">
                <div class="p-2.5 bg-red-50 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Hapus Draf Penerimaan</h3>
                    <p class="text-xs text-gray-500">Tindakan ini tidak dapat dibatalkan</p>
                </div>
            </div>

            <p class="text-sm text-gray-600 mb-6">
                Apakah Anda yakin ingin menghapus draf dokumen <span class="font-bold text-gray-900" x-text="deleteReceiptNumber"></span>?
            </p>

            <form :action="deleteActionUrl" method="POST" class="flex items-center justify-end gap-2">
                @csrf
                @method('DELETE')
                <button
                    type="button"
                    @click="deleteModalOpen = false"
                    class="px-4 py-2 text-sm font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    class="px-4 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded-lg shadow-sm transition"
                >
                    Hapus Dokumen
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
