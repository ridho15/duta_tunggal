@extends('layouts.app')

@section('title', 'Detail Gudang & Rak – Duta Tunggal ERP')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto" x-data="{
    rakModalOpen: false,
    rakEditMode: false,
    rakFormAction: '{{ route('master.rak.store') }}',
    rakId: null,
    rakCode: '',
    rakName: '',
    rakDesc: '',

    deleteModalOpen: false,
    deleteAction: '',
    deleteName: '',

    openAddRak() {
        this.rakEditMode = false;
        this.rakFormAction = '{{ route('master.rak.store') }}';
        this.rakId = null;
        this.rakCode = '';
        this.rakName = '';
        this.rakDesc = '';
        this.rakModalOpen = true;
    },

    openEditRak(id, code, name, desc) {
        this.rakEditMode = true;
        this.rakFormAction = '/master/rak/' + id;
        this.rakId = id;
        this.rakCode = code;
        this.rakName = name;
        this.rakDesc = desc || '';
        this.rakModalOpen = true;
    },

    openDelete(action, name) {
        this.deleteAction = action;
        this.deleteName = name;
        this.deleteModalOpen = true;
    }
}">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('master.gudang.index') }}" class="hover:text-gray-700">Master Gudang</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Detail & Rak</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ $gudang->name }}</h1>
                <span class="px-2.5 py-1 text-xs font-mono font-bold bg-gray-100 text-gray-700 rounded-lg">
                    {{ $gudang->kode }}
                </span>
            </div>
            <p class="text-sm text-gray-500 mt-0.5">Kelola penataan rak penyimpanan dan informasi gudang cabang.</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('master.gudang.edit', $gudang) }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs sm:text-sm font-semibold rounded-xl shadow-sm transition"
            >
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                <span>Edit Gudang</span>
            </a>
            <a
                href="{{ route('master.gudang.index') }}"
                class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs sm:text-sm font-semibold rounded-xl transition"
            >
                Kembali
            </a>
        </div>
    </div>

    {{-- Feedback Messages --}}
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
    @endif

    @if(session('error'))
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-rose-500 hover:text-rose-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
    @endif

    {{-- Warehouse Summary Card --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Cabang Terhubung</p>
                <p class="text-base font-bold text-gray-900 mt-1">
                    {{ $gudang->cabang->nama ?? 'Pusat / Tanpa Cabang' }}
                </p>
                <p class="text-xs text-gray-500 font-mono">{{ $gudang->cabang->kode ?? '-' }}</p>
            </div>

            <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Lokasi / Alamat</p>
                <p class="text-sm font-medium text-gray-800 mt-1 leading-snug">
                    {{ $gudang->location }}
                </p>
            </div>

            <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Kontak & Tipe</p>
                <p class="text-sm font-medium text-gray-900 mt-1">
                    {{ $gudang->telepon ?: 'Tidak ada telepon' }}
                </p>
                <p class="text-xs text-gray-500 capitalize">
                    Tipe: {{ str_replace('_', ' ', $gudang->tipe ?: 'general') }}
                </p>
            </div>

            <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Status & Karantina</p>
                <div class="flex items-center gap-2 mt-1">
                    @if($gudang->status)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                            Aktif
                        </span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 border border-gray-200">
                            Nonaktif
                        </span>
                    @endif

                    @if($gudang->is_quarantine)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                            Gudang Karantina
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Rak Section --}}
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-bold text-gray-900">Daftar Rak / Slot Penyimpanan</h2>
                <p class="text-xs text-gray-500">Kelola nomor rak, lorong, atau tingkat penyimpanan inventaris di gudang ini.</p>
            </div>
            <button
                type="button"
                @click="openAddRak()"
                class="inline-flex items-center gap-2 px-4 py-2 bg-[#15803d] hover:bg-[#166534] text-white text-xs sm:text-sm font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                <span>Tambah Rak</span>
            </button>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs sm:text-sm">
                    <thead>
                        <tr class="bg-gray-50/75 border-b border-gray-100 text-gray-500 font-semibold uppercase text-[11px] tracking-wider">
                            <th class="py-3 px-4">Kode Rak</th>
                            <th class="py-3 px-4">Nama / Lorong Rak</th>
                            <th class="py-3 px-4">Deskripsi / Keterangan</th>
                            <th class="py-3 px-4 text-center">Item Tersimpan</th>
                            <th class="py-3 px-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700">
                        @forelse($gudang->rak as $rak)
                            <tr class="hover:bg-gray-50/60 transition">
                                <td class="py-3.5 px-4 font-mono font-bold text-gray-900">
                                    {{ $rak->code }}
                                </td>
                                <td class="py-3.5 px-4 font-semibold text-gray-800">
                                    {{ $rak->name }}
                                </td>
                                <td class="py-3.5 px-4 text-gray-500 max-w-xs truncate">
                                    {{ $rak->description ?: '-' }}
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-slate-100 text-slate-700">
                                        {{ $rak->inventory_stock_count ?? 0 }} Produk
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button
                                            type="button"
                                            @click="openEditRak({{ $rak->id }}, '{{ addslashes($rak->code) }}', '{{ addslashes($rak->name) }}', '{{ addslashes($rak->description) }}')"
                                            class="p-1.5 text-gray-500 hover:text-[#15803d] hover:bg-emerald-50 rounded-lg transition"
                                            title="Edit Rak"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </button>
                                        <button
                                            type="button"
                                            @click="openDelete('{{ route('master.rak.destroy', $rak) }}', '{{ $rak->name }} ({{ $rak->code }})')"
                                            class="p-1.5 text-gray-500 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition"
                                            title="Hapus Rak"
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
                                <td colspan="5" class="py-8 text-center text-gray-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <svg class="w-8 h-8 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                                        </svg>
                                        <p class="font-medium text-xs">Belum ada rak yang terdaftar di gudang ini.</p>
                                        <button
                                            type="button"
                                            @click="openAddRak()"
                                            class="mt-2 text-xs font-semibold text-[#15803d] hover:underline"
                                        >
                                            + Tambah Rak Pertama
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Rak Create / Edit Modal --}}
    <div
        x-show="rakModalOpen"
        class="fixed inset-0 z-50 overflow-y-auto"
        style="display: none;"
    >
        <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity" @click="rakModalOpen = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-lg bg-white rounded-2xl shadow-xl overflow-hidden p-6 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                    <h3 class="text-base font-bold text-gray-900" x-text="rakEditMode ? 'Edit Data Rak' : 'Tambah Rak Baru'"></h3>
                    <button type="button" @click="rakModalOpen = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <form :action="rakFormAction" method="POST" class="space-y-4">
                    @csrf
                    <template x-if="rakEditMode">
                        <input type="hidden" name="_method" value="PUT">
                    </template>
                    <input type="hidden" name="warehouse_id" value="{{ $gudang->id }}">

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                            Kode Rak <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="code"
                            x-model="rakCode"
                            required
                            placeholder="Contoh: RAK-A01"
                            class="w-full px-3 py-2 text-xs sm:text-sm font-mono border border-gray-200 rounded-xl focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] outline-none"
                        >
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                            Nama / Keterangan Rak <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="name"
                            x-model="rakName"
                            required
                            placeholder="Contoh: Lorong A Tingkat 1"
                            class="w-full px-3 py-2 text-xs sm:text-sm border border-gray-200 rounded-xl focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] outline-none"
                        >
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                            Deskripsi Tambahan
                        </label>
                        <textarea
                            name="description"
                            x-model="rakDesc"
                            rows="2"
                            placeholder="Catatan kapasitas beban, jenis barang prioritas, dll."
                            class="w-full px-3 py-2 text-xs sm:text-sm border border-gray-200 rounded-xl focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] outline-none"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                        <button
                            type="button"
                            @click="rakModalOpen = false"
                            class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-xl transition"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="px-5 py-2 bg-[#15803d] hover:bg-[#166534] text-white text-xs font-semibold rounded-xl shadow-sm transition"
                            x-text="rakEditMode ? 'Simpan Perubahan' : 'Tambah Rak'"
                        ></button>
                    </div>
                </form>
            </div>
        </div>
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
                        <h3 class="text-base font-bold text-gray-900">Konfirmasi Hapus Rak</h3>
                        <p class="text-xs text-gray-500">Tindakan ini tidak dapat dibatalkan jika disetujui.</p>
                    </div>
                </div>

                <p class="text-sm text-gray-600">
                    Apakah Anda yakin ingin menghapus rak <span class="font-bold text-gray-900" x-text="deleteName"></span>?
                </p>

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
                            Hapus Rak
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
