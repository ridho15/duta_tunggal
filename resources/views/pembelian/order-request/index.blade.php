@extends('layouts.app')

@section('title', 'Permintaan Pembelian (Order Request) – Duta Tunggal ERP')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">
    {{-- Header & Action Button --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <span>Pembelian</span>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Permintaan Barang (OR)</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Permintaan Pembelian (Order Request)</h1>
            <p class="text-sm text-gray-500 mt-0.5">Daftar pengajuan pengadaan barang dari cabang dan gudang sebelum diterbitkan Purchase Order.</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('pembelian.or.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-xs sm:text-sm font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Buat Permintaan Baru
            </a>
        </div>
    </div>


    {{-- KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Permintaan</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($kpi['total_requests'] ?? 0) }}</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
            </div>
            <p class="text-[11px] text-gray-400 mt-2">Semua dokumen OR yang diajukan</p>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Menunggu Otorisasi</p>
                    <p class="text-2xl font-bold text-amber-600 mt-1">{{ number_format($kpi['waiting_approval_count'] ?? 0) }}</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <p class="text-[11px] text-amber-600 mt-2">Memerlukan persetujuan atasan</p>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Disetujui / Siap PO</p>
                    <p class="text-2xl font-bold text-emerald-600 mt-1">{{ number_format($kpi['approved_count'] ?? 0) }}</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <p class="text-[11px] text-emerald-600 mt-2">Siap diproses oleh Purchasing</p>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Ditolak</p>
                    <p class="text-2xl font-bold text-rose-600 mt-1">{{ number_format($kpi['rejected_count'] ?? 0) }}</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </div>
            </div>
            <p class="text-[11px] text-rose-500 mt-2">Permintaan yang tidak disetujui</p>
        </div>
    </div>

    {{-- Main Card: Tabs, Filter & Table --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
        {{-- Status Tabs --}}
        <div class="border-b border-gray-100 px-4 flex items-center gap-2 overflow-x-auto no-scrollbar">
            @php
                $tabs = [
                    'semua' => ['label' => 'Semua', 'count' => $counts['semua']],
                    'draft' => ['label' => 'Draf', 'count' => $counts['draft']],
                    'menunggu_approve' => ['label' => 'Menunggu Approval', 'count' => $counts['menunggu_approve']],
                    'approved' => ['label' => 'Disetujui', 'count' => $counts['approved']],
                    'diproses' => ['label' => 'Diproses / Selesai', 'count' => $counts['diproses']],
                    'ditolak' => ['label' => 'Ditolak', 'count' => $counts['ditolak']],
                    'closed' => ['label' => 'Ditutup', 'count' => $counts['closed']],
                ];
            @endphp
            @foreach ($tabs as $key => $tab)
                <a
                    href="{{ request()->fullUrlWithQuery(['tab' => $key]) }}"
                    class="py-3 px-3 text-xs font-bold border-b-2 whitespace-nowrap flex items-center gap-1.5 transition {{ $activeTab === $key ? 'border-[#15803d] text-[#15803d]' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
                >
                    <span>{{ $tab['label'] }}</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] {{ $activeTab === $key ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600' }}">
                        {{ $tab['count'] }}
                    </span>
                </a>
            @endforeach
        </div>

        {{-- Filters Toolbar (Layout Flex Responsif & Anti-Tumpang Tindih) --}}
        <form method="GET" action="{{ route('pembelian.or.index') }}" class="p-3.5 sm:p-4 bg-gray-50/60 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
            <input type="hidden" name="tab" value="{{ $activeTab }}">

            {{-- Kolom Kiri: Search & Cabang --}}
            <div class="flex flex-1 flex-wrap sm:flex-nowrap items-center gap-2.5 min-w-[280px]">
                {{-- Pencarian --}}
                <div class="relative flex-1 min-w-[200px]">
                    <input
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Cari No. Request, keperluan, atau produk..."
                        class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg text-xs bg-white focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d]"
                    />
                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>

                {{-- Cabang --}}
                <div class="w-full sm:w-48 flex-shrink-0">
                    <x-searchable-select
                        name="cabang_id"
                        size="sm"
                        :auto-submit="true"
                        :options="$cabangs->map(fn($c) => [
                            'id' => (string)$c->id,
                            'label' => $c->nama,
                            'subtext' => $c->kode ?? ''
                        ])->prepend(['id' => '', 'label' => 'Semua Cabang', 'subtext' => ''])"
                        :value="(string)$cabangFilter"
                        placeholder="Semua Cabang"
                    />
                </div>
            </div>

            {{-- Kolom Kanan: Rentang Tanggal & Tombol Aksi --}}
            <div class="flex flex-wrap sm:flex-nowrap items-center gap-2 flex-shrink-0">
                {{-- Tanggal Permintaan Dari - Sampai --}}
                <div class="flex items-center gap-1.5 bg-white border border-gray-300 rounded-lg px-2.5 py-1.5 shadow-2xs">
                    <span class="text-[11px] font-semibold text-gray-400 select-none">Tgl:</span>
                    <input
                        type="date"
                        name="date_from"
                        value="{{ $dateFrom }}"
                        class="w-28 sm:w-32 p-0 border-0 text-xs text-gray-700 bg-transparent focus:ring-0 focus:outline-none"
                        title="Tanggal Dari"
                    />
                    <span class="text-gray-300 text-xs flex-shrink-0 select-none">-</span>
                    <input
                        type="date"
                        name="date_to"
                        value="{{ $dateTo }}"
                        class="w-28 sm:w-32 p-0 border-0 text-xs text-gray-700 bg-transparent focus:ring-0 focus:outline-none"
                        title="Tanggal Sampai"
                    />
                </div>

                {{-- Tombol Filter & Reset --}}
                <div class="flex items-center gap-1.5 flex-shrink-0">
                    <button
                        type="submit"
                        class="px-3.5 py-2 bg-gray-900 hover:bg-black text-white text-xs font-semibold rounded-lg shadow-sm transition active:scale-95 flex items-center gap-1.5 whitespace-nowrap"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        <span>Filter</span>
                    </button>
                    @if ($search || $cabangFilter || $dateFrom || $dateTo || $activeTab !== 'semua')
                        <a
                            href="{{ route('pembelian.or.index') }}"
                            class="px-3 py-2 border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-xs font-semibold rounded-lg transition whitespace-nowrap"
                            title="Reset Filter"
                        >
                            Reset
                        </a>
                    @endif
                </div>
            </div>
        </form>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-gray-50/80 border-b border-gray-100 text-gray-500 font-semibold uppercase tracking-wider text-[11px]">
                        <th class="py-3 px-4">No. Request</th>
                        <th class="py-3 px-3">Tgl Request</th>
                        <th class="py-3 px-3">Tgl Dibutuhkan</th>
                        <th class="py-3 px-3">Cabang</th>
                        <th class="py-3 px-3">Keperluan / Pemohon</th>
                        <th class="py-3 px-3">Ringkasan Item</th>
                        <th class="py-3 px-3 text-center">Status</th>
                        <th class="py-3 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($orderRequests as $or)
                        @php
                            $itemsCount = $or->orderRequestItem->count();
                            $firstItem = $or->orderRequestItem->first();
                            $productText = $firstItem ? ($firstItem->product?->name ?? 'Produk') : '-';
                            if ($itemsCount > 1) {
                                $productText .= ' (+' . ($itemsCount - 1) . ' item lainnya)';
                            }

                            // Resolusi nama cabang
                            $cabangName = $cabangs->firstWhere('id', $or->cabang_id)?->nama ?? '-';
                        @endphp
                        <tr class="hover:bg-gray-50/70 transition">
                            {{-- Nomor Request --}}
                            <td class="py-3 px-4 font-semibold text-gray-900">
                                <a href="{{ route('pembelian.or.show', $or) }}" class="text-[#15803d] hover:underline font-bold">
                                    {{ $or->request_number }}
                                </a>
                                @if ($or->purchaseOrders->isNotEmpty())
                                    <div class="text-[10px] text-blue-600 font-medium mt-0.5">
                                        PO: {{ $or->purchaseOrders->pluck('po_number')->join(', ') }}
                                    </div>
                                @endif
                            </td>

                            {{-- Tanggal Request --}}
                            <td class="py-3 px-3 text-gray-600 whitespace-nowrap">
                                {{ $or->request_date ? $or->request_date->format('d/m/Y') : '-' }}
                            </td>

                            {{-- Tanggal Dibutuhkan --}}
                            <td class="py-3 px-3 text-gray-600 whitespace-nowrap">
                                @if ($or->required_date)
                                    <span class="inline-flex items-center gap-1 font-medium {{ $or->required_date->isPast() && !in_array($or->status, ['complete', 'closed']) ? 'text-rose-600' : 'text-gray-700' }}">
                                        {{ $or->required_date->format('d/m/Y') }}
                                    </span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>

                            {{-- Cabang --}}
                            <td class="py-3 px-3 text-gray-700 font-medium">
                                {{ $cabangName }}
                            </td>

                            {{-- Keperluan / Pemohon --}}
                            <td class="py-3 px-3 text-gray-700">
                                <div class="font-medium text-gray-900 truncate max-w-[200px]" title="{{ $or->purpose }}">
                                    {{ $or->purpose ?: 'Kebutuhan Operasional' }}
                                </div>
                                <div class="text-[11px] text-gray-400">
                                    Oleh: {{ $or->createdBy?->name ?? 'User' }}
                                </div>
                            </td>

                            {{-- Ringkasan Item --}}
                            <td class="py-3 px-3 text-gray-600 max-w-[220px]">
                                <span class="truncate block font-medium" title="{{ $productText }}">{{ $productText }}</span>
                                <span class="text-[10px] text-gray-400">Total: {{ number_format($or->orderRequestItem->sum('quantity'), 0) }} unit</span>
                            </td>

                            {{-- Status Badge --}}
                            <td class="py-3 px-3 text-center whitespace-nowrap">
                                @php
                                    $statusConfig = match ($or->status) {
                                        'draft' => ['bg' => 'bg-gray-50', 'text' => 'text-gray-700', 'border' => 'border-gray-200', 'dot' => 'bg-gray-400', 'label' => 'Draf'],
                                        'request_approve' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'border' => 'border-amber-200', 'dot' => 'bg-amber-500 animate-pulse', 'label' => 'Menunggu Approval'],
                                        'approved' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'border' => 'border-emerald-200', 'dot' => 'bg-emerald-500', 'label' => 'Disetujui'],
                                        'partial' => ['bg' => 'bg-sky-50', 'text' => 'text-sky-700', 'border' => 'border-sky-200', 'dot' => 'bg-sky-500', 'label' => 'Diproses Sebagian'],
                                        'complete' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'border' => 'border-emerald-200', 'dot' => 'bg-emerald-500', 'label' => 'Selesai Terpenuhi'],
                                        'rejected' => ['bg' => 'bg-rose-50', 'text' => 'text-rose-700', 'border' => 'border-rose-200', 'dot' => 'bg-rose-500', 'label' => 'Ditolak'],
                                        'closed' => ['bg' => 'bg-slate-50', 'text' => 'text-slate-700', 'border' => 'border-slate-200', 'dot' => 'bg-slate-400', 'label' => 'Ditutup'],
                                        default => ['bg' => 'bg-gray-50', 'text' => 'text-gray-700', 'border' => 'border-gray-200', 'dot' => 'bg-gray-400', 'label' => ucfirst($or->status)],
                                    };
                                @endphp
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold border {{ $statusConfig['bg'] }} {{ $statusConfig['text'] }} {{ $statusConfig['border'] }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $statusConfig['dot'] }}"></span>
                                    {{ $statusConfig['label'] }}
                                </span>
                            </td>

                            {{-- Aksi --}}
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
                                    <a
                                        href="{{ route('pembelian.or.show', $or) }}"
                                        class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-100 transition shadow-2xs"
                                        title="Lihat Rincian"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>

                                    @if (in_array($or->status, ['draft', 'rejected'], true))
                                        <a
                                            href="{{ route('pembelian.or.edit', $or) }}"
                                            class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-blue-200 text-blue-600 hover:bg-blue-50 transition shadow-2xs"
                                            title="Ubah Data"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </a>
                                    @endif

                                    <a
                                        href="{{ route('pembelian.or.pdf', $or) }}"
                                        target="_blank"
                                        class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-gray-200 text-gray-600 hover:text-emerald-700 hover:bg-emerald-50 hover:border-emerald-200 transition shadow-2xs"
                                        title="Cetak PDF"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-gray-400">
                                <svg class="w-12 h-12 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <p class="text-sm font-semibold text-gray-600">Tidak ada permintaan pembelian ditemukan</p>
                                <p class="text-xs text-gray-400 mt-0.5">Gunakan tombol "Buat Permintaan Baru" untuk menambahkan transaksi.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($orderRequests->hasPages())
            <div class="p-4 border-t border-gray-100 bg-gray-50/50">
                {{ $orderRequests->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
