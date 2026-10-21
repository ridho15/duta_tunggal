@extends('layouts.app')

@section('title', 'Edit Pembayaran Hutang ' . $payment->payment_number . ' – Duta Tunggal ERP')

@section('content')
@php
    $allocations = [];
    $selectedInvoices = [];
    foreach ($payment->vendorPaymentDetail as $d) {
        $selectedInvoices[] = (int)$d->invoice_id;
        $allocations[$d->invoice_id] = (float)$d->amount;
    }
@endphp
<div class="space-y-6" x-data="vendorPaymentEditForm({
    supplierId: '{{ $payment->supplier_id }}',
    targetBank: '{{ old('target_bank_account', $payment->target_bank_account ?? '') }}',
    initialAllocations: {{ Js::from(old('allocations', $allocations)) }},
    initialSelectedInvoices: {{ Js::from(old('selected_invoices', $selectedInvoices)) }},
    invoicesUrl: '{{ url('/pembelian/pembayaran-supplier-invoices') }}'
})">
    {{-- Header Form --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('pembelian.payments.index') }}" class="hover:text-gray-700">Pembayaran Hutang</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Edit Draft {{ $payment->payment_number }}</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Edit Draft Pembayaran Vendor</h1>
            <p class="text-sm text-gray-500 mt-0.5">Sesuaikan alokasi faktur, rekening pengeluar dana, atau tanggal pembayaran untuk draft ini.</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('pembelian.payments.show', $payment->id) }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-xs font-semibold rounded-lg shadow-xs transition"
            >
                &larr; Lihat Detail
            </a>
        </div>
    </div>

    {{-- Form Submission --}}
    <form action="{{ route('pembelian.payments.update', $payment->id) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Kartu 1: Data Vendor & Pembayaran --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 sm:p-6 space-y-4">
            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2.5 flex items-center gap-2">
                <span class="p-1.5 bg-emerald-50 text-[#15803d] rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                    </svg>
                </span>
                1. Data Vendor &amp; Rekening Pengeluar Dana
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                {{-- Supplier (Readonly for Edit) --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Supplier / Vendor
                    </label>
                    <input
                        type="text"
                        value="{{ $payment->supplier?->perusahaan ?? '-' }}"
                        readonly
                        class="w-full px-3.5 py-2 text-sm bg-gray-100 border border-gray-200 rounded-lg text-gray-700 font-bold cursor-not-allowed"
                    >
                    <input type="hidden" name="supplier_id" value="{{ $payment->supplier_id }}">
                </div>

                {{-- Tanggal Pembayaran --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Tanggal Pembayaran <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="date"
                        name="payment_date"
                        value="{{ old('payment_date', $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('Y-m-d') : now()->format('Y-m-d')) }}"
                        required
                        class="w-full px-3.5 py-2 text-sm bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                    >
                    @error('payment_date')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Metode Pembayaran --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Metode Pembayaran <span class="text-red-500">*</span>
                    </label>
                    <select
                        name="payment_method"
                        required
                        class="w-full px-3.5 py-2 text-sm bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                    >
                        @foreach (['Bank Transfer', 'Cash', 'Giro', 'Cheque', 'Deposit'] as $m)
                            <option value="{{ $m }}" {{ old('payment_method', $payment->payment_method) === $m ? 'selected' : '' }}>
                                {{ $m }}
                            </option>
                        @endforeach
                    </select>
                    @error('payment_method')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Akun Kas / Bank Pengeluar Dana (COA) --}}
                <div>
                    @php
                        $coaOptions = $cashBankAccounts->map(function ($c) {
                            return [
                                'value' => (string)$c->id,
                                'label' => "({$c->code}) {$c->name}",
                                'subtext' => 'Tipe: ' . $c->type . ' | Valuta: ' . ($c->currency_code ?? 'IDR'),
                            ];
                        })->values()->all();
                    @endphp
                    <x-searchable-select
                        name="coa_id"
                        label="Akun Kas / Bank Pengeluar"
                        :options="$coaOptions"
                        :value="old('coa_id', $payment->coa_id)"
                        placeholder="-- Pilih Akun Kas / Bank --"
                        required="true"
                    />
                    @error('coa_id')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Rekening Tujuan Supplier --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Rekening Tujuan Supplier
                    </label>
                    <input
                        type="text"
                        name="target_bank_account"
                        value="{{ old('target_bank_account', $payment->target_bank_account) }}"
                        class="w-full px-3.5 py-2 text-sm bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                    >
                    @error('target_bank_account')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Nomor Referensi Transfer / Bank --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        No. Referensi Transfer / Bank
                    </label>
                    <input
                        type="text"
                        name="transfer_reference_number"
                        value="{{ old('transfer_reference_number', $payment->transfer_reference_number) }}"
                        class="w-full px-3.5 py-2 text-sm bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                    >
                    @error('transfer_reference_number')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Kartu 2: Tagihan Faktur Pembelian --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 sm:p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
                    <span class="p-1.5 bg-blue-50 text-blue-600 rounded-lg">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </span>
                    2. Alokasi Pembayaran Faktur
                </h2>
            </div>

            {{-- Loading State --}}
            <div x-show="loadingInvoices" class="py-10 text-center text-gray-500">
                <div class="inline-flex items-center gap-2 text-xs font-medium">
                    <svg class="animate-spin h-5 w-5 text-[#15803d]" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Memuat daftar faktur...</span>
                </div>
            </div>

            {{-- Invoices Table --}}
            <div x-show="!loadingInvoices && invoices.length > 0" class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                    <thead class="bg-gray-50 text-gray-600 font-bold uppercase tracking-wider">
                        <tr>
                            <th class="px-3 py-2.5 w-10 text-center">Pilih</th>
                            <th class="px-3 py-2.5">No. Faktur Vendor / Sistem</th>
                            <th class="px-3 py-2.5">Tgl &amp; Jatuh Tempo</th>
                            <th class="px-3 py-2.5 text-right">Total Faktur</th>
                            <th class="px-3 py-2.5 text-right">Sisa Hutang (AP)</th>
                            <th class="px-3 py-2.5 text-right w-48">Nominal Bayar (Rp)</th>
                            <th class="px-3 py-2.5 text-right">Sisa Nanti</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <template x-for="inv in invoices" :key="inv.id">
                            <tr
                                class="hover:bg-gray-50/80 transition"
                                :class="{ 'bg-emerald-50/30': isSelected(inv.id) }"
                            >
                                <td class="px-3 py-3 text-center">
                                    <input
                                        type="checkbox"
                                        name="selected_invoices[]"
                                        :value="inv.id"
                                        :checked="isSelected(inv.id)"
                                        @change="toggleSelect(inv.id)"
                                        class="rounded text-[#15803d] focus:ring-[#15803d] h-4 w-4 border-gray-300"
                                    >
                                </td>
                                <td class="px-3 py-3">
                                    <div class="font-bold text-gray-900" x-text="inv.invoice_number"></div>
                                    <div class="text-[11px] text-gray-400" x-show="inv.supplier_invoice_number !== '-'">
                                        Vendor: <span class="text-gray-600 font-medium" x-text="inv.supplier_invoice_number"></span>
                                    </div>
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap">
                                    <div class="text-gray-700" x-text="inv.invoice_date"></div>
                                    <div class="text-[11px] text-gray-500" x-text="'Jatuh Tempo: ' + inv.due_date"></div>
                                </td>
                                <td class="px-3 py-3 text-right font-semibold text-gray-600" x-text="inv.total_formatted"></td>
                                <td class="px-3 py-3 text-right font-bold text-gray-900" x-text="inv.remaining_formatted"></td>
                                <td class="px-3 py-3 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            :max="inv.remaining"
                                            :name="'allocations[' + inv.id + ']'"
                                            x-model.number="allocations[inv.id]"
                                            :disabled="!isSelected(inv.id)"
                                            placeholder="0"
                                            class="w-32 px-2.5 py-1 text-right text-xs bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] disabled:bg-gray-100 disabled:text-gray-400 font-semibold text-gray-900"
                                        >
                                        <button
                                            type="button"
                                            @click="payFull(inv.id)"
                                            class="px-2 py-1 bg-gray-100 hover:bg-emerald-100 text-[#15803d] text-[10px] font-bold rounded border border-gray-200 transition"
                                        >
                                            Penuh
                                        </button>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-right whitespace-nowrap font-medium">
                                    <span
                                        :class="getProjectedRemaining(inv.id) === 0 ? 'text-[#15803d] font-bold' : 'text-gray-500'"
                                        x-text="formatRupiah(getProjectedRemaining(inv.id))"
                                    ></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Kartu 3: Ringkasan & Submit --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm p-5 sm:p-6 space-y-4">
                <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2.5">
                    Berkas Bukti &amp; Catatan
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                            Ganti Bukti Pembayaran / Transfer
                        </label>
                        <input
                            type="file"
                            name="proof_file"
                            accept="image/jpeg,image/png,image/jpg,application/pdf"
                            class="block w-full text-xs text-gray-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 border border-gray-300 rounded-lg cursor-pointer focus:outline-none"
                        >
                        @if ($payment->proof_file)
                            <p class="text-[11px] text-emerald-600 mt-1">File saat ini tersimpan: <a href="{{ Storage::url($payment->proof_file) }}" target="_blank" class="underline font-bold">Lihat Bukti</a></p>
                        @endif
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                            Catatan Internal
                        </label>
                        <textarea
                            name="notes"
                            rows="2"
                            class="w-full px-3 py-2 text-xs bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] transition"
                        >{{ old('notes', $payment->notes) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="bg-gray-50 rounded-xl border border-gray-200 p-5 sm:p-6 space-y-4 flex flex-col justify-between">
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-gray-700 border-b border-gray-200 pb-2">
                        Ringkasan Pembayaran
                    </h3>
                    <dl class="space-y-2.5 mt-3 text-xs">
                        <div class="flex justify-between items-center text-gray-600">
                            <span>Jumlah Faktur Dipilih</span>
                            <span class="font-bold text-gray-900" x-text="selectedInvoices.length + ' Faktur'"></span>
                        </div>
                        <div class="border-t border-gray-200 pt-2 flex justify-between items-center text-sm">
                            <span class="font-bold text-gray-900">Total Pembayaran</span>
                            <span class="font-bold text-xl text-[#15803d]" x-text="formatRupiah(calculateTotalPayment())"></span>
                        </div>
                    </dl>
                </div>

                <div class="space-y-2 pt-4 border-t border-gray-200">
                    <button
                        type="submit"
                        name="action"
                        value="save_and_post"
                        :disabled="selectedInvoices.length === 0 || calculateTotalPayment() <= 0"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-[#15803d] hover:bg-[#14532d] disabled:bg-gray-300 disabled:cursor-not-allowed text-white text-xs font-bold rounded-lg shadow-sm transition"
                    >
                        Perbarui &amp; Posting ke Buku Besar
                    </button>
                    <button
                        type="submit"
                        name="action"
                        value="save_draft"
                        class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-white border border-gray-300 hover:bg-gray-100 text-gray-700 text-xs font-semibold rounded-lg shadow-xs transition"
                    >
                        Simpan Perubahan Draft
                    </button>
                    <a
                        href="{{ route('pembelian.payments.show', $payment->id) }}"
                        class="block text-center text-xs text-gray-500 hover:text-gray-700 py-1"
                    >
                        Batal
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
function vendorPaymentEditForm(config) {
    return {
        supplierId: config.supplierId,
        targetBankAccount: config.targetBank || '',
        loadingInvoices: true,
        invoices: [],
        selectedInvoices: config.initialSelectedInvoices || [],
        allocations: config.initialAllocations || {},
        invoicesUrl: config.invoicesUrl,

        init() {
            fetch(`${this.invoicesUrl}/${this.supplierId}`)
                .then(res => res.json())
                .then(data => {
                    this.invoices = data.invoices || [];
                })
                .catch(err => console.error(err))
                .finally(() => {
                    this.loadingInvoices = false;
                });
        },

        isSelected(id) {
            return this.selectedInvoices.includes(Number(id));
        },

        toggleSelect(id) {
            const num = Number(id);
            const idx = this.selectedInvoices.indexOf(num);
            if (idx > -1) {
                this.selectedInvoices.splice(idx, 1);
                delete this.allocations[num];
            } else {
                this.selectedInvoices.push(num);
                const inv = this.invoices.find(i => Number(i.id) === num);
                if (inv && !this.allocations[num]) {
                    this.allocations[num] = inv.remaining;
                }
            }
        },

        payFull(id) {
            const num = Number(id);
            const inv = this.invoices.find(i => Number(i.id) === num);
            if (!inv) return;
            if (!this.isSelected(num)) this.selectedInvoices.push(num);
            this.allocations[num] = inv.remaining;
        },

        getProjectedRemaining(id) {
            const num = Number(id);
            const inv = this.invoices.find(i => Number(i.id) === num);
            if (!inv) return 0;
            const paid = Number(this.allocations[num]) || 0;
            return Math.max(0, inv.remaining - paid);
        },

        calculateTotalPayment() {
            return Object.entries(this.allocations).reduce((sum, [invId, amount]) => {
                if (this.isSelected(invId)) {
                    return sum + (Number(amount) || 0);
                }
                return sum;
            }, 0);
        },

        formatRupiah(num) {
            const n = Number(num) || 0;
            return 'Rp ' + n.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
        }
    };
}
</script>
@endpush
@endsection
