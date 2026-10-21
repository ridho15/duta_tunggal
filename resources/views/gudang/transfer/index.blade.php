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
                <span class="text-emerald-700 font-bold">Pindah Stok</span>
            </nav>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Pindah Stok Antar Gudang</h1>
            <p class="text-xs text-gray-500 mt-1">
                Kelola distribusi dan pergerakan fisik barang antar lokasi gudang, persetujuan transfer, dan bukti surat jalan pindah stok.
            </p>
        </div>
        <div class="flex items-center gap-2.5">
            <a href="{{ route('gudang.stok.index') }}"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition shadow-2xs">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M21 8 12 3 3 8v8l9 5 9-5z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                    <path d="M3 8l9 5 9-5M12 13v8" stroke-width="1.8" stroke-linecap="round"></path>
                </svg>
                <span>Cek Saldo Stok</span>
            </a>
            <a href="{{ route('gudang.transfer.create') }}"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition shadow-2xs">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M12 4v16m8-8H4" stroke-width="2" stroke-linecap="round"></path>
                </svg>
                <span>Transfer Stok Baru</span>
            </a>
        </div>
    </div>

    {{-- Alert Notifikasi --}}
    @if (session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700">✕</button>
        </div>
    @endif
    @if (session('error'))
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="9" stroke-width="2"></circle>
                    <path d="M12 8v4m0 4h.01" stroke-width="2" stroke-linecap="round"></path>
                </svg>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-700">✕</button>
        </div>
    @endif

    {{-- KPI Stat Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Transfer --}}
        <div class="bg-white rounded-xl p-4.5 border border-gray-200 shadow-2xs">
            <div class="flex items-center justify-between text-gray-500 mb-2">
                <span class="text-xs font-medium uppercase tracking-wider">Total Dokumen</span>
                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center">
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
        <div class="bg-white rounded-xl p-4.5 border border-gray-200 shadow-2xs">
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
        <div class="bg-white rounded-xl p-4.5 border border-gray-200 shadow-2xs">
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
        <div class="bg-white rounded-xl p-4.5 border border-gray-200 shadow-2xs">
            <div class="flex items-center justify-between text-gray-500 mb-2">
                <span class="text-xs font-medium uppercase tracking-wider">Disetujui / Selesai</span>
                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </div>
            </div>
            <div class="text-2xl font-bold text-emerald-700 tracking-tight">
                {{ number_format($kpi['approved'], 0, ',', '.') }}
            </div>
            <p class="text-[11px] text-gray-500 mt-1">Stok telah terpotong & terpindah</p>
        </div>
    </div>

    {{-- Filter & Pencarian Bar --}}
    <div class="bg-white rounded-xl p-4 border border-gray-200 shadow-2xs">
        <form action="{{ route('gudang.transfer.index') }}" method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-3">
            {{-- Search Box --}}
            <div class="md:col-span-3">
                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Cari No. Transfer / Item</label>
                <div class="relative">
                    <input type="text"
                           name="search"
                           value="{{ $search }}"
                           placeholder="No transfer, nama barang..."
                           class="w-full pl-9 pr-3 py-2 text-xs rounded-lg border border-gray-300 focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 text-gray-800">
                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2" stroke-linecap="round"></path>
                    </svg>
                </div>
            </div>

            {{-- Filter Gudang Asal --}}
            <div class="md:col-span-2">
                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Gudang Asal</label>
                <select name="from_warehouse_id"
                        class="w-full px-2.5 py-2 text-xs rounded-lg border border-gray-300 focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 text-gray-800">
                    <option value="">Semua Gudang Asal</option>
                    @foreach ($warehouses as $wh)
                        <option value="{{ $wh->id }}" {{ $fromWarehouseId == $wh->id ? 'selected' : '' }}>
                            {{ $wh->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Filter Gudang Tujuan --}}
            <div class="md:col-span-2">
                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Gudang Tujuan</label>
                <select name="to_warehouse_id"
                        class="w-full px-2.5 py-2 text-xs rounded-lg border border-gray-300 focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 text-gray-800">
                    <option value="">Semua Gudang Tujuan</option>
                    @foreach ($warehouses as $wh)
                        <option value="{{ $wh->id }}" {{ $toWarehouseId == $wh->id ? 'selected' : '' }}>
                            {{ $wh->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Filter Status --}}
            <div class="md:col-span-2">
                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Status</label>
                <select name="status"
                        class="w-full px-2.5 py-2 text-xs rounded-lg border border-gray-300 focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 text-gray-800">
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
                        class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition shadow-2xs">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2" stroke-linecap="round"></path>
                    </svg>
                    <span>Terapkan</span>
                </button>
                @if ($search || $fromWarehouseId || $toWarehouseId || $status !== 'all' || $startDate || $endDate)
                    <a href="{{ route('gudang.transfer.index') }}"
                       class="px-2.5 py-2 text-xs font-semibold text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition"
                       title="Reset Filter">
                        ✕
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Tabel Dokumen Transfer --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-2xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-gray-50/80 border-b border-gray-200 text-gray-500 uppercase tracking-wider text-[11px] font-semibold">
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
                <tbody class="divide-y divide-gray-200 text-gray-700">
                    @forelse ($transfers as $tf)
                        <tr class="hover:bg-gray-50/80 transition">
                            {{-- No. Dokumen --}}
                            <td class="py-3 px-4 font-mono font-bold text-emerald-800">
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
                                <svg class="w-4 h-4 mx-auto text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Disetujui
                                    </span>
                                @elseif (in_array($tf->status, ['Request', 'Pending']))
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                        Menunggu Approval
                                    </span>
                                @elseif (in_array($tf->status, ['Reject', 'cancelled']))
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                        Ditolak
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-700 border border-gray-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                                        Draft
                                    </span>
                                @endif
                            </td>

                            {{-- Tombol Aksi --}}
                            <td class="py-3 px-4 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <a href="{{ route('gudang.transfer.show', $tf) }}"
                                       class="px-2.5 py-1 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition shadow-2xs"
                                       title="Lihat Rincian">
                                        Detail
                                    </a>

                                    @if ($tf->status === 'Draft')
                                        <a href="{{ route('gudang.transfer.edit', $tf) }}"
                                           class="px-2.5 py-1 text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-md hover:bg-emerald-100 transition"
                                           title="Ubah Draft">
                                            Edit
                                        </a>
                                    @endif

                                    @if (in_array($tf->status, ['Approved', 'completed']))
                                        <a href="{{ route('gudang.transfer.pdf', $tf) }}"
                                           target="_blank"
                                           class="p-1 text-gray-500 hover:text-emerald-700 transition"
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
            <div class="p-4 border-t border-gray-200 bg-gray-50/50">
                {{ $transfers->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
