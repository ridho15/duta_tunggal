@extends('layouts.app')

@section('title', 'Tambah Supplier Baru – Duta Tunggal ERP')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    {{-- Header & Breadcrumb --}}
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('master.suppliers.index') }}" class="hover:text-gray-700">Master Supplier</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Pendaftaran Baru</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Tambah Supplier Baru</h1>
            <p class="text-sm text-gray-500 mt-0.5">Daftarkan vendor penyedia barang, syarat tempo hutang (TOP), dan rekening transfer bank.</p>
        </div>
        <a
            href="{{ route('master.suppliers.index') }}"
            class="inline-flex items-center gap-2 px-3.5 py-2 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition"
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

    {{-- Live Deduplication Warning Box --}}
    <div id="duplicate_warning_box" class="hidden p-4 rounded-xl bg-amber-50 border border-amber-300 text-amber-900 text-sm shadow-sm space-y-2">
        <div class="flex items-center gap-2 font-bold text-amber-800">
            <svg class="w-5 h-5 text-amber-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <span>Peringatan: Potensi Data Supplier Ganda Terdeteksi!</span>
        </div>
        <p class="text-xs text-amber-700 leading-relaxed">
            Ditemukan nama perusahaan supplier atau NPWP serupa di database. Pastikan Anda tidak mendaftarkan supplier yang sudah ada.
        </p>
        <div id="duplicate_list" class="space-y-1.5 pt-1 text-xs">
            {{-- Hasil kemiripan supplier --}}
        </div>
    </div>

    <form method="POST" action="{{ route('master.suppliers.store') }}" class="space-y-6">
        @csrf

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
                    <div class="flex gap-2">
                        <input
                            type="text"
                            name="code"
                            id="supplier_code"
                            value="{{ old('code', $generatedCode) }}"
                            required
                            class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d] font-mono uppercase bg-gray-50/50"
                        >
                        <button
                            type="button"
                            onclick="reloadCode()"
                            class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-lg transition flex items-center gap-1.5"
                            title="Generate Ulang Kode"
                        >
                            <svg id="code_spinner" class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <span>Generate</span>
                        </button>
                    </div>
                </div>

                {{-- Cabang --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Cabang Pengadaan
                    </label>
                    <x-searchable-select name="cabang_id" :options="collect($cabangs)->map(fn ($cb) => ['id' => $cb->id, 'label' => '(' . $cb->kode . ') ' . $cb->nama])->values()" :value="old('cabang_id')" placeholder="Semua Cabang (Pusat / Global)" />
                </div>

                {{-- Nama Perusahaan Supplier --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Nama Perusahaan Supplier <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="perusahaan"
                        id="supplier_perusahaan"
                        value="{{ old('perusahaan') }}"
                        required
                        placeholder="Contoh: PT Sumber Logam Perkasa"
                        oninput="debounceCheckDuplicates()"
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
                        value="{{ old('kontak_person') }}"
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
                        id="supplier_npwp"
                        value="{{ old('npwp') }}"
                        required
                        placeholder="15 atau 16 digit NPWP"
                        oninput="debounceCheckDuplicates()"
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
                        value="{{ old('address') }}"
                        required
                        placeholder="Jalan, Kawasan Industri, Kota, Kode Pos"
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
                        value="{{ old('phone') }}"
                        required
                        placeholder="Contoh: 021-5551234"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d] font-mono"
                    >
                </div>

                {{-- Handphone --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Nomor Handphone (PIC WhatsApp) <span class="text-xs text-gray-400 font-normal">(Opsional)</span>
                    </label>
                    <input
                        type="text"
                        name="handphone"
                        id="supplier_phone"
                        value="{{ old('handphone') }}"
                        placeholder="Contoh: 081298765432"
                        oninput="debounceCheckDuplicates()"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d] font-mono"
                    >
                </div>

                {{-- Email --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Alamat Email (Pengiriman PO) <span class="text-xs text-gray-400 font-normal">(Opsional)</span>
                    </label>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        placeholder="sales@supplier.com"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                    >
                </div>

                {{-- Fax --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Nomor Fax <span class="text-xs text-gray-400 font-normal">(Opsional)</span>
                    </label>
                    <input
                        type="text"
                        name="fax"
                        value="{{ old('fax') }}"
                        placeholder="Nomor Fax"
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
                        value="{{ old('nama_bank') }}"
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
                        value="{{ old('nomor_rekening') }}"
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
                        value="{{ old('nama_rekening') }}"
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
                            value="{{ old('tempo_hutang', 30) }}"
                            required
                            min="0"
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                        >
                        <span class="absolute right-3 top-2.5 text-xs text-gray-400">Hari</span>
                    </div>
                    <p class="text-[11px] text-gray-500 mt-1">Jangka waktu jatuh tempo faktur pembelian dari supplier ini.</p>
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
            >{{ old('keterangan') }}</textarea>
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
                Daftarkan Supplier Baru
            </button>
        </div>
    </form>
</div>

{{-- Script Live Deduplication & Code Generation --}}
<script>
    function reloadCode() {
        const spinner = document.getElementById('code_spinner');
        spinner.classList.add('animate-spin');

        fetch("{{ route('master.suppliers.generate-code') }}", {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.code) {
                document.getElementById('supplier_code').value = data.code;
            }
        })
        .catch(err => console.error('Gagal generate kode:', err))
        .finally(() => {
            setTimeout(() => spinner.classList.remove('animate-spin'), 300);
        });
    }

    let dedupTimeout = null;

    function debounceCheckDuplicates() {
        clearTimeout(dedupTimeout);
        dedupTimeout = setTimeout(checkDuplicates, 400);
    }

    function checkDuplicates() {
        const perusahaan = document.getElementById('supplier_perusahaan').value.trim();
        const npwp = document.getElementById('supplier_npwp').value.trim();
        const phone = document.getElementById('supplier_phone').value.trim();

        if (perusahaan.length < 3 && npwp.length < 8) {
            document.getElementById('duplicate_warning_box').classList.add('hidden');
            return;
        }

        const params = new URLSearchParams({
            perusahaan: perusahaan,
            npwp: npwp,
            phone: phone
        });

        fetch(`{{ route('master.suppliers.check-duplicates') }}?${params.toString()}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            const box = document.getElementById('duplicate_warning_box');
            const list = document.getElementById('duplicate_list');

            if (data.has_duplicates && data.duplicates.length > 0) {
                let html = '';
                data.duplicates.forEach(d => {
                    html += `
                        <div class="p-2 bg-white/80 rounded border border-amber-200 flex items-center justify-between">
                            <div>
                                <span class="font-bold text-gray-900">${d.perusahaan}</span>
                                <span class="text-gray-500 font-mono text-[11px]">(${d.code})</span>
                                <span class="text-gray-600 block text-[11px]">${d.kontak_person || '-'} • Rek: ${d.bank_info}</span>
                            </div>
                            <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800 border border-amber-300">
                                ${d.reason}
                            </span>
                        </div>
                    `;
                });
                list.innerHTML = html;
                box.classList.remove('hidden');
            } else {
                box.classList.add('hidden');
            }
        })
        .catch(err => console.error('Gagal memeriksa duplikasi supplier:', err));
    }
</script>
@endsection
