@extends('layouts.app')

@section('title', 'Tambah Akun COA Baru – Duta Tunggal ERP')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    {{-- Header & Breadcrumb --}}
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('master.coa.index') }}" class="hover:text-gray-700">Bagan Akun (COA)</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Tambah Akun Baru</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Tambah Akun Baru</h1>
            <p class="text-sm text-gray-500 mt-0.5">Daftarkan nomor akun baru untuk pencatatan jurnal, buku besar, dan laporan keuangan.</p>
        </div>
        <a
            href="{{ route('master.coa.index') }}"
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

    <form method="POST" action="{{ route('master.coa.store') }}" class="space-y-6">
        @csrf

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-5">
            <h2 class="text-base font-bold text-gray-900 pb-2 border-b border-gray-100 flex items-center gap-2">
                <svg class="w-5 h-5 text-[#15803d]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                Identitas & Struktur Akun
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- Kode Akun --}}
                <div>
                    <label for="code" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Nomor / Kode Akun <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="code"
                        id="code"
                        value="{{ old('code') }}"
                        required
                        placeholder="Contoh: 1111.01 atau 4100.10"
                        class="w-full px-3.5 py-2.5 text-sm font-mono border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#15803d] focus:border-transparent outline-none transition @error('code') border-rose-400 bg-rose-50/30 @enderror"
                    />
                    <p class="text-[11px] text-gray-400 mt-1">Gunakan format standar COA (e.g. 1xxx=Asset, 2xxx=Liability, 3xxx=Equity, 4xxx=Revenue, 5xxx=Expense).</p>
                </div>

                {{-- Klasifikasi Akun --}}
                <div>
                    <label for="type" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Klasifikasi Akun <span class="text-rose-500">*</span>
                    </label>
                    <select
                        name="type"
                        id="type"
                        required
                        class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#15803d] focus:border-transparent outline-none transition @error('type') border-rose-400 bg-rose-50/30 @enderror"
                    >
                        <option value="">-- Pilih Klasifikasi --</option>
                        @foreach($accountTypes as $at)
                            <option value="{{ $at }}" {{ old('type') === $at ? 'selected' : '' }}>{{ $at }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Nama Akun --}}
                <div class="sm:col-span-2">
                    <label for="name" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Nama Akun <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="name"
                        id="name"
                        value="{{ old('name') }}"
                        required
                        placeholder="Contoh: Kas Toko Utama, Bank BCA Operasional, Piutang Usaha"
                        class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#15803d] focus:border-transparent outline-none transition @error('name') border-rose-400 bg-rose-50/30 @enderror"
                    />
                </div>

                {{-- Akun Induk (Parent) --}}
                <div class="sm:col-span-2">
                    <label for="parent_id" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Akun Induk (Parent) <span class="text-gray-400 font-normal">(Opsional)</span>
                    </label>
                    <select data-searchable
                        name="parent_id"
                        id="parent_id"
                        class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#15803d] focus:border-transparent outline-none transition"
                    >
                        <option value="">(Tidak ada - Jadikan Akun Utama / Header)</option>
                        @foreach($parentAccounts as $parent)
                            <option value="{{ $parent->id }}" {{ old('parent_id') == $parent->id ? 'selected' : '' }}>
                                [{{ $parent->code }}] {{ $parent->name }} ({{ $parent->type }})
                            </option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-gray-400 mt-1">Pilih akun induk jika akun ini merupakan rincian (sub-akun) dari akun yang sudah ada.</p>
                </div>

                {{-- Saldo Awal --}}
                <div>
                    <label for="opening_balance" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Saldo Awal (Rp)
                    </label>
                    <input
                        type="number"
                        step="0.01"
                        name="opening_balance"
                        id="opening_balance"
                        value="{{ old('opening_balance', 0) }}"
                        class="w-full px-3.5 py-2.5 text-sm font-mono border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#15803d] focus:border-transparent outline-none transition"
                    />
                </div>

                {{-- Keterangan --}}
                <div class="sm:col-span-2">
                    <label for="description" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Keterangan Tambahan <span class="text-gray-400 font-normal">(Opsional)</span>
                    </label>
                    <textarea
                        name="description"
                        id="description"
                        rows="2"
                        placeholder="Catatan mengenai penggunaan akun ini..."
                        class="w-full px-3.5 py-2 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#15803d] focus:border-transparent outline-none transition"
                    >{{ old('description') }}</textarea>
                </div>
            </div>

            {{-- Flags & Options --}}
            <div class="pt-4 border-t border-gray-100 space-y-3">
                <label class="flex items-start gap-3 p-3 bg-purple-50/60 border border-purple-100 rounded-xl cursor-pointer hover:bg-purple-50 transition">
                    <input
                        type="checkbox"
                        name="is_cash_bank"
                        value="1"
                        {{ old('is_cash_bank') ? 'checked' : '' }}
                        class="mt-1 w-4 h-4 text-purple-600 rounded border-gray-300 focus:ring-purple-500"
                    />
                    <div>
                        <span class="block text-sm font-bold text-purple-900">Tandai sebagai Akun Kas & Bank</span>
                        <span class="block text-xs text-purple-700">
                            Centang jika akun ini adalah rekening bank atau kas tunai yang dapat dipilih pada transaksi penerimaan kasir, pelunasan piutang, dan pembayaran supplier.
                        </span>
                    </div>
                </label>

                <label class="flex items-start gap-3 p-3 bg-gray-50 border border-gray-100 rounded-xl cursor-pointer hover:bg-gray-100/70 transition">
                    <input
                        type="checkbox"
                        name="is_active"
                        value="1"
                        {{ old('is_active', true) ? 'checked' : '' }}
                        class="mt-1 w-4 h-4 text-[#15803d] rounded border-gray-300 focus:ring-[#15803d]"
                    />
                    <div>
                        <span class="block text-sm font-bold text-gray-900">Akun Aktif</span>
                        <span class="block text-xs text-gray-500">
                            Akun yang aktif dapat dipilih dalam pencatatan jurnal dan transaksi akuntansi.
                        </span>
                    </div>
                </label>
            </div>
        </div>

        {{-- Action Buttons --}}
        <div class="flex items-center justify-end gap-3 pt-2">
            <a
                href="{{ route('master.coa.index') }}"
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
                Simpan Akun Baru
            </button>
        </div>
    </form>
</div>
@endsection
