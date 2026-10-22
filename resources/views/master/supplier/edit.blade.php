@extends('layouts.app')

@section('title', 'Edit Supplier: ' . $supplier->perusahaan . ' – Duta Tunggal ERP')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    {{-- Header & Breadcrumb --}}
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('master.suppliers.index') }}" class="hover:text-gray-700">Master Supplier</a>
                <span class="text-gray-300">/</span>
                <span class="text-gray-500 font-mono">{{ $supplier->code }}</span>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Edit</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Edit Data Supplier</h1>
            <p class="text-sm text-gray-500 mt-0.5">Perbarui profil kontak, rekening bank pembayaran, atau tempo hutang dagang (TOP).</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('master.suppliers.show', $supplier) }}"
                class="inline-flex items-center gap-2 px-3.5 py-2 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition"
            >
                Lihat Detail
            </a>
            <a
                href="{{ route('master.suppliers.index') }}"
                class="inline-flex items-center gap-2 px-3.5 py-2 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition"
            >
                Kembali
            </a>
        </div>
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

    <form method="POST" action="{{ route('master.suppliers.update', $supplier) }}" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Seksi 1: Profil Perusahaan Supplier --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-5">
            <div class="border-b border-gray-100 pb-3">
                <h2 class="text-base font-bold text-gray-900">1. Profil Vendor / Pemasok</h2>
                <p class="text-xs text-gray-500 mt-0.5">Kode supplier, nama resmi badan usaha, kontak person, dan NPWP.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Kode Supplier --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Kode Supplier <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="code"
                        id="supplier_code"
                        value="{{ old('code', $supplier->code) }}"
                        required
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d] font-mono uppercase bg-gray-50/50"
                    >
                </div>

                {{-- Cabang --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Cabang Pengadaan
                    </label>
                    <select name="cabang_id" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]">
                        <option value="">Semua Cabang (Pusat / Global)</option>
                        @foreach ($cabangs as $cb)
                            <option value="{{ $cb->id }}" {{ old('cabang_id', $supplier->cabang_id) == $cb->id ? 'selected' : '' }}>
                                ({{ $cb->kode }}) {{ $cb->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Nama Perusahaan Supplier --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Nama Perusahaan Supplier <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="perusahaan"
                        value="{{ old('perusahaan', $supplier->perusahaan) }}"
                        required
                        placeholder="Contoh: PT Sumber Logam Perkasa"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                    >
                </div>

                {{-- Kontak Person --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Nama Kontak Person (Sales/PIC)
                    </label>
                    <input
                        type="text"
                        name="kontak_person"
                        value="{{ old('kontak_person', $supplier->kontak_person) }}"
                        placeholder="Contoh: Ibu Rina (Sales Manager)"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                    >
                </div>

                {{-- NPWP --}}
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Nomor NPWP <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="npwp"
                        value="{{ old('npwp', $supplier->npwp) }}"
                        required
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d] font-mono"
                    >
                </div>
            </div>
        </div>

        {{-- Seksi 2: Alamat & Kontak --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-5">
            <div class="border-b border-gray-100 pb-3">
                <h2 class="text-base font-bold text-gray-900">2. Alamat & Kontak Operasional</h2>
                <p class="text-xs text-gray-500 mt-0.5">Alamat gudang / kantor supplier, telepon, handphone, dan email pengiriman PO.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Alamat --}}
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Alamat Kantor / Pabrik <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="address"
                        value="{{ old('address', $supplier->address) }}"
                        required
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                    >
                </div>

                {{-- Telepon --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Nomor Telepon Kantor <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="phone"
                        value="{{ old('phone', $supplier->phone) }}"
                        required
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d] font-mono"
                    >
                </div>

                {{-- Handphone --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Nomor Handphone (PIC WhatsApp) <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="handphone"
                        value="{{ old('handphone', $supplier->handphone) }}"
                        required
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d] font-mono"
                    >
                </div>

                {{-- Email --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Alamat Email (Pengiriman PO) <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email', $supplier->email) }}"
                        required
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                    >
                </div>

                {{-- Fax --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Nomor Fax <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="fax"
                        value="{{ old('fax', $supplier->fax) }}"
                        required
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d] font-mono"
                    >
                </div>
            </div>
        </div>

        {{-- Seksi 3: Rekening Bank Pembayaran & Tempo Hutang (Item C7) --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-5">
            <div class="border-b border-gray-100 pb-3">
                <h2 class="text-base font-bold text-gray-900">3. Rekening Bank Pembayaran & Ketentuan TOP</h2>
                <p class="text-xs text-gray-500 mt-0.5">Data rekening bank resmi untuk transfer pembayaran hutang pembelian barang oleh divisi Keuangan.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- Nama Bank --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Nama Bank
                    </label>
                    <input
                        type="text"
                        name="nama_bank"
                        list="bank_suggestions"
                        value="{{ old('nama_bank', $supplier->nama_bank) }}"
                        placeholder="Pilih atau ketik bank (misal: BCA)"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                    >
                    <datalist id="bank_suggestions">
                        @foreach ($bankSuggestions as $bs)
                            <option value="{{ $bs }}"></option>
                        @endforeach
                    </datalist>
                </div>

                {{-- Nomor Rekening --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Nomor Rekening Bank
                    </label>
                    <input
                        type="text"
                        name="nomor_rekening"
                        value="{{ old('nomor_rekening', $supplier->nomor_rekening) }}"
                        placeholder="Contoh: 1234567890"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d] font-mono"
                    >
                </div>

                {{-- Atas Nama Rekening --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Nama Pemilik Rekening (a.n.)
                    </label>
                    <input
                        type="text"
                        name="nama_rekening"
                        value="{{ old('nama_rekening', $supplier->nama_rekening) }}"
                        placeholder="Contoh: PT Sumber Logam Perkasa"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                    >
                </div>

                {{-- Tempo Hutang (TOP) --}}
                <div class="md:col-span-3">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Tempo Hutang Pembayaran (TOP) <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative max-w-xs">
                        <input
                            type="number"
                            name="tempo_hutang"
                            value="{{ old('tempo_hutang', $supplier->tempo_hutang) }}"
                            required
                            min="0"
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                        >
                        <span class="absolute right-3 top-2.5 text-xs text-gray-400">Hari</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Seksi 4: Catatan --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-3">
            <h2 class="text-base font-bold text-gray-900">4. Keterangan Tambahan</h2>
            <textarea
                name="keterangan"
                rows="2"
                placeholder="Catatan khusus, syarat minimum pemesanan (MOQ), atau ketentuan garansi supplier..."
                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
            >{{ old('keterangan', $supplier->keterangan) }}</textarea>
        </div>

        {{-- Tombol Aksi --}}
        <div class="flex items-center justify-end gap-3 pt-4">
            <a
                href="{{ route('master.suppliers.index') }}"
                class="px-5 py-2.5 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition"
            >
                Batal
            </a>
            <button
                type="submit"
                class="px-6 py-2.5 text-sm font-semibold text-white bg-[#15803d] hover:bg-[#166534] rounded-lg shadow-sm transition active:scale-[0.98]"
            >
                Simpan Perubahan
            </button>
        </div>
    </form>
</div>
@endsection
