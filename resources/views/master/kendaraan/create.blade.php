@extends('layouts.app')

@section('title', 'Tambah Kendaraan Armada – Duta Tunggal ERP')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('master.kendaraan.index') }}" class="hover:text-gray-700">Master Kendaraan</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Tambah Baru</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Tambah Armada Kendaraan</h1>
            <p class="text-sm text-gray-500 mt-0.5">Daftarkan armada logistik baru untuk pengiriman surat jalan & transfer.</p>
        </div>
        <a
            href="{{ route('master.kendaraan.index') }}"
            class="px-4 py-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs sm:text-sm font-semibold rounded-xl shadow-sm transition"
        >
            Kembali
        </a>
    </div>

    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm shadow-sm space-y-1">
            <div class="flex items-center gap-2 font-bold">
                <svg class="w-5 h-5 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span>Mohon periksa kesalahan input berikut:</span>
            </div>
            <ul class="list-disc list-inside text-xs space-y-0.5 pl-6 text-rose-700">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 sm:p-8">
        <form method="POST" action="{{ route('master.kendaraan.store') }}" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                        Nomor Plat Kendaraan <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="plate"
                        value="{{ old('plate') }}"
                        required
                        placeholder="Contoh: B 1234 XYZ"
                        class="w-full px-4 py-2.5 text-xs sm:text-sm font-mono uppercase border rounded-xl focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] outline-none transition @error('plate') border-rose-300 bg-rose-50/30 @else border-gray-200 @enderror"
                    >
                    @error('plate')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                        Cabang Penempatan <span class="text-rose-500">*</span>
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
                            <option value="{{ $cb->id }}" {{ old('cabang_id') == $cb->id ? 'selected' : '' }}>
                                {{ $cb->nama }} ({{ $cb->kode }})
                            </option>
                        @endforeach
                    </select>
                    @error('cabang_id')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                        Jenis / Tipe Armada <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="type"
                        value="{{ old('type') }}"
                        required
                        placeholder="Contoh: Truk Engkel Box, Pickup Grand Max, Blind Van"
                        class="w-full px-4 py-2.5 text-xs sm:text-sm border rounded-xl focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] outline-none transition @error('type') border-rose-300 bg-rose-50/30 @else border-gray-200 @enderror"
                    >
                    @error('type')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                        Kapasitas Muatan <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="capacity"
                        value="{{ old('capacity') }}"
                        required
                        placeholder="Contoh: 2.5 Ton / 12 CBM"
                        class="w-full px-4 py-2.5 text-xs sm:text-sm border rounded-xl focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] outline-none transition @error('capacity') border-rose-300 bg-rose-50/30 @else border-gray-200 @enderror"
                    >
                    @error('capacity')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-6 border-t border-gray-100">
                <a
                    href="{{ route('master.kendaraan.index') }}"
                    class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs sm:text-sm font-semibold rounded-xl transition"
                >
                    Batal
                </a>
                <button
                    type="submit"
                    class="px-6 py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-xs sm:text-sm font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
                >
                    Simpan Kendaraan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
