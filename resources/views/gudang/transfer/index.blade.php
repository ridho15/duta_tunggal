@extends('layouts.app')

@section('title', 'Pindah Stok Antar Gudang – ERP Duta Tunggal')

@section('content')
<div class="space-y-6">
    {{-- Header & Breadcrumbs --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <nav class="flex items-center gap-2 text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">
                <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Dashboard</a>
                <span class="text-gray-300">/</span>
                <span class="text-gray-500">Gudang & Stok</span>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Pindah Stok</span>
            </nav>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Pindah Stok Antar Gudang</h1>
            <p class="text-xs text-gray-500 mt-1">
                Kelola distribusi dan pergerakan fisik barang antar lokasi gudang, persetujuan transfer, dan bukti surat jalan pindah stok.
            </p>
        </div>
        <div class="flex items-center gap-2.5">
            <a href="{{ route('gudang.stok.index') }}"
               class="inline-flex items-center gap-1.5 px-3.5 py-2.5 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M21 8 12 3 3 8v8l9 5 9-5z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                    <path d="M3 8l9 5 9-5M12 13v8" stroke-width="1.8" stroke-linecap="round"></path>
                </svg>
                <span>Cek Saldo Stok</span>
            </a>
            <a href="{{ route('gudang.transfer.create') }}"
               class="inline-flex items-center gap-1.5 px-4 py-2.5 text-xs sm:text-sm font-semibold text-white bg-[#15803d] hover:bg-[#166534] rounded-xl transition shadow-sm active:scale-[0.98]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M12 4v16m8-8H4" stroke-width="2" stroke-linecap="round"></path>
                </svg>
                <span>Transfer Stok Baru</span>
            </a>
        </div>
    </div>


    {{-- KPI Stat Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Transfer --}}
        <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm">
            <div class="flex items-center justify-between text-gray-500 mb-2">
                <span class="text-xs font-medium uppercase tracking-wider">Total Dokumen</span>
                <div class="w-8 h-8 rounded-lg bg-[#f0fdf4] text-[#15803d] flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M7 7h13l-3-3M17 17H4l3 3" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </div>
            </div>
            <div class="text-2xl font-bold text-gray-900 tracking-tight">
                {{ number_format($kpi['total'], 0, ',', '.') }}
            </div>
            <p class="text-[11px] text-gray-500 mt-1">Seluruh riwayat transfer stok</p>
        </div>

        {{-- Draft --}}
        <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm">
            <div class="flex items-center justify-between text-gray-500 mb-2">
                <span class="text-xs font-medium uppercase tracking-wider">Draft Persiapan</span>
                <div class="w-8 h-8 rounded-lg bg-gray-100 text-gray-600 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M11 5H6a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-5m-1.414-9.414a2 2 0 1 1 2.828 2.828L11.828 15H9v-2.828l8.586-8.586z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </div>
            </div>
            <div class="text-2xl font-bold text-gray-700 tracking-tight">
                {{ number_format($kpi['draft'], 0, ',', '.') }}
            </div>
            <p class="text-[11px] text-gray-500 mt-1">Belum diajukan / masih diedit</p>
        </div>

        {{-- Menunggu Persetujuan --}}
        <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm">
            <div class="flex items-center justify-between text-gray-500 mb-2">
                <span class="text-xs font-medium uppercase tracking-wider">Menunggu Approval</span>
                <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="9" stroke-width="1.8"></circle>
                        <path d="M12 6v6l4 2" stroke-width="1.8" stroke-linecap="round"></path>
                    </svg>
                </div>
            </div>
            <div class="text-2xl font-bold text-amber-600 tracking-tight">
                {{ number_format($kpi['request'], 0, ',', '.') }}
            </div>
            <p class="text-[11px] text-gray-500 mt-1">Menunggu persetujuan kepala gudang</p>
        </div>

        {{-- Disetujui / Selesai --}}
        <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm">
            <div class="flex items-center justify-between text-gray-500 mb-2">
                <span class="text-xs font-medium uppercase tracking-wider">Disetujui / Selesai</span>
                <div class="w-8 h-8 rounded-lg bg-[#f0fdf4] text-[#15803d] flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </div>
            </div>
            <div class="text-2xl font-bold text-[#15803d] tracking-tight">
                {{ number_format($kpi['approved'], 0, ',', '.') }}
            </div>
            <p class="text-[11px] text-gray-500 mt-1">Stok telah terpotong & terpindah</p>
        </div>
    </div>

    {{-- Filter & Pencarian Bar --}}
    <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm">
        <form action="{{ route('gudang.transfer.index') }}" method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-3">
            {{-- Search Box --}}
            <div class="md:col-span-3">
                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Cari No. Transfer / Item</label>
                <div class="relative">
                    <input type="text"
                           name="search"
                           value="{{ $search }}"
                           placeholder="No transfer, nama barang..."
                           class="w-full pl-9 pr-3 py-2 text-xs rounded-xl border border-gray-200 focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] text-gray-800">
                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2" stroke-linecap="round"></path>
                    </svg>
                </div>
            </div>

            {{-- Filter Gudang Asal --}}
            <div class="md:col-span-2">
                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Gudang Asal</label>
                <x-searchable-select name="from_warehouse_id" :options="collect($warehouses)->map(fn ($wh) => ['id' => $wh->id, 'label' => $wh->name])->values()" :value="$fromWarehouseId" placeholder="Semua Gudang Asal" size="sm" :auto-submit="true" />
            </div>

            {{-- Filter Gudang Tujuan --}}
            <div class="md:col-span-2">
                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Gudang Tujuan</label>
                <x-searchable-select name="to_warehouse_id" :options="collect($warehouses)->map(fn ($wh) => ['id' => $wh->id, 'label' => $wh->name])->values()" :value="$toWarehouseId" placeholder="Semua Gudang Tujuan" size="sm" :auto-submit="true" />
            </div>

            {{-- Filter Status --}}
            <div class="md:col-span-2">
                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Status</label>
                <select name="status"
                        class="w-full px-2.5 py-2 text-xs rounded-xl border border-gray-200 focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] text-gray-800">
                    <option value="all" {{ $status === 'all' ? 'selected' : '' }}>Semua Status</option>
                    <option value="Draft" {{ $status === 'Draft' ? 'selected' : '' }}>Draft</option>
                    <option value="Request" {{ $status === 'Request' ? 'selected' : '' }}>Menunggu Approval (Request)</option>
                    <option value="Approved" {{ $status === 'Approved' ? 'selected' : '' }}>Disetujui (Approved)</option>
                    <option value="Reject" {{ $status === 'Reject' ? 'selected' : '' }}>Ditolak (Reject)</option>
                </select>
            </div>

            {{-- Action Buttons --}}
            <div class="md:col-span-3 flex items-end gap-2">
                <button type="submit"
                        class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-gray-900 rounded-xl hover:bg-black transition shadow-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2" stroke-linecap="round"></path>
                    </svg>
                    <span>Terapkan</span>
                </button>
                @if ($search || $fromWarehouseId || $toWarehouseId || $status !== 'all' || $startDate || $endDate)
                    <a href="{{ route('gudang.transfer.index') }}"
                       class="inline-flex items-center justify-center p-2 text-gray-500 bg-gray-100 rounded-xl hover:bg-gray-200 transition"
                       title="Reset Filter">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M6 18L18 6M6 6l12 12" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Tabel Dokumen Transfer --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-gray-50/80 border-b border-gray-100 text-gray-500 uppercase tracking-wider text-[11px] font-semibold">
                        <th class="py-3 px-4">No. Dokumen</th>
                        <th class="py-3 px-4">Tanggal Transfer</th>
                        <th class="py-3 px-4">Gudang Asal</th>
                        <th class="py-3 px-4 text-center">Arah</th>
                        <th class="py-3 px-4">Gudang Tujuan</th>
                        <th class="py-3 px-4 text-center">Jumlah Item</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    @forelse ($transfers as $tf)
                        <tr class="hover:bg-gray-50/80 transition">
                            {{-- No. Dokumen --}}
                            <td class="py-3 px-4 font-mono font-bold text-[#15803d]">
                                <a href="{{ route('gudang.transfer.show', $tf) }}" class="hover:underline">
                                    {{ $tf->transfer_number }}
                                </a>
                            </td>

                            {{-- Tanggal --}}
                            <td class="py-3 px-4 text-gray-600">
                                {{ \Carbon\Carbon::parse($tf->transfer_date)->translatedFormat('d M Y') }}
                            </td>

                            {{-- Gudang Asal --}}
                            <td class="py-3 px-4 font-medium text-gray-900">
                                {{ $tf->fromWarehouse?->name ?? '-' }}
                            </td>

                            {{-- Panah Arah --}}
                            <td class="py-3 px-4 text-center text-gray-400">
                                <svg class="w-4 h-4 mx-auto text-[#15803d]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path d="M14 5l7 7m0 0l-7 7m7-7H3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                            </td>

                            {{-- Gudang Tujuan --}}
                            <td class="py-3 px-4 font-medium text-gray-900">
                                {{ $tf->toWarehouse?->name ?? '-' }}
                            </td>

                            {{-- Jumlah Item --}}
                            <td class="py-3 px-4 text-center font-semibold text-gray-800">
                                {{ $tf->stockTransferItem->count() }} Item
                                <span class="text-[10px] text-gray-400 block font-normal">
                                    ({{ number_format($tf->stockTransferItem->sum('quantity'), 0, ',', '.') }} Qty)
                                </span>
                            </td>

                            {{-- Status Badge --}}
                            <td class="py-3 px-4 text-center">
                                @if (in_array($tf->status, ['Approved', 'completed']))
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-[#f0fdf4] text-[#15803d] border border-[#86efac]">
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#15803d]"></span>
                                        Disetujui
                                    </span>
                                @elseif (in_array($tf->status, ['Request', 'Pending']))
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                        Menunggu Approval
                                    </span>
                                @elseif (in_array($tf->status, ['Reject', 'cancelled']))
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                        Ditolak
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-gray-50 text-gray-700 border border-gray-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                                        Draft
                                    </span>
                                @endif
                            </td>

                            {{-- Tombol Aksi --}}
                            <td class="py-3 px-4 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <a href="{{ route('gudang.transfer.show', $tf) }}"
                                       class="px-2.5 py-1 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm"
                                       title="Lihat Rincian">
                                        Detail
                                    </a>

                                    @if ($tf->status === 'Draft')
                                        <a href="{{ route('gudang.transfer.edit', $tf) }}"
                                           class="px-2.5 py-1 text-xs font-semibold text-[#15803d] bg-emerald-50 border border-emerald-200 rounded-xl hover:bg-emerald-100 transition"
                                           title="Ubah Draft">
                                            Edit
                                        </a>
                                    @endif

                                    @if (in_array($tf->status, ['Approved', 'completed']))
                                        <a href="{{ route('gudang.transfer.pdf', $tf) }}"
                                           target="_blank"
                                           class="p-1 text-gray-500 hover:text-[#15803d] transition"
                                           title="Cetak Surat Jalan Transfer (PDF)">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path d="M17 17h2a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h2m2 4h6a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2zm8-12V5a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v4h10z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                                            </svg>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-gray-400">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path d="M7 7h13l-3-3M17 17H4l3 3" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                    </svg>
                                    <span class="font-medium text-gray-500">Tidak ada dokumen transfer stok yang sesuai kriteria</span>
                                    <span class="text-xs text-gray-400">Silakan sesuaikan filter pencarian atau buat transfer stok baru</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($transfers->hasPages())
            <div class="p-4 border-t border-gray-100 bg-gray-50/50">
                {{ $transfers->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
