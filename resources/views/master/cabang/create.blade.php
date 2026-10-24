@extends('layouts.app')

@section('title', 'Tambah Cabang Baru – Duta Tunggal ERP')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    {{-- Header & Breadcrumb --}}
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('master.cabang.index') }}" class="hover:text-gray-700">Master Cabang</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Tambah Cabang Baru</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Tambah Cabang Baru</h1>
            <p class="text-sm text-gray-500 mt-0.5">Daftarkan kantor cabang operasional baru, konfigurasi invoice, dan alamat kontak.</p>
        </div>
        <a
            href="{{ route('master.cabang.index') }}"
            class="inline-flex items-center gap-2 px-3.5 py-2 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 transition"
        >
            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Kembali
        </a>
    </div>

    {{-- Alert Errors --}}
    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm shadow-sm space-y-1">
            <div class="flex items-center gap-2 font-bold">
                <svg class="w-5 h-5 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Mohon periksa kembali formulir Anda:</span>
            </div>
            <ul class="list-disc list-inside text-xs pl-6 space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('master.cabang.store') }}" class="space-y-6">
        @csrf

        {{-- Form Card: Identitas Cabang --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-5">
            <h2 class="text-base font-bold text-gray-900 pb-2 border-b border-gray-100 flex items-center gap-2">
                <svg class="w-5 h-5 text-[#15803d]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
                Profil & Identitas Kantor Cabang
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- Kode Cabang --}}
                <div>
                    <label for="kode" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Kode Cabang <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="kode"
                        id="kode"
                        value="{{ old('kode') }}"
                        required
                        placeholder="Contoh: CB-001, CB-SBY"
                        class="w-full px-3.5 py-2.5 text-sm font-mono border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#15803d] focus:border-transparent outline-none transition @error('kode') border-rose-400 bg-rose-50/30 @enderror"
                    />
                </div>

                {{-- Nama Cabang --}}
                <div>
                    <label for="nama" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Nama Cabang <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="nama"
                        id="nama"
                        value="{{ old('nama') }}"
                        required
                        placeholder="Contoh: Cabang Utama, Cabang Surabaya"
                        class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#15803d] focus:border-transparent outline-none transition @error('nama') border-rose-400 bg-rose-50/30 @enderror"
                    />
                </div>

                {{-- Telepon --}}
                <div>
                    <label for="telepon" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Nomor Telepon
                    </label>
                    <input
                        type="text"
                        name="telepon"
                        id="telepon"
                        value="{{ old('telepon') }}"
                        placeholder="Contoh: 021-1234567 / 08123456789"
                        class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#15803d] focus:border-transparent outline-none transition"
                    />
                </div>

                {{-- Kenaikan Harga (%) --}}
                <div>
                    <label for="kenaikan_harga" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Kenaikan Harga Wilayah (%)
                    </label>
                    <input
                        type="number"
                        step="0.01"
                        name="kenaikan_harga"
                        id="kenaikan_harga"
                        value="{{ old('kenaikan_harga', 0) }}"
                        placeholder="0"
                        class="w-full px-3.5 py-2.5 text-sm font-mono border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#15803d] focus:border-transparent outline-none transition"
                    />
                    <p class="text-[11px] text-gray-400 mt-1">Persentase markup harga jual dasar untuk cabang ini.</p>
                </div>

                {{-- Alamat --}}
                <div class="sm:col-span-2">
                    <label for="alamat" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Alamat Operasional
                    </label>
                    <textarea
                        name="alamat"
                        id="alamat"
                        rows="2"
                        placeholder="Alamat kantor / gudang cabang..."
                        class="w-full px-3.5 py-2 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#15803d] focus:border-transparent outline-none transition"
                    >{{ old('alamat') }}</textarea>
                </div>
            </div>
        </div>

        {{-- Form Card: Konfigurasi Invoice & Pajak --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-5">
            <h2 class="text-base font-bold text-gray-900 pb-2 border-b border-gray-100 flex items-center gap-2">
                <svg class="w-5 h-5 text-[#15803d]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Konfigurasi Faktur, Kwitansi & Legalitas
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="kode_invoice_pajak" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Prefix Invoice Pajak
                    </label>
                    <input
                        type="text"
                        name="kode_invoice_pajak"
                        id="kode_invoice_pajak"
                        value="{{ old('kode_invoice_pajak', 'INV-P') }}"
                        placeholder="INV-P"
                        class="w-full px-3.5 py-2.5 text-sm font-mono border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#15803d] focus:border-transparent outline-none transition"
                    />
                </div>

                <div>
                    <label for="kode_invoice_non_pajak" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Prefix Invoice Non-Pajak
                    </label>
                    <input
                        type="text"
                        name="kode_invoice_non_pajak"
                        id="kode_invoice_non_pajak"
                        value="{{ old('kode_invoice_non_pajak', 'INV-NP') }}"
                        placeholder="INV-NP"
                        class="w-full px-3.5 py-2.5 text-sm font-mono border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#15803d] focus:border-transparent outline-none transition"
                    />
                </div>

                <div class="sm:col-span-2">
                    <label for="nama_kwitansi" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Kop / Nama Kwitansi Cetak
                    </label>
                    <input
                        type="text"
                        name="nama_kwitansi"
                        id="nama_kwitansi"
                        value="{{ old('nama_kwitansi', 'PT DUTA TUNGGAL') }}"
                        class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#15803d] focus:border-transparent outline-none transition"
                    />
                </div>

                {{-- Status Aktif --}}
                <div class="sm:col-span-2 pt-2 border-t border-gray-100">
                    <label class="flex items-start gap-3 p-3 bg-gray-50 border border-gray-100 rounded-xl cursor-pointer hover:bg-gray-100/70 transition">
                        <input
                            type="checkbox"
                            name="status"
                            value="1"
                            {{ old('status', true) ? 'checked' : '' }}
                            class="mt-1 w-4 h-4 text-[#15803d] rounded border-gray-300 focus:ring-[#15803d]"
                        />
                        <div>
                            <span class="block text-sm font-bold text-gray-900">Cabang Berstatus Aktif</span>
                            <span class="block text-xs text-gray-500">
                                Cabang aktif dapat dipilih untuk penempatan gudang, penugasan staf, dan pembuatan transaksi.
                            </span>
                        </div>
                    </label>
                </div>
            </div>
        </div>

        {{-- Action Buttons --}}
        <div class="flex items-center justify-end gap-3 pt-2">
            <a
                href="{{ route('master.cabang.index') }}"
                class="px-5 py-2.5 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 transition active:scale-[0.98]"
            >
                Batal
            </a>
            <button
                type="submit"
                class="inline-flex items-center gap-2 px-6 py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-sm font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Simpan Cabang Baru
            </button>
        </div>
    </form>
</div>
@endsection
