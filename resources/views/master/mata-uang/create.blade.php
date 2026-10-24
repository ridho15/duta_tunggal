@extends('layouts.app')

@section('title', 'Tambah Mata Uang – Duta Tunggal ERP')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('master.mata-uang.index') }}" class="hover:text-gray-700">Mata Uang & Kurs</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Tambah Baru</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Tambah Mata Uang Baru</h1>
            <p class="text-sm text-gray-500 mt-0.5">Daftarkan valuta asing dan tentukan nilai tukar kurs konversi default terhadap IDR.</p>
        </div>
        <a
            href="{{ route('master.mata-uang.index') }}"
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
        <form method="POST" action="{{ route('master.mata-uang.store') }}" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                        Kode ISO Valuta <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="code"
                        value="{{ old('code') }}"
                        required
                        placeholder="Contoh: USD, SGD, EUR, JPY"
                        class="w-full px-4 py-2.5 text-xs sm:text-sm font-mono uppercase border rounded-xl focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] outline-none transition @error('code') border-rose-300 bg-rose-50/30 @else border-gray-200 @enderror"
                    >
                    @error('code')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                        Simbol Mata Uang <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="symbol"
                        value="{{ old('symbol') }}"
                        required
                        placeholder="Contoh: $, S$, €, ¥"
                        class="w-full px-4 py-2.5 text-xs sm:text-sm border rounded-xl focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] outline-none transition @error('symbol') border-rose-300 bg-rose-50/30 @else border-gray-200 @enderror"
                    >
                    @error('symbol')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                        Nama Mata Uang <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="name"
                        value="{{ old('name') }}"
                        required
                        placeholder="Contoh: United States Dollar"
                        class="w-full px-4 py-2.5 text-xs sm:text-sm border rounded-xl focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] outline-none transition @error('name') border-rose-300 bg-rose-50/30 @else border-gray-200 @enderror"
                    >
                    @error('name')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                        Nilai Kurs ke Rupiah (IDR) <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-3 text-xs text-gray-400 font-bold">Rp</span>
                        <input
                            type="number"
                            step="0.0001"
                            min="0.0001"
                            name="to_rupiah"
                            value="{{ old('to_rupiah') }}"
                            required
                            placeholder="Contoh: 16000"
                            class="w-full pl-10 pr-4 py-2.5 text-xs sm:text-sm font-mono border rounded-xl focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] outline-none transition @error('to_rupiah') border-rose-300 bg-rose-50/30 @else border-gray-200 @enderror"
                        >
                    </div>
                    @error('to_rupiah')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                    <p class="text-[11px] text-gray-400 mt-1.5">Nilai tukar 1 satuan mata uang ini dalam satuan Rupiah (IDR).</p>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-6 border-t border-gray-100">
                <a
                    href="{{ route('master.mata-uang.index') }}"
                    class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs sm:text-sm font-semibold rounded-xl transition"
                >
                    Batal
                </a>
                <button
                    type="submit"
                    class="px-6 py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-xs sm:text-sm font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
                >
                    Simpan Mata Uang
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
