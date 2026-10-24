@extends('layouts.app')

@section('title', 'Pengeluaran & Retur Bahan Baku – Duta Tunggal ERP')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">
    {{-- Header & Action Button --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <span>Manufaktur & Produksi</span>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Pengeluaran & Retur Bahan</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Pengeluaran Bahan Baku (Material Issue &amp; Return)</h1>
            <p class="text-sm text-gray-500 mt-0.5">Pencatatan serah terima bahan baku dari gudang ke lantai produksi dan pengembalian sisa bahan beserta mutasi jurnal BDP.</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('manufaktur.material-issue.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-sm font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Catat Pengeluaran / Retur
            </a>
        </div>
    </div>


    {{-- KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Transaksi</p>
                    <p class="text-2xl font-extrabold text-gray-900 mt-1">{{ number_format($totalCount) }}</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
            </div>
            <div class="flex items-center gap-2 mt-2 text-[11px] text-gray-500">
                <span class="font-semibold text-emerald-600">{{ $issueCount }} Ambil</span>
                <span>•</span>
                <span class="font-semibold text-amber-600">{{ $returnCount }} Retur</span>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Menunggu Gudang</p>
                    <p class="text-2xl font-extrabold text-amber-600 mt-1">{{ number_format($pendingCount) }}</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <p class="text-[11px] text-amber-600 font-medium mt-2">Menunggu konfirmasi fisik stok</p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Selesai Dikeluarkan</p>
                    <p class="text-2xl font-extrabold text-emerald-600 mt-1">{{ number_format($completedCount) }}</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
            </div>
            <p class="text-[11px] text-emerald-600 font-medium mt-2">Stok terpangkas & jurnal BDP aktif</p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Nilai BDP Bahan</p>
                    <p class="text-xl font-extrabold text-indigo-700 mt-1">Rp {{ number_format($totalBdpCost, 0, ',', '.') }}</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <p class="text-[11px] text-indigo-600 font-medium mt-2">Akun 1400.04 Pos Sementara</p>
        </div>
    </div>

    {{-- Tabs & Filters Card --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        {{-- Segmented Tabs --}}
        <div class="border-b border-gray-100 px-4 sm:px-6 pt-3 flex flex-wrap gap-2">
            @php
                $tabs = [
                    'semua' => 'Semua Dokumen',
                    'issue' => 'Pengeluaran Bahan (' . $issueCount . ')',
                    'return' => 'Retur Bahan (' . $returnCount . ')',
                    'draft' => 'Draft',
                    'pending' => 'Menunggu Gudang (' . $pendingCount . ')',
                    'approved' => 'Disetujui',
                    'completed' => 'Selesai',
                    'rejected' => 'Ditolak',
                ];
            @endphp
            @foreach ($tabs as $key => $label)
                <a
                    href="{{ route('manufaktur.material-issue.index', array_merge(request()->query(), ['tab' => $key, 'page' => 1])) }}"
                    class="pb-3 px-3 text-xs sm:text-sm font-semibold border-b-2 transition {{ $tab === $key ? 'border-[#15803d] text-[#15803d]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                >
                    {{ $label }}
                </a>
            @endforeach
        </div>

        {{-- Filter Form --}}
        <form method="GET" action="{{ route('manufaktur.material-issue.index') }}" class="p-4 sm:p-6 bg-gray-50/50 border-b border-gray-100">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3">
                <div class="lg:col-span-5 relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </span>
                    <input
                        type="text"
                        name="q"
                        value="{{ $search }}"
                        placeholder="Cari no. bukti, SPK, produk jadi, bahan..."
                        class="w-full pl-9 pr-3 py-2 text-xs sm:text-sm border border-gray-200 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d]"
                    >
                </div>

                <div class="lg:col-span-3">
                    <x-searchable-select name="warehouse_id" :options="collect($warehouses)->map(fn ($wh) => ['id' => $wh->id, 'label' => $wh->kode . ' - ' . $wh->name])->values()" :value="$warehouseId" placeholder="Semua Gudang" size="sm" />
                </div>

                <div class="lg:col-span-2">
                    <input
                        type="date"
                        name="start_date"
                        value="{{ $startDate }}"
                        class="w-full py-2 px-3 text-xs sm:text-sm border border-gray-200 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d]"
                        title="Dari Tanggal"
                    >
                </div>

                <div class="lg:col-span-2 flex items-center gap-2">
                    <input
                        type="date"
                        name="end_date"
                        value="{{ $endDate }}"
                        class="w-full py-2 px-3 text-xs sm:text-sm border border-gray-200 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d]"
                        title="Sampai Tanggal"
                    >
                    <button
                        type="submit"
                        class="px-3.5 py-2 bg-gray-900 hover:bg-black text-white text-xs font-semibold rounded-xl transition"
                    >
                        Filter
                    </button>
                    @if ($search || $warehouseId || $startDate || $endDate)
                        <a
                            href="{{ route('manufaktur.material-issue.index', ['tab' => $tab]) }}"
                            class="p-2 text-gray-400 hover:text-gray-600 rounded-xl hover:bg-gray-200 transition"
                            title="Reset Filter"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </a>
                    @endif
                </div>
            </div>
        </form>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs sm:text-sm">
                <thead>
                    <tr class="bg-gray-50/80 border-b border-gray-100 text-gray-500 font-semibold uppercase tracking-wider text-[11px]">
                        <th class="py-3 px-4 sm:px-6">Bukti & Tipe</th>
                        <th class="py-3 px-4">Ref. SPK & Produksi</th>
                        <th class="py-3 px-4">Gudang</th>
                        <th class="py-3 px-4">Tanggal</th>
                        <th class="py-3 px-4 text-right">Total Biaya Bahan</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 sm:px-6 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    @forelse ($materialIssues as $issue)
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-3.5 px-4 sm:px-6">
                                <div class="flex items-center gap-2">
                                    <a
                                        href="{{ route('manufaktur.material-issue.show', $issue) }}"
                                        class="font-bold text-gray-900 hover:text-[#15803d] transition"
                                    >
                                        {{ $issue->issue_number }}
                                    </a>
                                    @if ($issue->type === 'issue')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 text-emerald-800">
                                            Ambil Bahan
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-100 text-amber-800">
                                            Retur Bahan
                                        </span>
                                    @endif
                                </div>
                                <p class="text-[11px] text-gray-400 mt-0.5">{{ $issue->items->count() }} jenis bahan baku</p>
                            </td>

                            <td class="py-3.5 px-4">
                                @if ($issue->manufacturingOrder && $issue->manufacturingOrder->exists)
                                    <a
                                        href="{{ route('manufaktur.spk.show', $issue->manufacturingOrder) }}"
                                        class="font-semibold text-gray-800 hover:text-[#15803d]"
                                    >
                                        {{ $issue->manufacturingOrder->mo_number }}
                                    </a>
                                @elseif ($issue->productionPlan && $issue->productionPlan->exists)
                                    <span class="font-semibold text-gray-800">{{ $issue->productionPlan->plan_number }}</span>
                                @else
                                    <span class="text-gray-400 italic">Non-SPK / Manual</span>
                                @endif
                                <div class="text-[11px] text-gray-500 line-clamp-1">
                                    {{ $issue->productionPlan?->product?->name ?? $issue->manufacturingOrder?->productionPlan?->product?->name ?? '-' }}
                                </div>
                            </td>

                            <td class="py-3.5 px-4">
                                <div class="font-medium text-gray-800">{{ $issue->warehouse?->name ?? '-' }}</div>
                                <div class="text-[11px] text-gray-400">{{ $issue->warehouse?->kode ?? '' }}</div>
                            </td>

                            <td class="py-3.5 px-4">
                                <span class="text-gray-800">{{ $issue->issue_date?->format('d/m/Y') ?? '-' }}</span>
                            </td>

                            <td class="py-3.5 px-4 text-right">
                                <span class="font-bold text-gray-900">Rp {{ number_format($issue->total_cost, 0, ',', '.') }}</span>
                            </td>

                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                @php
                                    $statusConfig = [
                                        'draft' => ['badge' => 'bg-gray-50 text-gray-700 border-gray-200', 'dot' => 'bg-gray-400', 'label' => 'Draft'],
                                        'pending_approval' => ['badge' => 'bg-amber-50 text-amber-700 border-amber-200', 'dot' => 'bg-amber-500', 'label' => 'Menunggu Gudang'],
                                        'approved' => ['badge' => 'bg-blue-50 text-blue-700 border-blue-200', 'dot' => 'bg-blue-500', 'label' => 'Disetujui'],
                                        'completed' => ['badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'dot' => 'bg-emerald-500', 'label' => 'Selesai'],
                                        'rejected' => ['badge' => 'bg-rose-50 text-rose-700 border-rose-200', 'dot' => 'bg-rose-500', 'label' => 'Ditolak'],
                                    ];
                                    $st = $statusConfig[$issue->status] ?? ['badge' => 'bg-gray-50 text-gray-700 border-gray-200', 'dot' => 'bg-gray-400', 'label' => ucfirst($issue->status)];
                                @endphp
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold border {{ $st['badge'] }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $st['dot'] }}"></span>
                                    {{ $st['label'] }}
                                </span>
                            </td>

                            <td class="py-3.5 px-4 sm:px-6 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a
                                        href="{{ route('manufaktur.material-issue.show', $issue) }}"
                                        class="p-1.5 text-gray-500 hover:text-[#15803d] hover:bg-gray-100 rounded-lg transition"
                                        title="Lihat Detail"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>

                                    <a
                                        href="{{ route('manufaktur.material-issue.pdf', $issue) }}"
                                        target="_blank"
                                        class="p-1.5 text-gray-500 hover:text-blue-600 hover:bg-gray-100 rounded-lg transition"
                                        title="Cetak Bukti PDF"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                        </svg>
                                    </a>

                                    @if ($issue->status === 'draft')
                                        <a
                                            href="{{ route('manufaktur.material-issue.edit', $issue) }}"
                                            class="p-1.5 text-gray-500 hover:text-amber-600 hover:bg-gray-100 rounded-lg transition"
                                            title="Edit Dokumen"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </a>

                                        <button
                                            type="button"
                                            @click="$dispatch('open-modal-delete-material-issue', { action: '{{ route('manufaktur.material-issue.destroy', $issue) }}', number: '{{ $issue->issue_number }}' })"
                                            class="p-1.5 text-gray-400 hover:text-rose-600 hover:bg-gray-100 rounded-lg transition"
                                            title="Hapus Draft"
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
                            <td colspan="7" class="py-12 text-center text-gray-400">
                                <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                </svg>
                                <p class="text-sm font-semibold text-gray-600">Tidak ada data Pengeluaran / Retur Bahan</p>
                                <p class="text-xs text-gray-400 mt-1">Gunakan tombol "Catat Pengeluaran / Retur" untuk membuat dokumen baru.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($materialIssues->hasPages())
            <div class="p-4 border-t border-gray-100 bg-gray-50/50">
                {{ $materialIssues->links() }}
            </div>
        @endif
    </div>

    {{-- Modal Konfirmasi Hapus Draft Dokumen --}}
    <x-confirmation-modal
        name="delete-material-issue"
        title="Hapus Draft Dokumen Pengeluaran Bahan"
        variant="danger"
        confirm-text="Ya, Hapus Draft"
        cancel-text="Batal"
        method="DELETE"
    >
        <p class="text-sm text-gray-600">
            Apakah Anda yakin ingin menghapus draft dokumen <strong class="text-gray-900" x-text="data.number"></strong>?
        </p>
        <p class="text-xs text-rose-600 mt-2 font-medium">
            Tindakan ini tidak dapat dibatalkan.
        </p>
    </x-confirmation-modal>
</div>
@endsection
