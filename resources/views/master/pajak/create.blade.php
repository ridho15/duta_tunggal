@extends('layouts.app')

@section('title', 'Tambah Tarif Pajak – Duta Tunggal ERP')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('master.pajak.index') }}" class="hover:text-gray-700">Pengaturan Pajak</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Tambah Baru</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Tambah Tarif Pajak Baru</h1>
            <p class="text-sm text-gray-500 mt-0.5">Daftarkan konfigurasi tarif PPN, PPh, atau potongan pajak lainnya untuk faktur transaksi.</p>
        </div>
        <a
            href="{{ route('master.pajak.index') }}"
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
        <form method="POST" action="{{ route('master.pajak.store') }}" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                        Nama Pengaturan Pajak <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="name"
                        value="{{ old('name') }}"
                        required
                        placeholder="Contoh: PPN 12%, PPN 11%, PPh 21, PPh 23 Jasa"
                        class="w-full px-4 py-2.5 text-xs sm:text-sm border rounded-xl focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] outline-none transition @error('name') border-rose-300 bg-rose-50/30 @else border-gray-200 @enderror"
                    >
                    @error('name')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                        Tipe Pajak <span class="text-rose-500">*</span>
                    </label>
                    <select
                        name="type"
                        required
                        class="w-full px-4 py-2.5 text-xs sm:text-sm border rounded-xl bg-white focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] outline-none transition @error('type') border-rose-300 bg-rose-50/30 @else border-gray-200 @enderror"
                    >
                        <option value="">-- Pilih Tipe Pajak --</option>
                        <option value="PPN" {{ old('type') == 'PPN' ? 'selected' : '' }}>PPN (Pajak Pertambahan Nilai)</option>
                        <option value="PPH" {{ old('type') == 'PPH' ? 'selected' : '' }}>PPh (Pajak Penghasilan)</option>
                        <option value="Custom" {{ old('type') == 'Custom' ? 'selected' : '' }}>Custom / Lainnya</option>
                    </select>
                    @error('type')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                        Tarif Pajak (%) <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            max="100"
                            name="rate"
                            value="{{ old('rate') }}"
                            required
                            placeholder="Contoh: 12.00"
                            class="w-full pl-4 pr-10 py-2.5 text-xs sm:text-sm font-mono border rounded-xl focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] outline-none transition @error('rate') border-rose-300 bg-rose-50/30 @else border-gray-200 @enderror"
                        >
                        <span class="absolute inset-y-0 right-0 pr-4 flex items-center text-xs font-bold text-gray-400">
                            %
                        </span>
                    </div>
                    @error('rate')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                        Tanggal Mulai Berlaku <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="date"
                        name="effective_date"
                        value="{{ old('effective_date', date('Y-m-d')) }}"
                        required
                        class="w-full px-4 py-2.5 text-xs sm:text-sm border rounded-xl focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] outline-none transition @error('effective_date') border-rose-300 bg-rose-50/30 @else border-gray-200 @enderror"
                    >
                    <p class="text-[11px] text-gray-400 mt-1">Tarif dengan tanggal efektif terbaru yang &le; hari ini akan otomatis dipakai kalkulasi sistem.</p>
                    @error('effective_date')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                        Status Keaktifan <span class="text-rose-500">*</span>
                    </label>
                    <div class="grid grid-cols-2 gap-3 pt-1">
                        <label class="flex items-center gap-2 p-3 border rounded-xl cursor-pointer hover:bg-gray-50 transition border-gray-200">
                            <input
                                type="radio"
                                name="status"
                                value="1"
                                {{ old('status', '1') == '1' ? 'checked' : '' }}
                                class="w-4 h-4 text-[#15803d] focus:ring-[#15803d]"
                            >
                            <span class="text-xs font-bold text-gray-700">Aktif</span>
                        </label>
                        <label class="flex items-center gap-2 p-3 border rounded-xl cursor-pointer hover:bg-gray-50 transition border-gray-200">
                            <input
                                type="radio"
                                name="status"
                                value="0"
                                {{ old('status') === '0' ? 'checked' : '' }}
                                class="w-4 h-4 text-gray-400 focus:ring-gray-400"
                            >
                            <span class="text-xs font-medium text-gray-600">Nonaktif</span>
                        </label>
                    </div>
                    @error('status')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="pt-4 border-t border-gray-100 flex items-center justify-end gap-3">
                <a
                    href="{{ route('master.pajak.index') }}"
                    class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs sm:text-sm font-semibold rounded-xl transition"
                >
                    Batal
                </a>
                <button
                    type="submit"
                    class="px-6 py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-xs sm:text-sm font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
                >
                    Simpan Tarif Pajak
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
