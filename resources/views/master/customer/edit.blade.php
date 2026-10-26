@extends('layouts.app')

@section('title', 'Edit Customer: ' . $customer->name . ' – Duta Tunggal ERP')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    {{-- Header & Breadcrumb --}}
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('master.customers.index') }}" class="hover:text-gray-700">Master Customer</a>
                <span class="text-gray-300">/</span>
                <span class="text-gray-500 font-mono">{{ $customer->getDisplayCode() }}</span>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Edit</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Edit Data Customer</h1>
            <p class="text-sm text-gray-500 mt-0.5">Perbarui informasi profil, alamat penagihan, atau penyesuaian plafon kredit limit.</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('master.customers.show', $customer) }}"
                class="inline-flex items-center gap-2 px-3.5 py-2 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition"
            >
                Lihat Profil
            </a>
            <a
                href="{{ route('master.customers.index') }}"
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

    <form method="POST" action="{{ route('master.customers.update', $customer) }}" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Seksi 1: Identitas & Legalitas --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-5">
            <div class="border-b border-gray-100 pb-3 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-gray-900">1. Identitas & Legalitas Customer</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Kode customer, nama lengkap, perusahaan, dan NIK/NPWP.</p>
                </div>
                <div>
                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer">
                        <input type="checkbox" name="isSpecial" value="1" {{ old('isSpecial', $customer->isSpecial) ? 'checked' : '' }} class="w-4 h-4 text-amber-600 rounded border-gray-300 focus:ring-amber-500">
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
                    <input
                        type="text"
                        name="code"
                        id="customer_code"
                        value="{{ old('code', $customer->code) }}"
                        required
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d] font-mono uppercase bg-gray-50/50"
                    >
                </div>

                {{-- Cabang --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Cabang Pendaftaran
                    </label>
                    <x-searchable-select name="cabang_id" :options="collect($cabangs)->map(fn ($cb) => ['id' => $cb->id, 'label' => '(' . $cb->kode . ') ' . $cb->nama])->values()" :value="old('cabang_id', $customer->cabang_id)" placeholder="Semua Cabang (Global)" />
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
                        value="{{ old('name', $customer->name) }}"
                        required
                        placeholder="Contoh: Bpk. Hendra Gunawan"
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
                        value="{{ old('perusahaan', $customer->perusahaan) }}"
                        required
                        placeholder="Contoh: PT Daya Teknik Medika"
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
                        value="{{ old('nik_npwp', $customer->nik_npwp) }}"
                        required
                        placeholder="16 digit NIK atau NPWP perusahaan"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d] font-mono"
                    >
                </div>

                {{-- Tipe Legalitas Customer --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Tipe Customer <span class="text-rose-500">*</span>
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex items-center justify-center p-2.5 text-xs font-medium border rounded-lg cursor-pointer transition {{ old('tipe', $customer->tipe) === 'PKP' ? 'bg-purple-50 border-purple-400 text-purple-700 font-bold' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50' }}">
                            <input type="radio" name="tipe" value="PKP" {{ old('tipe', $customer->tipe) === 'PKP' ? 'checked' : '' }} class="sr-only">
                            <span>PKP (Perusahaan / Kena Pajak)</span>
                        </label>
                        <label class="flex items-center justify-center p-2.5 text-xs font-medium border rounded-lg cursor-pointer transition {{ old('tipe', $customer->tipe) === 'PRI' ? 'bg-purple-50 border-purple-400 text-purple-700 font-bold' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50' }}">
                            <input type="radio" name="tipe" value="PRI" {{ old('tipe', $customer->tipe) === 'PRI' ? 'checked' : '' }} class="sr-only">
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
                        value="{{ old('address', $customer->address) }}"
                        required
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
                        value="{{ old('phone', $customer->phone) }}"
                        required
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
                        value="{{ old('telephone', $customer->telephone) }}"
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
                        value="{{ old('email', $customer->email) }}"
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
                        value="{{ old('fax', $customer->fax) }}"
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
                            <label class="flex items-center justify-center p-2.5 text-xs font-medium border rounded-lg cursor-pointer transition {{ old('tipe_pembayaran', $customer->tipe_pembayaran) === $tb ? 'bg-emerald-50 border-[#15803d] text-[#15803d] font-bold' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50' }}">
                                <input type="radio" name="tipe_pembayaran" value="{{ $tb }}" {{ old('tipe_pembayaran', $customer->tipe_pembayaran) === $tb ? 'checked' : '' }} class="sr-only">
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
                            value="{{ old('tempo_kredit', $customer->tempo_kredit) }}"
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
                            value="{{ old('kredit_limit', $customer->kredit_limit) > 0 ? number_format(old('kredit_limit', $customer->kredit_limit), 0, ',', '.') : '' }}"
                            oninput="handleKreditLimitInput(this.value)"
                            required
                            placeholder="0"
                            class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d] font-bold text-gray-900 font-mono"
                        >
                        <input
                            type="hidden"
                            name="kredit_limit"
                            id="kredit_limit"
                            value="{{ old('kredit_limit', $customer->kredit_limit) }}"
                        >
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
                placeholder="Catatan profil customer, preferensi penagihan, atau instruksi khusus..."
                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
            >{{ old('keterangan', $customer->keterangan) }}</textarea>
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
                Simpan Perubahan
            </button>
        </div>
    </form>
</div>

<script>
    function handleKreditLimitInput(rawVal) {
        const clean = String(rawVal).replace(/\D/g, '');
        const num = clean ? parseFloat(clean) : 0;
        document.getElementById('kredit_limit').value = num;
        document.getElementById('display_kredit_limit').value = num > 0 ? Number(num).toLocaleString('id-ID') : '';
    }
</script>
@endsection
