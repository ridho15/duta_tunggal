@extends('layouts.app')

@section('title', 'Buat Nota Kredit (Draf) – Duta Tunggal ERP')

@section('content')
<div
    class="max-w-5xl mx-auto space-y-6"
    x-data="creditNoteCreate({
        initialCustomerId: '{{ $selectedCustomer->id ?? '' }}',
        initialInvoiceId: '{{ $selectedInvoice->id ?? '' }}',
        initialType: '{{ $presetType ?? \App\Models\CreditNote::TYPE_CORRECTION }}',
        initialReturnId: '{{ $selectedReturn->id ?? '' }}',
        initialReason: '{{ addslashes($presetReason ?? '') }}'
    })"
    x-init="init()"
>
    {{-- Header & Breadcrumbs --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('penjualan.credit-notes.index') }}" class="hover:text-gray-700 transition">Nota Kredit</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d]">Buat Baru</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Buat Nota Kredit (Draf)</h1>
            <p class="text-sm text-gray-500 mt-0.5">Nota Kredit dibuat sebagai draf terlebih dahulu dan baru berdampak pada jurnal & piutang setelah diterbitkan.</p>
        </div>
        <div>
            <a
                href="{{ route('penjualan.credit-notes.index') }}"
                class="inline-flex items-center gap-2 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold rounded-lg transition"
            >
                Kembali ke Daftar
            </a>
        </div>
    </div>

    {{-- Error Banner --}}
    @if ($errors->any())
        <div class="bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-xl text-sm space-y-1">
            <div class="font-bold flex items-center gap-2">
                <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Periksa kembali data formulir:
            </div>
            <ul class="list-disc list-inside pl-4 space-y-0.5 text-xs text-rose-700">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Form Utama --}}
    <form method="POST" action="{{ route('penjualan.credit-notes.store') }}" class="space-y-6" @submit="handleSubmit($event)">
        @csrf
        <input type="hidden" name="customer_return_id" :value="returnId">

        {{-- Card 1: Informasi Pelanggan & Faktur --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-5">
            <h2 class="text-base font-bold text-gray-900 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full bg-[#dcfce7] text-[#15803d] text-xs font-black flex items-center justify-center">1</span>
                Pelanggan & Faktur Penjualan Rujukan
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Pilih Pelanggan --}}
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-600 mb-1.5">
                        Customer (Pelanggan) <span class="text-rose-500">*</span>
                    </label>
                    <select data-searchable
                        class="w-full text-sm border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                        x-model="customerId"
                        @change="onCustomerChange()"
                        required
                    >
                        <option value="">-- Pilih Customer --</option>
                        @foreach ($customers as $c)
                            <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->code ?? '-' }})</option>
                        @endforeach
                    </select>
                </div>

                {{-- Pilih Invoice --}}
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-600 mb-1.5">
                        Faktur Penjualan (Invoice) <span class="text-rose-500">*</span>
                    </label>
                    <select data-searchable
                        name="invoice_id"
                        class="w-full text-sm border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                        x-model="invoiceId"
                        @change="onInvoiceChange()"
                        :disabled="!customerId || isLoadingInvoices"
                        required
                    >
                        <option value="">-- Pilih Faktur Terbit --</option>
                        <template x-for="inv in invoices" :key="inv.id">
                            <option :value="inv.id" x-text="`${inv.invoice_number} • ${inv.invoice_date} • ${inv.total_formatted} (Sisa Piutang: ${inv.remaining_ar_formatted})`"></option>
                        </template>
                    </select>
                    <div x-show="isLoadingInvoices" class="text-xs text-gray-400 mt-1 flex items-center gap-1">
                        <svg class="animate-spin w-3 h-3 text-gray-500" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Memuat invoice aktif customer...
                    </div>
                </div>
            </div>

            {{-- Ringkasan Invoice Terpilih --}}
            <div x-show="invoiceDetails" x-transition class="bg-gray-50 border border-gray-200 rounded-lg p-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                <div>
                    <span class="text-gray-400 font-semibold block">Total Faktur:</span>
                    <span class="font-bold text-gray-900 text-sm" x-text="invoiceDetails ? invoiceDetails.total_formatted : '-'"></span>
                </div>
                <div>
                    <span class="text-gray-400 font-semibold block">Sisa Tagihan Piutang:</span>
                    <span class="font-bold text-amber-700 text-sm" x-text="invoiceDetails ? invoiceDetails.ar_remaining_formatted : '-'"></span>
                </div>
                <div>
                    <span class="text-gray-400 font-semibold block">Tanggal Faktur:</span>
                    <span class="font-medium text-gray-700" x-text="invoiceDetails ? invoiceDetails.invoice_date : '-'"></span>
                </div>
                <div>
                    <span class="text-gray-400 font-semibold block">Ongkos Kirim Faktur:</span>
                    <span class="font-medium text-gray-700" x-text="invoiceDetails ? `Rp ${formatNumber(invoiceDetails.shipping_cost)}` : '-'"></span>
                </div>
            </div>
        </div>

        {{-- Card 2: Jenis & Parameter Nota Kredit --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-5" x-show="invoiceDetails" x-transition>
            <h2 class="text-base font-bold text-gray-900 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full bg-[#dcfce7] text-[#15803d] text-xs font-black flex items-center justify-center">2</span>
                Jenis & Tanggal Nota Kredit
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Jenis Nota Kredit --}}
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-600 mb-1.5">
                        Jenis Nota Kredit <span class="text-rose-500">*</span>
                    </label>
                    <select
                        name="type"
                        class="w-full text-sm border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                        x-model="type"
                        @change="onTypeChange()"
                        required
                    >
                        <option value="koreksi">Koreksi Sebagian (Penyesuaian Qty / Nilai)</option>
                        <option value="retur">Retur Barang (Berdasarkan Retur Penjualan)</option>
                        <option value="pembatalan">Pembatalan Invoice (Batalkan Seluruh Sisa Faktur)</option>
                    </select>
                    <p class="text-[11.5px] text-gray-500 mt-1">
                        <span x-show="type === 'pembatalan'">Mengisi kuantitas seluruh sisa item & ongkir otomatis. Status invoice menjadi <strong>Cancelled</strong> saat terbit.</span>
                        <span x-show="type === 'retur'">Mengkreditkan nilai barang dari retur penjualan yang telah disetujui.</span>
                        <span x-show="type === 'koreksi'">Mengkreditkan kuantitas parsial yang ditentukan secara mandiri.</span>
                    </p>
                </div>

                {{-- Tanggal Nota Kredit --}}
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-600 mb-1.5">
                        Tanggal Nota Kredit <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="date"
                        name="credit_date"
                        x-model="creditDate"
                        class="w-full text-sm border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                        required
                    >
                </div>
            </div>

            {{-- Opsi Biaya Kirim (untuk type bukan pembatalan) --}}
            <div x-show="type !== 'pembatalan' && invoiceDetails && invoiceDetails.creditable_shipping > 0" class="pt-2 border-t border-gray-100">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input
                        type="checkbox"
                        name="include_shipping"
                        value="1"
                        x-model="includeShipping"
                        @change="recomputeTotals()"
                        class="rounded border-gray-300 text-[#15803d] focus:ring-[#15803d]"
                    >
                    <span class="text-sm font-semibold text-gray-700">
                        Ikut mengkreditkan sisa biaya pengiriman
                        (<span class="text-gray-900 font-bold" x-text="`Rp ${formatNumber(invoiceDetails ? invoiceDetails.creditable_shipping : 0)}`"></span>)
                    </span>
                </label>
            </div>
        </div>

        {{-- Card 3: Rincian Item yang Dikreditkan --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4" x-show="invoiceDetails" x-transition>
            <div class="flex items-center justify-between">
                <h2 class="text-base font-bold text-gray-900 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-[#dcfce7] text-[#15803d] text-xs font-black flex items-center justify-center">3</span>
                    Kuantitas Item yang Dikreditkan
                </h2>
                <div x-show="type === 'pembatalan'" class="text-xs bg-rose-50 text-rose-700 font-bold px-2.5 py-1 rounded-md border border-rose-200">
                    Mode Pembatalan: Seluruh sisa kuantitas otomatis dikreditkan
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600">
                    <thead class="bg-gray-50 border-b border-gray-200 text-xs font-bold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="py-3 px-3">Produk</th>
                            <th class="py-3 px-3 text-right">Harga Satuan</th>
                            <th class="py-3 px-3 text-right">Sisa Dpt Dikreditkan</th>
                            <th class="py-3 px-3 text-right w-36">Qty Dikreditkan</th>
                            <th class="py-3 px-3 text-right">DPP</th>
                            <th class="py-3 px-3 text-right">PPN</th>
                            <th class="py-3 px-3 text-right">Total Baris</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <template x-for="(item, idx) in items" :key="item.id">
                            <tr class="hover:bg-gray-50/70 transition">
                                <td class="py-3 px-3">
                                    <div class="font-bold text-gray-900" x-text="item.product_name"></div>
                                    <div class="text-[11px] text-gray-400 font-mono" x-text="item.product_code"></div>
                                </td>
                                <td class="py-3 px-3 text-right font-medium text-gray-700" x-text="`Rp ${formatNumber(item.unit_price)}`"></td>
                                <td class="py-3 px-3 text-right">
                                    <span class="font-bold text-gray-900" x-text="item.creditable_qty"></span>
                                    <span class="text-xs text-gray-500" x-text="item.unit"></span>
                                </td>
                                <td class="py-3 px-3 text-right">
                                    <input
                                        type="number"
                                        :name="`quantities[${item.id}]`"
                                        x-model.number="item.credit_qty"
                                        @input="onItemQtyChange(item)"
                                        step="0.01"
                                        min="0"
                                        :max="item.creditable_qty"
                                        :disabled="type === 'pembatalan'"
                                        class="w-24 text-right py-1 px-2 text-sm font-bold border-gray-300 rounded-md focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                                    >
                                </td>
                                <td class="py-3 px-3 text-right font-medium text-gray-800" x-text="`Rp ${formatNumber(item.subtotal)}`"></td>
                                <td class="py-3 px-3 text-right font-medium text-gray-600" x-text="`Rp ${formatNumber(item.tax_amount)}`"></td>
                                <td class="py-3 px-3 text-right font-bold text-gray-900" x-text="`Rp ${formatNumber(item.total)}`"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- Estimasi Alokasi Finansial Live --}}
            <div class="mt-4 pt-4 border-t border-gray-200 grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- Alokasi Breakdown --}}
                <div class="bg-blue-50/50 border border-blue-200 rounded-xl p-4 text-xs space-y-2">
                    <div class="font-bold text-blue-900 flex items-center gap-1.5 uppercase tracking-wider text-[11px]">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Simulasi Alokasi Finansial
                    </div>
                    <div class="flex items-center justify-between text-gray-600">
                        <span>Potong Sisa Piutang Invoice:</span>
                        <span class="font-bold text-gray-900 text-sm" x-text="`Rp ${formatNumber(applyToAr)}`"></span>
                    </div>
                    <div class="flex items-center justify-between text-blue-800">
                        <span>Masuk ke Deposit Pelanggan (Store Credit):</span>
                        <span class="font-bold text-blue-900 text-sm" x-text="`Rp ${formatNumber(toDeposit)}`"></span>
                    </div>
                    <p class="text-[11px] text-gray-500 pt-1 border-t border-blue-100">
                        * Bagian yang masih berstatus piutang akan mengurangi tagihan faktur. Kelebihannya dialokasikan ke Deposit Customer tanpa menghasilkan saldo piutang negatif.
                    </p>
                </div>

                {{-- Total Ringkasan Finansial --}}
                <div class="bg-gray-50 rounded-xl border border-gray-200 p-4 text-xs space-y-2 text-right">
                    <div class="flex justify-between text-gray-600">
                        <span>Total DPP (Subtotal):</span>
                        <span class="font-semibold text-gray-900" x-text="`Rp ${formatNumber(totalSubtotal)}`"></span>
                    </div>
                    <div class="flex justify-between text-gray-600">
                        <span>Total PPN Reversal:</span>
                        <span class="font-semibold text-gray-900" x-text="`Rp ${formatNumber(totalTax)}`"></span>
                    </div>
                    <div class="flex justify-between text-gray-600" x-show="totalShipping > 0">
                        <span>Biaya Pengiriman:</span>
                        <span class="font-semibold text-gray-900" x-text="`Rp ${formatNumber(totalShipping)}`"></span>
                    </div>
                    <div class="flex justify-between text-base font-black text-gray-900 pt-2 border-t border-gray-200">
                        <span>Total Nilai Nota Kredit:</span>
                        <span class="text-[#15803d]" x-text="`Rp ${formatNumber(grandTotal)}`"></span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 4: Alasan & Submit --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4" x-show="invoiceDetails" x-transition>
            <h2 class="text-base font-bold text-gray-900 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full bg-[#dcfce7] text-[#15803d] text-xs font-black flex items-center justify-center">4</span>
                Alasan & Konfirmasi Pengajuan
            </h2>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-gray-600 mb-1.5">
                    Alasan Pembuatan Nota Kredit <span class="text-rose-500">*</span>
                    <span class="font-normal text-gray-400 normal-case">(minimal 10 karakter; tercatat di dokumen audit & cetak)</span>
                </label>
                <textarea
                    name="reason"
                    rows="3"
                    x-model="reason"
                    class="w-full text-sm border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                    placeholder="Contoh: Barang cacat dikembalikan customer sesuai kesepakatan retur..."
                    required
                ></textarea>
                <div class="text-[11px] text-gray-400 mt-1 flex justify-between">
                    <span :class="reason.length < 10 ? 'text-amber-600' : 'text-emerald-600 font-semibold'">
                        Panjang karakter: <span x-text="reason.length"></span> / min 10
                    </span>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                <a
                    href="{{ route('penjualan.credit-notes.index') }}"
                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold rounded-lg transition"
                >
                    Batal
                </a>
                <button
                    type="submit"
                    :disabled="grandTotal <= 0 || reason.length < 10"
                    class="inline-flex items-center gap-2 px-5 py-2 bg-[#15803d] hover:bg-[#166534] disabled:bg-gray-300 disabled:cursor-not-allowed text-white text-sm font-semibold rounded-lg shadow-sm transition active:scale-[0.98]"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                    </svg>
                    Simpan Draf Nota Kredit
                </button>
            </div>
        </div>
    </form>
</div>

<script>
function creditNoteCreate(config) {
    return {
        customerId: config.initialCustomerId || '',
        invoiceId: config.initialInvoiceId || '',
        type: config.initialType || 'koreksi',
        returnId: config.initialReturnId || '',
        reason: config.initialReason || '',
        creditDate: new Date().toISOString().split('T')[0],
        includeShipping: false,

        invoices: [],
        invoiceDetails: null,
        items: [],

        isLoadingInvoices: false,
        isLoadingInvoiceDetails: false,

        totalSubtotal: 0,
        totalTax: 0,
        totalShipping: 0,
        grandTotal: 0,
        applyToAr: 0,
        toDeposit: 0,

        init() {
            if (this.customerId) {
                this.loadInvoices(this.customerId, this.invoiceId);
            }
        },

        onCustomerChange() {
            this.invoiceId = '';
            this.invoiceDetails = null;
            this.items = [];
            this.invoices = [];
            if (this.customerId) {
                this.loadInvoices(this.customerId);
            }
        },

        async loadInvoices(customerId, autoSelectInvoiceId = null) {
            this.isLoadingInvoices = true;
            try {
                const res = await fetch(`/penjualan/nota-kredit/customer-invoices/${customerId}`);
                const data = await res.json();
                if (data.success) {
                    this.invoices = data.invoices;
                    if (autoSelectInvoiceId) {
                        this.invoiceId = autoSelectInvoiceId;
                        this.loadInvoiceDetails(this.invoiceId);
                    }
                }
            } catch (err) {
                console.error('Failed to load customer invoices:', err);
            } finally {
                this.isLoadingInvoices = false;
            }
        },

        onInvoiceChange() {
            this.invoiceDetails = null;
            this.items = [];
            if (this.invoiceId) {
                this.loadInvoiceDetails(this.invoiceId);
            }
        },

        async loadInvoiceDetails(invoiceId) {
            this.isLoadingInvoiceDetails = true;
            try {
                const res = await fetch(`/penjualan/nota-kredit/invoice-details/${invoiceId}`);
                const data = await res.json();
                if (data.success) {
                    this.invoiceDetails = data.invoice;
                    this.items = (data.items || []).map(item => ({
                        ...item,
                        credit_qty: this.type === 'pembatalan' ? item.creditable_qty : 0,
                        subtotal: 0,
                        tax_amount: 0,
                        total: 0
                    }));

                    if (this.type === 'pembatalan') {
                        this.includeShipping = true;
                    }
                    this.recomputeTotals();
                }
            } catch (err) {
                console.error('Failed to load invoice details:', err);
            } finally {
                this.isLoadingInvoiceDetails = false;
            }
        },

        onTypeChange() {
            if (this.type === 'pembatalan') {
                this.includeShipping = true;
                this.items.forEach(item => {
                    item.credit_qty = item.creditable_qty;
                });
            }
            this.recomputeTotals();
        },

        onItemQtyChange(item) {
            let val = parseFloat(item.credit_qty) || 0;
            if (val < 0) val = 0;
            if (val > item.creditable_qty) val = item.creditable_qty;
            item.credit_qty = val;
            this.recomputeTotals();
        },

        recomputeTotals() {
            let subtotal = 0;
            let tax = 0;

            this.items.forEach(item => {
                const qty = parseFloat(item.credit_qty) || 0;
                if (qty > 0 && item.original_qty > 0) {
                    const ratio = qty / item.original_qty;
                    item.subtotal = Math.round(item.subtotal_original || (item.unit_price * qty) * 100) / 100;
                    item.tax_amount = Math.round((item.tax_amount / item.original_qty * qty) * 100) / 100;
                    item.total = Math.round((item.subtotal + item.tax_amount) * 100) / 100;

                    subtotal += item.subtotal;
                    tax += item.tax_amount;
                } else {
                    item.subtotal = 0;
                    item.tax_amount = 0;
                    item.total = 0;
                }
            });

            let shipping = 0;
            if (this.invoiceDetails) {
                if (this.type === 'pembatalan' || this.includeShipping) {
                    shipping = parseFloat(this.invoiceDetails.creditable_shipping) || 0;
                }
            }

            this.totalSubtotal = Math.round(subtotal * 100) / 100;
            this.totalTax = Math.round(tax * 100) / 100;
            this.totalShipping = Math.round(shipping * 100) / 100;
            this.grandTotal = Math.round((this.totalSubtotal + this.totalTax + this.totalShipping) * 100) / 100;

            const remainingAr = this.invoiceDetails ? parseFloat(this.invoiceDetails.ar_remaining) || 0 : 0;
            this.applyToAr = Math.min(this.grandTotal, Math.max(0, remainingAr));
            this.toDeposit = Math.max(0, Math.round((this.grandTotal - this.applyToAr) * 100) / 100);
        },

        formatNumber(num) {
            return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(num || 0);
        },

        handleSubmit(e) {
            if (this.grandTotal <= 0) {
                e.preventDefault();
                alert('Paling sedikit satu kuantitas barang atau ongkir harus dikreditkan.');
                return;
            }
            if (this.reason.trim().length < 10) {
                e.preventDefault();
                alert('Alasan Nota Kredit minimal 10 karakter.');
                return;
            }
        }
    };
}
</script>
@endsection
