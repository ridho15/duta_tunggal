@extends('layouts.app')

@section('title', 'Catat Transaksi Kas & Bank – Duta Tunggal ERP')

@section('content')
<div class="space-y-6" x-data="cashBankForm({
    initialType: '{{ old('type', $defaultType) }}',
    initialMode: '{{ old('mode', (old('details') ? 'breakdown' : 'single')) }}',
    initialAmount: '{{ old('amount', '') }}',
    initialDetails: {{ Js::from(old('details', [])) }}
})">
    {{-- Header Form --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('keuangan.cashbank.index') }}" class="hover:text-gray-700">Kas &amp; Bank</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Catat Transaksi Baru</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Catat Transaksi Kas &amp; Bank</h1>
            <p class="text-sm text-gray-500 mt-0.5">Membukukan mutasi penerimaan kas/bank (masuk) atau pengeluaran operasional (keluar) beserta jurnal otomatis.</p>
        </div>
        <div>
            <a
                href="{{ route('keuangan.cashbank.index') }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-xs font-semibold rounded-lg shadow-sm transition"
            >
                &larr; Kembali ke Daftar
            </a>
        </div>
    </div>

    {{-- Form Input --}}
    <form action="{{ route('keuangan.cashbank.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf

        {{-- Kartu 1: Tipe Transaksi & Rekening Kas/Bank --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 sm:p-6 space-y-5">
            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2.5 flex items-center gap-2">
                <span class="p-1.5 bg-emerald-50 text-[#15803d] rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </span>
                1. Tipe Transaksi &amp; Akun Kas / Bank
            </h2>

            {{-- Pemilihan Tipe Transaksi Visual --}}
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Tipe Mutasi <span class="text-rose-500">*</span></label>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <label
                        class="border rounded-xl p-3.5 flex flex-col items-center justify-center text-center cursor-pointer transition relative"
                        :class="type === 'cash_out' ? 'border-amber-500 bg-amber-50/50 ring-1 ring-amber-500' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50/50'"
                    >
                        <input type="radio" name="type" value="cash_out" x-model="type" class="sr-only">
                        <span class="p-2 bg-amber-100 text-amber-700 rounded-lg mb-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"/></svg>
                        </span>
                        <span class="text-xs font-bold text-gray-900">Kas Keluar</span>
                        <span class="text-[10px] text-gray-500 mt-0.5">Pengeluaran kas kecil/tunai</span>
                    </label>

                    <label
                        class="border rounded-xl p-3.5 flex flex-col items-center justify-center text-center cursor-pointer transition relative"
                        :class="type === 'bank_out' ? 'border-rose-500 bg-rose-50/50 ring-1 ring-rose-500' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50/50'"
                    >
                        <input type="radio" name="type" value="bank_out" x-model="type" class="sr-only">
                        <span class="p-2 bg-rose-100 text-rose-700 rounded-lg mb-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        </span>
                        <span class="text-xs font-bold text-gray-900">Bank Keluar</span>
                        <span class="text-[10px] text-gray-500 mt-0.5">Transfer bayar via rekening</span>
                    </label>

                    <label
                        class="border rounded-xl p-3.5 flex flex-col items-center justify-center text-center cursor-pointer transition relative"
                        :class="type === 'cash_in' ? 'border-emerald-500 bg-emerald-50/50 ring-1 ring-emerald-500' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50/50'"
                    >
                        <input type="radio" name="type" value="cash_in" x-model="type" class="sr-only">
                        <span class="p-2 bg-emerald-100 text-emerald-700 rounded-lg mb-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"/></svg>
                        </span>
                        <span class="text-xs font-bold text-gray-900">Kas Masuk</span>
                        <span class="text-[10px] text-gray-500 mt-0.5">Penerimaan kas tunai</span>
                    </label>

                    <label
                        class="border rounded-xl p-3.5 flex flex-col items-center justify-center text-center cursor-pointer transition relative"
                        :class="type === 'bank_in' ? 'border-blue-500 bg-blue-50/50 ring-1 ring-blue-500' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50/50'"
                    >
                        <input type="radio" name="type" value="bank_in" x-model="type" class="sr-only">
                        <span class="p-2 bg-blue-100 text-blue-700 rounded-lg mb-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </span>
                        <span class="text-xs font-bold text-gray-900">Bank Masuk</span>
                        <span class="text-[10px] text-gray-500 mt-0.5">Penerimaan transfer masuk</span>
                    </label>
                </div>
                @error('type')
                    <p class="mt-1 text-xs text-rose-600 font-semibold">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 pt-2">
                {{-- No. Bukti Transaksi --}}
                <div>
                    <label for="number" class="block text-xs font-semibold text-gray-700 mb-1">
                        No. Bukti Transaksi <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="number"
                        id="number"
                        value="{{ old('number', $suggestedNumber) }}"
                        required
                        class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 font-mono font-bold text-gray-900 uppercase @error('number') border-rose-300 bg-rose-50/50 @enderror"
                        placeholder="CB-YYYYMMDD-XXXX"
                    >
                    @error('number')
                        <p class="mt-1 text-xs text-rose-600 font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Tanggal Transaksi --}}
                <div>
                    <label for="date" class="block text-xs font-semibold text-gray-700 mb-1">
                        Tanggal Transaksi <span class="text-rose-500">*</span>
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
                    <x-searchable-select name="cabang_id" :options="collect($cabangs)->map(fn ($cabang) => ['id' => $cabang->id, 'label' => $cabang->nama . ' (' . $cabang->kode . ')'])->values()" :value="(old('cabang_id', Auth::user()?->cabang_id)) ?: collect($cabangs)->first()?->id" size="sm" id="cabang_id" />
                </div>

                {{-- Rekening Kas / Bank --}}
                <div class="sm:col-span-2 lg:col-span-3">
                    @php
                        $cashBankOptions = $cashBankCoas->map(function ($c) {
                            return [
                                'value' => (string)$c->id,
                                'label' => $c->name,
                                'subtext' => 'Kode: ' . $c->code . ' | Tipe: ' . $c->type,
                            ];
                        })->values()->all();
                    @endphp
                    <x-searchable-select
                        name="account_coa_id"
                        label="Pilih Rekening Kas / Bank (Sumber/Tujuan Dana)"
                        :options="$cashBankOptions"
                        :value="old('account_coa_id', '')"
                        placeholder="-- Cari dan Pilih Akun Kas atau Bank (1111* / 1112*) --"
                        required="true"
                    />
                    @error('account_coa_id')
                        <p class="mt-1 text-xs text-rose-600 font-semibold">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Kartu 2: Nominal & Alokasi Akun Lawan --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 sm:p-6 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-gray-100 pb-2.5">
                <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
                    <span class="p-1.5 bg-blue-50 text-blue-600 rounded-lg">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                    </span>
                    2. Nominal &amp; Alokasi Akun Lawan
                </h2>

                {{-- Mode Switch (Single vs Breakdown) --}}
                <div class="flex items-center gap-2 bg-gray-100 p-1 rounded-lg">
                    <button
                        type="button"
                        @click="setMode('single')"
                        :class="mode === 'single' ? 'bg-white text-gray-900 shadow-xs font-bold' : 'text-gray-500 hover:text-gray-900 font-medium'"
                        class="px-2.5 py-1 text-xs rounded-md transition"
                    >
                        Transaksi Tunggal (1 Akun)
                    </button>
                    <button
                        type="button"
                        @click="setMode('breakdown')"
                        :class="mode === 'breakdown' ? 'bg-white text-gray-900 shadow-xs font-bold' : 'text-gray-500 hover:text-gray-900 font-medium'"
                        class="px-2.5 py-1 text-xs rounded-md transition"
                    >
                        Rincian Multi-Akun (Breakdown)
                    </button>
                </div>
            </div>

            <input type="hidden" name="mode" :value="mode">

            {{-- MODE TUNGGAL (1 AKUN LAWAN) --}}
            <div x-show="mode === 'single'" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {{-- Akun Lawan --}}
                    <div>
                        @php
                            $offsetOptions = $offsetCoas->map(function ($c) {
                                return [
                                    'value' => (string)$c->id,
                                    'label' => $c->name,
                                    'subtext' => 'Kode: ' . $c->code . ' | Tipe: ' . $c->type,
                                ];
                            })->values()->all();
                        @endphp
                        <x-searchable-select
                            name="offset_coa_id"
                            label="Akun Lawan (Pos Beban / Pendapatan / Kewajiban)"
                            :options="$offsetOptions"
                            :value="old('offset_coa_id', '')"
                            placeholder="-- Pilih Akun Lawan --"
                        />
                        @error('offset_coa_id')
                            <p class="mt-1 text-xs text-rose-600 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Jumlah Total --}}
                    <div>
                        <label for="amount_single" class="block text-xs font-semibold text-gray-700 mb-1">
                            Jumlah Nominal (Rp) <span class="text-rose-500">*</span>
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
                                id="amount_single"
                                x-model="amount"
                                class="w-full pl-9 pr-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 font-bold text-gray-900 @error('amount') border-rose-300 bg-rose-50/50 @enderror"
                                placeholder="0"
                            >
                        </div>
                        @error('amount')
                            <p class="mt-1 text-xs text-rose-600 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- MODE RINCIAN (BREAKDOWN MULTI-AKUN) --}}
            <div x-show="mode === 'breakdown'" class="space-y-3">
                <p class="text-xs text-gray-500">Gunakan rincian jika 1 transaksi membiayai beberapa pos akun (misal: Biaya Listrik + Biaya Air + Biaya Kebersihan). Total rincian akan otomatis menjadi total transaksi.</p>

                <div class="border border-gray-200 rounded-lg overflow-hidden">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 border-b border-gray-200 text-gray-600 font-bold uppercase text-[11px]">
                            <tr>
                                <th class="py-2.5 px-3 w-1/3">Akun COA Rincian</th>
                                <th class="py-2.5 px-3">Keterangan / Memo</th>
                                <th class="py-2.5 px-3 w-40 text-right">Nominal (Rp)</th>
                                <th class="py-2.5 px-3 w-12 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <template x-for="(detail, index) in details" :key="index">
                                <tr class="hover:bg-gray-50/60">
                                    <td class="py-2 px-3">
                                        <select data-searchable
                                            :name="'details[' + index + '][chart_of_account_id]'"
                                            x-model="detail.chart_of_account_id"
                                            class="w-full px-2 py-1.5 text-xs border border-gray-300 rounded focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 bg-white"
                                            required
                                        >
                                            <option value="">-- Pilih Akun --</option>
                                            @foreach ($offsetCoas as $c)
                                                <option value="{{ $c->id }}">{{ $c->code }} - {{ $c->name }} ({{ $c->type }})</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="py-2 px-3">
                                        <input
                                            type="text"
                                            :name="'details[' + index + '][description]'"
                                            x-model="detail.description"
                                            class="w-full px-2 py-1.5 text-xs border border-gray-300 rounded focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                                            placeholder="Deskripsi rincian pos..."
                                        >
                                    </td>
                                    <td class="py-2 px-3 text-right">
                                        <input
                                            type="number"
                                            step="any"
                                            min="0"
                                            :name="'details[' + index + '][amount]'"
                                            x-model.number="detail.amount"
                                            @input="recalculateBreakdownTotal()"
                                            class="w-full px-2 py-1.5 text-xs text-right font-bold border border-gray-300 rounded focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                                            placeholder="0"
                                            required
                                        >
                                    </td>
                                    <td class="py-2 px-3 text-center">
                                        <button
                                            type="button"
                                            @click="removeDetail(index)"
                                            class="p-1 text-gray-400 hover:text-rose-600 rounded transition"
                                            title="Hapus Baris"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot class="bg-gray-50 border-t border-gray-200 font-bold">
                            <tr>
                                <td colspan="2" class="py-2.5 px-3 text-right text-gray-700">
                                    Total Akumulasi Rincian:
                                </td>
                                <td class="py-2.5 px-3 text-right text-sm text-emerald-700 font-bold">
                                    Rp <span x-text="formatNumber(amount)"></span>
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="flex justify-start">
                    <button
                        type="button"
                        @click="addDetail()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-lg transition"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        + Tambah Rincian Akun
                    </button>
                </div>
            </div>
        </div>

        {{-- Kartu 3: Pihak Terkait, Keterangan & Lampiran --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 sm:p-6 space-y-4">
            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2.5 flex items-center gap-2">
                <span class="p-1.5 bg-purple-50 text-purple-600 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                    </svg>
                </span>
                3. Pihak Terkait, Catatan &amp; Dokumen Lampiran
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- Pihak Terkait --}}
                <div>
                    <label for="counterparty" class="block text-xs font-semibold text-gray-700 mb-1">
                        Pihak Terkait (Penyetor / Penerima / Rekanan)
                    </label>
                    <input
                        type="text"
                        name="counterparty"
                        id="counterparty"
                        value="{{ old('counterparty') }}"
                        class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 text-gray-900"
                        placeholder="Contoh: PLN, PDAM, Bpk. Ahmad, PT Toko Jaya..."
                    >
                </div>

                {{-- Upload Lampiran --}}
                <div>
                    <label for="attachment" class="block text-xs font-semibold text-gray-700 mb-1">
                        Lampiran Bukti Transaksi (Struk / Kuitansi / Bukti Transfer)
                    </label>
                    <input
                        type="file"
                        name="attachment"
                        id="attachment"
                        accept=".pdf,.jpg,.jpeg,.png"
                        class="w-full px-3 py-1.5 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-emerald-500 text-gray-700 bg-white"
                    >
                    <p class="text-[10.5px] text-gray-400 mt-1">Format PDF, JPG, PNG (Maks 5 MB)</p>
                </div>

                {{-- Catatan / Keterangan --}}
                <div class="sm:col-span-2">
                    <label for="description" class="block text-xs font-semibold text-gray-700 mb-1">
                        Keterangan / Memo Transaksi
                    </label>
                    <textarea
                        name="description"
                        id="description"
                        rows="3"
                        class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 text-gray-900"
                        placeholder="Tuliskan keterangan detail transaksi operasional ini..."
                    >{{ old('description') }}</textarea>
                </div>
            </div>
        </div>

        {{-- Action Buttons --}}
        <div class="flex items-center justify-end gap-3 pt-2">
            <a
                href="{{ route('keuangan.cashbank.index') }}"
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
                Simpan &amp; Bukukan Jurnal
            </button>
        </div>
    </form>
</div>

<script>
function cashBankForm(config) {
    return {
        type: config.initialType || 'cash_out',
        mode: config.initialMode || 'single',
        amount: config.initialAmount || '',
        details: Array.isArray(config.initialDetails) && config.initialDetails.length > 0 
            ? config.initialDetails 
            : [{ chart_of_account_id: '', description: '', amount: '' }],

        setMode(newMode) {
            this.mode = newMode;
            if (newMode === 'breakdown') {
                if (this.details.length === 0) {
                    this.addDetail();
                }
                this.recalculateBreakdownTotal();
            }
        },

        addDetail() {
            this.details.push({
                chart_of_account_id: '',
                description: '',
                amount: ''
            });
        },

        removeDetail(index) {
            if (this.details.length > 1) {
                this.details.splice(index, 1);
                this.recalculateBreakdownTotal();
            }
        },

        recalculateBreakdownTotal() {
            let total = 0;
            this.details.forEach(item => {
                const val = parseFloat(item.amount);
                if (!isNaN(val)) {
                    total += val;
                }
            });
            this.amount = total;
        },

        formatNumber(num) {
            const val = parseFloat(num);
            if (isNaN(val)) return '0';
            return new Intl.NumberFormat('id-ID').format(val);
        }
    };
}
</script>
@endsection
