@extends('layouts.app')

@section('title', 'Stock Opname & Penyesuaian Fisik – ERP Duta Tunggal')

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
                <span class="text-[#15803d] font-bold">Stock Opname</span>
            </nav>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Stock Opname & Penyesuaian Persediaan</h1>
            <p class="text-xs text-gray-500 mt-1">
                Proses audit fisik stok berkala per gudang, pencatatan hasil hitung fisik, deteksi selisih kuantitas, dan penyesuaian otomatis ke buku besar.
            </p>
        </div>
        <div class="flex items-center gap-2.5">
            <a href="{{ route('gudang.stok.index') }}"
               class="inline-flex items-center gap-1.5 px-3.5 py-2.5 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M21 8 12 3 3 8v8l9 5 9-5z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                    <path d="M3 8l9 5 9-5M12 13v8" stroke-width="1.8" stroke-linecap="round"></path>
                </svg>
                <span>Cek Stok Sistem</span>
            </a>
            <a href="{{ route('gudang.opname.create') }}"
               class="inline-flex items-center gap-1.5 px-4 py-2.5 text-xs sm:text-sm font-semibold text-white bg-[#15803d] hover:bg-[#166534] rounded-xl transition shadow-sm active:scale-[0.98]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M12 4v16m8-8H4" stroke-width="2" stroke-linecap="round"></path>
                </svg>
                <span>Sesi Opname Baru</span>
            </a>
        </div>
    </div>


    {{-- KPI Stat Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Sesi Opname --}}
        <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm">
            <div class="flex items-center justify-between text-gray-500 mb-2">
                <span class="text-xs font-medium uppercase tracking-wider">Total Sesi Opname</span>
                <div class="w-8 h-8 rounded-lg bg-[#f0fdf4] text-[#15803d] flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2" stroke-width="1.8"></path>
                        <rect x="9" y="3" width="6" height="4" rx="1" stroke-width="1.8"></rect>
                        <path d="M9 12h6M9 16h4" stroke-width="1.8" stroke-linecap="round"></path>
                    </svg>
                </div>
            </div>
            <div class="text-2xl font-bold text-gray-900 tracking-tight">
                {{ number_format($kpi['total'], 0, ',', '.') }}
            </div>
            <p class="text-[11px] text-gray-500 mt-1">Seluruh sesi audit fisik stok</p>
        </div>

        {{-- Sedang Dihitung --}}
        <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm">
            <div class="flex items-center justify-between text-gray-500 mb-2">
                <span class="text-xs font-medium uppercase tracking-wider">Sedang Dihitung</span>
                <div class="w-8 h-8 rounded-lg bg-sky-50 text-sky-700 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="1.8" stroke-linecap="round"></path>
                    </svg>
                </div>
            </div>
            <div class="text-2xl font-bold text-sky-600 tracking-tight">
                {{ number_format($kpi['in_progress'], 0, ',', '.') }}
            </div>
            <p class="text-[11px] text-gray-500 mt-1">Petugas sedang input hasil hitung</p>
        </div>

        {{-- Menunggu Approval (Completed) --}}
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
                {{ number_format($kpi['completed'], 0, ',', '.') }}
            </div>
            <p class="text-[11px] text-gray-500 mt-1">Selesai hitung, siap disetujui</p>
        </div>

        {{-- Disetujui / Selesai --}}
        <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm">
            <div class="flex items-center justify-between text-gray-500 mb-2">
                <span class="text-xs font-medium uppercase tracking-wider">Disetujui & Disesuaikan</span>
                <div class="w-8 h-8 rounded-lg bg-[#f0fdf4] text-[#15803d] flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </div>
            </div>
            <div class="text-2xl font-bold text-[#15803d] tracking-tight">
                {{ number_format($kpi['approved'], 0, ',', '.') }}
            </div>
            <p class="text-[11px] text-gray-500 mt-1">Stok & Jurnal GL telah disesuaikan</p>
        </div>
    </div>

    {{-- Filter & Pencarian Bar --}}
    <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm">
        <form action="{{ route('gudang.opname.index') }}" method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-3">
            {{-- Search Box --}}
            <div class="md:col-span-4">
                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Cari No. Opname / Catatan</label>
                <div class="relative">
                    <input type="text"
                           name="search"
                           value="{{ $search }}"
                           placeholder="OPN-..., catatan sesi..."
                           class="w-full pl-9 pr-3 py-2 text-xs rounded-xl border border-gray-200 focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] text-gray-800">
                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2" stroke-linecap="round"></path>
                    </svg>
                </div>
            </div>

            {{-- Filter Gudang --}}
            <div class="md:col-span-3">
                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Gudang</label>
                <x-searchable-select name="warehouse_id" :options="collect($warehouses)->map(fn ($wh) => ['id' => $wh->id, 'label' => $wh->name . ' (' . ($wh->kode ?? 'WH') . ')'])->values()" :value="$warehouseId" placeholder="Semua Lokasi Gudang" size="sm" :auto-submit="true" />
            </div>

            {{-- Filter Status --}}
            <div class="md:col-span-3">
                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Status Opname</label>
                <select name="status"
                        class="w-full px-2.5 py-2 text-xs rounded-xl border border-gray-200 focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] text-gray-800">
                    <option value="all" {{ $status === 'all' ? 'selected' : '' }}>Semua Status</option>
                    <option value="draft" {{ $status === 'draft' ? 'selected' : '' }}>Draft (Persiapan)</option>
                    <option value="in_progress" {{ $status === 'in_progress' ? 'selected' : '' }}>Sedang Berlangsung (In Progress)</option>
                    <option value="completed" {{ $status === 'completed' ? 'selected' : '' }}>Selesai Hitung (Completed)</option>
                    <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>Disetujui & Disesuaikan (Approved)</option>
                </select>
            </div>

            {{-- Action Buttons --}}
            <div class="md:col-span-2 flex items-end gap-2">
                <button type="submit"
                        class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-gray-900 rounded-xl hover:bg-black transition shadow-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2" stroke-linecap="round"></path>
                    </svg>
                    <span>Filter</span>
                </button>
                @if ($search || $warehouseId || $status !== 'all' || $startDate || $endDate)
                    <a href="{{ route('gudang.opname.index') }}"
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

    {{-- Tabel Sesi Opname --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-gray-50/80 border-b border-gray-100 text-gray-500 uppercase tracking-wider text-[11px] font-semibold">
                        <th class="py-3 px-4">No. Sesi Opname</th>
                        <th class="py-3 px-4">Tanggal Opname</th>
                        <th class="py-3 px-4">Gudang Audit</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-center">Progres Hitung</th>
                        <th class="py-3 px-4 text-right">Selisih Unit</th>
                        <th class="py-3 px-4 text-right">Nilai Selisih</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    @forelse ($opnames as $op)
                        @php
                            $totalItems = $op->items->count();
                            $countedItems = $op->items->whereNotNull('physical_qty')->count();
                            $diffQty = $op->items->whereNotNull('physical_qty')->sum('difference_qty');
                            $diffValue = $op->items->whereNotNull('physical_qty')->sum('difference_value');
                        @endphp
                        <tr class="hover:bg-gray-50/80 transition">
                            {{-- No. Dokumen --}}
                            <td class="py-3 px-4 font-mono font-bold text-[#15803d]">
                                <a href="{{ route('gudang.opname.show', $op) }}" class="hover:underline">
                                    {{ $op->opname_number }}
                                </a>
                                @if ($op->notes)
                                    <span class="text-[10px] text-gray-400 block font-normal truncate max-w-xs">{{ $op->notes }}</span>
                                @endif
                            </td>

                            {{-- Tanggal --}}
                            <td class="py-3 px-4 text-gray-600">
                                {{ \Carbon\Carbon::parse($op->opname_date)->translatedFormat('d M Y') }}
                            </td>

                            {{-- Gudang --}}
                            <td class="py-3 px-4 font-medium text-gray-900">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-[#15803d]"></span>
                                    <span>{{ $op->warehouse?->name ?? '-' }}</span>
                                </div>
                            </td>

                            {{-- Status Badge --}}
                            <td class="py-3 px-4 text-center">
                                @if ($op->status === 'approved')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-[#f0fdf4] text-[#15803d] border border-[#86efac]">
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#15803d]"></span>
                                        Disetujui
                                    </span>
                                @elseif ($op->status === 'completed')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                        Selesai Hitung
                                    </span>
                                @elseif ($op->status === 'in_progress')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-sky-50 text-sky-700 border border-sky-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-sky-500 animate-pulse"></span>
                                        Sedang Dihitung
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-gray-50 text-gray-700 border border-gray-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                                        Draft
                                    </span>
                                @endif
                            </td>

                            {{-- Progres Hitung --}}
                            <td class="py-3 px-4 text-center font-medium">
                                @if ($totalItems > 0)
                                    <span>{{ $countedItems }} / {{ $totalItems }}</span>
                                    <span class="text-[10px] text-gray-400 block font-mono">({{ round(($countedItems / $totalItems) * 100) }}%)</span>
                                @else
                                    <span class="text-gray-400 text-xs">Belum Dimulai</span>
                                @endif
                            </td>

                            {{-- Selisih Unit --}}
                            <td class="py-3 px-4 text-right font-mono font-bold">
                                @if ($countedItems > 0)
                                    @if ($diffQty > 0)
                                        <span class="text-[#15803d]">+{{ number_format($diffQty, 0, ',', '.') }}</span>
                                    @elseif ($diffQty < 0)
                                        <span class="text-rose-600">{{ number_format($diffQty, 0, ',', '.') }}</span>
                                    @else
                                        <span class="text-gray-500">0</span>
                                    @endif
                                @else
                                    <span class="text-gray-300">-</span>
                                @endif
                            </td>

                            {{-- Nilai Selisih --}}
                            <td class="py-3 px-4 text-right font-mono font-bold">
                                @if ($countedItems > 0)
                                    @if ($diffValue > 0)
                                        <span class="text-[#15803d]">+Rp {{ number_format($diffValue, 0, ',', '.') }}</span>
                                    @elseif ($diffValue < 0)
                                        <span class="text-rose-600">-Rp {{ number_format(abs($diffValue), 0, ',', '.') }}</span>
                                    @else
                                        <span class="text-gray-500">Rp 0</span>
                                    @endif
                                @else
                                    <span class="text-gray-300">-</span>
                                @endif
                            </td>

                            {{-- Tombol Aksi --}}
                            <td class="py-3 px-4 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <a href="{{ route('gudang.opname.show', $op) }}"
                                       class="px-2.5 py-1 text-xs font-semibold text-[#15803d] bg-emerald-50 border border-emerald-200 rounded-xl hover:bg-emerald-100 transition shadow-sm">
                                        Workspace
                                    </a>

                                    <a href="{{ route('gudang.opname.pdf', $op) }}"
                                       target="_blank"
                                       class="p-1 text-gray-500 hover:text-[#15803d] transition"
                                       title="Cetak Berita Acara / Lembar Kerja (PDF)">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path d="M17 17h2a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h2m2 4h6a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2zm8-12V5a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v4h10z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-gray-400">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2" stroke-width="1.5"></path>
                                    </svg>
                                    <span class="font-medium text-gray-500">Tidak ada sesi stock opname yang sesuai kriteria</span>
                                    <span class="text-xs text-gray-400">Silakan sesuaikan filter atau buka sesi stock opname baru</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($opnames->hasPages())
            <div class="p-4 border-t border-gray-100 bg-gray-50/50">
                {{ $opnames->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
