@extends('layouts.app')

@section('title', 'Catat Pembayaran Hutang Vendor – Duta Tunggal ERP')

@section('content')
<div class="space-y-6" x-data="vendorPaymentForm({
    initialSupplierId: '{{ old('supplier_id', $preselectedSupplierId ?? '') }}',
    initialInvoiceId: '{{ old('preselected_invoice_id', $preselectedInvoiceId ?? '') }}',
    initialTargetBank: '{{ old('target_bank_account', '') }}',
    initialAllocations: {{ Js::from(old('allocations', [])) }},
    initialSelectedInvoices: {{ Js::from(old('selected_invoices', [])) }},
    invoicesUrl: '{{ url('/pembelian/pembayaran-supplier-invoices') }}'
})">
    {{-- Header Form --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('pembelian.payments.index') }}" class="hover:text-gray-700">Pembayaran Hutang</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Catat Pembayaran Baru</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Catat Pembayaran Hutang Vendor</h1>
            <p class="text-sm text-gray-500 mt-0.5">Pilih pemasok, tentukan faktur yang akan dilunasi, pilih akun kas/bank, dan tentukan nominal pembayaran.</p>
        </div>
        <div>
            <a
                href="{{ route('pembelian.payments.index') }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-xs font-semibold rounded-lg shadow-xs transition"
            >
                &larr; Kembali ke Daftar
            </a>
        </div>
    </div>

    {{-- Form Submission --}}
    <form action="{{ route('pembelian.payments.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf

        {{-- Kartu 1: Informasi Pembayaran & Vendor --}}
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
                {{-- Supplier Selection --}}
                <div class="sm:col-span-2 lg:col-span-1" @change="onSupplierChange($event.target.value)">
                    @php
                        $supplierOptions = $suppliers->map(function ($s) {
                            return [
                                'value' => (string)$s->id,
                                'label' => $s->perusahaan ?: 'Supplier #' . $s->id,
                                'subtext' => 'Kode: ' . ($s->code ?: '-') . ' | Bank: ' . ($s->nama_bank ?: $s->bank_name ?: '-'),
                            ];
                        })->values()->all();
                    @endphp
                    <x-searchable-select
                        name="supplier_id"
                        label="Supplier / Vendor"
                        :options="$supplierOptions"
                        :value="old('supplier_id', $preselectedSupplierId ?? '')"
                        placeholder="-- Pilih Supplier / Vendor --"
                        required="true"
                    />
                    @error('supplier_id')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Tanggal Pembayaran --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Tanggal Pembayaran <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="date"
                        name="payment_date"
                        value="{{ old('payment_date', now()->format('Y-m-d')) }}"
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
                            <option value="{{ $m }}" {{ old('payment_method', 'Bank Transfer') === $m ? 'selected' : '' }}>
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
                        :value="old('coa_id')"
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
                        x-model="targetBankAccount"
                        placeholder="Contoh: BCA - 1234567890 (a.n. PT Vendor)"
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
                        value="{{ old('transfer_reference_number') }}"
                        placeholder="Contoh: TRF-20261021-0091"
                        class="w-full px-3.5 py-2 text-sm bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                    >
                    @error('transfer_reference_number')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Cabang Operasional --}}
                @if ($cabangs->count() > 1)
                    <div class="sm:col-span-2 lg:col-span-3">
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                            Cabang Operasional
                        </label>
                        <x-searchable-select name="cabang_id" :options="collect($cabangs)->map(fn ($c) => ['id' => $c->id, 'label' => $c->nama])->values()" :value="(old('cabang_id', auth()->user()?->cabang_id)) ?: collect($cabangs)->first()?->id" />
                    </div>
                @endif
            </div>
        </div>

        {{-- Kartu 2: Daftar Faktur Pembelian Belum Lunas (Unpaid Invoices) --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 sm:p-6 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-gray-100 pb-3">
                <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
                    <span class="p-1.5 bg-blue-50 text-blue-600 rounded-lg">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </span>
                    2. Tagihan Faktur Pembelian yang Akan Dilunasi
                </h2>
                <div class="flex items-center gap-2" x-show="invoices.length > 0">
                    <button
                        type="button"
                        @click="selectAllInvoices()"
                        class="text-xs font-bold text-[#15803d] hover:text-[#14532d] hover:underline"
                    >
                        Pilih &amp; Alokasikan Penuh Semua
                    </button>
                    <span class="text-gray-300">|</span>
                    <button
                        type="button"
                        @click="clearAllSelections()"
                        class="text-xs text-gray-500 hover:text-gray-700 hover:underline"
                    >
                        Reset Pilihan
                    </button>
                </div>
            </div>

            {{-- Error Validation Feedback for Invoices --}}
            @error('selected_invoices')
                <div class="p-3 bg-rose-50 border border-rose-200 rounded-lg text-xs font-semibold text-rose-700">
                    {{ $message }}
                </div>
            @enderror
            @error('allocations')
                <div class="p-3 bg-rose-50 border border-rose-200 rounded-lg text-xs font-semibold text-rose-700">
                    {{ $message }}
                </div>
            @enderror

            {{-- Loading State --}}
            <div x-show="loadingInvoices" class="py-10 text-center text-gray-500">
                <div class="inline-flex items-center gap-2 text-xs font-medium">
                    <svg class="animate-spin h-5 w-5 text-[#15803d]" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Memuat daftar faktur tagihan supplier...</span>
                </div>
            </div>

            {{-- Empty State: Belum pilih supplier --}}
            <div x-show="!loadingInvoices && !supplierId" class="py-10 text-center text-gray-400">
                <div class="max-w-xs mx-auto space-y-1">
                    <svg class="w-8 h-8 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                    </svg>
                    <p class="text-xs font-semibold text-gray-600">Pilih supplier terlebih dahulu</p>
                    <p class="text-[11px] text-gray-400">Daftar faktur yang belum lunas akan muncul secara otomatis setelah supplier dipilih.</p>
                </div>
            </div>

            {{-- Empty State: Tidak ada tagihan --}}
            <div x-show="!loadingInvoices && supplierId && invoices.length === 0" class="py-10 text-center text-gray-400">
                <div class="max-w-sm mx-auto space-y-1">
                    <div class="p-2.5 bg-emerald-50 text-[#15803d] rounded-full w-10 h-10 mx-auto flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <p class="text-xs font-bold text-gray-800">Tidak ada tagihan yang belum lunas</p>
                    <p class="text-[11px] text-gray-500">Semua faktur pembelian untuk supplier ini telah lunas atau belum ada faktur yang diterbitkan.</p>
                </div>
            </div>

            {{-- Invoices Table --}}
            <div x-show="!loadingInvoices && invoices.length > 0" class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                    <thead class="bg-gray-50 text-gray-600 font-bold uppercase tracking-wider">
                        <tr>
                            <th class="px-3 py-2.5 w-10 text-center">
                                <span class="sr-only">Pilih</span>
                            </th>
                            <th class="px-3 py-2.5">No. Faktur Vendor / Sistem</th>
                            <th class="px-3 py-2.5">Tgl &amp; Jatuh Tempo</th>
                            <th class="px-3 py-2.5 text-right">Total Faktur</th>
                            <th class="px-3 py-2.5 text-right">Sisa Hutang (AP)</th>
                            <th class="px-3 py-2.5 text-right w-48">Nominal Pembayaran (Rp)</th>
                            <th class="px-3 py-2.5 text-right">Sisa Tagihan Nanti</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <template x-for="inv in invoices" :key="inv.id">
                            <tr
                                class="hover:bg-gray-50/80 transition"
                                :class="{ 'bg-emerald-50/30': isSelected(inv.id) }"
                            >
                                {{-- Checkbox --}}
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

                                {{-- No Faktur --}}
                                <td class="px-3 py-3">
                                    <div class="font-bold text-gray-900" x-text="inv.invoice_number"></div>
                                    <div class="text-[11px] text-gray-400" x-show="inv.supplier_invoice_number !== '-'">
                                        Vendor: <span class="text-gray-600 font-medium" x-text="inv.supplier_invoice_number"></span>
                                    </div>
                                </td>

                                {{-- Tanggal & Jatuh Tempo --}}
                                <td class="px-3 py-3 whitespace-nowrap">
                                    <div class="text-gray-700" x-text="inv.invoice_date"></div>
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <span class="text-[11px] text-gray-500" x-text="'Jatuh Tempo: ' + inv.due_date"></span>
                                        <template x-if="inv.is_overdue">
                                            <span class="px-1.5 py-0.2 rounded text-[9.5px] font-bold bg-rose-100 text-rose-800">
                                                Overdue
                                            </span>
                                        </template>
                                    </div>
                                </td>

                                {{-- Total Faktur --}}
                                <td class="px-3 py-3 text-right font-semibold text-gray-600 whitespace-nowrap" x-text="inv.total_formatted"></td>

                                {{-- Sisa Hutang --}}
                                <td class="px-3 py-3 text-right font-bold text-gray-900 whitespace-nowrap" x-text="inv.remaining_formatted"></td>

                                {{-- Input Alokasi Pembayaran --}}
                                <td class="px-3 py-3 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <div class="relative w-36">
                                            <span class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none text-[10px] font-bold text-gray-400">Rp</span>
                                            <input
                                                type="text"
                                                :value="allocations[inv.id] ? formatRupiah(allocations[inv.id], false) : (allocations[inv.id] === 0 ? '0' : '')"
                                                @input="allocations[inv.id] = parseRupiah($event.target.value); onAllocationInput(inv.id)"
                                                @blur="$event.target.value = (allocations[inv.id] ? formatRupiah(allocations[inv.id], false) : (allocations[inv.id] === 0 ? '0' : ''))"
                                                @focus="if($event.target.value === '0') $event.target.value = ''"
                                                :disabled="!isSelected(inv.id)"
                                                placeholder="0"
                                                class="w-full pl-6 pr-2 py-1 text-right text-xs bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] disabled:bg-gray-100 disabled:text-gray-400 font-semibold text-gray-900 font-mono"
                                            >
                                            <input type="hidden" :name="'allocations[' + inv.id + ']'" :value="allocations[inv.id] || 0" :disabled="!isSelected(inv.id)">
                                        </div>
                                        <button
                                            type="button"
                                            @click="payFull(inv.id)"
                                            class="px-2 py-1 bg-gray-100 hover:bg-emerald-100 text-[#15803d] text-[10px] font-bold rounded border border-gray-200 transition cursor-pointer"
                                            title="Bayar Penuh Sisa Hutang Ini"
                                        >
                                            Penuh
                                        </button>
                                    </div>
                                </td>

                                {{-- Sisa Tagihan Nanti --}}
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

        {{-- Kartu 3: Catatan, Bukti Transfer, dan Ringkasan Finansial --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Dokumen Pendukung & Catatan --}}
            <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm p-5 sm:p-6 space-y-4">
                <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2.5 flex items-center gap-2">
                    <span class="p-1.5 bg-purple-50 text-purple-600 rounded-lg">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                        </svg>
                    </span>
                    3. Berkas Pendukung &amp; Keterangan
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {{-- File Bukti Transfer --}}
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                            Upload Bukti Pembayaran / Transfer
                        </label>
                        <input
                            type="file"
                            name="proof_file"
                            accept="image/jpeg,image/png,image/jpg,application/pdf"
                            class="block w-full text-xs text-gray-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 border border-gray-300 rounded-lg cursor-pointer focus:outline-none"
                        >
                        <p class="text-[11px] text-gray-400 mt-1">Format: JPG, PNG, atau PDF. Maksimal 5 MB.</p>
                        @error('proof_file')
                            <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Catatan Pembayaran --}}
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                            Catatan Internal / Keterangan
                        </label>
                        <textarea
                            name="notes"
                            rows="2"
                            placeholder="Tambahkan catatan pembayaran jika ada..."
                            class="w-full px-3 py-2 text-xs bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] transition"
                        >{{ old('notes') }}</textarea>
                        @error('notes')
                            <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- Ringkasan Finansial Card --}}
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
                        <div class="flex justify-between items-center text-gray-600">
                            <span>Total Sisa Hutang Terpilih</span>
                            <span class="font-semibold text-gray-900" x-text="formatRupiah(calculateTotalSelectedRemaining())"></span>
                        </div>
                        <div class="border-t border-gray-200 pt-2 flex justify-between items-center text-sm">
                            <span class="font-bold text-gray-900">Total Pembayaran</span>
                            <span class="font-bold text-xl text-[#15803d]" x-text="formatRupiah(calculateTotalPayment())"></span>
                        </div>
                        <div class="flex justify-between items-center text-xs text-gray-500 pt-1">
                            <span>Sisa Hutang Setelah Bayar</span>
                            <span class="font-semibold" x-text="formatRupiah(calculateTotalRemainingAfterPayment())"></span>
                        </div>
                    </dl>
                </div>

                {{-- Action Buttons --}}
                <div class="space-y-2 pt-4 border-t border-gray-200">
                    <button
                        type="submit"
                        name="action"
                        value="save_and_post"
                        :disabled="selectedInvoices.length === 0 || calculateTotalPayment() <= 0"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-[#15803d] hover:bg-[#14532d] disabled:bg-gray-300 disabled:cursor-not-allowed text-white text-xs font-bold rounded-lg shadow-sm transition active:scale-[0.98]"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Simpan &amp; Posting ke Buku Besar
                    </button>

                    <button
                        type="submit"
                        name="action"
                        value="save_draft"
                        :disabled="selectedInvoices.length === 0 || calculateTotalPayment() <= 0"
                        class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-white border border-gray-300 hover:bg-gray-100 disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed text-gray-700 text-xs font-semibold rounded-lg shadow-xs transition"
                    >
                        Simpan sebagai Draft
                    </button>

                    <a
                        href="{{ route('pembelian.payments.index') }}"
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
function vendorPaymentForm(config) {
    return {
        supplierId: config.initialSupplierId || '',
        targetBankAccount: config.initialTargetBank || '',
        loadingInvoices: false,
        invoices: [],
        selectedInvoices: config.initialSelectedInvoices || [],
        allocations: config.initialAllocations || {},
        invoicesUrl: config.invoicesUrl,

        init() {
            if (this.supplierId) {
                this.loadSupplierInvoices(this.supplierId, config.initialInvoiceId);
            }
        },

        onSupplierChange(supplierId) {
            this.supplierId = supplierId;
            this.selectedInvoices = [];
            this.allocations = {};
            if (supplierId) {
                this.loadSupplierInvoices(supplierId);
            } else {
                this.invoices = [];
                this.targetBankAccount = '';
            }
        },

        loadSupplierInvoices(supplierId, autoSelectInvoiceId = null) {
            this.loadingInvoices = true;
            fetch(`${this.invoicesUrl}/${supplierId}`)
                .then(res => res.json())
                .then(data => {
                    this.invoices = data.invoices || [];
                    if (data.supplier && data.supplier.target_bank_account && !this.targetBankAccount) {
                        this.targetBankAccount = data.supplier.target_bank_account;
                    }

                    // Pre-select specific invoice if requested
                    if (autoSelectInvoiceId) {
                        const targetId = Number(autoSelectInvoiceId);
                        const match = this.invoices.find(inv => Number(inv.id) === targetId);
                        if (match) {
                            if (!this.selectedInvoices.includes(targetId)) {
                                this.selectedInvoices.push(targetId);
                            }
                            if (!this.allocations[targetId]) {
                                this.allocations[targetId] = match.remaining;
                            }
                        }
                    }
                })
                .catch(err => {
                    console.error('Failed to load supplier invoices:', err);
                })
                .finally(() => {
                    this.loadingInvoices = false;
                });
        },

        isSelected(invoiceId) {
            return this.selectedInvoices.includes(Number(invoiceId));
        },

        toggleSelect(invoiceId) {
            const id = Number(invoiceId);
            const index = this.selectedInvoices.indexOf(id);
            if (index > -1) {
                this.selectedInvoices.splice(index, 1);
                delete this.allocations[id];
            } else {
                this.selectedInvoices.push(id);
                const inv = this.invoices.find(item => Number(item.id) === id);
                if (inv && !this.allocations[id]) {
                    this.allocations[id] = inv.remaining;
                }
            }
        },

        selectAllInvoices() {
            this.selectedInvoices = this.invoices.map(inv => Number(inv.id));
            this.invoices.forEach(inv => {
                this.allocations[inv.id] = inv.remaining;
            });
        },

        clearAllSelections() {
            this.selectedInvoices = [];
            this.allocations = {};
        },

        payFull(invoiceId) {
            const id = Number(invoiceId);
            const inv = this.invoices.find(item => Number(item.id) === id);
            if (!inv) return;

            if (!this.isSelected(id)) {
                this.selectedInvoices.push(id);
            }
            this.allocations[id] = inv.remaining;
        },

        onAllocationInput(invoiceId) {
            const id = Number(invoiceId);
            const inv = this.invoices.find(item => Number(item.id) === id);
            if (!inv) return;

            if (this.allocations[id] > inv.remaining) {
                this.allocations[id] = inv.remaining;
            }
            if (!this.isSelected(id) && this.allocations[id] > 0) {
                this.selectedInvoices.push(id);
            }
        },

        getProjectedRemaining(invoiceId) {
            const id = Number(invoiceId);
            const inv = this.invoices.find(item => Number(item.id) === id);
            if (!inv) return 0;

            const paid = Number(this.allocations[id]) || 0;
            return Math.max(0, inv.remaining - paid);
        },

        calculateTotalSelectedRemaining() {
            return this.invoices
                .filter(inv => this.isSelected(inv.id))
                .reduce((sum, inv) => sum + Number(inv.remaining || 0), 0);
        },

        calculateTotalPayment() {
            return Object.entries(this.allocations).reduce((sum, [invId, amount]) => {
                if (this.isSelected(invId)) {
                    return sum + (Number(amount) || 0);
                }
                return sum;
            }, 0);
        },

        calculateTotalRemainingAfterPayment() {
            const totalRemaining = this.calculateTotalSelectedRemaining();
            const totalPayment = this.calculateTotalPayment();
            return Math.max(0, totalRemaining - totalPayment);
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
