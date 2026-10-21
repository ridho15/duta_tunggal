@extends('layouts.app')

@section('title', 'Pesanan Pembelian (PO) – Duta Tunggal ERP')

@section('content')
<div class="space-y-6" x-data="{ 
    approveModalOpen: false, 
    approveActionUrl: '', 
    approvePoNumber: '',
    deleteModalOpen: false,
    deleteActionUrl: '',
    deletePoNumber: '',
    confirmApprove(url, number) {
        this.approveActionUrl = url;
        this.approvePoNumber = number;
        this.approveModalOpen = true;
    },
    confirmDelete(url, number) {
        this.deleteActionUrl = url;
        this.deletePoNumber = number;
        this.deleteModalOpen = true;
    }
}">
    {{-- Header Halaman --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <span>Pembelian</span>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Pesanan Pembelian (PO)</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Pesanan Pembelian (Purchase Order)</h1>
            <p class="text-sm text-gray-500 mt-0.5">Kelola komitmen pesanan barang/jasa ke supplier, otorisasi approval, dan pantau status pemenuhan gudang.</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('pembelian.po.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-[#15803d] hover:bg-[#14532d] text-white text-sm font-semibold rounded-lg shadow-sm transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                + Buat PO Baru
            </a>
        </div>
    </div>

    {{-- 4 KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Nilai PO --}}
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm hover:border-gray-300 transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Total Nilai PO</span>
                <span class="p-2 bg-blue-50 text-blue-600 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-xl font-bold text-gray-900">Rp {{ number_format($kpi['total_po_amount'] ?? 0, 0, ',', '.') }}</span>
            </div>
            <span class="text-xs text-gray-500 mt-1 block">Akumulasi seluruh PO aktif</span>
        </div>

        {{-- Draf Belum Diajukan --}}
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm hover:border-gray-300 transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Draf PO</span>
                <span class="p-2 bg-amber-50 text-amber-600 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-gray-900">{{ $kpi['draft_count'] ?? 0 }}</span>
                <span class="text-xs font-semibold text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded">Draf</span>
            </div>
            <span class="text-xs text-gray-500 mt-1 block">Menunggu verifikasi / diajukan</span>
        </div>

        {{-- Aktif & Disetujui --}}
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm hover:border-gray-300 transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Aktif & Disetujui</span>
                <span class="p-2 bg-indigo-50 text-indigo-600 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-gray-900">{{ $kpi['active_or_approved_count'] ?? 0 }}</span>
                <span class="text-xs font-semibold text-indigo-700 bg-indigo-100 px-1.5 py-0.5 rounded">Proses</span>
            </div>
            <span class="text-xs text-gray-500 mt-1 block">Siap diterima di gudang</span>
        </div>

        {{-- Selesai / Terpenuhi --}}
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm hover:border-gray-300 transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">PO Selesai</span>
                <span class="p-2 bg-emerald-50 text-emerald-600 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-gray-900">{{ $kpi['completed_count'] ?? 0 }}</span>
                <span class="text-xs font-semibold text-emerald-700 bg-emerald-100 px-1.5 py-0.5 rounded">Completed</span>
            </div>
            <span class="text-xs text-gray-500 mt-1 block">Semua pesanan diterima lengkap</span>
        </div>
    </div>

    {{-- Filter Tabs & Pencarian --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        {{-- Tabs Status --}}
        <div class="flex border-b border-gray-200 overflow-x-auto text-sm font-medium text-gray-600 px-4 pt-2">
            @php
                $tabs = [
                    'semua' => ['label' => 'Semua PO', 'count' => $counts['semua'] ?? 0],
                    'draft' => ['label' => 'Draf', 'count' => $counts['draft'] ?? 0],
                    'menunggu_approve' => ['label' => 'Menunggu Approval', 'count' => $counts['menunggu_approve'] ?? 0],
                    'approved' => ['label' => 'Disetujui', 'count' => $counts['approved'] ?? 0],
                    'partially_received' => ['label' => 'Diterima Sebagian', 'count' => $counts['partially_received'] ?? 0],
                    'completed' => ['label' => 'Selesai', 'count' => $counts['completed'] ?? 0],
                    'closed' => ['label' => 'Ditutup', 'count' => $counts['closed'] ?? 0],
                ];
            @endphp

            @foreach ($tabs as $key => $tabInfo)
                <a
                    href="{{ route('pembelian.po.index', array_merge(request()->query(), ['tab' => $key, 'page' => 1])) }}"
                    class="flex items-center gap-2 py-3 px-3.5 border-b-2 font-medium transition whitespace-nowrap {{ $activeTab === $key ? 'border-[#15803d] text-[#15803d] font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                >
                    <span>{{ $tabInfo['label'] }}</span>
                    <span class="px-2 py-0.5 text-xs rounded-full {{ $activeTab === $key ? 'bg-[#f0fdf4] text-[#15803d] border border-[#bbf7d0] font-bold' : 'bg-gray-100 text-gray-600' }}">
                        {{ $tabInfo['count'] }}
                    </span>
                </a>
            @endforeach
        </div>

        {{-- Baris Filter Terpadu --}}
        <div class="p-4 bg-gray-50/50 border-b border-gray-200">
            <form action="{{ route('pembelian.po.index') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-center">
                <input type="hidden" name="tab" value="{{ $activeTab }}">

                {{-- Input Cari --}}
                <div class="lg:col-span-3 relative">
                    <input
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Cari nomor PO, supplier, atau catatan..."
                        class="w-full pl-9 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d]"
                    />
                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>

                {{-- Filter Supplier --}}
                <div class="lg:col-span-3">
                    <select name="supplier_id" class="w-full py-2 px-3 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d]">
                        <option value="">-- Semua Supplier --</option>
                        @foreach ($suppliers as $s)
                            <option value="{{ $s->id }}" {{ (string) $supplierFilter === (string) $s->id ? 'selected' : '' }}>
                                {{ $s->perusahaan }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Filter Cabang --}}
                <div class="lg:col-span-2">
                    <select name="cabang_id" class="w-full py-2 px-3 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d]">
                        <option value="">-- Semua Cabang --</option>
                        @foreach ($cabangs as $c)
                            <option value="{{ $c->id }}" {{ (string) $cabangFilter === (string) $c->id ? 'selected' : '' }}>
                                {{ $c->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Rentang Tanggal --}}
                <div class="lg:col-span-2 flex items-center gap-1.5">
                    <input
                        type="date"
                        name="date_from"
                        value="{{ $dateFrom }}"
                        class="w-full py-2 px-2.5 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d]"
                        title="Dari Tanggal Order"
                    />
                    <span class="text-gray-400 text-xs">-</span>
                    <input
                        type="date"
                        name="date_to"
                        value="{{ $dateTo }}"
                        class="w-full py-2 px-2.5 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d]"
                        title="Sampai Tanggal Order"
                    />
                </div>

                {{-- Tombol Filter & Reset --}}
                <div class="lg:col-span-2 flex items-center gap-2">
                    <button
                        type="submit"
                        class="w-full px-3 py-2 bg-gray-900 hover:bg-black text-white text-sm font-semibold rounded-lg shadow-sm transition"
                    >
                        Filter
                    </button>
                    @if ($search || $supplierFilter || $cabangFilter || $dateFrom || $dateTo || $activeTab !== 'semua')
                        <a
                            href="{{ route('pembelian.po.index') }}"
                            class="px-3 py-2 bg-white border border-gray-300 hover:bg-gray-100 text-gray-700 text-sm font-semibold rounded-lg transition"
                            title="Reset Filter"
                        >
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Tabel Data --}}
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-600">
                <thead class="bg-gray-50 text-xs uppercase font-bold text-gray-500 tracking-wider border-b border-gray-200">
                    <tr>
                        <th class="py-3 px-4">Nomor PO</th>
                        <th class="py-3 px-4">Tanggal Order</th>
                        <th class="py-3 px-4">Supplier</th>
                        <th class="py-3 px-4">Cabang & Gudang</th>
                        <th class="py-3 px-4 text-right">Nilai Total</th>
                        <th class="py-3 px-4 text-center">Pemenuhan</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($purchaseOrders as $po)
                        @php
                            $statusBadge = match($po->status) {
                                'draft' => ['bg' => 'bg-gray-100', 'text' => 'text-gray-700', 'label' => 'Draf'],
                                'request_approve' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-800', 'label' => 'Menunggu Approval'],
                                'approved' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-800', 'label' => 'Disetujui'],
                                'partially_received' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-800', 'label' => 'Diterima Sebagian'],
                                'completed' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-800', 'label' => 'Selesai'],
                                'closed' => ['bg' => 'bg-red-100', 'text' => 'text-red-800', 'label' => 'Ditutup'],
                                'request_close' => ['bg' => 'bg-rose-100', 'text' => 'text-rose-800', 'label' => 'Request Close'],
                                default => ['bg' => 'bg-gray-100', 'text' => 'text-gray-700', 'label' => ucfirst($po->status)],
                            };

                            // Hitung rasio pemenuhan gudang langsung dari item eager-loaded
                            $totalQty = (float) $po->purchaseOrderItem->sum('quantity');
                            $receivedQty = (float) $po->purchaseOrderItem->sum(function($item) {
                                return $item->purchaseReceiptItem->sum('qty_received');
                            });
                            $fulfillPct = $totalQty > 0 ? min(100, round(($receivedQty / $totalQty) * 100)) : 0;
                            $barColor = match(true) {
                                $fulfillPct >= 100 => 'bg-emerald-500',
                                $fulfillPct > 0 => 'bg-[#15803d]',
                                default => 'bg-gray-300',
                            };
                        @endphp
                        <tr class="hover:bg-emerald-50/30 transition">
                            {{-- Nomor PO --}}
                            <td class="py-3.5 px-4 font-semibold text-gray-900 whitespace-nowrap">
                                <a href="{{ route('pembelian.po.show', $po) }}" class="text-[#15803d] hover:text-[#14532d] hover:underline">
                                    {{ $po->po_number }}
                                </a>
                                @if ($po->is_asset)
                                    <span class="ml-1 px-1.5 py-0.5 text-[10px] font-bold bg-indigo-100 text-indigo-700 rounded">Asset</span>
                                @endif
                                @if ($po->is_import)
                                    <span class="ml-1 px-1.5 py-0.5 text-[10px] font-bold bg-amber-100 text-amber-700 rounded">Import</span>
                                @endif
                            </td>

                            {{-- Tanggal Order & Estimasi --}}
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <div class="font-medium text-gray-900">{{ optional($po->order_date)->format('d/m/Y') ?? '-' }}</div>
                                @if ($po->expected_date)
                                    <div class="text-xs text-gray-400">Tiba: {{ optional($po->expected_date)->format('d/m/Y') }}</div>
                                @endif
                            </td>

                            {{-- Supplier --}}
                            <td class="py-3.5 px-4">
                                <div class="font-medium text-gray-900">{{ $po->supplier->perusahaan ?? '-' }}</div>
                                @if ($po->supplier && $po->supplier->kontak_person)
                                    <div class="text-xs text-gray-400">U.p: {{ $po->supplier->kontak_person }}</div>
                                @endif
                            </td>

                            {{-- Cabang & Gudang --}}
                            <td class="py-3.5 px-4">
                                <div class="text-xs font-semibold text-gray-800">{{ $po->cabang->nama ?? 'Semua Cabang' }}</div>
                                <div class="text-xs text-gray-400">{{ $po->warehouse->name ?? '-' }}</div>
                            </td>

                            {{-- Nilai Total --}}
                            <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                <div class="font-bold text-gray-900">
                                    Rp {{ number_format((float)$po->total_amount, 2, ',', '.') }}
                                </div>
                                <div class="text-[11px] text-gray-400 uppercase">
                                    {{ str_replace('_', ' ', $po->top_type ?? 'COD') }}
                                    @if ($po->tempo_hutang > 0)
                                        ({{ $po->tempo_hutang }}h)
                                    @endif
                                </div>
                            </td>

                            {{-- Progress Pemenuhan --}}
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <div class="flex flex-col items-center gap-1 w-28 mx-auto">
                                    <div class="w-full bg-gray-200 rounded-full h-1.5 overflow-hidden">
                                        <div class="{{ $barColor }} h-1.5 rounded-full transition-all duration-300" style="width: {{ $fulfillPct }}%"></div>
                                    </div>
                                    <span class="text-[10px] font-semibold text-gray-500">
                                        {{ $fulfillPct }}% ({{ round($receivedQty) }}/{{ round($totalQty) }})
                                    </span>
                                </div>
                            </td>

                            {{-- Status Badge --}}
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $statusBadge['bg'] }} {{ $statusBadge['text'] }}">
                                    {{ $statusBadge['label'] }}
                                </span>
                            </td>

                            {{-- Aksi --}}
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                <div class="inline-flex items-center gap-1">
                                    <a
                                        href="{{ route('pembelian.po.show', $po) }}"
                                        class="p-1.5 text-gray-500 hover:text-[#15803d] hover:bg-emerald-50 rounded-lg transition"
                                        title="Lihat Detail PO"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>

                                    @if ($po->status === 'draft')
                                        <a
                                            href="{{ route('pembelian.po.edit', $po) }}"
                                            class="p-1.5 text-gray-500 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition"
                                            title="Ubah PO"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </a>

                                        <button
                                            type="button"
                                            @click="confirmApprove('{{ route('pembelian.po.approve', $po) }}', '{{ $po->po_number }}')"
                                            class="p-1.5 text-gray-500 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition"
                                            title="Setujui PO (Approve)"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </button>

                                        <button
                                            type="button"
                                            @click="confirmDelete('{{ route('pembelian.po.destroy', $po) }}', '{{ $po->po_number }}')"
                                            class="p-1.5 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition"
                                            title="Hapus PO (Draf)"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    @endif

                                    <a
                                        href="{{ route('pembelian.po.pdf', $po) }}"
                                        target="_blank"
                                        class="p-1.5 text-gray-500 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition"
                                        title="Unduh / Cetak PDF"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-gray-400">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="w-16 h-16 bg-emerald-50 text-[#15803d] rounded-2xl flex items-center justify-center mb-3">
                                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                        </svg>
                                    </div>
                                    <span class="text-base font-bold text-gray-700">Belum Ada Pesanan Pembelian</span>
                                    <span class="text-xs text-gray-400 mt-1 max-w-sm text-center">
                                        Tidak ditemukan transaksi yang cocok dengan filter. Silakan sesuaikan kriteria atau buat PO baru.
                                    </span>
                                    <a
                                        href="{{ route('pembelian.po.create') }}"
                                        class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-[#15803d] hover:bg-[#14532d] text-white text-xs font-semibold rounded-lg shadow-sm transition"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                        + Buat PO Baru
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($purchaseOrders->hasPages())
            <div class="p-4 border-t border-gray-200">
                {{ $purchaseOrders->links() }}
            </div>
        @endif
    </div>

    {{-- Modal Konfirmasi Approve Cepat --}}
    <div
        x-show="approveModalOpen"
        x-cloak
        class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4 backdrop-blur-sm"
        @keydown.escape.window="approveModalOpen = false"
    >
        <div 
            class="bg-white rounded-2xl max-w-sm w-full p-6 shadow-2xl space-y-4 border border-gray-100"
            @click.away="approveModalOpen = false"
        >
            <div class="flex items-center gap-3">
                <div class="p-2 bg-emerald-50 text-emerald-600 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-gray-900">Setujui Pesanan Pembelian</h3>
                    <p class="text-xs text-gray-500">Otorisasi dokumen untuk diproses gudang</p>
                </div>
            </div>

            <p class="text-xs text-gray-600 leading-relaxed">
                Apakah Anda yakin ingin menyetujui <span class="font-bold text-gray-900" x-text="approvePoNumber"></span>? Status akan berubah menjadi <span class="font-semibold text-emerald-700">Disetujui</span> dan siap menerima barang.
            </p>

            <form :action="approveActionUrl" method="POST" class="pt-2 flex justify-end gap-2">
                @csrf
                <button
                    type="button"
                    @click="approveModalOpen = false"
                    class="px-4 py-2 border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 hover:bg-gray-50 transition"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    class="px-4 py-2 bg-[#15803d] hover:bg-[#14532d] text-white rounded-lg text-xs font-bold shadow-sm transition"
                >
                    Ya, Setujui Sekarang
                </button>
            </form>
        </div>
    </div>

    {{-- Modal Konfirmasi Hapus Draf PO --}}
    <div
        x-show="deleteModalOpen"
        x-cloak
        class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4 backdrop-blur-sm"
        @keydown.escape.window="deleteModalOpen = false"
    >
        <div 
            class="bg-white rounded-2xl max-w-sm w-full p-6 shadow-2xl space-y-4 border border-gray-100"
            @click.away="deleteModalOpen = false"
        >
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-red-50 text-red-600 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-gray-900">Hapus Pesanan Pembelian</h3>
                    <p class="text-xs text-gray-500">Tindakan ini tidak dapat dibatalkan</p>
                </div>
            </div>

            <p class="text-xs text-gray-600 leading-relaxed">
                Apakah Anda yakin ingin menghapus dokumen PO <span class="font-bold text-gray-900" x-text="deletePoNumber"></span>?
            </p>

            <form :action="deleteActionUrl" method="POST" class="pt-2 flex justify-end gap-2">
                @csrf
                @method('DELETE')
                <button
                    type="button"
                    @click="deleteModalOpen = false"
                    class="px-4 py-2 border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 hover:bg-gray-50 transition"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-xs font-bold shadow-sm transition"
                >
                    Ya, Hapus PO
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
