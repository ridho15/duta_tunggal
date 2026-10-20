@extends('layouts.app', ['title' => 'Retur Penjualan (Customer Return)'])

@section('content')
<div class="space-y-6">

    {{-- Breadcrumbs & Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-gray-400 mb-1">
                <span>Penjualan</span>
                <span class="text-gray-300">/</span>
                <span class="text-gray-700 font-semibold">Retur Penjualan</span>
            </nav>
            <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Retur Penjualan (Customer Return)</h1>
            <p class="text-xs text-gray-500 mt-0.5">
                Pengelolaan klaim pengembalian barang pelanggan, penerimaan fisik gudang, inspeksi QC, dan pemulihan stok/jurnal.
            </p>
        </div>

        <div>
            <a
                href="{{ route('penjualan.returns.create') }}"
                class="inline-flex items-center gap-2 bg-[#15803d] hover:bg-[#14532d] text-white px-4 py-2.5 rounded-xl font-bold text-sm shadow-sm transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M12 4v16m8-8H4" stroke-width="2.2" stroke-linecap="round"></path>
                </svg>
                <span>Ajukan Retur Baru</span>
            </a>
        </div>
    </div>

    {{-- Alert Notifikasi Flash --}}
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-start gap-3 shadow-xs">
            <svg class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div class="flex-1 font-medium">{{ session('success') }}</div>
        </div>
    @endif

    @if(session('error'))
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-start gap-3 shadow-xs">
            <svg class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div class="flex-1 font-medium">{{ session('error') }}</div>
        </div>
    @endif

    @if(session('info'))
        <div class="p-4 rounded-xl bg-blue-50 border border-blue-200 text-blue-800 text-sm flex items-start gap-3 shadow-xs">
            <svg class="w-5 h-5 text-blue-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div class="flex-1 font-medium">{{ session('info') }}</div>
        </div>
    @endif

    {{-- KPI Cards Summary --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white border border-gray-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Total Pengajuan Retur</span>
            <div class="text-xl font-black text-gray-900 mt-1">
                {{ $kpis['total_returns'] }} Dokumen
            </div>
            <span class="text-[11px] text-gray-500 mt-0.5 block">Akumulasi seluruh berkas</span>
        </div>

        <div class="bg-white border border-gray-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold text-amber-600 uppercase tracking-wider block">Menunggu Penerimaan & QC</span>
            <div class="text-xl font-black text-amber-700 mt-1">
                {{ $kpis['waiting_processing'] }} Dokumen
            </div>
            <span class="text-[11px] text-gray-500 mt-0.5 block">Tahap pending / received / QC</span>
        </div>

        <div class="bg-white border border-gray-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold text-blue-600 uppercase tracking-wider block">Disetujui (Siap Selesai)</span>
            <div class="text-xl font-black text-blue-700 mt-1">
                {{ $kpis['approved_ready'] }} Dokumen
            </div>
            <span class="text-[11px] text-gray-500 mt-0.5 block">Siap dipulihkan ke stok</span>
        </div>

        <div class="bg-white border border-gray-200 rounded-2xl p-4 shadow-xs">
            <span class="text-[11px] font-bold text-emerald-600 uppercase tracking-wider block">Nilai Retur Selesai</span>
            <div class="text-xl font-black text-[#15803d] mt-1">
                Rp {{ number_format($kpis['total_financial'], 0, ',', '.') }}
            </div>
            <span class="text-[11px] text-gray-500 mt-0.5 block">{{ $kpis['completed_count'] }} retur tuntas & terjurnal</span>
        </div>
    </div>

    {{-- Filter Status Tabs --}}
    <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none text-xs font-semibold">
        @php
            $tabClass = function(bool $active) {
                return $active
                    ? 'px-3.5 py-1.5 rounded-full bg-[#15803d] text-white flex items-center gap-2 shadow-sm shrink-0'
                    : 'px-3.5 py-1.5 rounded-full bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 flex items-center gap-2 transition shrink-0';
            };
        @endphp

        <a href="{{ route('penjualan.returns.index', ['tab' => 'semua', 'q' => $search, 'cabang_id' => $cabangId, 'warehouse_id' => $warehouseId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
           class="{{ $tabClass($tab === 'semua') }}">
            <span>Semua</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'semua' ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-700' }}">{{ $counts['semua'] }}</span>
        </a>

        <a href="{{ route('penjualan.returns.index', ['tab' => 'pending', 'q' => $search, 'cabang_id' => $cabangId, 'warehouse_id' => $warehouseId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
           class="{{ $tabClass($tab === 'pending') }}">
            <span>Menunggu (Draft)</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'pending' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-800' }}">{{ $counts['pending'] }}</span>
        </a>

        <a href="{{ route('penjualan.returns.index', ['tab' => 'received', 'q' => $search, 'cabang_id' => $cabangId, 'warehouse_id' => $warehouseId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
           class="{{ $tabClass($tab === 'received') }}">
            <span>Diterima Gudang</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'received' ? 'bg-white/20 text-white' : 'bg-blue-100 text-blue-800' }}">{{ $counts['received'] }}</span>
        </a>

        <a href="{{ route('penjualan.returns.index', ['tab' => 'qc_inspection', 'q' => $search, 'cabang_id' => $cabangId, 'warehouse_id' => $warehouseId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
           class="{{ $tabClass($tab === 'qc_inspection') }}">
            <span>Inspeksi QC</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'qc_inspection' ? 'bg-white/20 text-white' : 'bg-purple-100 text-purple-800' }}">{{ $counts['qc_inspection'] }}</span>
        </a>

        <a href="{{ route('penjualan.returns.index', ['tab' => 'approved', 'q' => $search, 'cabang_id' => $cabangId, 'warehouse_id' => $warehouseId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
           class="{{ $tabClass($tab === 'approved') }}">
            <span>Disetujui</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'approved' ? 'bg-white/20 text-white' : 'bg-emerald-100 text-emerald-800' }}">{{ $counts['approved'] }}</span>
        </a>

        <a href="{{ route('penjualan.returns.index', ['tab' => 'completed', 'q' => $search, 'cabang_id' => $cabangId, 'warehouse_id' => $warehouseId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
           class="{{ $tabClass($tab === 'completed') }}">
            <span>Selesai</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'completed' ? 'bg-white/20 text-white' : 'bg-emerald-100 text-emerald-800' }}">{{ $counts['completed'] }}</span>
        </a>

        <a href="{{ route('penjualan.returns.index', ['tab' => 'rejected', 'q' => $search, 'cabang_id' => $cabangId, 'warehouse_id' => $warehouseId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
           class="{{ $tabClass($tab === 'rejected') }}">
            <span>Ditolak</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'rejected' ? 'bg-white/20 text-white' : 'bg-rose-100 text-rose-800' }}">{{ $counts['rejected'] }}</span>
        </a>
    </div>

    {{-- Filter Search & Cabang --}}
    <div class="bg-white border border-gray-200 rounded-2xl p-4 shadow-xs">
        <form method="GET" action="{{ route('penjualan.returns.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <input type="hidden" name="tab" value="{{ $tab }}">

            {{-- Input Pencarian --}}
            <div class="lg:col-span-2 relative">
                <input
                    type="text"
                    name="q"
                    value="{{ $search }}"
                    placeholder="Cari no. return, invoice, pelanggan, alasan..."
                    class="w-full pl-9 pr-4 py-2 border border-gray-300 rounded-xl text-xs focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d]"
                >
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
            </div>

            {{-- Filter Cabang --}}
            <div>
                <select
                    name="cabang_id"
                    class="w-full py-2 px-3 border border-gray-300 rounded-xl text-xs focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d]"
                >
                    <option value="">Semua Cabang</option>
                    @foreach($cabangs as $c)
                        <option value="{{ $c->id }}" {{ (string)$cabangId === (string)$c->id ? 'selected' : '' }}>
                            {{ $c->nama }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Filter Gudang --}}
            <div>
                <select
                    name="warehouse_id"
                    class="w-full py-2 px-3 border border-gray-300 rounded-xl text-xs focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d]"
                >
                    <option value="">Semua Gudang</option>
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}" {{ (string)$warehouseId === (string)$wh->id ? 'selected' : '' }}>
                            ({{ $wh->kode }}) {{ $wh->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Tombol Terapkan & Reset --}}
            <div class="flex items-center gap-2">
                <button
                    type="submit"
                    class="flex-1 bg-gray-900 hover:bg-gray-800 text-white py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 shadow-xs"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                    </svg>
                    <span>Filter</span>
                </button>
                @if($search || $cabangId || $warehouseId || $dateFrom || $dateTo || $tab !== 'semua')
                    <a
                        href="{{ route('penjualan.returns.index') }}"
                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-3 rounded-xl text-xs font-semibold transition"
                        title="Reset Filter"
                    >
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Tabel Retur Penjualan --}}
    <div class="bg-white border border-gray-200 rounded-2xl shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-gray-50/80 border-b border-gray-200 text-gray-500 font-bold uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-4">No. Return & Tanggal</th>
                        <th class="py-3 px-4">Pelanggan</th>
                        <th class="py-3 px-4">Invoice Rujukan</th>
                        <th class="py-3 px-4">Gudang Penerima</th>
                        <th class="py-3 px-4 text-center">Jumlah Item</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($returns as $item)
                        @php
                            $badgeClasses = match($item->status) {
                                \App\Models\CustomerReturn::STATUS_PENDING       => 'bg-amber-50 text-amber-700 border-amber-200',
                                \App\Models\CustomerReturn::STATUS_RECEIVED      => 'bg-blue-50 text-blue-700 border-blue-200',
                                \App\Models\CustomerReturn::STATUS_QC_INSPECTION => 'bg-purple-50 text-purple-700 border-purple-200',
                                \App\Models\CustomerReturn::STATUS_APPROVED      => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                \App\Models\CustomerReturn::STATUS_COMPLETED     => 'bg-emerald-100 text-emerald-800 border-emerald-300 font-black',
                                \App\Models\CustomerReturn::STATUS_REJECTED      => 'bg-rose-50 text-rose-700 border-rose-200',
                                default                                          => 'bg-gray-50 text-gray-700 border-gray-200',
                            };
                            $totalItemsCount = $item->customerReturnItems->sum('quantity');
                        @endphp
                        <tr class="hover:bg-gray-50/60 transition group">
                            {{-- No Return & Tanggal --}}
                            <td class="py-3 px-4">
                                <a href="{{ route('penjualan.returns.show', $item->id) }}" class="font-extrabold text-[#15803d] hover:underline block text-[13px]">
                                    {{ $item->return_number }}
                                </a>
                                <span class="text-[11px] text-gray-400 mt-0.5 block">
                                    {{ $item->return_date ? \Carbon\Carbon::parse($item->return_date)->format('d/m/Y') : '-' }}
                                </span>
                            </td>

                            {{-- Pelanggan --}}
                            <td class="py-3 px-4">
                                <span class="font-bold text-gray-900 block text-xs">
                                    {{ $item->customer->name ?? '-' }}
                                </span>
                                <span class="text-[11px] text-gray-400 block mt-0.5">
                                    {{ $item->customer->phone ?? 'Tanpa telepon' }}
                                </span>
                            </td>

                            {{-- Invoice Rujukan --}}
                            <td class="py-3 px-4">
                                @if($item->invoice && $item->invoice->id)
                                    <a href="{{ route('penjualan.invoices.show', $item->invoice->id) }}" class="font-semibold text-gray-800 hover:text-blue-600 hover:underline block text-xs">
                                        {{ $item->invoice->invoice_number }}
                                    </a>
                                    <span class="text-[10px] text-gray-400 block mt-0.5">
                                        {{ $item->invoice->invoice_date ? \Carbon\Carbon::parse($item->invoice->invoice_date)->format('d/m/Y') : '' }}
                                    </span>
                                @else
                                    <span class="text-gray-400 text-xs italic">-</span>
                                @endif
                            </td>

                            {{-- Gudang & Cabang --}}
                            <td class="py-3 px-4">
                                <span class="font-medium text-gray-800 block text-xs">
                                    {{ $item->warehouse->name ?? 'Belum ditentukan' }}
                                </span>
                                <span class="text-[10px] text-gray-400 block mt-0.5">
                                    Cabang: {{ $item->cabang->nama ?? '-' }}
                                </span>
                            </td>

                            {{-- Jumlah Item --}}
                            <td class="py-3 px-4 text-center">
                                <span class="font-bold text-gray-900 text-xs">
                                    {{ count($item->customerReturnItems) }} jenis
                                </span>
                                <span class="text-[10px] text-gray-400 block mt-0.5">
                                    ({{ (float)$totalItemsCount }} pcs)
                                </span>
                            </td>

                            {{-- Status Badge --}}
                            <td class="py-3 px-4 text-center">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold border {{ $badgeClasses }}">
                                    {{ $item->status_label }}
                                </span>
                            </td>

                            {{-- Aksi --}}
                            <td class="py-3 px-4 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
                                    <a
                                        href="{{ route('penjualan.returns.show', $item->id) }}"
                                        class="px-2.5 py-1.5 rounded-lg bg-gray-100 hover:bg-[#15803d] hover:text-white text-gray-700 font-semibold text-xs transition inline-flex items-center gap-1"
                                        title="Lihat Detail Retur"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                        <span>Detail</span>
                                    </a>

                                    <a
                                        href="{{ route('penjualan.returns.pdf', $item->id) }}"
                                        target="_blank"
                                        class="p-1.5 rounded-lg border border-gray-200 text-gray-600 hover:text-gray-900 hover:bg-gray-50 transition"
                                        title="Cetak PDF Formulir Retur"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center">
                                <div class="max-w-xs mx-auto text-gray-400">
                                    <svg class="w-10 h-10 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 14 4 9l5-5m0 0h10a6 6 0 0 1 0 12h-3"></path>
                                    </svg>
                                    <p class="text-sm font-semibold text-gray-700">Belum ada data retur penjualan</p>
                                    <p class="text-xs text-gray-400 mt-0.5">
                                        Belum ada dokumen retur untuk kriteria filter yang dipilih.
                                    </p>
                                    <a
                                        href="{{ route('penjualan.returns.create') }}"
                                        class="mt-3 inline-flex items-center gap-1 text-xs font-bold text-[#15803d] hover:underline"
                                    >
                                        + Ajukan Retur Pertama
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($returns->hasPages())
            <div class="p-4 border-t border-gray-100 bg-gray-50/50">
                {{ $returns->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
