@extends('layouts.app')

@section('title', 'Buat Jurnal Penyesuaian Manual – Duta Tunggal ERP')

@section('content')
<div class="space-y-6" x-data="manualJournalForm({
    initialReference: '{{ old('reference', $suggestedReference) }}',
    initialEntries: {{ Js::from(old('entries', [
        ['coa_id' => '', 'description' => '', 'debit' => '', 'credit' => ''],
        ['coa_id' => '', 'description' => '', 'debit' => '', 'credit' => ''],
    ])) }}
})">
    {{-- Header Form --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('keuangan.journal.index') }}" class="hover:text-gray-700">Buku Besar &amp; Jurnal</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Input Jurnal Penyesuaian Manual</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Buat Jurnal Penyesuaian Manual</h1>
            <p class="text-sm text-gray-500 mt-0.5">Membukukan entri penyesuaian, koreksi, penyusutan aset, atau voucher jurnal manual dengan validasi keseimbangan debit dan kredit.</p>
        </div>
        <div>
            <a
                href="{{ route('keuangan.journal.index') }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-xs font-semibold rounded-lg shadow-sm transition"
            >
                &larr; Kembali ke Daftar
            </a>
        </div>
    </div>

    {{-- Form Input --}}
    <form action="{{ route('keuangan.journal.store') }}" method="POST" class="space-y-6">
        @csrf

        {{-- Kartu 1: Header Dokumen Jurnal --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 sm:p-6 space-y-4">
            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2.5 flex items-center gap-2">
                <span class="p-1.5 bg-emerald-50 text-[#15803d] rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </span>
                1. Informasi Voucher Jurnal
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                {{-- No. Referensi / Voucher --}}
                <div>
                    <label for="reference" class="block text-xs font-semibold text-gray-700 mb-1">
                        No. Referensi / Voucher <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="reference"
                        id="reference"
                        value="{{ old('reference', $suggestedReference) }}"
                        required
                        class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 font-mono font-bold text-gray-900 uppercase @error('reference') border-rose-300 bg-rose-50/50 @enderror"
                        placeholder="JV-YYYYMMDD-XXXX"
                    >
                    @error('reference')
                        <p class="mt-1 text-xs text-rose-600 font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Tanggal Jurnal --}}
                <div>
                    <label for="date" class="block text-xs font-semibold text-gray-700 mb-1">
                        Tanggal Jurnal <span class="text-rose-500">*</span>
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

                {{-- Tipe Jurnal --}}
                <div>
                    <label for="journal_type" class="block text-xs font-semibold text-gray-700 mb-1">
                        Tipe Jurnal <span class="text-rose-500">*</span>
                    </label>
                    <select
                        name="journal_type"
                        id="journal_type"
                        required
                        class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-emerald-500 text-gray-900 bg-white"
                    >
                        <option value="manual" {{ old('journal_type') == 'manual' ? 'selected' : '' }}>Jurnal Manual (General)</option>
                        <option value="adjustment" {{ old('journal_type') == 'adjustment' ? 'selected' : '' }}>Jurnal Penyesuaian (Adjustment)</option>
                        <option value="reversal" {{ old('journal_type') == 'reversal' ? 'selected' : '' }}>Jurnal Pembalik (Reversal)</option>
                    </select>
                </div>

                {{-- Cabang --}}
                <div>
                    <label for="cabang_id" class="block text-xs font-semibold text-gray-700 mb-1">
                        Cabang Operasional
                    </label>
                    <x-searchable-select name="cabang_id" :options="collect($cabangs)->map(fn ($cabang) => ['id' => $cabang->id, 'label' => $cabang->nama . ' (' . $cabang->kode . ')'])->values()" :value="(old('cabang_id', Auth::user()?->cabang_id)) ?: collect($cabangs)->first()?->id" size="sm" id="cabang_id" />
                </div>

                {{-- Keterangan / Deskripsi Umum --}}
                <div class="sm:col-span-2 lg:col-span-4">
                    <label for="description" class="block text-xs font-semibold text-gray-700 mb-1">
                        Keterangan Umum Voucher Jurnal <span class="text-rose-500">*</span>
                    </label>
                    <textarea
                        name="description"
                        id="description"
                        rows="2"
                        required
                        class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-emerald-500 text-gray-900 @error('description') border-rose-300 bg-rose-50/50 @enderror"
                        placeholder="Contoh: Penyesuaian biaya sewa dibayar di muka bulan Oktober, koreksi salah input akun, dsb."
                    >{{ old('description') }}</textarea>
                    @error('description')
                        <p class="mt-1 text-xs text-rose-600 font-semibold">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Kartu 2: Baris Jurnal Akun (Debit & Credit Multi-Row) --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 sm:p-6 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-gray-100 pb-2.5">
                <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
                    <span class="p-1.5 bg-blue-50 text-blue-600 rounded-lg">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                    </span>
                    2. Baris Akun Debit &amp; Kredit
                </h2>

                <div class="flex items-center gap-2">
                    <span
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border transition"
                        :class="isBalanced ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200'"
                    >
                        <span class="w-2 h-2 rounded-full" :class="isBalanced ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                        <span x-text="isBalanced ? 'Seimbang / Balanced' : 'Belum Seimbang (Selisih: Rp ' + formatRupiah(difference) + ')'"></span>
                    </span>
                </div>
            </div>

            <div class="border border-gray-200 rounded-lg overflow-hidden">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50 text-gray-600 font-bold uppercase text-[11px] border-b border-gray-200">
                        <tr>
                            <th class="py-2.5 px-3 w-5/12">Akun Chart of Account (COA) <span class="text-rose-500">*</span></th>
                            <th class="py-2.5 px-3">Keterangan Baris (Memo)</th>
                            <th class="py-2.5 px-3 w-40 text-right">Debit (Rp)</th>
                            <th class="py-2.5 px-3 w-40 text-right">Kredit (Rp)</th>
                            <th class="py-2.5 px-3 w-12 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <template x-for="(line, index) in entries" :key="index">
                            <tr class="hover:bg-gray-50/60">
                                {{-- Pilih Akun COA --}}
                                <td class="py-2 px-3">
                                    <select data-searchable
                                        :name="'entries[' + index + '][coa_id]'"
                                        x-model="line.coa_id"
                                        required
                                        class="w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 bg-white"
                                    >
                                        <option value="">-- Pilih Akun COA --</option>
                                        @foreach ($chartOfAccounts as $acc)
                                            <option value="{{ $acc->id }}">
                                                {{ $acc->code }} - {{ $acc->name }} ({{ $acc->type }})
                                            </option>
                                        @endforeach
                                    </select>
                                </td>

                                {{-- Keterangan Baris --}}
                                <td class="py-2 px-3">
                                    <input
                                        type="text"
                                        :name="'entries[' + index + '][description]'"
                                        x-model="line.description"
                                        class="w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                                        placeholder="Keterangan spesifik baris akun ini..."
                                    >
                                </td>

                                {{-- Debit --}}
                                <td class="py-2 px-3 text-right">
                                    <input
                                        type="number"
                                        step="any"
                                        min="0"
                                        :name="'entries[' + index + '][debit]'"
                                        x-model.number="line.debit"
                                        @input="onDebitInput(index)"
                                        class="w-full px-2.5 py-1.5 text-xs text-right font-mono font-bold border border-gray-300 rounded focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                                        placeholder="0"
                                    >
                                </td>

                                {{-- Kredit --}}
                                <td class="py-2 px-3 text-right">
                                    <input
                                        type="number"
                                        step="any"
                                        min="0"
                                        :name="'entries[' + index + '][credit]'"
                                        x-model.number="line.credit"
                                        @input="onCreditInput(index)"
                                        class="w-full px-2.5 py-1.5 text-xs text-right font-mono font-bold border border-gray-300 rounded focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                                        placeholder="0"
                                    >
                                </td>

                                {{-- Hapus Baris --}}
                                <td class="py-2 px-3 text-center">
                                    <button
                                        type="button"
                                        @click="removeLine(index)"
                                        :disabled="entries.length <= 2"
                                        class="p-1 text-gray-400 hover:text-rose-600 rounded transition disabled:opacity-30 disabled:cursor-not-allowed"
                                        title="Hapus Baris"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot class="bg-gray-50 border-t-2 border-gray-200 font-bold font-mono">
                        <tr>
                            <td colspan="2" class="py-2.5 px-3 text-right text-gray-700 font-sans uppercase text-[11px]">
                                Total Keseimbangan:
                            </td>
                            <td class="py-2.5 px-3 text-right text-emerald-800 text-sm">
                                Rp <span x-text="formatRupiah(totalDebit)"></span>
                            </td>
                            <td class="py-2.5 px-3 text-right text-blue-800 text-sm">
                                Rp <span x-text="formatRupiah(totalCredit)"></span>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="flex items-center justify-between">
                <button
                    type="button"
                    @click="addLine()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-lg transition"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    + Tambah Baris Akun
                </button>

                <p class="text-[11px] text-gray-400">Minimal 2 baris akun. Nilai Total Debit dan Total Kredit harus sama persis.</p>
            </div>
        </div>

        {{-- Action Buttons --}}
        <div class="flex items-center justify-end gap-3 pt-2">
            <a
                href="{{ route('keuangan.journal.index') }}"
                class="px-4 py-2.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-xs font-semibold rounded-lg shadow-sm transition"
            >
                Batal
            </a>
            <button
                type="submit"
                :disabled="!isBalanced || totalDebit <= 0"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#15803d] hover:bg-[#14532d] text-white text-xs font-bold rounded-lg shadow-sm transition active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
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
function manualJournalForm(config) {
    return {
        entries: Array.isArray(config.initialEntries) && config.initialEntries.length >= 2
            ? config.initialEntries
            : [
                { coa_id: '', description: '', debit: '', credit: '' },
                { coa_id: '', description: '', debit: '', credit: '' }
            ],

        addLine() {
            this.entries.push({
                coa_id: '',
                description: '',
                debit: '',
                credit: ''
            });
        },

        removeLine(index) {
            if (this.entries.length > 2) {
                this.entries.splice(index, 1);
            }
        },

        onDebitInput(index) {
            if (parseFloat(this.entries[index].debit) > 0) {
                this.entries[index].credit = '';
            }
        },

        onCreditInput(index) {
            if (parseFloat(this.entries[index].credit) > 0) {
                this.entries[index].debit = '';
            }
        },

        get totalDebit() {
            let sum = 0;
            this.entries.forEach(line => {
                const val = parseFloat(line.debit);
                if (!isNaN(val)) sum += val;
            });
            return sum;
        },

        get totalCredit() {
            let sum = 0;
            this.entries.forEach(line => {
                const val = parseFloat(line.credit);
                if (!isNaN(val)) sum += val;
            });
            return sum;
        },

        get difference() {
            return Math.abs(this.totalDebit - this.totalCredit);
        },

        get isBalanced() {
            return this.difference < 0.01 && this.totalDebit > 0;
        },

        formatRupiah(num) {
            const val = parseFloat(num);
            if (isNaN(val)) return '0';
            return new Intl.NumberFormat('id-ID').format(val);
        }
    };
}
</script>
@endsection
