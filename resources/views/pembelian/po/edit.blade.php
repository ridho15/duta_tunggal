@extends('layouts.app')

@section('title', 'Ubah Pesanan Pembelian ' . $purchaseOrder->po_number . ' – Duta Tunggal ERP')

@section('content')
<div 
    class="space-y-6 max-w-7xl mx-auto"
    x-data="purchaseOrderForm({
        products: {{ Js::from($products->map(fn($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku ?? '-',
            'price' => (float)($p->cost_price ?? 0),
            'uom' => $p->uom?->name ?? 'Pcs'
        ])) }},
        suppliers: {{ Js::from($suppliers->map(fn($s) => [
            'id' => $s->id,
            'perusahaan' => $s->perusahaan,
            'code' => $s->code ?? 'SUP',
            'kontak_person' => $s->kontak_person ?? '-',
            'phone' => $s->telepon ?? $s->phone ?? $s->handphone ?? '-',
            'address' => $s->address ?? $s->alamat ?? '-',
            'top_type' => $s->top_type ?? 'cod',
            'tempo_hutang' => (int)($s->tempo_hutang ?? 0)
        ])) }},
        currencies: {{ Js::from($currencies->map(fn($c) => [
            'id' => $c->id,
            'code' => $c->code,
            'name' => $c->name,
            'symbol' => $c->symbol,
            'rate' => (float)($c->to_rupiah ?? 1)
        ])) }},
        initialSupplierId: '{{ old('supplier_id', $purchaseOrder->supplier_id) }}',
        initialCurrencyId: '{{ old('currency_id', $purchaseOrder->purchaseOrderCurrency?->first()?->currency_id ?? 1) }}',
        initialTopType: '{{ old('top_type', $purchaseOrder->top_type ?? 'cod') }}',
        initialTempoHutang: {{ (int) old('tempo_hutang', $purchaseOrder->tempo_hutang ?? 0) }},
        initialExchangeRate: {{ (float) old('exchange_rate', $purchaseOrder->purchaseOrderCurrency?->first()?->nominal ?? 1.0) }},
        oldItems: {{ Js::from(old('items', $purchaseOrder->purchaseOrderItem->map(fn($item) => [
            'product_id' => $item->product_id,
            'quantity' => (float)$item->quantity,
            'unit_price' => (float)$item->unit_price,
            'discount' => (float)$item->discount,
            'tax' => (float)$item->tax,
            'tipe_pajak' => $item->tipe_pajak,
            'currency_id' => $item->currency_id,
        ]))) }}
    })"
>
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('pembelian.po.index') }}" class="hover:text-[#15803d] transition">Pesanan Pembelian</a>
                <span class="text-gray-300">/</span>
                <a href="{{ route('pembelian.po.show', $purchaseOrder) }}" class="hover:text-[#15803d] transition">{{ $purchaseOrder->po_number }}</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Edit Draf</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Ubah Pesanan Pembelian</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                    {{ $purchaseOrder->po_number }} (Draf)
                </span>
            </div>
            <p class="text-sm text-gray-500 mt-0.5">Perbarui rincian pesanan pembelian berstatus draf sebelum diajukan ke proses persetujuan.</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('pembelian.po.show', $purchaseOrder) }}"
                class="px-4 py-2 border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 text-sm font-semibold rounded-lg shadow-sm transition active:scale-[0.98]"
            >
                Batal
            </a>
        </div>
    </div>

    {{-- Error Banner --}}
    @if ($errors->any())
        <div class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm space-y-1">
            <div class="font-bold flex items-center gap-2">
                <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                Terdapat kesalahan pada input Anda:
            </div>
            <ul class="list-disc list-inside text-xs space-y-0.5 text-red-700">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Form Edit PO --}}
    <form action="{{ route('pembelian.po.update', $purchaseOrder) }}" method="POST" id="poForm" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Card 1: Informasi Header & Supplier --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
            <div class="border-b border-gray-100 pb-3 mb-5 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-gray-900">1. Informasi Supplier & Logistik</h2>
                    <p class="text-xs text-gray-500">Sesuaikan data vendor, gudang penerima, dan termin pembayaran.</p>
                </div>
                <div class="flex items-center gap-3">
                    <label class="inline-flex items-center gap-1.5 text-xs text-gray-700 cursor-pointer">
                        <input type="checkbox" name="is_asset" value="1" {{ old('is_asset', $purchaseOrder->is_asset) ? 'checked' : '' }} class="rounded border-gray-300 text-[#15803d] focus:ring-[#15803d]">
                        <span>Pembelian Aset</span>
                    </label>
                    <label class="inline-flex items-center gap-1.5 text-xs text-gray-700 cursor-pointer">
                        <input type="checkbox" name="is_import" value="1" {{ old('is_import', $purchaseOrder->is_import) ? 'checked' : '' }} class="rounded border-gray-300 text-[#15803d] focus:ring-[#15803d]">
                        <span>Pengadaan Impor</span>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- Supplier --}}
                <div class="md:col-span-1">
                    <div @change="supplierId = $event.detail; onSupplierChange()">
                        <x-searchable-select
                            name="supplier_id"
                            label="Supplier"
                            :options="$suppliers->map(fn($s) => [
                                'id' => $s->id,
                                'label' => $s->perusahaan,
                                'subtext' => $s->code ?? 'SUP'
                            ])"
                            :value="old('supplier_id', $purchaseOrder->supplier_id)"
                            placeholder="-- Cari atau Pilih Supplier --"
                            :required="true"
                        />
                    </div>

                    {{-- Supplier Quick Info Box --}}
                    <div x-show="selectedSupplierInfo" class="mt-2.5 p-3 rounded-lg bg-emerald-50/60 border border-emerald-100 text-xs text-gray-600 space-y-1">
                        <div class="font-bold text-[#14532d]" x-text="selectedSupplierInfo ? selectedSupplierInfo.perusahaan : ''"></div>
                        <div class="flex items-center gap-1 text-[11px] text-gray-500">
                            <span>U.p:</span>
                            <span class="font-medium text-gray-700" x-text="selectedSupplierInfo ? selectedSupplierInfo.kontak_person : '-'"></span>
                            <span class="text-gray-300">|</span>
                            <span>Telp:</span>
                            <span class="font-medium text-gray-700" x-text="selectedSupplierInfo ? selectedSupplierInfo.phone : '-'"></span>
                        </div>
                        <div class="text-[11px] text-gray-500 truncate" x-text="selectedSupplierInfo ? selectedSupplierInfo.address : '-'"></div>
                    </div>
                </div>

                {{-- Cabang --}}
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1">Cabang Operasional</label>
                    <select name="cabang_id" class="w-full py-2 px-3 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        @foreach ($cabangs as $c)
                            <option value="{{ $c->id }}" {{ old('cabang_id', $purchaseOrder->cabang_id) == $c->id ? 'selected' : '' }}>
                                {{ $c->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Gudang Tujuan --}}
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1">Gudang Penerimaan</label>
                    <select name="warehouse_id" class="w-full py-2 px-3 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">-- Pilih Gudang Tujuan --</option>
                        @foreach ($warehouses as $w)
                            <option value="{{ $w->id }}" {{ old('warehouse_id', $purchaseOrder->warehouse_id) == $w->id ? 'selected' : '' }}>
                                {{ $w->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Nomor PO --}}
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1">Nomor PO</label>
                    <input
                        type="text"
                        name="po_number"
                        value="{{ old('po_number', $purchaseOrder->po_number) }}"
                        required
                        class="w-full py-2 px-3 border border-gray-300 rounded-lg text-sm bg-gray-50 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                </div>

                {{-- Tanggal Order --}}
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1">
                        Tanggal Order <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="date"
                        name="order_date"
                        value="{{ old('order_date', optional($purchaseOrder->order_date)->format('Y-m-d')) }}"
                        required
                        class="w-full py-2 px-3 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                </div>

                {{-- Tanggal Estimasi Tiba --}}
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1">Estimasi Tiba (Expected)</label>
                    <input
                        type="date"
                        name="expected_date"
                        value="{{ old('expected_date', optional($purchaseOrder->expected_date)->format('Y-m-d')) }}"
                        class="w-full py-2 px-3 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                </div>

                {{-- Termin Pembayaran (TOP) --}}
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1">Termin Pembayaran (TOP)</label>
                    <select 
                        name="top_type" 
                        x-model="topType" 
                        class="w-full py-2 px-3 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="cod">COD (Cash on Delivery)</option>
                        <option value="credit_days">Kredit (Tempo Hari)</option>
                        <option value="advance_before_delivery">Bayar di Muka (Advance)</option>
                        <option value="deposit_balance">Deposit + Pelunasan</option>
                    </select>
                </div>

                {{-- Tempo Hutang --}}
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1">Tempo Hutang (Hari)</label>
                    <input
                        type="number"
                        name="tempo_hutang"
                        x-model="tempoHutang"
                        min="0"
                        class="w-full py-2 px-3 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                </div>

                {{-- Mata Uang & Kurs --}}
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1">Mata Uang Transaksi</label>
                    <div class="flex gap-2">
                        <select 
                            name="currency_id" 
                            x-model="currencyId" 
                            @change="onCurrencyChange()"
                            class="w-full py-2 px-3 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                            @foreach ($currencies as $c)
                                <option value="{{ $c->id }}" {{ old('currency_id', $purchaseOrder->purchaseOrderCurrency?->first()?->currency_id ?? 1) == $c->id ? 'selected' : '' }}>
                                    {{ $c->code }} - {{ $c->name }}
                                </option>
                            @endforeach
                        </select>

                        <div x-show="isForeignCurrency" class="w-32 flex-shrink-0">
                            <input
                                type="number"
                                name="exchange_rate"
                                x-model="exchangeRate"
                                step="any"
                                min="0.0001"
                                placeholder="Kurs"
                                title="Kurs ke Rupiah"
                                class="w-full py-2 px-2 border border-gray-300 rounded-lg text-sm text-right focus:ring-2 focus:ring-blue-500"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 2: Items Barang / Jasa --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3 mb-4">
                <div>
                    <h2 class="text-base font-bold text-gray-900">2. Rincian Barang / Jasa yang Dipesan</h2>
                    <p class="text-xs text-gray-500">Sesuaikan produk pesanan, kuantitas, harga satuan, dan tarif pajak PPN 11%.</p>
                </div>
                <button
                    type="button"
                    @click="addRow()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 text-[#15803d] hover:bg-emerald-100 text-xs font-bold rounded-lg transition active:scale-[0.98]"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    + Tambah Baris Barang
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm" id="itemsTable">
                    <thead class="bg-gray-50 text-xs uppercase font-bold text-gray-500 border-b border-gray-200">
                        <tr>
                            <th class="py-2.5 px-3 w-8 text-center">#</th>
                            <th class="py-2.5 px-3 min-w-[260px]">Produk & SKU <span class="text-red-500">*</span></th>
                            <th class="py-2.5 px-3 w-24">Satuan</th>
                            <th class="py-2.5 px-3 w-28 text-right">Qty <span class="text-red-500">*</span></th>
                            <th class="py-2.5 px-3 w-36 text-right">Harga Satuan <span class="text-red-500">*</span></th>
                            <th class="py-2.5 px-3 w-24 text-right">Diskon (%)</th>
                            <th class="py-2.5 px-3 w-36">Tipe Pajak</th>
                            <th class="py-2.5 px-3 text-right w-36">Subtotal</th>
                            <th class="py-2.5 px-2 text-center w-10"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="(item, index) in items" :key="index">
                            <tr class="hover:bg-blue-50/20 transition">
                                <td class="py-2.5 px-3 text-center text-xs text-gray-400 font-mono" x-text="index + 1"></td>
                                
                                {{-- Produk --}}
                                <td class="py-2.5 px-3">
                                    <select 
                                        :name="'items[' + index + '][product_id]'" 
                                        x-model="item.productId" 
                                        @change="onProductSelect(index)"
                                        required 
                                        class="w-full py-1.5 px-2 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-blue-500"
                                    >
                                        <option value="">-- Pilih Produk --</option>
                                        @foreach ($products as $p)
                                            <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku ?? '-' }})</option>
                                        @endforeach
                                    </select>
                                    <input type="hidden" :name="'items[' + index + '][currency_id]'" :value="currencyId">
                                    <input type="hidden" :name="'items[' + index + '][tax]'" value="11">
                                </td>

                                {{-- Satuan (UOM) --}}
                                <td class="py-2.5 px-3 text-xs text-gray-600 font-medium" x-text="item.uom || '-'"></td>

                                {{-- Kuantitas --}}
                                <td class="py-2.5 px-3 text-right">
                                    <input 
                                        type="number" 
                                        :name="'items[' + index + '][quantity]'" 
                                        x-model.number="item.quantity" 
                                        min="0.01" 
                                        step="any" 
                                        required 
                                        class="w-full py-1.5 px-2 border border-gray-300 rounded-lg text-sm text-right focus:ring-1 focus:ring-blue-500" 
                                    />
                                </td>

                                {{-- Harga Satuan --}}
                                <td class="py-2.5 px-3 text-right">
                                    <input 
                                        type="number" 
                                        :name="'items[' + index + '][unit_price]'" 
                                        x-model.number="item.unitPrice" 
                                        min="0" 
                                        step="any" 
                                        required 
                                        class="w-full py-1.5 px-2 border border-gray-300 rounded-lg text-sm text-right focus:ring-1 focus:ring-blue-500" 
                                    />
                                </td>

                                {{-- Diskon (%) --}}
                                <td class="py-2.5 px-3 text-right">
                                    <input 
                                        type="number" 
                                        :name="'items[' + index + '][discount]'" 
                                        x-model.number="item.discount" 
                                        min="0" 
                                        max="100" 
                                        step="any" 
                                        class="w-full py-1.5 px-2 border border-gray-300 rounded-lg text-sm text-right focus:ring-1 focus:ring-blue-500" 
                                    />
                                </td>

                                {{-- Tipe Pajak --}}
                                <td class="py-2.5 px-3">
                                    <select 
                                        :name="'items[' + index + '][tipe_pajak]'" 
                                        x-model="item.taxType" 
                                        class="w-full py-1.5 px-2 border border-gray-300 rounded-lg text-xs focus:ring-1 focus:ring-blue-500"
                                    >
                                        <option value="none">Non Pajak</option>
                                        <option value="eklusif">PPN 11% (Eksklusif)</option>
                                        <option value="inklusif">PPN (Inklusif)</option>
                                    </select>
                                </td>

                                {{-- Subtotal --}}
                                <td class="py-2.5 px-3 text-right font-bold text-gray-900 whitespace-nowrap" x-text="formatCurrency(calcSubtotal(item))"></td>

                                {{-- Hapus Baris --}}
                                <td class="py-2.5 px-2 text-center">
                                    <button 
                                        type="button" 
                                        @click="removeRow(index)" 
                                        :disabled="items.length <= 1"
                                        class="text-gray-400 hover:text-red-500 disabled:opacity-30 disabled:hover:text-gray-400 p-1 transition"
                                        title="Hapus Baris"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Card 3: Catatan, Ringkasan Biaya, dan Tombol Simpan --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm flex flex-col lg:flex-row gap-6 justify-between items-start">
            {{-- Bagian Kiri: Catatan --}}
            <div class="w-full lg:w-1/2 space-y-3">
                <label class="block text-xs font-bold uppercase tracking-wider text-gray-700">
                    Catatan / Instruksi Pengiriman Vendor
                </label>
                <textarea
                    name="note"
                    rows="4"
                    placeholder="Instruksi khusus vendor..."
                    class="w-full p-3 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >{{ old('note', $purchaseOrder->note) }}</textarea>
                <p class="text-xs text-gray-400">Catatan ini akan tercetak otomatis pada lembar dokumen Purchase Order resmi.</p>
            </div>

            {{-- Bagian Kanan: Ringkasan Finansial & Aksi --}}
            <div class="w-full lg:w-5/12 space-y-3 bg-gray-50/70 p-4 rounded-xl border border-gray-200/80">
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200 pb-2">
                    Ringkasan Biaya Pembelian
                </h3>

                <div class="space-y-1.5 text-xs text-gray-600">
                    <div class="flex justify-between py-0.5">
                        <span>Subtotal Kotor:</span>
                        <span class="font-medium text-gray-800" x-text="formatCurrency(grossTotal())"></span>
                    </div>
                    <div class="flex justify-between py-0.5" x-show="totalDiscount() > 0">
                        <span>Total Diskon Item:</span>
                        <span class="font-medium text-rose-600" x-text="'- ' + formatCurrency(totalDiscount())"></span>
                    </div>
                    <div class="flex justify-between py-0.5">
                        <span>Dasar Pengenaan Pajak (DPP):</span>
                        <span class="font-medium text-gray-800" x-text="formatCurrency(dppTotal())"></span>
                    </div>
                    <div class="flex justify-between py-0.5">
                        <span>PPN (11%):</span>
                        <span class="font-medium text-gray-800" x-text="formatCurrency(totalTax())"></span>
                    </div>
                </div>

                <div class="pt-2.5 border-t border-gray-200 flex justify-between items-baseline">
                    <span class="text-sm font-bold text-gray-900">Grand Total:</span>
                    <div class="text-right">
                        <div class="font-extrabold text-xl text-[#15803d]" x-text="formatCurrency(grandTotal())"></div>
                        <div x-show="isForeignCurrency" class="text-xs text-gray-500 mt-0.5">
                            Setara IDR: <span class="font-semibold text-gray-800" x-text="formatRupiah(grandTotalIdr())"></span>
                        </div>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="pt-4 border-t border-gray-200 space-y-2">
                    <button
                        type="submit"
                        name="action"
                        value="save_and_approve"
                        class="w-full py-2.5 px-4 bg-[#15803d] hover:bg-[#14532d] text-white text-xs font-bold rounded-lg shadow-sm transition active:scale-[0.98] flex items-center justify-center gap-1.5"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Perbarui & Setujui Langsung
                    </button>

                    <div class="flex items-center gap-2">
                        <button
                            type="submit"
                            name="action"
                            value="save_draft"
                            class="flex-1 py-2 px-3 border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-xs font-semibold rounded-lg shadow-sm transition"
                        >
                            Simpan Perubahan Draf
                        </button>
                        <button
                            type="submit"
                            name="action"
                            value="save_and_request"
                            class="flex-1 py-2 px-3 border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-800 text-xs font-semibold rounded-lg transition"
                        >
                            Ajukan Approval
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function purchaseOrderForm(config) {
    return {
        products: config.products || [],
        suppliers: config.suppliers || [],
        currencies: config.currencies || [],
        supplierId: config.initialSupplierId || '',
        currencyId: config.initialCurrencyId || 1,
        topType: config.initialTopType || 'cod',
        tempoHutang: config.initialTempoHutang || 0,
        exchangeRate: config.initialExchangeRate || 1.0,
        items: [],

        init() {
            if (config.oldItems && config.oldItems.length > 0) {
                this.items = config.oldItems.map(item => ({
                    productId: item.product_id || '',
                    quantity: parseFloat(item.quantity) || 1,
                    unitPrice: parseFloat(item.unit_price) || 0,
                    discount: parseFloat(item.discount) || 0,
                    taxType: item.tipe_pajak || 'eklusif',
                    uom: this.findProductUom(item.product_id),
                    sku: this.findProductSku(item.product_id)
                }));
            } else {
                this.addRow();
            }

            if (this.supplierId) {
                this.onSupplierChange();
            }

            this.onCurrencyChange();
        },

        get selectedSupplierInfo() {
            return this.suppliers.find(s => String(s.id) === String(this.supplierId));
        },

        get isForeignCurrency() {
            const curr = this.currencies.find(c => String(c.id) === String(this.currencyId));
            return curr && curr.code !== 'IDR';
        },

        get currentCurrencySymbol() {
            const curr = this.currencies.find(c => String(c.id) === String(this.currencyId));
            return curr ? curr.symbol : 'Rp';
        },

        onSupplierChange() {
            const supplier = this.selectedSupplierInfo;
            if (supplier) {
                if (supplier.top_type && !config.initialTopType) this.topType = supplier.top_type;
                if (supplier.tempo_hutang !== undefined && !config.initialTempoHutang) this.tempoHutang = supplier.tempo_hutang;
            }
        },

        onCurrencyChange() {
            const curr = this.currencies.find(c => String(c.id) === String(this.currencyId));
            if (curr && !config.initialExchangeRate) {
                this.exchangeRate = curr.rate > 0 ? curr.rate : 1.0;
            }
        },

        findProductUom(productId) {
            const p = this.products.find(x => String(x.id) === String(productId));
            return p ? p.uom : 'Pcs';
        },

        findProductSku(productId) {
            const p = this.products.find(x => String(x.id) === String(productId));
            return p ? p.sku : '-';
        },

        onProductSelect(index) {
            const item = this.items[index];
            if (!item) return;
            const p = this.products.find(x => String(x.id) === String(item.productId));
            if (p) {
                item.uom = p.uom;
                item.sku = p.sku;
                if (!item.unitPrice || item.unitPrice <= 0) {
                    item.unitPrice = p.price;
                }
            }
        },

        addRow() {
            this.items.push({
                productId: '',
                quantity: 1,
                unitPrice: 0,
                discount: 0,
                taxType: 'eklusif',
                uom: 'Pcs',
                sku: '-'
            });
        },

        removeRow(index) {
            if (this.items.length > 1) {
                this.items.splice(index, 1);
            }
        },

        calcSubtotal(item) {
            const qty = parseFloat(item.quantity) || 0;
            const price = parseFloat(item.unitPrice) || 0;
            const disc = parseFloat(item.discount) || 0;

            const gross = qty * price;
            const afterDisc = Math.max(0, gross - (gross * (disc / 100)));

            if (item.taxType === 'eklusif') {
                return afterDisc * 1.11;
            } else {
                return afterDisc;
            }
        },

        grossTotal() {
            return this.items.reduce((sum, item) => {
                const qty = parseFloat(item.quantity) || 0;
                const price = parseFloat(item.unitPrice) || 0;
                return sum + (qty * price);
            }, 0);
        },

        totalDiscount() {
            return this.items.reduce((sum, item) => {
                const qty = parseFloat(item.quantity) || 0;
                const price = parseFloat(item.unitPrice) || 0;
                const disc = parseFloat(item.discount) || 0;
                const gross = qty * price;
                return sum + (gross * (disc / 100));
            }, 0);
        },

        dppTotal() {
            return this.items.reduce((sum, item) => {
                const qty = parseFloat(item.quantity) || 0;
                const price = parseFloat(item.unitPrice) || 0;
                const disc = parseFloat(item.discount) || 0;
                const gross = qty * price;
                const afterDisc = Math.max(0, gross - (gross * (disc / 100)));

                if (item.taxType === 'inklusif') {
                    return sum + (afterDisc / 1.11);
                }
                return sum + afterDisc;
            }, 0);
        },

        totalTax() {
            return this.items.reduce((sum, item) => {
                const qty = parseFloat(item.quantity) || 0;
                const price = parseFloat(item.unitPrice) || 0;
                const disc = parseFloat(item.discount) || 0;
                const gross = qty * price;
                const afterDisc = Math.max(0, gross - (gross * (disc / 100)));

                if (item.taxType === 'eklusif') {
                    return sum + (afterDisc * 0.11);
                } else if (item.taxType === 'inklusif') {
                    return sum + (afterDisc - (afterDisc / 1.11));
                }
                return sum;
            }, 0);
        },

        grandTotal() {
            return this.items.reduce((sum, item) => sum + this.calcSubtotal(item), 0);
        },

        grandTotalIdr() {
            return this.grandTotal() * (parseFloat(this.exchangeRate) || 1.0);
        },

        formatCurrency(val) {
            const sym = this.currentCurrencySymbol;
            return sym + ' ' + Math.round(val || 0).toLocaleString('id-ID');
        },

        formatRupiah(val) {
            return 'Rp ' + Math.round(val || 0).toLocaleString('id-ID');
        }
    };
}
</script>
@endsection
