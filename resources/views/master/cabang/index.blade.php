@extends('layouts.app')

@section('title', 'Master Cabang – Duta Tunggal ERP')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto" x-data="{
    deleteModalOpen: false,
    deleteAction: '',
    deleteName: '',
    openDelete(action, name) {
        this.deleteAction = action;
        this.deleteName = name;
        this.deleteModalOpen = true;
    }
}">
    {{-- Header & Action Button --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <span>Master Data</span>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Cabang</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Daftar Kantor Cabang</h1>
            <p class="text-sm text-gray-500 mt-0.5">Kelola cabang operasional, penomoran faktur cabang, kontak, dan gudang terhubung.</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('master.cabang.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-xs sm:text-sm font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Tambah Cabang Baru
            </a>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Cabang</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($kpi['total_cabang'] ?? 0) }}</p>
                </div>
                <div class="p-2.5 bg-blue-50 text-blue-600 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Cabang Aktif</p>
                    <p class="text-2xl font-bold text-emerald-600 mt-1">{{ number_format($kpi['active_cabang'] ?? 0) }}</p>
                </div>
                <div class="p-2.5 bg-emerald-50 text-emerald-600 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Cabang Non-Aktif</p>
                    <p class="text-2xl font-bold text-gray-500 mt-1">{{ number_format($kpi['inactive_cabang'] ?? 0) }}</p>
                </div>
                <div class="p-2.5 bg-gray-50 text-gray-600 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Gudang Terhubung</p>
                    <p class="text-2xl font-bold text-indigo-600 mt-1">{{ number_format($kpi['total_warehouses'] ?? 0) }}</p>
                </div>
                <div class="p-2.5 bg-indigo-50 text-indigo-600 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter & Search Bar --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm">
        <form method="GET" action="{{ route('master.cabang.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            {{-- Search Input --}}
            <div class="lg:col-span-2">
                <label for="search" class="block text-xs font-medium text-gray-600 mb-1">Cari Kode, Nama, Alamat, atau Telepon</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input
                        type="text"
                        name="search"
                        id="search"
                        value="{{ $search }}"
                        placeholder="Contoh: CB-001, Cabang Surabaya, 021..."
                        class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#15803d] focus:border-transparent outline-none transition"
                    />
                </div>
            </div>

            {{-- Filter Status & Submit --}}
            <div>
                <label for="status" class="block text-xs font-medium text-gray-600 mb-1">Status Cabang</label>
                <select
                    name="status"
                    id="status"
                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#15803d] focus:border-transparent outline-none transition"
                >
                    <option value="all">Semua Status</option>
                    <option value="1" {{ $status === '1' ? 'selected' : '' }}>Aktif Saja</option>
                    <option value="0" {{ $status === '0' ? 'selected' : '' }}>Non-Aktif Saja</option>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button
                    type="submit"
                    class="w-full px-4 py-2 bg-gray-900 hover:bg-gray-800 text-white text-sm font-semibold rounded-xl shadow-sm transition"
                >
                    Terapkan Filter
                </button>
                @if($search !== '' || $status !== 'all')
                    <a
                        href="{{ route('master.cabang.index') }}"
                        class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-xl transition"
                        title="Reset Filter"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </a>
                @endif
            </div>
        </form>
    </div>


    {{-- Cabang Table Card --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-600">
                <thead class="bg-gray-50/80 border-b border-gray-200 text-xs uppercase font-bold text-gray-500 tracking-wider">
                    <tr>
                        <th class="py-3.5 px-4 w-32">Kode</th>
                        <th class="py-3.5 px-4">Nama Cabang</th>
                        <th class="py-3.5 px-4">Alamat & Kontak</th>
                        <th class="py-3.5 px-4 text-center">Gudang Terhubung</th>
                        <th class="py-3.5 px-4 text-center">Kenaikan Harga</th>
                        <th class="py-3.5 px-4 text-center">Status</th>
                        <th class="py-3.5 px-4 text-center w-28">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 font-normal">
                    @forelse($cabangs as $c)
                        <tr class="hover:bg-gray-50/80 transition">
                            {{-- Kode Cabang --}}
                            <td class="py-3 px-4 font-mono font-bold text-gray-900">
                                {{ $c->kode }}
                            </td>

                            {{-- Nama Cabang --}}
                            <td class="py-3 px-4">
                                <div class="font-bold text-gray-900">{{ $c->nama }}</div>
                                @if($c->nama_legal)
                                    <div class="text-xs text-gray-400 mt-0.5">Legal: {{ $c->nama_legal }}</div>
                                @endif
                            </td>

                            {{-- Alamat & Kontak --}}
                            <td class="py-3 px-4">
                                <p class="text-gray-700 line-clamp-1">{{ $c->alamat ?: '-' }}</p>
                                <p class="text-xs text-gray-400 mt-0.5 font-mono">{{ $c->telepon ?: '-' }}</p>
                            </td>

                            {{-- Gudang Terhubung --}}
                            <td class="py-3 px-4 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                    {{ $c->warehouse_count }} Gudang
                                </span>
                            </td>

                            {{-- Kenaikan Harga --}}
                            <td class="py-3 px-4 text-center font-mono">
                                {{ $c->kenaikan_harga ? '+' . $c->kenaikan_harga . '%' : '0%' }}
                            </td>

                            {{-- Status Aktif --}}
                            <td class="py-3 px-4 text-center">
                                @if($c->status)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                        Aktif
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">
                                        Non-Aktif
                                    </span>
                                @endif
                            </td>

                            {{-- Aksi --}}
                            <td class="py-3 px-4 text-center">
                                <div class="inline-flex items-center gap-1">
                                    <a
                                        href="{{ route('master.cabang.edit', $c) }}"
                                        class="p-1.5 text-gray-600 hover:text-[#15803d] hover:bg-emerald-50 rounded-lg transition"
                                        title="Edit Cabang"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                    <button
                                        type="button"
                                        @click="openDelete('{{ route('master.cabang.destroy', $c) }}', '{{ addslashes($c->nama) }} ({{ $c->kode }})')"
                                        class="p-1.5 text-gray-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition"
                                        title="Hapus Cabang"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-12 text-gray-400">
                                <svg class="w-12 h-12 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                                <p class="text-sm font-medium text-gray-600">Tidak ada cabang ditemukan</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($cabangs->hasPages())
            <div class="p-4 border-t border-gray-100">
                {{ $cabangs->links() }}
            </div>
        @endif
    </div>

    {{-- Modal Konfirmasi Hapus --}}
    <div
        x-show="deleteModalOpen"
        class="fixed inset-0 z-50 overflow-y-auto"
        x-cloak
        style="display: none;"
    >
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-gray-900/60" @click="deleteModalOpen = false"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
            <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-2xl shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form :action="deleteAction" method="POST">
                    @csrf
                    @method('DELETE')
                    <div class="p-6">
                        <div class="flex items-center gap-4">
                            <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 bg-rose-100 rounded-full text-rose-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">Hapus Cabang</h3>
                                <p class="text-sm text-gray-500 mt-1">
                                    Apakah Anda yakin ingin menghapus cabang <strong x-text="deleteName" class="text-gray-900"></strong>? Tindakan ini tidak dapat dibatalkan jika cabang memiliki gudang atau transaksi aktif.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-4 bg-gray-50 flex items-center justify-end gap-2 border-t border-gray-100">
                        <button
                            type="button"
                            @click="deleteModalOpen = false"
                            class="px-4 py-2 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 transition"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="px-4 py-2 text-sm font-semibold text-white bg-rose-600 rounded-xl hover:bg-rose-700 transition"
                        >
                            Ya, Hapus Cabang
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
