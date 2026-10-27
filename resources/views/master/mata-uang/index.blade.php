@extends('layouts.app')

@section('title', 'Master Mata Uang & Kurs – Duta Tunggal ERP')

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
                <span>Keuangan & Master</span>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Mata Uang & Kurs</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Daftar Mata Uang & Kurs</h1>
            <p class="text-sm text-gray-500 mt-0.5">Kelola mata uang transaksi, kode valuta internasional, simbol, dan nilai tukar konversi ke Rupiah.</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('master.pajak.index') }}"
                class="px-4 py-2.5 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs sm:text-sm font-semibold rounded-xl shadow-sm transition"
            >
                Pengaturan Pajak
            </a>
            <a
                href="{{ route('master.mata-uang.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-xs sm:text-sm font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                <span>Tambah Mata Uang</span>
            </a>
        </div>
    </div>


    {{-- KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Total Mata Uang</p>
                <h3 class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($kpi['total_currencies'] ?? 0) }}</h3>
            </div>
            <div class="w-10 h-10 rounded-xl bg-slate-50 text-slate-600 flex items-center justify-center font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-emerald-600 uppercase tracking-wider">Valuta Asing (Foreign)</p>
                <h3 class="text-2xl font-bold text-emerald-700 mt-1">{{ number_format($kpi['foreign_currencies'] ?? 0) }} Valuta</h3>
            </div>
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
    </div>

    {{-- Filter Toolbar & Search --}}
    <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
        <form method="GET" action="{{ route('master.mata-uang.index') }}" class="flex flex-col sm:flex-row items-center gap-3">
            <div class="flex-1 relative w-full">
                <input
                    type="text"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Cari kode valuta (IDR, USD, SGD) atau nama mata uang..."
                    class="w-full pl-9 pr-4 py-2 text-xs sm:text-sm border border-gray-200 rounded-xl focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] outline-none transition"
                >
                <svg class="w-4 h-4 text-gray-400 absolute left-3 top-3 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <button
                    type="submit"
                    class="px-5 py-2 bg-gray-900 hover:bg-black text-white text-xs font-semibold rounded-xl transition text-center w-full sm:w-auto"
                >
                    Cari
                </button>
                @if($search !== '')
                    <a
                        href="{{ route('master.mata-uang.index') }}"
                        class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 text-xs font-semibold rounded-xl transition text-center"
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
                        <th class="py-3 px-4">Kode Valuta</th>
                        <th class="py-3 px-4">Nama Mata Uang</th>
                        <th class="py-3 px-4 text-center">Simbol</th>
                        <th class="py-3 px-4 text-right">Nilai Kurs ke IDR</th>
                        <th class="py-3 px-4 text-center">Status Mata Uang</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    @forelse($currencies as $cur)
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-3.5 px-4 font-mono font-bold text-gray-900">
                                <span class="px-2.5 py-1 bg-gray-100 text-gray-800 rounded-md text-xs font-bold">
                                    {{ $cur->code }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 font-bold text-gray-900">
                                {{ $cur->name }}
                            </td>
                            <td class="py-3.5 px-4 text-center font-bold text-gray-700">
                                {{ $cur->symbol }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono font-bold text-gray-900">
                                Rp {{ number_format($cur->to_rupiah, 2, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                @if(strtoupper($cur->code) === 'IDR')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        Mata Uang Dasar (Base)
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                        Valuta Asing
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a
                                        href="{{ route('master.mata-uang.edit', $cur) }}"
                                        class="p-1.5 text-gray-500 hover:text-[#15803d] hover:bg-emerald-50 rounded-lg transition"
                                        title="Edit Kurs"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </a>
                                    @if(strtoupper($cur->code) !== 'IDR')
                                        <button
                                            type="button"
                                            @click="openDelete('{{ route('master.mata-uang.destroy', $cur) }}', '{{ $cur->name }} ({{ $cur->code }})')"
                                            class="p-1.5 text-gray-500 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition"
                                            title="Hapus Mata Uang"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-gray-400">
                                <div class="flex flex-col items-center justify-center">
                                    <svg class="w-10 h-10 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <p class="font-medium text-xs">Belum ada mata uang yang terdaftar.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($currencies->hasPages())
            <div class="px-4 py-3 border-t border-gray-100 bg-gray-50/50">
                {{ $currencies->links() }}
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
                        <h3 class="text-base font-bold text-gray-900">Konfirmasi Hapus Mata Uang</h3>
                        <p class="text-xs text-gray-500">Tindakan ini tidak dapat dibatalkan jika disetujui.</p>
                    </div>
                </div>

                <p class="text-sm text-gray-600">
                    Apakah Anda yakin ingin menghapus valuta <span class="font-bold text-gray-900" x-text="deleteName"></span>?
                </p>

                <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-amber-800 text-xs flex gap-2">
                    <svg class="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>Sistem akan memblokir penghapusan jika valuta masih memiliki catatan transaksi invoice, PO, atau SO aktif.</span>
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
                            Hapus Mata Uang
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
