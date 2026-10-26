@extends('layouts.app')

@section('title', 'Edit Faktur Pembelian ' . $invoice->invoice_number . ' – Duta Tunggal ERP')

@section('content')
<div
    class="max-w-6xl mx-auto space-y-6"
    x-data="{
        items: {{ json_encode($invoice->invoiceItem->map(fn($it) => [
            'id' => $it->id,
            'product_id' => $it->product_id,
            'product_name' => $it->product?->name ?? 'Produk',
            'sku' => $it->product?->sku ?? '-',
            'uom' => $it->product?->uom?->name ?? 'Pcs',
            'quantity' => (float)$it->quantity,
            'po_price' => (float)($it->po_price ?? $it->price),
            'price' => (float)$it->price,
            'tax_rate' => (float)($it->tax_rate ?? $invoice->ppn_rate ?? 0),
        ])->values()->all()) }},
        otherFee: {{ (float)old('other_fee', $invoice->other_fee_total ?? 0) }},
        invoiceDate: '{{ old('invoice_date', $invoice->invoice_date ? $invoice->invoice_date->format('Y-m-d') : date('Y-m-d')) }}',
        dueDate: '{{ old('due_date', $invoice->due_date ? $invoice->due_date->format('Y-m-d') : date('Y-m-d')) }}',

        calculateSubtotal() {
            return this.items.reduce((sum, it) => sum + (it.quantity * it.price), 0);
        },

        calculateTax() {
            return this.items.reduce((sum, it) => {
                const lineTotal = it.quantity * it.price;
                return sum + (lineTotal * (it.tax_rate / 100));
            }, 0);
        },

        calculateVariance() {
            return this.items.reduce((sum, it) => {
                const diff = (it.price - it.po_price) * it.quantity;
                return sum + diff;
            }, 0);
        },

        calculateTotal() {
            return this.calculateSubtotal() + this.calculateTax() + parseFloat(this.otherFee || 0);
        },

        parseRupiah(val) {
            if (!val) return 0;
            const clean = String(val).replace(/[^0-9]/g, '');
            return clean ? parseInt(clean, 10) : 0;
        },

        formatNumber(val) {
            if (!val) return '';
            return Number(val).toLocaleString('id-ID');
        },

        formatMoney(amount) {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0,
                maximumFractionDigits: 2
            }).format(amount || 0);
        }
    }"
>
    {{-- Header Breadcrumb & Judul --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('pembelian.invoices.index') }}" class="text-gray-500 hover:text-gray-900 transition">Faktur Pembelian</a>
                <span class="text-gray-300">/</span>
                <a href="{{ route('pembelian.invoices.show', $invoice) }}" class="text-gray-500 hover:text-gray-900 transition">{{ $invoice->invoice_number }}</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Edit Faktur Draft</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Edit Faktur Pembelian (Draft)</h1>
            <p class="text-sm text-gray-500 mt-0.5">Perbarui rincian nomor invoice supplier, tanggal, atau sesuaikan harga item sebelum difinalkan.</p>
        </div>
        <div>
            <a
                href="{{ route('pembelian.invoices.show', $invoice) }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 border border-gray-200 text-gray-700 bg-white hover:bg-gray-50 text-xs font-semibold rounded-lg shadow-sm transition"
            >
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Kembali ke Detail
            </a>
        </div>
    </div>

    {{-- Form Edit --}}
    <form action="{{ route('pembelian.invoices.update', $invoice) }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Kartu 1: Informasi Header --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-6">
            <h2 class="text-base font-bold text-gray-900 border-b border-gray-100 pb-3 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-[#15803d]"></span>
                Informasi Dokumen &amp; Rekanan
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                {{-- Supplier (Readonly) --}}
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Pemasok (Supplier)
                    </label>
                    <input
                        type="text"
                        readonly
                        value="{{ $invoice->supplier?->perusahaan ? $invoice->supplier->perusahaan . ' (' . $invoice->supplier->name . ')' : ($invoice->supplier_name ?? '-') }}"
                        class="w-full px-3 py-2 border border-gray-200 bg-gray-50 rounded-lg text-xs text-gray-700 font-semibold focus:outline-none"
                    />
                </div>

                {{-- No. Faktur Vendor --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        No. Faktur Vendor / Supplier
                    </label>
                    <input
                        type="text"
                        name="supplier_invoice_number"
                        value="{{ old('supplier_invoice_number', $invoice->supplier_invoice_number) }}"
                        placeholder="Contoh: INV/2026/001"
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:border-transparent transition"
                    />
                    @error('supplier_invoice_number')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- No. Faktur Pajak --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        No. Faktur Pajak (NSFP)
                    </label>
                    <input
                        type="text"
                        name="tax_invoice_number"
                        value="{{ old('tax_invoice_number', $invoice->tax_invoice_number) }}"
                        placeholder="Contoh: 010.000-26.12345678"
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:border-transparent transition"
                    />
                    @error('tax_invoice_number')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Tanggal Faktur --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Tanggal Faktur <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="date"
                        name="invoice_date"
                        x-model="invoiceDate"
                        required
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:border-transparent transition"
                    />
                    @error('invoice_date')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Tanggal Jatuh Tempo --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Tanggal Jatuh Tempo <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="date"
                        name="due_date"
                        x-model="dueDate"
                        required
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:border-transparent transition"
                    />
                    @error('due_date')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Biaya Pengiriman / Lain --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Ongkos Kirim / Biaya Lain (Rp)
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-xs font-bold text-gray-400">Rp</span>
                        <input
                            type="text"
                            :value="otherFee > 0 ? formatNumber(otherFee) : ''"
                            @input="otherFee = parseRupiah($event.target.value); $event.target.value = otherFee > 0 ? formatNumber(otherFee) : ''"
                            placeholder="0"
                            class="w-full pl-9 pr-3 py-2 border border-gray-200 rounded-lg text-xs font-semibold font-mono text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:border-transparent transition"
                        />
                        <input type="hidden" name="other_fee" :value="otherFee">
                    </div>
                    @error('other_fee')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Cabang (Readonly) --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Cabang
                    </label>
                    <input
                        type="text"
                        readonly
                        value="{{ $invoice->cabang?->name ?? 'Pusat' }}"
                        class="w-full px-3 py-2 border border-gray-200 bg-gray-50 rounded-lg text-xs text-gray-500 focus:outline-none"
                    />
                </div>
            </div>
        </div>

        {{-- Kartu 2: Rincian Item Barang --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-4">
            <h2 class="text-base font-bold text-gray-900 border-b border-gray-100 pb-3 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-[#15803d]"></span>
                Rincian Barang &amp; Harga Satuan
            </h2>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200 text-gray-500 uppercase tracking-wider font-semibold">
                            <th class="py-2.5 px-3">Produk / Barang</th>
                            <th class="py-2.5 px-3 text-right">Qty</th>
                            <th class="py-2.5 px-3">Satuan</th>
                            <th class="py-2.5 px-3 text-right">Harga PO (Rp)</th>
                            <th class="py-2.5 px-3 text-right w-36">Harga Vendor (Rp)</th>
                            <th class="py-2.5 px-3 text-right">PPN %</th>
                            <th class="py-2.5 px-3 text-right">Subtotal</th>
                            <th class="py-2.5 px-3 text-right">Selisih (PPV)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700">
                        <template x-for="(item, idx) in items" :key="item.product_id">
                            <tr class="hover:bg-gray-50/75 transition">
                                <td class="py-2.5 px-3">
                                    <div class="font-bold text-gray-900" x-text="item.product_name"></div>
                                    <div class="text-[10px] text-gray-400 font-mono" x-text="item.sku"></div>
                                    <input type="hidden" :name="`items[${idx}][product_id]`" :value="item.product_id">
                                    <input type="hidden" :name="`items[${idx}][quantity]`" :value="item.quantity">
                                    <input type="hidden" :name="`items[${idx}][po_price]`" :value="item.po_price">
                                    <input type="hidden" :name="`items[${idx}][tax_rate]`" :value="item.tax_rate">
                                </td>
                                <td class="py-2.5 px-3 text-right font-semibold text-gray-900" x-text="item.quantity"></td>
                                <td class="py-2.5 px-3 text-gray-500" x-text="item.uom"></td>
                                <td class="py-2.5 px-3 text-right text-gray-500 font-mono" x-text="formatMoney(item.po_price)"></td>
                                <td class="py-2.5 px-3 text-right">
                                    <div class="relative w-36 ml-auto">
                                        <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-[10px] font-bold text-gray-400">Rp</span>
                                        <input
                                            type="text"
                                            :value="item.price > 0 ? formatNumber(item.price) : ''"
                                            @input="item.price = parseRupiah($event.target.value); $event.target.value = item.price > 0 ? formatNumber(item.price) : ''"
                                            placeholder="0"
                                            class="w-full pl-7 pr-2 py-1 border border-gray-200 rounded text-right text-xs font-semibold font-mono text-gray-900 focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d]"
                                        />
                                        <input type="hidden" :name="`items[${idx}][price]`" :value="item.price">
                                    </div>
                                </td>
                                <td class="py-2.5 px-3 text-right text-gray-600" x-text="`${item.tax_rate}%`"></td>
                                <td class="py-2.5 px-3 text-right font-bold text-gray-900 font-mono" x-text="formatMoney(item.quantity * item.price)"></td>
                                <td class="py-2.5 px-3 text-right font-semibold font-mono" :class="(item.price - item.po_price) > 0 ? 'text-amber-700' : ((item.price - item.po_price) < 0 ? 'text-emerald-700' : 'text-gray-400')">
                                    <span x-text="formatMoney((item.price - item.po_price) * item.quantity)"></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Kartu 3: Ringkasan Finansial & Tombol Simpan --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="lg:col-span-7 bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-3">
                <label class="block text-xs font-semibold text-gray-700">
                    Catatan / Keterangan Tagihan
                </label>
                <textarea
                    name="notes"
                    rows="4"
                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:border-transparent transition"
                >{{ old('notes', $invoice->notes) }}</textarea>
            </div>

            <div class="lg:col-span-5 bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 border-b border-gray-100 pb-2">
                    Ringkasan Finansial Tagihan
                </h3>

                <div class="space-y-2 text-xs">
                    <div class="flex items-center justify-between text-gray-600">
                        <span>Subtotal DPP:</span>
                        <span class="font-bold text-gray-900 font-mono" x-text="formatMoney(calculateSubtotal())"></span>
                    </div>
                    <div class="flex items-center justify-between text-gray-600">
                        <span>PPN Masukan:</span>
                        <span class="font-bold text-gray-900 font-mono" x-text="formatMoney(calculateTax())"></span>
                    </div>
                    <div class="flex items-center justify-between text-gray-600">
                        <span>Ongkos Kirim / Biaya Lain:</span>
                        <span class="font-bold text-gray-900 font-mono" x-text="formatMoney(otherFee)"></span>
                    </div>
                    <div class="flex items-center justify-between text-gray-600 border-t border-gray-100 pt-2" x-show="Math.abs(calculateVariance()) > 0.01">
                        <span>Total Selisih Harga (PPV):</span>
                        <span class="font-bold font-mono" :class="calculateVariance() > 0 ? 'text-amber-700' : 'text-emerald-700'" x-text="formatMoney(calculateVariance())"></span>
                    </div>
                    <div class="flex items-center justify-between text-sm font-bold text-gray-900 border-t-2 border-gray-200 pt-3">
                        <span class="text-base">Total Tagihan (AP):</span>
                        <span class="text-lg text-[#15803d] font-mono font-bold" x-text="formatMoney(calculateTotal())"></span>
                    </div>
                </div>

                {{-- Tombol Aksi Form --}}
                <div class="pt-4 space-y-2">
                    <button
                        type="submit"
                        name="action"
                        value="update_and_post"
                        class="w-full py-2.5 px-4 bg-[#15803d] hover:bg-[#14532d] text-white text-xs font-bold rounded-lg shadow-sm transition active:scale-[0.98] flex items-center justify-center gap-2"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Perbarui &amp; Posting ke Hutang Usaha (AP)
                    </button>

                    <button
                        type="submit"
                        name="action"
                        value="update_draft"
                        class="w-full py-2 px-4 border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs font-semibold rounded-lg transition active:scale-[0.98]"
                    >
                        Simpan Perubahan Draft
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
