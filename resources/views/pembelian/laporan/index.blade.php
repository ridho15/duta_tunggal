@extends('layouts.app', ['title' => 'Laporan Pembelian'])

@section('content')
<div class="space-y-6">

    {{-- Breadcrumb & Top Bar --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-gray-200">
        <div>
            <div class="flex items-center gap-2 text-xs text-gray-500 mb-1">
                <a href="{{ route('pembelian.po.index') }}" class="hover:text-gray-700">Pembelian</a>
                <span>&rsaquo;</span>
                <span class="text-gray-800 font-semibold">Laporan Pembelian</span>
            </div>
            <h1 class="text-2xl font-black text-gray-900 tracking-tight">Laporan Pembelian</h1>
            <p class="text-xs text-gray-500 mt-0.5">
                Rekapitulasi pesanan pembelian ke supplier, pengadaan barang, dan evaluasi pengeluaran operasional.
            </p>
        </div>

        {{-- Action Buttons (PDF & Excel) --}}
        <div class="flex items-center gap-2">
            <a
                href="{{ route('pembelian.laporan.pdf', request()->query()) }}"
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
                href="{{ route('pembelian.laporan.excel', request()->query()) }}"
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

    {{-- Ringkasan KPI Cards --}}
    @if (!empty($summary))
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block mb-1">
                    Total Pembelian
                </span>
                <span class="text-base sm:text-lg font-black text-[#15803d] font-mono tracking-tight block">
                    Rp {{ number_format($summary['total_amount'] ?? 0, 0, ',', '.') }}
                </span>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block mb-1">
                    Total Transaksi PO
                </span>
                <span class="text-base sm:text-lg font-black text-gray-900 font-mono tracking-tight block">
                    {{ number_format($summary['total_orders'] ?? 0, 0, ',', '.') }} PO
                </span>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block mb-1">
                    Rata-rata per PO
                </span>
                <span class="text-base sm:text-lg font-black text-gray-900 font-mono tracking-tight block">
                    Rp {{ number_format($summary['average_amount'] ?? 0, 0, ',', '.') }}
                </span>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block mb-1">
                    Total Kuantitas
                </span>
                <span class="text-base sm:text-lg font-black text-gray-900 font-mono tracking-tight block">
                    {{ number_format($summary['total_quantity'] ?? 0, 0, ',', '.') }} pcs
                </span>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block mb-1">
                    Produk Unik
                </span>
                <span class="text-base sm:text-lg font-black text-gray-900 font-mono tracking-tight block">
                    {{ number_format($summary['unique_products'] ?? 0, 0, ',', '.') }} SKU
                </span>
            </div>
        </div>
    @endif

    {{-- Filter Toolbar --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <form method="GET" action="{{ route('pembelian.laporan.index') }}" class="space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                {{-- Tanggal Mulai --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">
                        Tanggal Mulai (Order PO)
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

                {{-- Supplier Filter --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">
                        Supplier / Vendor
                    </label>
                    <select
                        name="supplier_id"
                        class="w-full text-xs rounded-xl border border-gray-200 px-3 py-2 bg-gray-50 focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] transition"
                    >
                        <option value="">-- Semua Supplier --</option>
                        @foreach ($suppliers as $s)
                            <option value="{{ $s->id }}" {{ (string)$supplierId === (string)$s->id ? 'selected' : '' }}>
                                {{ $s->code }} - {{ $s->perusahaan ?? $s->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Status Filter --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">
                        Status PO
                    </label>
                    <select
                        name="status"
                        class="w-full text-xs rounded-xl border border-gray-200 px-3 py-2 bg-gray-50 focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] transition"
                    >
                        <option value="">-- Semua Status PO --</option>
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
                        <option value="desc" {{ $sortByTotal === 'desc' ? 'selected' : '' }}>Nilai Tertinggi ke Terendah</option>
                        <option value="asc" {{ $sortByTotal === 'asc' ? 'selected' : '' }}>Nilai Terendah ke Tertinggi</option>
                    </select>
                </div>

                <div class="flex items-center gap-2 justify-end">
                    <a
                        href="{{ route('pembelian.laporan.index') }}"
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
            <table class="w-full text-left text-xs">
                <thead class="bg-gray-50/75 border-b border-gray-200 text-gray-600 font-bold uppercase tracking-wider text-[11px]">
                    <tr>
                        <th class="px-3.5 py-3">No. PO</th>
                        <th class="px-3.5 py-3">Tanggal Order</th>
                        <th class="px-3.5 py-3">Supplier</th>
                        <th class="px-3.5 py-3 text-right">Total Qty</th>
                        <th class="px-3.5 py-3 text-center">Status PO</th>
                        <th class="px-3.5 py-3 text-right font-black">Total Nilai PO</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($records as $po)
                        @php
                            $totalQty = (float) $po->purchaseOrderItem->sum('quantity');
                            $status = $po->status;
                            $badgeClass = match ($status) {
                                'approved', 'completed' => 'bg-emerald-50 text-[#15803d] border-emerald-200',
                                'processing', 'partial' => 'bg-blue-50 text-blue-800 border-blue-200',
                                'pending' => 'bg-amber-50 text-amber-800 border-amber-200',
                                'cancelled' => 'bg-red-50 text-red-800 border-red-200',
                                default => 'bg-gray-50 text-gray-700 border-gray-200',
                            };
                            $statusLabel = $statusOptions[$status] ?? ucfirst($status);
                        @endphp
                        <tr class="hover:bg-gray-50/80 transition">
                            <td class="px-3.5 py-2.5 font-mono font-bold text-gray-900">
                                <a href="{{ route('pembelian.po.show', $po) }}" class="text-[#15803d] hover:underline">
                                    {{ $po->po_number }}
                                </a>
                            </td>
                            <td class="px-3.5 py-2.5 text-gray-600 font-mono">
                                {{ \Carbon\Carbon::parse($po->order_date)->format('d/m/Y') }}
                            </td>
                            <td class="px-3.5 py-2.5">
                                <span class="font-bold text-gray-900 block">{{ $po->supplier->name ?? 'Supplier' }}</span>
                                <span class="text-[11px] font-mono text-gray-400">{{ $po->supplier->code ?? '-' }}</span>
                            </td>
                            <td class="px-3.5 py-2.5 text-right font-mono font-semibold text-gray-800">
                                {{ number_format($totalQty, 0, ',', '.') }} pcs
                            </td>
                            <td class="px-3.5 py-2.5 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold border uppercase tracking-wider {{ $badgeClass }}">
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td class="px-3.5 py-2.5 text-right font-mono font-extrabold text-gray-900">
                                Rp {{ number_format($po->total_amount, 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-12 text-gray-400">
                                <svg class="w-12 h-12 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Tidak ada data pesanan pembelian (PO) yang sesuai dengan filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination Footer --}}
        <div class="px-4 py-3 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <span class="text-xs text-gray-500 font-mono">
                Menampilkan {{ $records->firstItem() ?? 0 }} - {{ $records->lastItem() ?? 0 }} dari {{ $records->total() }} pesanan
            </span>

            <div>
                {{ $records->links() }}
            </div>
        </div>
    </div>

</div>
@endsection
