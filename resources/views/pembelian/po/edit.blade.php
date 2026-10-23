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
                <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Ubah Pesanan Pembelian</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                    {{ $purchaseOrder->po_number }} (Draf)
                </span>
            </div>
            <p class="text-sm text-gray-500 mt-0.5">Perbarui rincian pesanan pembelian berstatus draf sebelum diajukan ke proses persetujuan.</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('pembelian.po.show', $purchaseOrder) }}"
                class="px-4 py-2 border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 text-sm font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
            >
                Batal
            </a>
        </div>
    </div>

    {{-- Error Banner --}}
    @if ($errors->any())
        <div class="p-4 rounded-2xl bg-red-50 border border-red-200 text-red-800 text-sm space-y-1 shadow-sm">
            <div class="font-bold flex items-center gap-2">
                <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                Terdapat kesalahan pada input formulir:
            </div>
            <ul class="list-disc list-inside text-xs space-y-0.5 text-red-700 pl-6">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Form Edit PO --}}
    <form action="{{ route('pembelian.po.update', $purchaseOrder) }}" method="POST" id="poForm">
        @csrf
        @method('PUT')

        {{-- Desktop 2-Kolom Layout --}}
        <div class="flex flex-col lg:flex-row gap-6 items-start">
            
            {{-- Kolom Kiri: Input Form, Items, Catatan --}}
            <div class="w-full lg:flex-1 min-w-0 space-y-6">

                {{-- Card 1: Informasi Header & Supplier --}}
                <div class="bg-white rounded-2xl border border-gray-200 p-5 sm:p-6 shadow-sm">
                    <div class="border-b border-gray-100 pb-3 mb-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <div class="flex items-center gap-2.5">
                            <span class="w-7 h-7 rounded-lg bg-emerald-50 text-[#15803d] inline-flex items-center justify-center font-extrabold text-sm">1</span>
                            <div>
                                <h2 class="text-base font-bold text-gray-900">Informasi Supplier & Logistik</h2>
                                <p class="text-xs text-gray-500">Sesuaikan data vendor, gudang penerima, dan termin pembayaran.</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 text-xs font-medium text-gray-700">
                            <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                <input type="checkbox" name="is_asset" value="1" {{ old('is_asset', $purchaseOrder->is_asset) ? 'checked' : '' }} class="rounded border-gray-300 text-[#15803d] focus:ring-[#15803d]">
                                <span>Pembelian Aset</span>
                            </label>
                            <label class="inline-flex items-center gap-1.5 cursor-pointer">
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
                            <div x-show="selectedSupplierInfo" class="mt-2.5 p-3 rounded-xl bg-emerald-50/60 border border-emerald-100 text-xs text-gray-600 space-y-1">
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
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1.5">Cabang Operasional</label>
                            <select name="cabang_id" class="w-full py-2.5 px-3 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition">
                                @foreach ($cabangs as $c)
                                    <option value="{{ $c->id }}" {{ old('cabang_id', $purchaseOrder->cabang_id) == $c->id ? 'selected' : '' }}>
                                        {{ $c->nama }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Gudang Tujuan Searchable --}}
                        <div>
                            <x-searchable-select
                                name="warehouse_id"
                                label="Gudang Penerimaan"
                                :options="$warehouses->map(fn($w) => [
                                    'id' => $w->id,
                                    'label' => $w->name,
                                    'subtext' => $w->code ?? 'GDG'
                                ])"
                                :value="old('warehouse_id', $purchaseOrder->warehouse_id)"
                                placeholder="-- Cari atau Pilih Gudang --"
                            />
                        </div>

                        {{-- Nomor PO --}}
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1.5">Nomor PO</label>
                            <input
                                type="text"
                                name="po_number"
                                value="{{ old('po_number', $purchaseOrder->po_number) }}"
                                required
                                class="w-full py-2.5 px-3 border border-gray-300 rounded-lg text-sm bg-gray-50 font-mono font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                            />
                        </div>

                        {{-- Tanggal Order --}}
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1.5">
                                Tanggal Order <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="date"
                                name="order_date"
                                value="{{ old('order_date', $purchaseOrder->order_date ? \Carbon\Carbon::parse($purchaseOrder->order_date)->format('Y-m-d') : '') }}"
                                required
                                class="w-full py-2.5 px-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                            />
                        </div>

                        {{-- Tanggal Estimasi Tiba --}}
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1.5">Estimasi Tiba (Expected)</label>
                            <input
                                type="date"
                                name="expected_date"
                                value="{{ old('expected_date', $purchaseOrder->expected_date ? \Carbon\Carbon::parse($purchaseOrder->expected_date)->format('Y-m-d') : '') }}"
                                class="w-full py-2.5 px-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                            />
                        </div>

                        {{-- Termin Pembayaran (TOP) --}}
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1.5">Termin Pembayaran (TOP)</label>
                            <select 
                                name="top_type" 
                                x-model="topType" 
                                class="w-full py-2.5 px-3 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                            >
                                <option value="cod">COD (Cash on Delivery)</option>
                                <option value="credit_days">Kredit (Tempo Hari)</option>
                                <option value="advance_before_delivery">Bayar di Muka (Advance)</option>
                                <option value="deposit_balance">Deposit + Pelunasan</option>
                            </select>
                        </div>

                        {{-- Tempo Hutang --}}
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1.5">Tempo Hutang (Hari)</label>
                            <input
                                type="number"
                                name="tempo_hutang"
                                x-model="tempoHutang"
                                min="0"
                                class="w-full py-2.5 px-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                            />
                        </div>

                        {{-- Mata Uang & Kurs --}}
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1.5">Mata Uang Transaksi</label>
                            <div class="flex gap-2">
                                <select 
                                    name="currency_id" 
                                    x-model="currencyId" 
                                    @change="onCurrencyChange()"
                                    class="w-full py-2.5 px-3 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
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
                                        class="w-full py-2.5 px-2 border border-gray-300 rounded-lg text-sm text-right focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Card 2: Items Barang / Jasa --}}
                <div class="bg-white rounded-2xl border border-gray-200 p-5 sm:p-6 shadow-sm">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-3 mb-4 border-b border-gray-100 gap-2">
                        <div class="flex items-center gap-2.5">
                            <span class="w-7 h-7 rounded-lg bg-emerald-50 text-[#15803d] inline-flex items-center justify-center font-extrabold text-sm">2</span>
                            <div>
                                <h2 class="text-base font-bold text-gray-900">Rincian Barang / Jasa yang Dipesan</h2>
                                <p class="text-xs text-gray-500">Pilih produk menggunakan fitur pencarian cepat, sesuaikan kuantitas, harga, diskon, dan PPN.</p>
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="addRow()"
                            class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 bg-emerald-50 text-[#15803d] hover:bg-emerald-100 text-xs font-bold rounded-xl transition active:scale-[0.98] self-start sm:self-auto cursor-pointer"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                            </svg>
                            + Tambah Baris Barang
                        </button>
                    </div>

                    {{-- Desktop & Tablet Table (Hidden on Mobile) --}}
                    <div class="hidden md:block overflow-visible">
                        <table class="w-full text-left text-sm" id="itemsTable">
                            <thead class="bg-gray-50 text-xs uppercase font-bold text-gray-500 border-b border-gray-200">
                                <tr>
                                    <th class="py-2.5 px-3 w-8 text-center">#</th>
                                    <th class="py-2.5 px-3 min-w-[280px]">Produk & SKU <span class="text-red-500">*</span></th>
                                    <th class="py-2.5 px-3 w-20 text-center">Satuan</th>
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
                                    <tr class="hover:bg-emerald-50/20 transition">
                                        <td class="py-2.5 px-3 text-center text-xs text-gray-400 font-mono" x-text="index + 1"></td>
                                        
                                        {{-- Produk Searchable Combobox --}}
                                        <td class="py-2.5 px-3 relative" @click.outside="item.dropdownOpen = false">
                                            <div class="relative">
                                                <input 
                                                    type="text" 
                                                    x-model="item.searchQuery" 
                                                    @focus="item.dropdownOpen = true"
                                                    @input="item.dropdownOpen = true; if (!item.searchQuery) item.productId = ''"
                                                    placeholder="-- Ketik nama atau SKU produk --"
                                                    class="w-full py-2 pl-3 pr-7 border border-gray-300 rounded-lg text-xs font-medium focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] bg-white transition"
                                                />
                                                <template x-if="item.productId">
                                                    <button 
                                                        type="button" 
                                                        @click.stop="clearProduct(item)"
                                                        class="absolute inset-y-0 right-0 pr-2 flex items-center text-gray-400 hover:text-red-500"
                                                        title="Hapus pilihan produk"
                                                    >
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                    </button>
                                                </template>
                                            </div>

                                            {{-- Dropdown Suggestions --}}
                                            <div 
                                                x-show="item.dropdownOpen" 
                                                x-transition
                                                class="absolute left-0 top-full mt-1 w-full min-w-[300px] max-w-md bg-white border border-gray-200 rounded-xl shadow-xl z-50 max-h-56 overflow-y-auto p-1 text-xs"
                                                style="display: none;"
                                            >
                                                <template x-for="p in getItemFilteredProducts(item)" :key="p.id">
                                                    <div 
                                                        @click="selectProduct(item, p)"
                                                        class="p-2 rounded-lg hover:bg-emerald-50 cursor-pointer flex flex-col gap-0.5 border-b border-gray-50 last:border-b-0 transition"
                                                        :class="{'bg-emerald-50/80 font-bold text-emerald-900': item.productId == p.id}"
                                                    >
                                                        <div class="font-medium text-gray-800 flex items-center justify-between">
                                                            <span x-text="p.name" class="truncate"></span>
                                                            <span class="text-[10px] text-gray-400 font-mono ml-2 shrink-0" x-text="p.sku"></span>
                                                        </div>
                                                        <div class="text-[11px] text-gray-500 flex items-center justify-between">
                                                            <span>Satuan: <strong class="text-gray-700" x-text="p.uom"></strong></span>
                                                            <span class="text-[#15803d] font-semibold" x-text="formatRupiah(p.price)"></span>
                                                        </div>
                                                    </div>
                                                </template>
                                                <div x-show="getItemFilteredProducts(item).length === 0" class="p-3 text-center text-gray-400 select-none">
                                                    Tidak ada produk ditemukan
                                                </div>
                                            </div>

                                            {{-- Native Hidden Form Inputs --}}
                                            <input type="hidden" :name="'items[' + index + '][product_id]'" :value="item.productId" required>
                                            <input type="hidden" :name="'items[' + index + '][currency_id]'" :value="currencyId">
                                            <input type="hidden" :name="'items[' + index + '][tax]'" value="11">
                                        </td>

                                        {{-- Satuan (UOM) --}}
                                        <td class="py-2.5 px-3 text-center">
                                            <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-gray-100 text-gray-700" x-text="item.uom || 'Pcs'"></span>
                                        </td>

                                        {{-- Kuantitas --}}
                                        <td class="py-2.5 px-3 text-right">
                                            <input 
                                                type="number" 
                                                :name="'items[' + index + '][quantity]'" 
                                                x-model.number="item.quantity" 
                                                min="0.01" 
                                                step="any" 
                                                required 
                                                class="w-full py-1.5 px-2 border border-gray-300 rounded-lg text-sm text-right font-bold text-gray-800 focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d]" 
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
                                                class="w-full py-1.5 px-2 border border-gray-300 rounded-lg text-sm text-right font-medium text-gray-800 focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d]" 
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
                                                class="w-full py-1.5 px-2 border border-gray-300 rounded-lg text-sm text-right focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d]" 
                                            />
                                        </td>

                                        {{-- Tipe Pajak --}}
                                        <td class="py-2.5 px-3">
                                            <select 
                                                :name="'items[' + index + '][tipe_pajak]'" 
                                                x-model="item.taxType" 
                                                class="w-full py-1.5 px-2 border border-gray-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d]"
                                            >
                                                <option value="none">Non Pajak</option>
                                                <option value="eklusif">PPN 11% (Eksklusif)</option>
                                                <option value="inklusif">PPN (Inklusif)</option>
                                            </select>
                                        </td>

                                        {{-- Subtotal --}}
                                        <td class="py-2.5 px-3 text-right font-extrabold text-[#15803d] whitespace-nowrap" x-text="formatCurrency(calcSubtotal(item))"></td>

                                        {{-- Hapus Baris --}}
                                        <td class="py-2.5 px-2 text-center">
                                            <button 
                                                type="button" 
                                                @click="removeRow(index)" 
                                                :disabled="items.length <= 1"
                                                class="text-gray-400 hover:text-red-500 disabled:opacity-20 disabled:hover:text-gray-400 p-1 transition cursor-pointer"
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

                    {{-- Mobile Card View (< 768px) --}}
                    <div class="block md:hidden space-y-4">
                        <template x-for="(item, index) in items" :key="index">
                            <div class="border border-gray-200 rounded-xl p-4 bg-white shadow-xs relative flex flex-col gap-3">
                                <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                                    <span class="text-xs font-bold text-gray-500 font-mono">Item #<span x-text="index + 1"></span></span>
                                    <button 
                                        type="button" 
                                        @click="removeRow(index)" 
                                        :disabled="items.length <= 1"
                                        class="text-gray-400 hover:text-red-500 disabled:opacity-20 p-1 transition"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>

                                {{-- Produk Searchable di Mobile --}}
                                <div class="relative" @click.outside="item.dropdownOpen = false">
                                    <label class="block text-[11px] font-bold uppercase text-gray-600 mb-1">Produk <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <input 
                                            type="text" 
                                            x-model="item.searchQuery" 
                                            @focus="item.dropdownOpen = true"
                                            @input="item.dropdownOpen = true; if (!item.searchQuery) item.productId = ''"
                                            placeholder="-- Cari nama atau SKU --"
                                            class="w-full py-2 pl-3 pr-7 border border-gray-300 rounded-lg text-xs font-medium focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] bg-white"
                                        />
                                        <template x-if="item.productId">
                                            <button 
                                                type="button" 
                                                @click.stop="clearProduct(item)"
                                                class="absolute inset-y-0 right-0 pr-2 flex items-center text-gray-400 hover:text-red-500"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                            </button>
                                        </template>
                                    </div>

                                    <div 
                                        x-show="item.dropdownOpen" 
                                        class="absolute left-0 top-full mt-1 w-full bg-white border border-gray-200 rounded-xl shadow-xl z-50 max-h-52 overflow-y-auto p-1 text-xs"
                                        style="display: none;"
                                    >
                                        <template x-for="p in getItemFilteredProducts(item)" :key="p.id">
                                            <div 
                                                @click="selectProduct(item, p)"
                                                class="p-2 rounded-lg hover:bg-emerald-50 cursor-pointer flex flex-col gap-0.5 border-b border-gray-50 last:border-b-0"
                                                :class="{'bg-emerald-50 font-bold text-emerald-900': item.productId == p.id}"
                                            >
                                                <div class="font-medium text-gray-800" x-text="p.name"></div>
                                                <div class="text-[10px] text-gray-500 flex justify-between">
                                                    <span x-text="p.sku"></span>
                                                    <span class="text-[#15803d]" x-text="formatRupiah(p.price)"></span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>

                                    <input type="hidden" :name="'items[' + index + '][product_id]'" :value="item.productId" required>
                                    <input type="hidden" :name="'items[' + index + '][currency_id]'" :value="currencyId">
                                    <input type="hidden" :name="'items[' + index + '][tax]'" value="11">
                                </div>

                                {{-- Grid Qty & Harga --}}
                                <div class="grid grid-cols-2 gap-2.5">
                                    <div>
                                        <label class="block text-[11px] font-bold text-gray-600 mb-1">Qty (<span x-text="item.uom || 'Pcs'"></span>)</label>
                                        <input 
                                            type="number" 
                                            :name="'items[' + index + '][quantity]'" 
                                            x-model.number="item.quantity" 
                                            min="0.01" 
                                            step="any" 
                                            required 
                                            class="w-full py-1.5 px-2 border border-gray-300 rounded-lg text-sm text-right font-bold focus:outline-none focus:ring-1 focus:ring-[#15803d]" 
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-bold text-gray-600 mb-1">Harga Satuan</label>
                                        <input 
                                            type="number" 
                                            :name="'items[' + index + '][unit_price]'" 
                                            x-model.number="item.unitPrice" 
                                            min="0" 
                                            step="any" 
                                            required 
                                            class="w-full py-1.5 px-2 border border-gray-300 rounded-lg text-sm text-right focus:outline-none focus:ring-1 focus:ring-[#15803d]" 
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-bold text-gray-600 mb-1">Diskon (%)</label>
                                        <input 
                                            type="number" 
                                            :name="'items[' + index + '][discount]'" 
                                            x-model.number="item.discount" 
                                            min="0" 
                                            max="100" 
                                            class="w-full py-1.5 px-2 border border-gray-300 rounded-lg text-sm text-right focus:outline-none focus:ring-1 focus:ring-[#15803d]" 
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-bold text-gray-600 mb-1">Pajak</label>
                                        <select 
                                            :name="'items[' + index + '][tipe_pajak]'" 
                                            x-model="item.taxType" 
                                            class="w-full py-1.5 px-2 border border-gray-300 rounded-lg text-xs bg-white focus:outline-none focus:ring-1 focus:ring-[#15803d]"
                                        >
                                            <option value="none">Non Pajak</option>
                                            <option value="eklusif">PPN 11% (Eksk)</option>
                                            <option value="inklusif">PPN (Inkl)</option>
                                        </select>
                                    </div>
                                </div>

                                {{-- Subtotal Footer --}}
                                <div class="flex justify-between items-center pt-2 border-t border-gray-100 text-xs">
                                    <span class="text-gray-500 font-medium">Subtotal Item:</span>
                                    <span class="font-extrabold text-sm text-[#15803d]" x-text="formatCurrency(calcSubtotal(item))"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Card 3: Catatan Instruksi Pengiriman --}}
                <div class="bg-white rounded-2xl border border-gray-200 p-5 sm:p-6 shadow-sm space-y-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700">
                        Catatan / Instruksi Pengiriman Vendor
                    </label>
                    <textarea
                        name="note"
                        rows="3"
                        placeholder="Contoh: Barang harap dikirim menggunakan armada tertutup, cantumkan nomor PO pada surat jalan vendor..."
                        class="w-full p-3 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                    >{{ old('note', $purchaseOrder->note) }}</textarea>
                    <p class="text-xs text-gray-400">Catatan ini akan tercetak otomatis pada lembar dokumen Purchase Order resmi.</p>
                </div>
            </div>

            {{-- Kolom Kanan: Ringkasan Sticky (Aside) --}}
            <aside class="w-full lg:w-[320px] lg:shrink-0 bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 flex flex-col gap-4 shadow-sm lg:sticky lg:top-[70px]">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <h2 class="text-base font-bold text-gray-900 m-0">Ringkasan Biaya</h2>
                    <span class="text-xs text-gray-500 font-semibold" x-text="items.length + ' Baris'"></span>
                </div>

                <div class="space-y-2 text-xs text-gray-600">
                    <div class="flex justify-between py-0.5">
                        <span>Subtotal Kotor:</span>
                        <span class="font-semibold text-gray-800" x-text="formatCurrency(grossTotal())"></span>
                    </div>
                    <div class="flex justify-between py-0.5" x-show="totalDiscount() > 0">
                        <span>Total Diskon Item:</span>
                        <span class="font-semibold text-rose-600" x-text="'- ' + formatCurrency(totalDiscount())"></span>
                    </div>
                    <div class="flex justify-between py-0.5">
                        <span>Dasar Pengenaan Pajak (DPP):</span>
                        <span class="font-semibold text-gray-800" x-text="formatCurrency(dppTotal())"></span>
                    </div>
                    <div class="flex justify-between py-0.5">
                        <span>PPN (11%):</span>
                        <span class="font-semibold text-gray-800" x-text="formatCurrency(totalTax())"></span>
                    </div>
                </div>

                <div class="pt-3 border-t border-gray-100 flex justify-between items-baseline">
                    <span class="text-sm font-bold text-gray-900">Grand Total:</span>
                    <div class="text-right">
                        <div class="font-extrabold text-xl text-[#15803d]" x-text="formatCurrency(grandTotal())"></div>
                        <div x-show="isForeignCurrency" class="text-xs text-gray-500 mt-0.5">
                            Setara IDR: <span class="font-semibold text-gray-800" x-text="formatRupiah(grandTotalIdr())"></span>
                        </div>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="pt-3 border-t border-gray-100 space-y-2.5">
                    <button
                        type="submit"
                        name="action"
                        value="save_and_approve"
                        class="w-full py-3 px-4 bg-[#15803d] hover:bg-[#14532d] text-white text-xs font-bold rounded-xl shadow-sm transition active:scale-[0.98] flex items-center justify-center gap-1.5 cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                        </svg>
                        Simpan & Setujui Langsung
                    </button>

                    <button
                        type="submit"
                        name="action"
                        value="save_and_request"
                        class="w-full py-2.5 px-3 border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-800 text-xs font-bold rounded-xl transition cursor-pointer"
                    >
                        Ajukan Approval Atasan
                    </button>

                    <button
                        type="submit"
                        name="action"
                        value="save_draft"
                        class="w-full py-2.5 px-3 border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-xs font-semibold rounded-xl shadow-xs transition cursor-pointer"
                    >
                        Perbarui Draf
                    </button>
                </div>
            </aside>
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
            // Inisialisasi items dari old input jika ada, atau buat 1 baris kosong
            if (config.oldItems && config.oldItems.length > 0) {
                this.items = config.oldItems.map(item => this.createEmptyItem(item));
            } else {
                this.addRow();
            }

            // Sync supplier default
            if (this.supplierId && !this.topType) {
                this.onSupplierChange();
            }

            this.onCurrencyChange();
        },

        createEmptyItem(prefill = {}) {
            const prodId = prefill.product_id || prefill.productId || '';
            const p = this.products.find(x => String(x.id) === String(prodId));
            return {
                productId: prodId,
                searchQuery: p ? (p.name + (p.sku && p.sku !== '-' ? ' (' + p.sku + ')' : '')) : '',
                dropdownOpen: false,
                quantity: prefill.quantity !== undefined ? parseFloat(prefill.quantity) : 1,
                unitPrice: prefill.unit_price !== undefined ? parseFloat(prefill.unit_price) : (p ? p.price : 0),
                discount: prefill.discount !== undefined ? parseFloat(prefill.discount) : 0,
                taxType: prefill.tipe_pajak || prefill.taxType || 'eklusif',
                uom: p ? p.uom : 'Pcs',
                sku: p ? p.sku : '-'
            };
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
                if (supplier.top_type) this.topType = supplier.top_type;
                if (supplier.tempo_hutang !== undefined) this.tempoHutang = supplier.tempo_hutang;
            }
        },

        onCurrencyChange() {
            const curr = this.currencies.find(c => String(c.id) === String(this.currencyId));
            if (curr && (!this.exchangeRate || this.exchangeRate === 1.0)) {
                this.exchangeRate = curr.rate > 0 ? curr.rate : 1.0;
            }
        },

        getItemFilteredProducts(item) {
            if (!item.searchQuery || !item.searchQuery.trim()) {
                return this.products.slice(0, 50);
            }
            const q = item.searchQuery.toLowerCase();
            return this.products.filter(p => {
                const nameMatch = (p.name || '').toLowerCase().includes(q);
                const skuMatch = (p.sku || '').toLowerCase().includes(q);
                return nameMatch || skuMatch;
            }).slice(0, 50);
        },

        selectProduct(item, p) {
            item.productId = p.id;
            item.searchQuery = p.name + (p.sku && p.sku !== '-' ? ' (' + p.sku + ')' : '');
            item.uom = p.uom;
            item.sku = p.sku;
            if (!item.unitPrice || item.unitPrice <= 0) {
                item.unitPrice = p.price;
            }
            item.dropdownOpen = false;
        },

        clearProduct(item) {
            item.productId = '';
            item.searchQuery = '';
            item.uom = 'Pcs';
            item.sku = '-';
            item.dropdownOpen = false;
        },

        addRow() {
            this.items.push(this.createEmptyItem());
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
