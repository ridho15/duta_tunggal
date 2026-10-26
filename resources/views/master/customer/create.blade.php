@extends('layouts.app')

@section('title', 'Tambah Customer Baru – Duta Tunggal ERP')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    {{-- Header & Breadcrumb --}}
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('master.customers.index') }}" class="hover:text-gray-700">Master Customer</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Pendaftaran Baru</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Tambah Customer Baru</h1>
            <p class="text-sm text-gray-500 mt-0.5">Lengkapi profil pelanggan, data legalitas PKP/PRI, plafon kredit, dan kontak penagihan.</p>
        </div>
        <a
            href="{{ route('master.customers.index') }}"
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
            <span>Peringatan: Potensi Data Customer Ganda Terdeteksi!</span>
        </div>
        <p class="text-xs text-amber-700 leading-relaxed">
            Sistem mendeteksi ada customer di database dengan nama, perusahaan, kontak, atau NIK/NPWP yang serupa. Mohon pastikan Anda tidak membuat data customer ganda.
        </p>
        <div id="duplicate_list" class="space-y-1.5 pt-1 text-xs">
            {{-- Data kemiripan akan di-render di sini --}}
        </div>
    </div>

    <form method="POST" action="{{ route('master.customers.store') }}" class="space-y-6">
        @csrf

        {{-- Seksi 1: Identitas & Legalitas --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-5">
            <div class="border-b border-gray-100 pb-3 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-gray-900">1. Identitas & Legalitas Customer</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Kode customer, nama lengkap, perusahaan, dan NIK/NPWP.</p>
                </div>
                <div>
                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer">
                        <input type="checkbox" name="isSpecial" value="1" {{ old('isSpecial') ? 'checked' : '' }} class="w-4 h-4 text-amber-600 rounded border-gray-300 focus:ring-amber-500">
                        <span class="font-bold text-amber-800">⭐ Customer Spesial</span>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Kode Customer --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Kode Customer <span class="text-rose-500">*</span>
                    </label>
                    <div class="flex gap-2">
                        <input
                            type="text"
                            name="code"
                            id="customer_code"
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
                        Cabang Pendaftaran
                    </label>
                    <x-searchable-select name="cabang_id" :options="collect($cabangs)->map(fn ($cb) => ['id' => $cb->id, 'label' => '(' . $cb->kode . ') ' . $cb->nama])->values()" :value="old('cabang_id')" placeholder="Semua Cabang (Global)" />
                </div>

                {{-- Nama Customer --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Nama Customer (PIC / Kontak Utama) <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="name"
                        id="customer_name"
                        value="{{ old('name') }}"
                        required
                        placeholder="Contoh: Bpk. Hendra Gunawan"
                        oninput="debounceCheckDuplicates()"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                    >
                </div>

                {{-- Nama Perusahaan --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Nama Perusahaan / Instansi <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="perusahaan"
                        id="customer_perusahaan"
                        value="{{ old('perusahaan') }}"
                        required
                        placeholder="Contoh: PT Daya Teknik Medika"
                        oninput="debounceCheckDuplicates()"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                    >
                </div>

                {{-- NIK / NPWP --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        NIK / NPWP <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="nik_npwp"
                        id="customer_nik"
                        value="{{ old('nik_npwp') }}"
                        required
                        placeholder="16 digit NIK atau NPWP perusahaan"
                        oninput="debounceCheckDuplicates()"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d] font-mono"
                    >
                </div>

                {{-- Tipe Legalitas Customer --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Tipe Customer <span class="text-rose-500">*</span>
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex items-center justify-center p-2.5 text-xs font-medium border rounded-lg cursor-pointer transition {{ old('tipe', 'PKP') === 'PKP' ? 'bg-purple-50 border-purple-400 text-purple-700 font-bold' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50' }}">
                            <input type="radio" name="tipe" value="PKP" {{ old('tipe', 'PKP') === 'PKP' ? 'checked' : '' }} class="sr-only">
                            <span>PKP (Perusahaan / Kena Pajak)</span>
                        </label>
                        <label class="flex items-center justify-center p-2.5 text-xs font-medium border rounded-lg cursor-pointer transition {{ old('tipe') === 'PRI' ? 'bg-purple-50 border-purple-400 text-purple-700 font-bold' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50' }}">
                            <input type="radio" name="tipe" value="PRI" {{ old('tipe') === 'PRI' ? 'checked' : '' }} class="sr-only">
                            <span>PRI (Pribadi / Perorangan)</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        {{-- Seksi 2: Kontak & Alamat --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-5">
            <div class="border-b border-gray-100 pb-3">
                <h2 class="text-base font-bold text-gray-900">2. Alamat & Kontak Pelanggan</h2>
                <p class="text-xs text-gray-500 mt-0.5">Alamat pengiriman faktur, nomor handphone, telepon kantor, dan email.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Alamat --}}
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Alamat Lengkap <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="address"
                        value="{{ old('address') }}"
                        required
                        placeholder="Jalan, Nomor, RT/RW, Kelurahan, Kecamatan, Kota, Kode Pos"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                    >
                </div>

                {{-- Handphone --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Nomor Handphone (WhatsApp) <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="phone"
                        id="customer_phone"
                        value="{{ old('phone') }}"
                        required
                        placeholder="Contoh: 081234567890"
                        oninput="debounceCheckDuplicates()"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d] font-mono"
                    >
                </div>

                {{-- Telepon Kantor --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Telepon Kantor <span class="text-xs text-gray-400 font-normal">(Opsional)</span>
                    </label>
                    <input
                        type="text"
                        name="telephone"
                        value="{{ old('telephone') }}"
                        placeholder="Contoh: 021-12345678"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d] font-mono"
                    >
                </div>

                {{-- Email --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Alamat Email <span class="text-xs text-gray-400 font-normal">(Opsional)</span>
                    </label>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        placeholder="finance@customer.com"
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

        {{-- Seksi 3: Syarat Pembayaran & Plafon Kredit --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-5">
            <div class="border-b border-gray-100 pb-3">
                <h2 class="text-base font-bold text-gray-900">3. Syarat Pembayaran & Plafon Kredit</h2>
                <p class="text-xs text-gray-500 mt-0.5">Penetapan metode pembayaran, tempo waktu kredit, dan batas maksimal piutang.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- Tipe Pembayaran --}}
                <div class="md:col-span-3">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Tipe Pembayaran <span class="text-rose-500">*</span>
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        @foreach (['Bebas', 'COD (Bayar Lunas)', 'Kredit'] as $tb)
                            <label class="flex items-center justify-center p-2.5 text-xs font-medium border rounded-lg cursor-pointer transition {{ old('tipe_pembayaran', 'Kredit') === $tb ? 'bg-emerald-50 border-[#15803d] text-[#15803d] font-bold' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50' }}">
                                <input type="radio" name="tipe_pembayaran" value="{{ $tb }}" {{ old('tipe_pembayaran', 'Kredit') === $tb ? 'checked' : '' }} class="sr-only">
                                <span>{{ $tb }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Tempo Kredit --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Tempo Kredit (Hari) <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <input
                            type="number"
                            name="tempo_kredit"
                            value="{{ old('tempo_kredit', 30) }}"
                            required
                            min="0"
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                        >
                        <span class="absolute right-3 top-2.5 text-xs text-gray-400">Hari</span>
                    </div>
                </div>

                {{-- Kredit Limit --}}
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Plafon Kredit Limit (Rp) <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-2.5 text-xs font-bold text-gray-400 pointer-events-none">Rp</span>
                        <input
                            type="text"
                            id="display_kredit_limit"
                            value="{{ old('kredit_limit', 10000000) > 0 ? number_format(old('kredit_limit', 10000000), 0, ',', '.') : '' }}"
                            oninput="handleKreditLimitInput(this.value)"
                            required
                            placeholder="0"
                            class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d] font-bold text-gray-900 font-mono"
                        >
                        <input
                            type="hidden"
                            name="kredit_limit"
                            id="kredit_limit"
                            value="{{ old('kredit_limit', 10000000) }}"
                        >
                    </div>
                    <p class="text-[11px] text-gray-500 mt-1">Sistem akan memblokir persetujuan Sales Order jika piutang customer melampaui angka ini.</p>
                </div>
            </div>
        </div>

        {{-- Seksi 4: Catatan & Override --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-4">
            <h2 class="text-base font-bold text-gray-900">4. Keterangan Tambahan</h2>
            <textarea
                name="keterangan"
                rows="2"
                placeholder="Catatan profil customer, preferensi penagihan, atau instruksi khusus..."
                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
            >{{ old('keterangan') }}</textarea>

            <div id="duplicate_reason_container" class="hidden pt-3 border-t border-gray-100">
                <label class="block text-xs font-bold text-amber-800 mb-1">
                    Alasan Persetujuan Data Serupa (Wajib jika ada kemiripan data)
                </label>
                <textarea
                    name="allow_duplicate_reason"
                    rows="2"
                    placeholder="Tuliskan alasan mengapa customer ini tetap dibuat meskipun ditemukan kemiripan nama/telepon..."
                    class="w-full px-3 py-2 text-sm border border-amber-300 rounded-lg bg-amber-50/40 focus:ring-2 focus:ring-amber-500"
                >{{ old('allow_duplicate_reason') }}</textarea>
            </div>
        </div>

        {{-- Tombol Aksi --}}
        <div class="flex items-center justify-end gap-3 pt-4">
            <a
                href="{{ route('master.customers.index') }}"
                class="px-5 py-2.5 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition"
            >
                Batal
            </a>
            <button
                type="submit"
                class="px-6 py-2.5 text-sm font-semibold text-white bg-[#15803d] hover:bg-[#166534] rounded-lg shadow-sm transition active:scale-[0.98]"
            >
                Daftarkan Customer Baru
            </button>
        </div>
    </form>
</div>

{{-- Script Live Deduplication & Code Generation --}}
<script>
    function reloadCode() {
        const spinner = document.getElementById('code_spinner');
        spinner.classList.add('animate-spin');

        fetch("{{ route('master.customers.generate-code') }}", {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.code) {
                document.getElementById('customer_code').value = data.code;
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
        const name = document.getElementById('customer_name').value.trim();
        const perusahaan = document.getElementById('customer_perusahaan').value.trim();
        const phone = document.getElementById('customer_phone').value.trim();
        const nikNpwp = document.getElementById('customer_nik').value.trim();

        if (name.length < 3 && perusahaan.length < 3 && phone.length < 7 && nikNpwp.length < 8) {
            document.getElementById('duplicate_warning_box').classList.add('hidden');
            document.getElementById('duplicate_reason_container').classList.add('hidden');
            return;
        }

        const params = new URLSearchParams({
            name: name,
            perusahaan: perusahaan,
            phone: phone,
            nik_npwp: nikNpwp
        });

        fetch(`{{ route('master.customers.check-duplicates') }}?${params.toString()}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            const box = document.getElementById('duplicate_warning_box');
            const list = document.getElementById('duplicate_list');
            const reasonContainer = document.getElementById('duplicate_reason_container');

            if (data.has_duplicates && data.duplicates.length > 0) {
                let html = '';
                data.duplicates.forEach(d => {
                    html += `
                        <div class="p-2 bg-white/80 rounded border border-amber-200 flex items-center justify-between">
                            <div>
                                <span class="font-bold text-gray-900">${d.name}</span>
                                <span class="text-gray-500 font-mono text-[11px]">(${d.code})</span>
                                <span class="text-gray-600 block text-[11px]">${d.perusahaan || '-'} • Telp: ${d.phone || '-'}</span>
                            </div>
                            <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800 border border-amber-300">
                                ${d.reason}
                            </span>
                        </div>
                    `;
                });
                list.innerHTML = html;
                box.classList.remove('hidden');
                reasonContainer.classList.remove('hidden');
            } else {
                box.classList.add('hidden');
                reasonContainer.classList.add('hidden');
            }
        })
        .catch(err => console.error('Gagal memeriksa duplikasi customer:', err));
    }

    function handleKreditLimitInput(rawVal) {
        const clean = String(rawVal).replace(/\D/g, '');
        const num = clean ? parseFloat(clean) : 0;
        document.getElementById('kredit_limit').value = num;
        document.getElementById('display_kredit_limit').value = num > 0 ? Number(num).toLocaleString('id-ID') : '';
    }
</script>
@endsection
