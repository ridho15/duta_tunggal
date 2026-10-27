@extends('layouts.app')

@section('title', 'Retur Pembelian & Debit Note – Duta Tunggal ERP')

@section('content')
<div class="space-y-6">
    {{-- Header Halaman --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-emerald-100 text-[#15803d]">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M9 14 4 9l5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M4 9h10a6 6 0 0 1 0 12h-3" stroke-width="2" stroke-linecap="round"></path>
                    </svg>
                </span>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Retur Pembelian &amp; Debit Note</h1>
                    <p class="text-sm text-gray-500 mt-0.5">Kelola pengembalian barang rusak/tidak sesuai ke vendor, pemotongan stok fisik, dan nota debit hutang dagang.</p>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('pembelian.returns.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-xs sm:text-sm font-semibold rounded-xl shadow-sm transition active:scale-[0.98]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                <span>Buat Retur Pembelian</span>
            </a>
        </div>
    </div>

    {{-- 4 Kartu KPI --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Nilai Retur Disetujui --}}
        <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex items-center gap-3.5">
            <div class="w-11 h-11 rounded-lg bg-emerald-50 text-[#15803d] flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div class="min-w-0">
                <div class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Total Nilai Disetujui</div>
                <div class="text-lg font-bold text-gray-900 truncate" title="{{ \App\Helpers\MoneyHelper::rupiah($kpi['total_approved_amount']) }}">
                    {{ \App\Helpers\MoneyHelper::rupiah($kpi['total_approved_amount']) }}
                </div>
                <div class="text-[10px] text-gray-400">Total kredit hutang / klaim</div>
            </div>
        </div>

        {{-- Retur Bulan Ini --}}
        <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex items-center gap-3.5">
            <div class="w-11 h-11 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>
            <div class="min-w-0">
                <div class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Retur Bulan Ini</div>
                <div class="text-lg font-bold text-gray-900 truncate">
                    {{ $kpi['this_month_count'] }} Dokumen
                </div>
                <div class="text-[10px] text-gray-400">Periode {{ now()->translatedFormat('F Y') }}</div>
            </div>
        </div>

        {{-- Menunggu Approval --}}
        <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex items-center gap-3.5">
            <div class="w-11 h-11 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div class="min-w-0">
                <div class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Menunggu Persetujuan</div>
                <div class="text-lg font-bold text-gray-900 truncate">
                    {{ $kpi['pending_approval_count'] }} Retur
                </div>
                <div class="text-[10px] text-amber-600 font-medium">Perlu ditinjau</div>
            </div>
        </div>

        {{-- Draft Retur --}}
        <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex items-center gap-3.5">
            <div class="w-11 h-11 rounded-lg bg-gray-100 text-gray-600 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            </div>
            <div class="min-w-0">
                <div class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Draft Disimpan</div>
                <div class="text-lg font-bold text-gray-900 truncate">
                    {{ $kpi['draft_count'] }} Retur
                </div>
                <div class="text-[10px] text-gray-400">Belum diajukan</div>
            </div>
        </div>
    </div>

    {{-- Card Utama Konten --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        {{-- Status Tabs --}}
        <div class="border-b border-gray-100 bg-gray-50/50 px-4 pt-2">
            <nav class="flex space-x-2 sm:space-x-4 overflow-x-auto text-xs font-semibold">
                @php
                    $tabs = [
                        'semua' => ['label' => 'Semua Retur', 'count' => $counts['semua']],
                        'draft' => ['label' => 'Draft', 'count' => $counts['draft']],
                        'pending_approval' => ['label' => 'Menunggu Approval', 'count' => $counts['pending_approval']],
                        'approved' => ['label' => 'Disetujui', 'count' => $counts['approved']],
                        'rejected' => ['label' => 'Ditolak', 'count' => $counts['rejected']],
                    ];
                @endphp
                @foreach ($tabs as $key => $info)
                    <a
                        href="{{ route('pembelian.returns.index', array_merge(request()->query(), ['tab' => $key, 'page' => 1])) }}"
                        class="pb-2.5 px-3 whitespace-nowrap border-b-2 flex items-center gap-1.5 transition {{ $tab === $key ? 'border-[#15803d] text-[#15803d] font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                    >
                        <span>{{ $info['label'] }}</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === $key ? 'bg-emerald-100 text-[#15803d]' : 'bg-gray-200 text-gray-600' }}">
                            {{ $info['count'] }}
                        </span>
                    </a>
                @endforeach
            </nav>
        </div>

        {{-- Filter & Pencarian Bar --}}
        <div class="p-4 bg-white border-b border-gray-100">
            <form method="GET" action="{{ route('pembelian.returns.index') }}" class="flex flex-col lg:flex-row lg:items-end justify-between gap-3">
                <input type="hidden" name="tab" value="{{ $tab }}">

                {{-- Grup Kiri: Input Pencarian & Dropdown Filter --}}
                <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-medium text-gray-600 mb-1">Cari Retur / Supplier / GRN</label>
                        <div class="relative">
                            <input
                                type="text"
                                name="q"
                                value="{{ $search }}"
                                placeholder="Ketik No. Nota Retur, Supplier, No. GRN..."
                                class="w-full pl-9 pr-3 py-1.5 text-xs bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] transition"
                            />
                            <svg class="w-4 h-4 absolute left-2.5 top-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-gray-600 mb-1">Supplier / Vendor</label>
                        <x-searchable-select name="supplier_id" :options="collect($suppliers)->map(fn ($s) => ['id' => $s->id, 'label' => $s->perusahaan])->values()" :value="$supplierId" placeholder="Semua Supplier" size="xs" :auto-submit="true" />
                    </div>
                </div>

                {{-- Grup Kanan: Rentang Tanggal Terpadu & Aksi Filter (Anti-Overlap) --}}
                <div class="flex flex-wrap sm:flex-nowrap items-end gap-2 shrink-0">
                    <div>
                        <label class="block text-[11px] font-medium text-gray-600 mb-1">Periode Tanggal Retur</label>
                        <div class="inline-flex items-center bg-gray-50 border border-gray-200 rounded-lg px-2 py-0.5 focus-within:border-[#15803d] focus-within:ring-1 focus-within:ring-[#15803d] transition">
                            <input
                                type="date"
                                name="start_date"
                                value="{{ $startDate }}"
                                class="bg-transparent border-0 text-xs py-1 px-1 focus:ring-0 focus:outline-none text-gray-700 min-w-[130px]"
                                title="Tanggal Mulai"
                            />
                            <span class="text-xs text-gray-400 px-1 font-medium">s/d</span>
                            <input
                                type="date"
                                name="end_date"
                                value="{{ $endDate }}"
                                class="bg-transparent border-0 text-xs py-1 px-1 focus:ring-0 focus:outline-none text-gray-700 min-w-[130px]"
                                title="Tanggal Akhir"
                            />
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5 self-end pb-0.5">
                        <button type="submit" class="px-3.5 py-1.5 bg-gray-900 hover:bg-black text-white text-xs font-semibold rounded-lg shadow-xs transition" title="Terapkan Filter">
                            Filter
                        </button>
                        @if ($search || $supplierId || $startDate || $endDate)
                            <a href="{{ route('pembelian.returns.index', ['tab' => $tab]) }}" class="px-2.5 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-xs font-semibold rounded-lg transition" title="Reset Filter">
                                ✕
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </div>

        {{-- Tabel Data --}}
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-gray-50/80 border-b border-gray-100 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">
                        <th class="px-4 py-3">No. Nota Retur</th>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3">Supplier / Vendor</th>
                        <th class="px-4 py-3">Penerimaan Terkait</th>
                        <th class="px-4 py-3 text-center">Item Retur</th>
                        <th class="px-4 py-3 text-right">Nilai Retur (DPP)</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white text-gray-800">
                    @forelse ($returns as $ret)
                        @php
                            $totalVal = $ret->purchaseReturnItem->sum(fn ($i) => (float) $i->qty_returned * (float) $i->unit_price);
                            $supplier = $ret->purchaseReceipt?->supplier ?? $ret->purchaseReceipt?->purchaseOrder?->supplier;
                        @endphp
                        <tr class="hover:bg-gray-50/60 transition group">
                            {{-- No. Nota Retur --}}
                            <td class="px-4 py-3 font-semibold text-gray-900 whitespace-nowrap">
                                <a href="{{ route('pembelian.returns.show', $ret->id) }}" class="text-[#15803d] hover:text-[#14532d] hover:underline font-bold">
                                    {{ $ret->nota_retur }}
                                </a>
                                @if ($ret->credit_note_number)
                                    <div class="text-[10px] text-gray-400 font-normal">
                                        DN: {{ $ret->credit_note_number }}
                                    </div>
                                @endif
                            </td>

                            {{-- Tanggal --}}
                            <td class="px-4 py-3 whitespace-nowrap text-gray-600">
                                <div>{{ $ret->return_date ? \Carbon\Carbon::parse($ret->return_date)->format('d/m/Y') : '-' }}</div>
                                <div class="text-[10px] text-gray-400">{{ $ret->created_at ? $ret->created_at->format('H:i') : '' }} WIB</div>
                            </td>

                            {{-- Supplier --}}
                            <td class="px-4 py-3">
                                <div class="font-bold text-gray-900">{{ $supplier?->perusahaan ?? 'Supplier Tidak Ditemukan' }}</div>
                                <div class="text-[10.5px] text-gray-400">{{ $supplier?->code ?? '-' }}</div>
                            </td>

                            {{-- Penerimaan Terkait --}}
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if ($ret->purchase_receipt_id && $ret->purchaseReceipt?->id)
                                    <a href="{{ route('gudang.penerimaan.show', $ret->purchaseReceipt->id) }}" class="text-blue-600 hover:underline font-medium">
                                        {{ $ret->purchaseReceipt->receipt_number ?: ('GRN-' . $ret->purchaseReceipt->id) }}
                                    </a>
                                    @if ($ret->purchaseReceipt->purchaseOrder?->id)
                                        <div class="text-[10.5px] text-gray-400">PO: {{ $ret->purchaseReceipt->purchaseOrder->po_number }}</div>
                                    @endif
                                @elseif ($ret->quality_control_id && $ret->qualityControl?->id)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-50 text-amber-700 border border-amber-200">
                                        QC #{{ $ret->qualityControl->qc_number }}
                                    </span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>

                            {{-- Jumlah Item & Total Qty --}}
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <span class="font-semibold text-gray-900">{{ $ret->purchaseReturnItem->count() }} item</span>
                                <span class="text-gray-400 text-[10.5px]">({{ $ret->purchaseReturnItem->sum('qty_returned') }} pcs)</span>
                            </td>

                            {{-- Total Nilai Retur --}}
                            <td class="px-4 py-3 text-right font-bold text-gray-900 whitespace-nowrap">
                                {{ \App\Helpers\MoneyHelper::rupiah($totalVal) }}
                            </td>

                            {{-- Status Badge --}}
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                @php
                                    $statusMap = [
                                        'draft' => ['badge' => 'bg-gray-50 text-gray-700 border-gray-200', 'dot' => 'bg-gray-400', 'label' => 'Draft'],
                                        'pending_approval' => ['badge' => 'bg-amber-50 text-amber-700 border-amber-200', 'dot' => 'bg-amber-500', 'label' => 'Menunggu Approval'],
                                        'approved' => ['badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'dot' => 'bg-emerald-500', 'label' => 'Disetujui'],
                                        'rejected' => ['badge' => 'bg-rose-50 text-rose-700 border-rose-200', 'dot' => 'bg-rose-500', 'label' => 'Ditolak'],
                                    ];
                                    $st = $statusMap[$ret->status] ?? ['badge' => 'bg-gray-50 text-gray-700 border-gray-200', 'dot' => 'bg-gray-400', 'label' => ucfirst($ret->status)];
                                @endphp
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold border {{ $st['badge'] }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $st['dot'] }}"></span>
                                    {{ $st['label'] }}
                                </span>
                            </td>

                            {{-- Aksi --}}
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    {{-- Lihat --}}
                                    <a
                                        href="{{ route('pembelian.returns.show', $ret->id) }}"
                                        class="px-2 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded text-[11px] transition"
                                        title="Lihat Detail Retur"
                                    >
                                        Lihat
                                    </a>

                                    {{-- Edit Draft/Rejected --}}
                                    @if (in_array($ret->status, ['draft', 'rejected']))
                                        <a
                                            href="{{ route('pembelian.returns.edit', $ret->id) }}"
                                            class="px-2 py-1 bg-amber-50 hover:bg-amber-100 text-amber-700 font-semibold rounded text-[11px] transition"
                                            title="Ubah Retur"
                                        >
                                            Ubah
                                        </a>
                                    @endif

                                    {{-- Ajukan (Submit for approval) --}}
                                    @if ($ret->status === 'draft')
                                        <button
                                            type="button"
                                            @click="$dispatch('open-modal-submit-return', { action: '{{ route('pembelian.returns.submit', $ret->id) }}', nota: '{{ $ret->nota_retur }}' })"
                                            class="px-2 py-1 bg-blue-50 hover:bg-blue-100 text-blue-700 font-semibold rounded text-[11px] transition"
                                            title="Ajukan Persetujuan"
                                        >
                                            Ajukan
                                        </button>
                                    @endif

                                    {{-- Approve Button (Modal) --}}
                                    @if (in_array($ret->status, ['draft', 'pending_approval']))
                                        <button
                                            type="button"
                                            onclick="openApproveModal({{ $ret->id }}, '{{ $ret->nota_retur }}')"
                                            class="px-2 py-1 bg-emerald-50 hover:bg-emerald-100 text-[#15803d] font-semibold rounded text-[11px] transition"
                                            title="Setujui Retur"
                                        >
                                            Setujui
                                        </button>
                                    @endif

                                    {{-- Reject Button (Modal) --}}
                                    @if ($ret->status === 'pending_approval')
                                        <button
                                            type="button"
                                            onclick="openRejectModal({{ $ret->id }}, '{{ $ret->nota_retur }}')"
                                            class="px-2 py-1 bg-red-50 hover:bg-red-100 text-red-700 font-semibold rounded text-[11px] transition"
                                            title="Tolak Retur"
                                        >
                                            Tolak
                                        </button>
                                    @endif

                                    {{-- Cetak PDF --}}
                                    <a
                                        href="{{ route('pembelian.returns.pdf', $ret->id) }}"
                                        target="_blank"
                                        class="p-1 text-gray-500 hover:text-gray-800 transition"
                                        title="Cetak Nota Retur PDF"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                        </svg>
                                    </a>

                                    {{-- Hapus Draft --}}
                                    @if (in_array($ret->status, ['draft', 'rejected']))
                                        <button
                                            type="button"
                                            onclick="openDeleteModal({{ $ret->id }}, '{{ $ret->nota_retur }}')"
                                            class="p-1 text-red-400 hover:text-red-700 transition"
                                            title="Hapus Retur"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center">
                                <div class="flex flex-col items-center justify-center text-gray-400">
                                    <svg class="w-12 h-12 mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 14 4 9l5-5m0 0h10a6 6 0 0 1 0 12h-3" />
                                    </svg>
                                    <p class="text-sm font-medium text-gray-600">Tidak ada dokumen retur pembelian yang ditemukan.</p>
                                    <p class="text-xs text-gray-400 mt-1">Gunakan tombol "Buat Retur Pembelian" di kanan atas untuk membuat retur baru.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginasi Standar Indonesia --}}
        @if ($returns->hasPages())
            <div class="p-4 border-t border-gray-100 bg-gray-50/50">
                {{ $returns->links('vendor.pagination.tailwind') }}
            </div>
        @endif
    </div>
</div>

{{-- Modal Konfirmasi Persetujuan (Approve Modal) --}}
<div id="approveModal" class="fixed inset-0 z-50 hidden bg-gray-900/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl max-w-md w-full overflow-hidden border border-gray-200">
        <form id="approveForm" method="POST" action="">
            @csrf
            <div class="p-5">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-full bg-emerald-100 text-[#15803d] flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">Setujui Retur Pembelian</h3>
                        <p id="approveModalTitle" class="text-xs text-gray-500"></p>
                    </div>
                </div>
                <div class="p-3 bg-emerald-50/60 border border-emerald-100 rounded-lg text-xs text-emerald-800 space-y-1 mb-3">
                    <p class="font-semibold">Efek Otomatis Persetujuan:</p>
                    <ul class="list-disc list-inside space-y-0.5 text-[11px]">
                        <li>Stok fisik barang di gudang akan dikurangi otomatis.</li>
                        <li>Jurnal Akuntansi GL: Debit Hutang Dagang, Kredit Persediaan (+ Kredit PPN jika ada).</li>
                        <li>Saldo sisa hutang pada Faktur Pembelian (AP) akan terpotong senilai retur.</li>
                    </ul>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Catatan Persetujuan (Opsional)</label>
                    <textarea
                        name="approval_notes"
                        rows="2"
                        class="w-full text-xs p-2.5 bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d]"
                        placeholder="Contoh: Barang cacat sudah diverifikasi fisik dan disetujui retur ke vendor."
                    ></textarea>
                </div>
            </div>
            <div class="p-3 bg-gray-50 border-t border-gray-200 flex items-center justify-end gap-2">
                <button type="button" onclick="closeApproveModal()" class="px-3 py-1.5 text-xs font-semibold text-gray-600 hover:text-gray-800 transition">
                    Batal
                </button>
                <button type="submit" class="px-4 py-1.5 bg-[#15803d] hover:bg-[#14532d] text-white text-xs font-semibold rounded-lg shadow-sm transition">
                    Ya, Setujui &amp; Proses Akuntansi
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Konfirmasi Penolakan (Reject Modal) --}}
<div id="rejectModal" class="fixed inset-0 z-50 hidden bg-gray-900/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl max-w-md w-full overflow-hidden border border-gray-200">
        <form id="rejectForm" method="POST" action="">
            @csrf
            <div class="p-5">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">Tolak Retur Pembelian</h3>
                        <p id="rejectModalTitle" class="text-xs text-gray-500"></p>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Alasan Penolakan <span class="text-red-500">*</span></label>
                    <textarea
                        name="rejection_notes"
                        rows="3"
                        required
                        minlength="5"
                        class="w-full text-xs p-2.5 bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:ring-1 focus:ring-red-500 focus:border-red-500"
                        placeholder="Wajib diisi. Contoh: Masa garansi pengembalian habis atau kerusakan akibat kelalaian internal."
                    ></textarea>
                </div>
            </div>
            <div class="p-3 bg-gray-50 border-t border-gray-200 flex items-center justify-end gap-2">
                <button type="button" onclick="closeRejectModal()" class="px-3 py-1.5 text-xs font-semibold text-gray-600 hover:text-gray-800 transition">
                    Batal
                </button>
                <button type="submit" class="px-4 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                    Tolak Dokumen
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Konfirmasi Hapus Draft --}}
<div id="deleteModal" class="fixed inset-0 z-50 hidden bg-gray-900/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl max-w-sm w-full overflow-hidden border border-gray-200">
        <form id="deleteForm" method="POST" action="">
            @csrf
            @method('DELETE')
            <div class="p-5 text-center">
                <div class="w-12 h-12 rounded-full bg-red-100 text-red-600 flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </div>
                <h3 class="text-sm font-bold text-gray-900 mb-1">Hapus Draft Retur Pembelian?</h3>
                <p id="deleteModalBody" class="text-xs text-gray-500"></p>
            </div>
            <div class="p-3 bg-gray-50 border-t border-gray-200 flex items-center justify-center gap-2">
                <button type="button" onclick="closeDeleteModal()" class="px-3 py-1.5 text-xs font-semibold text-gray-600 hover:text-gray-800 transition">
                    Batal
                </button>
                <button type="submit" class="px-4 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                    Hapus Dokumen
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    function openApproveModal(id, notaRetur) {
        document.getElementById('approveForm').action = "{{ url('pembelian/retur') }}/" + id + "/approve";
        document.getElementById('approveModalTitle').innerText = 'Dokumen ' + notaRetur;
        document.getElementById('approveModal').classList.remove('hidden');
    }
    function closeApproveModal() {
        document.getElementById('approveModal').classList.add('hidden');
    }

    function openRejectModal(id, notaRetur) {
        document.getElementById('rejectForm').action = "{{ url('pembelian/retur') }}/" + id + "/reject";
        document.getElementById('rejectModalTitle').innerText = 'Dokumen ' + notaRetur;
        document.getElementById('rejectModal').classList.remove('hidden');
    }
    function closeRejectModal() {
        document.getElementById('rejectModal').classList.add('hidden');
    }

    function openDeleteModal(id, notaRetur) {
        document.getElementById('deleteForm').action = "{{ url('pembelian/retur') }}/" + id;
        document.getElementById('deleteModalBody').innerText = 'Apakah Anda yakin ingin menghapus draft retur ' + notaRetur + '? Tindakan ini dapat dipulihkan.';
        document.getElementById('deleteModal').classList.remove('hidden');
    }
    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
    }
</script>
@endpush

    {{-- Modal Konfirmasi Ajukan Retur Pembelian --}}
    <x-confirmation-modal
        name="submit-return"
        title="Ajukan Retur Pembelian"
        type="info"
        confirm-text="Ya, Ajukan Retur"
        cancel-text="Batal"
        method="POST"
    >
        <p class="text-sm text-gray-600">
            Ajukan dokumen retur pembelian <span class="font-bold text-gray-900 font-mono" x-text="data.nota"></span> untuk persetujuan manajer?
        </p>
    </x-confirmation-modal>
@endsection
