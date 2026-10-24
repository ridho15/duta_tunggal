@extends('layouts.app')

@section('title', 'Edit Gudang – Duta Tunggal ERP')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('master.gudang.index') }}" class="hover:text-gray-700">Master Gudang</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Edit Gudang</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Edit Gudang: {{ $gudang->name }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">Perbarui informasi cabang, lokasi fisik, zonasi, atau status operasional gudang.</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('master.gudang.show', $gudang) }}"
                class="px-4 py-2 bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs sm:text-sm font-semibold rounded-xl transition"
            >
                Kelola Rak
            </a>
            <a
                href="{{ route('master.gudang.index') }}"
                class="px-4 py-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs sm:text-sm font-semibold rounded-xl shadow-sm transition"
            >
                Kembali
            </a>
        </div>
    </div>

    {{-- Error Banner --}}
    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm shadow-sm space-y-1">
            <div class="flex items-center gap-2 font-bold">
                <svg class="w-5 h-5 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span>Mohon periksa kembali formulir input berikut:</span>
            </div>
            <ul class="list-disc list-inside text-xs space-y-0.5 pl-6 text-rose-700">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Form Card --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 sm:p-8">
        <form method="POST" action="{{ route('master.gudang.update', $gudang) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                {{-- Kode Gudang --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                        Kode Gudang <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="kode"
                        value="{{ old('kode', $gudang->kode) }}"
                        required
                        class="w-full px-4 py-2.5 text-xs sm:text-sm font-mono border rounded-xl focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] outline-none transition @error('kode') border-rose-300 bg-rose-50/30 @else border-gray-200 @enderror"
                    >
                    @error('kode')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Nama Gudang --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                        Nama Gudang <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="name"
                        value="{{ old('name', $gudang->name) }}"
                        required
                        class="w-full px-4 py-2.5 text-xs sm:text-sm border rounded-xl focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] outline-none transition @error('name') border-rose-300 bg-rose-50/30 @else border-gray-200 @enderror"
                    >
                    @error('name')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Kantor Cabang --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                        Cabang Operasional <span class="text-rose-500">*</span>
                    </label>
                    <select
                        name="cabang_id"
                        data-searchable
                        data-placeholder="-- Pilih Cabang --"
                        required
                        class="w-full px-4 py-2.5 text-xs sm:text-sm border rounded-xl focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] outline-none transition text-gray-700 @error('cabang_id') border-rose-300 bg-rose-50/30 @else border-gray-200 @enderror"
                    >
                        <option value="">-- Pilih Cabang --</option>
                        @foreach($cabangs as $cb)
                            <option value="{{ $cb->id }}" {{ old('cabang_id', $gudang->cabang_id) == $cb->id ? 'selected' : '' }}>
                                {{ $cb->nama }} ({{ $cb->kode }})
                            </option>
                        @endforeach
                    </select>
                    @error('cabang_id')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Tipe Gudang --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                        Tipe Kapasitas Gudang
                    </label>
                    <select
                        name="tipe"
                        class="w-full px-4 py-2.5 text-xs sm:text-sm border rounded-xl focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] outline-none transition text-gray-700 border-gray-200"
                    >
                        <option value="Kecil" {{ old('tipe', $gudang->tipe) === 'Kecil' ? 'selected' : '' }}>Gudang Kecil / Transit</option>
                        <option value="Besar" {{ old('tipe', $gudang->tipe) === 'Besar' ? 'selected' : '' }}>Gudang Besar / Induk</option>
                    </select>
                </div>

                {{-- Nomor Telepon --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                        Nomor Telepon
                    </label>
                    <input
                        type="text"
                        name="telepon"
                        value="{{ old('telepon', $gudang->telepon) }}"
                        class="w-full px-4 py-2.5 text-xs sm:text-sm border rounded-xl focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] outline-none transition border-gray-200"
                    >
                </div>

                {{-- Warna Identifikasi --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                        Warna Background Identitas
                    </label>
                    <div class="flex items-center gap-3">
                        <input
                            type="color"
                            name="warna_background"
                            value="{{ old('warna_background', $gudang->warna_background ?: '#e2e8f0') }}"
                            class="w-12 h-10 p-1 border border-gray-200 rounded-xl cursor-pointer"
                        >
                        <span class="text-xs text-gray-500">Pilih warna label gudang untuk visualisasi cepat.</span>
                    </div>
                </div>

                {{-- Lokasi / Alamat --}}
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                        Alamat / Lokasi Fisik <span class="text-rose-500">*</span>
                    </label>
                    <textarea
                        name="location"
                        rows="3"
                        required
                        class="w-full px-4 py-2.5 text-xs sm:text-sm border rounded-xl focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] outline-none transition @error('location') border-rose-300 bg-rose-50/30 @else border-gray-200 @enderror"
                    >{{ old('location', $gudang->location) }}</textarea>
                    @error('location')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Flags & Status --}}
            <div class="pt-4 border-t border-gray-100 grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- Status Aktif --}}
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl border border-gray-100">
                    <div>
                        <p class="text-xs font-bold text-gray-800">Status Aktif</p>
                        <p class="text-[11px] text-gray-500">Gudang aktif dapat digunakan untuk transaksi stok & penerimaan.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="hidden" name="status" value="0">
                        <input
                            type="checkbox"
                            name="status"
                            value="1"
                            {{ old('status', (string)(int)$gudang->status) == '1' ? 'checked' : '' }}
                            class="sr-only peer"
                        >
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#15803d]"></div>
                    </label>
                </div>

                {{-- Gudang Karantina --}}
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl border border-gray-100">
                    <div>
                        <p class="text-xs font-bold text-gray-800">Zonasi Karantina (QC)</p>
                        <p class="text-[11px] text-gray-500">Tandai jika gudang ini difungsikan khusus barang retur / inspeksi QC.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="hidden" name="is_quarantine" value="0">
                        <input
                            type="checkbox"
                            name="is_quarantine"
                            value="1"
                            {{ old('is_quarantine', (string)(int)$gudang->is_quarantine) == '1' ? 'checked' : '' }}
                            class="sr-only peer"
                        >
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-600"></div>
                    </label>
                </div>
            </div>

            {{-- Submit Action --}}
            <div class="flex items-center justify-end gap-3 pt-6 border-t border-gray-100">
                <a
                    href="{{ route('master.gudang.index') }}"
                    class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs sm:text-sm font-semibold rounded-xl transition"
                >
                    Batal
                </a>
                <button
                    type="submit"
                    class="px-6 py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-xs sm:text-sm font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
                >
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
