@extends('layouts.app')

@section('title', 'Transfer Antar Kas & Bank – Duta Tunggal ERP')

@section('content')
<div class="space-y-6" x-data="{
    amount: '{{ old('amount', '') }}',
    otherCosts: '{{ old('other_costs', 0) }}',
    get totalDikeluarkan() {
        const a = parseFloat(this.amount) || 0;
        const b = parseFloat(this.otherCosts) || 0;
        return a + b;
    },
    formatRupiah(num) {
        return new Intl.NumberFormat('id-ID').format(num);
    }
}">
    {{-- Header Form --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('keuangan.cashbank.index') }}" class="hover:text-gray-700">Kas &amp; Bank</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Transfer Antar Rekening</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Transfer Antar Kas &amp; Bank</h1>
            <p class="text-sm text-gray-500 mt-0.5">Pemindahan saldo antar rekening bank, penarikan tunai dari bank ke kas kecil, atau penyetoran uang kas ke rekening bank.</p>
        </div>
        <div>
            <a
                href="{{ route('keuangan.cashbank.index', ['tab' => 'transfer']) }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-xs font-semibold rounded-lg shadow-sm transition"
            >
                &larr; Kembali ke Daftar
            </a>
        </div>
    </div>

    {{-- Form Input --}}
    <form action="{{ route('keuangan.cashbank.transfer.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf

        {{-- Kartu 1: Informasi Dokumen & Rekening Asal - Tujuan --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 sm:p-6 space-y-5">
            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2.5 flex items-center gap-2">
                <span class="p-1.5 bg-emerald-50 text-[#15803d] rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                </span>
                1. Rekening Sumber Dana &amp; Rekening Tujuan
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                {{-- No. Transfer --}}
                <div>
                    <label for="number" class="block text-xs font-semibold text-gray-700 mb-1">
                        No. Bukti Transfer <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="number"
                        id="number"
                        value="{{ old('number', $suggestedNumber) }}"
                        required
                        class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 font-mono font-bold text-gray-900 uppercase @error('number') border-rose-300 bg-rose-50/50 @enderror"
                        placeholder="TRF-YYYYMMDD-XXXX"
                    >
                    @error('number')
                        <p class="mt-1 text-xs text-rose-600 font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Tanggal Transfer --}}
                <div>
                    <label for="date" class="block text-xs font-semibold text-gray-700 mb-1">
                        Tanggal Transfer <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="date"
                        name="date"
                        id="date"
                        value="{{ old('date', now()->toDateString()) }}"
                        required
                        class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 text-gray-900 @error('date') border-rose-300 bg-rose-50/50 @enderror"
                    >
                    @error('date')
                        <p class="mt-1 text-xs text-rose-600 font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Cabang --}}
                <div>
                    <label for="cabang_id" class="block text-xs font-semibold text-gray-700 mb-1">
                        Cabang Operasional
                    </label>
                    <select
                        name="cabang_id"
                        id="cabang_id"
                        class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 text-gray-900 bg-white"
                    >
                        @foreach ($cabangs as $cabang)
                            <option value="{{ $cabang->id }}" {{ old('cabang_id', Auth::user()?->cabang_id) == $cabang->id ? 'selected' : '' }}>
                                {{ $cabang->nama }} ({{ $cabang->kode }})
                            </option>
                        @endforeach
                    </select>
                </div>

                @php
                    $cashBankOptions = $cashBankCoas->map(function ($c) {
                        return [
                            'value' => (string)$c->id,
                            'label' => $c->name,
                            'subtext' => 'Kode: ' . $c->code . ' | ' . $c->type,
                        ];
                    })->values()->all();
                @endphp

                {{-- Dari Kas / Bank --}}
                <div class="sm:col-span-2 lg:col-span-1">
                    <x-searchable-select
                        name="from_coa_id"
                        label="Dari Rekening (Sumber Dana)"
                        :options="$cashBankOptions"
                        :value="old('from_coa_id', '')"
                        placeholder="-- Pilih Rekening Asal --"
                        required="true"
                    />
                    @error('from_coa_id')
                        <p class="mt-1 text-xs text-rose-600 font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Ke Kas / Bank --}}
                <div class="sm:col-span-2 lg:col-span-1">
                    <x-searchable-select
                        name="to_coa_id"
                        label="Ke Rekening (Tujuan Transfer)"
                        :options="$cashBankOptions"
                        :value="old('to_coa_id', '')"
                        placeholder="-- Pilih Rekening Tujuan --"
                        required="true"
                    />
                    @error('to_coa_id')
                        <p class="mt-1 text-xs text-rose-600 font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Referensi Transaksi Bank --}}
                <div class="sm:col-span-2 lg:col-span-1">
                    <label for="reference" class="block text-xs font-semibold text-gray-700 mb-1">
                        No. Referensi / Transaksi Bank
                    </label>
                    <input
                        type="text"
                        name="reference"
                        id="reference"
                        value="{{ old('reference') }}"
                        class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 text-gray-900"
                        placeholder="Contoh: REF982312, Kliring, dsb."
                    >
                </div>
            </div>
        </div>

        {{-- Kartu 2: Nominal & Biaya Admin --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 sm:p-6 space-y-4">
            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2.5 flex items-center gap-2">
                <span class="p-1.5 bg-blue-50 text-blue-600 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
                2. Nominal Transfer &amp; Biaya Administrasi
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                {{-- Nominal Transfer --}}
                <div>
                    <label for="amount" class="block text-xs font-semibold text-gray-700 mb-1">
                        Nominal Transfer (Dana yang Masuk ke Tujuan) <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-xs font-bold text-gray-400">
                            Rp
                        </span>
                        <input
                            type="number"
                            step="any"
                            min="0.01"
                            name="amount"
                            id="amount"
                            x-model="amount"
                            required
                            class="w-full pl-9 pr-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 font-bold text-gray-900 @error('amount') border-rose-300 bg-rose-50/50 @enderror"
                            placeholder="0"
                        >
                    </div>
                    @error('amount')
                        <p class="mt-1 text-xs text-rose-600 font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Biaya Administrasi Bank --}}
                <div>
                    <label for="other_costs" class="block text-xs font-semibold text-gray-700 mb-1">
                        Biaya Admin Bank / Transfer (Jika Ada)
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-xs font-bold text-gray-400">
                            Rp
                        </span>
                        <input
                            type="number"
                            step="any"
                            min="0"
                            name="other_costs"
                            id="other_costs"
                            x-model="otherCosts"
                            class="w-full pl-9 pr-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 font-bold text-gray-900"
                            placeholder="0"
                        >
                    </div>
                </div>

                {{-- Akun Beban Admin Bank --}}
                <div>
                    <label for="other_costs_coa_id" class="block text-xs font-semibold text-gray-700 mb-1">
                        Pos Akun Biaya Admin Bank
                    </label>
                    <select
                        name="other_costs_coa_id"
                        id="other_costs_coa_id"
                        class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 text-gray-900 bg-white"
                    >
                        <option value="">-- Pilih Akun Biaya Admin --</option>
                        @foreach ($expenseCoas as $exp)
                            <option value="{{ $exp->id }}" {{ (old('other_costs_coa_id') == $exp->id || str_contains(strtolower($exp->name), 'admin')) ? 'selected' : '' }}>
                                {{ $exp->code }} - {{ $exp->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Ringkasan Pengeluaran Saldo Asal --}}
            <div class="p-3 bg-emerald-50/70 border border-emerald-200 rounded-lg flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs">
                <div>
                    <span class="font-bold text-emerald-900">Total Pengurangan Saldo di Rekening Asal:</span>
                    <span class="text-emerald-700 block text-[11px] mt-0.5">Nominal Transfer (Rp <span x-text="formatRupiah(parseFloat(amount) || 0)"></span>) + Biaya Admin (Rp <span x-text="formatRupiah(parseFloat(otherCosts) || 0)"></span>)</span>
                </div>
                <div class="text-right">
                    <span class="text-base font-bold text-emerald-900">Rp <span x-text="formatRupiah(totalDikeluarkan)"></span></span>
                </div>
            </div>
        </div>

        {{-- Kartu 3: Keterangan & Bukti Transfer --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 sm:p-6 space-y-4">
            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2.5 flex items-center gap-2">
                <span class="p-1.5 bg-purple-50 text-purple-600 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                    </svg>
                </span>
                3. Catatan &amp; Lampiran Bukti Transfer
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- Upload Bukti Transfer --}}
                <div class="sm:col-span-2">
                    <label for="attachment" class="block text-xs font-semibold text-gray-700 mb-1">
                        Upload Bukti Transfer Bank (Slip / Resi / Screenshot Mobile Banking)
                    </label>
                    <input
                        type="file"
                        name="attachment"
                        id="attachment"
                        accept=".pdf,.jpg,.jpeg,.png"
                        class="w-full px-3 py-1.5 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-emerald-500 text-gray-700 bg-white"
                    >
                    <p class="text-[10.5px] text-gray-400 mt-1">Format PDF, JPG, PNG (Maksimal 5 MB)</p>
                </div>

                {{-- Keterangan --}}
                <div class="sm:col-span-2">
                    <label for="description" class="block text-xs font-semibold text-gray-700 mb-1">
                        Keterangan / Keperluan Transfer
                    </label>
                    <textarea
                        name="description"
                        id="description"
                        rows="3"
                        class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 text-gray-900"
                        placeholder="Contoh: Pemindahan dana operasional cabang, penarikan tunai kas kecil mingguan, dsb."
                    >{{ old('description') }}</textarea>
                </div>
            </div>
        </div>

        {{-- Action Buttons --}}
        <div class="flex items-center justify-end gap-3 pt-2">
            <a
                href="{{ route('keuangan.cashbank.index', ['tab' => 'transfer']) }}"
                class="px-4 py-2.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-xs font-semibold rounded-lg shadow-sm transition"
            >
                Batal
            </a>
            <button
                type="submit"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#15803d] hover:bg-[#14532d] text-white text-xs font-bold rounded-lg shadow-sm transition active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:ring-offset-2"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Proses Transfer &amp; Bukukan Jurnal
            </button>
        </div>
    </form>
</div>
@endsection
