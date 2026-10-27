@extends('layouts.app')

@section('title', 'Master Armada Kendaraan – Duta Tunggal ERP')

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
                <span class="text-[#15803d] font-bold">Armada Pengiriman</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Daftar Kendaraan Pengiriman</h1>
            <p class="text-sm text-gray-500 mt-0.5">Kelola armada kendaraan operasional, nomor plat, kapasitas muat, dan penempatan cabang.</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('master.driver.index') }}"
                class="px-4 py-2.5 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs sm:text-sm font-semibold rounded-xl shadow-sm transition"
            >
                Kelola Supir / Driver
            </a>
            <a
                href="{{ route('master.kendaraan.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-xs sm:text-sm font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                <span>Tambah Kendaraan</span>
            </a>
        </div>
    </div>


    {{-- KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Total Kendaraan</p>
                <h3 class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($kpi['total_vehicles'] ?? 0) }}</h3>
            </div>
            <div class="w-10 h-10 rounded-xl bg-slate-50 text-slate-600 flex items-center justify-center font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7h8m-8 4h8m-8 4h4M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z"></path>
                </svg>
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-emerald-600 uppercase tracking-wider">Pernah Kirim (DO)</p>
                <h3 class="text-2xl font-bold text-emerald-700 mt-1">{{ number_format($kpi['vehicles_with_deliveries'] ?? 0) }}</h3>
            </div>
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-blue-600 uppercase tracking-wider">Total Driver / Supir</p>
                <h3 class="text-2xl font-bold text-blue-700 mt-1">{{ number_format($kpi['total_drivers'] ?? 0) }}</h3>
            </div>
            <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
            </div>
        </div>
    </div>

    {{-- Filter Toolbar & Search --}}
    <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
        <form method="GET" action="{{ route('master.kendaraan.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="lg:col-span-2 relative">
                <input
                    type="text"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Cari plat nomor, tipe armada, atau kapasitas muat..."
                    class="w-full pl-9 pr-4 py-2 text-xs sm:text-sm border border-gray-200 rounded-xl focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] outline-none transition"
                >
                <svg class="w-4 h-4 text-gray-400 absolute left-3 top-3 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>

            {{-- Filter Cabang (Data Dinamis) --}}
            <div>
                <x-searchable-select
                    name="cabang_id"
                    :options="collect($cabangs)->map(fn ($cb) => ['id' => $cb->id, 'label' => $cb->nama . ' (' . $cb->kode . ')'])->values()"
                    :value="$cabangId === 'all' ? null : $cabangId"
                    placeholder="Semua Cabang"
                    size="sm"
                    :auto-submit="true"
                />
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="submit"
                    class="flex-1 py-2 bg-gray-900 hover:bg-black text-white text-xs font-semibold rounded-xl transition text-center"
                >
                    Filter
                </button>
                @if($search !== '' || $cabangId !== 'all')
                    <a
                        href="{{ route('master.kendaraan.index') }}"
                        class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 text-xs font-semibold rounded-xl transition"
                    >
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Main Table --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs sm:text-sm">
                <thead>
                    <tr class="bg-gray-50/75 border-b border-gray-100 text-gray-500 font-semibold uppercase text-[11px] tracking-wider">
                        <th class="py-3 px-4">Plat Nomor</th>
                        <th class="py-3 px-4">Tipe Armada</th>
                        <th class="py-3 px-4">Kapasitas Muat</th>
                        <th class="py-3 px-4">Cabang</th>
                        <th class="py-3 px-4 text-center">Riwayat DO</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    @forelse($vehicles as $veh)
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-3.5 px-4 font-mono font-bold text-gray-900">
                                <span class="px-2.5 py-1 bg-gray-900 text-white rounded-md text-xs tracking-wider">
                                    {{ $veh->plate }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 font-bold text-gray-900">
                                {{ $veh->type }}
                            </td>
                            <td class="py-3.5 px-4 text-gray-600">
                                {{ $veh->capacity }}
                            </td>
                            <td class="py-3.5 px-4">
                                @if($veh->cabang && $veh->cabang->nama)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-gray-100 text-gray-700">
                                        {{ $veh->cabang->nama }}
                                    </span>
                                @else
                                    <span class="text-gray-400 italic">Pusat</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium {{ $veh->delivery_order_count > 0 ? 'bg-blue-50 text-blue-700' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $veh->delivery_order_count }} Pengiriman
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a
                                        href="{{ route('master.kendaraan.edit', $veh) }}"
                                        class="p-1.5 text-gray-500 hover:text-[#15803d] hover:bg-emerald-50 rounded-lg transition"
                                        title="Edit Kendaraan"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </a>
                                    <button
                                        type="button"
                                        @click="openDelete('{{ route('master.kendaraan.destroy', $veh) }}', '{{ $veh->plate }} ({{ $veh->type }})')"
                                        class="p-1.5 text-gray-500 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition"
                                        title="Hapus Kendaraan"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-gray-400">
                                <div class="flex flex-col items-center justify-center">
                                    <svg class="w-10 h-10 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7h8m-8 4h8m-8 4h4M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z"></path>
                                    </svg>
                                    <p class="font-medium text-xs">Belum ada armada kendaraan yang terdaftar.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($vehicles->hasPages())
            <div class="px-4 py-3 border-t border-gray-100 bg-gray-50/50">
                {{ $vehicles->links() }}
            </div>
        @endif
    </div>

    {{-- Delete Modal --}}
    <div
        x-show="deleteModalOpen"
        class="fixed inset-0 z-50 overflow-y-auto"
        style="display: none;"
    >
        <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity" @click="deleteModalOpen = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-md bg-white rounded-2xl shadow-xl overflow-hidden p-6 space-y-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-900">Konfirmasi Hapus Kendaraan</h3>
                        <p class="text-xs text-gray-500">Tindakan ini tidak dapat dibatalkan jika disetujui.</p>
                    </div>
                </div>

                <p class="text-sm text-gray-600">
                    Apakah Anda yakin ingin menghapus armada <span class="font-bold text-gray-900" x-text="deleteName"></span>?
                </p>

                <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-amber-800 text-xs flex gap-2">
                    <svg class="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>Sistem akan memblokir penghapusan jika kendaraan memiliki riwayat surat jalan aktif atau jadwal pengiriman.</span>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button
                        type="button"
                        @click="deleteModalOpen = false"
                        class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-xl transition"
                    >
                        Batal
                    </button>
                    <form :action="deleteAction" method="POST">
                        @csrf
                        @method('DELETE')
                        <button
                            type="submit"
                            class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-xl shadow-sm transition"
                        >
                            Hapus Kendaraan
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
