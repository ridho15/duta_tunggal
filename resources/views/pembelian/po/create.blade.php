@extends('layouts.app')

@section('title', 'Buat Pesanan Pembelian (PO) Baru – Duta Tunggal ERP')

@section('content')
<div 
    class="space-y-6 max-w-7xl mx-auto pb-16"
    x-data="purchaseOrderForm({
        suppliers: {{ Js::from($suppliers->map(fn($s) => [
            'id' => $s->id,
            'perusahaan' => $s->perusahaan,
            'code' => $s->code ?? 'SUP',
            'kontak_person' => $s->kontak_person ?? '-',
            'phone' => $s->telepon ?? $s->phone ?? '-',
            'address' => $s->address ?? $s->alamat ?? '-',
            'top_type' => $s->top_type ?? 'cod',
            'tempo_hutang' => (int)($s->tempo_hutang ?? 0)
        ])) }},
        warehouses: {{ Js::from($warehouses->map(fn($w) => ['id' => $w->id, 'name' => $w->name, 'code' => $w->kode ?? 'GDG'])) }},
        cabangs: {{ Js::from($cabangs->map(fn($c) => ['id' => $c->id, 'nama' => $c->nama, 'kode' => $c->kode ?? ''])) }},
        currencies: {{ Js::from($currencies->map(fn($c) => [
            'id' => $c->id,
            'code' => $c->code,
            'name' => $c->name,
            'symbol' => $c->symbol,
            'rate' => (float)($c->to_rupiah ?? 1)
        ])) }},
        defaultCabangId: {{ (int) $defaultCabangId }},
        defaultCurrencyId: {{ (int) $defaultCurrency->id }},
        initialSupplierId: '{{ old('supplier_id', $selectedSupplier?->id ?? '') }}',
        initialCurrencyId: '{{ old('currency_id', $defaultCurrency->id) }}',
        productsSearchUrl: '{{ route('pembelian.products.search') }}',
        initialItems: {{ Js::from(old('items', $initialPoItems ?? [])) }},
        preloadedProducts: {{ Js::from($preloadedProducts->map(fn($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku ?? '-',
            'cost_price' => (float)($p->cost_price ?? 0),
            'tipe_pajak' => $p->tipe_pajak ?? 'inklusif',
            'uom' => $p->uom?->abbreviation ?? $p->uom?->name ?? 'Pcs',
            'cabang_id' => $p->cabang_id,
            'default_tax_rate' => (float)($p->pajak > 0 ? $p->pajak : 11),
            'suppliers' => $p->suppliers->map(fn($s) => [
                'id' => $s->id,
                'perusahaan' => $s->perusahaan,
                'price' => $s->pivot?->supplier_price !== null ? (float) $s->pivot->supplier_price : null
            ])->values()->all(),
        ])) }}
    })"
>
    {{-- Header Breadcrumb & Actions --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('pembelian.po.index') }}" class="hover:text-[#15803d] transition">Pesanan Pembelian</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Buat Baru</span>
            </div>
            <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Buat Pesanan Pembelian (Purchase Order)</h1>
            <p class="text-sm text-gray-500 mt-0.5">Input order pembelian resmi kepada supplier untuk kebutuhan persediaan stok atau operasional.</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('pembelian.po.index') }}"
                class="px-4 py-2 border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 text-sm font-semibold rounded-xl shadow-xs transition active:scale-[0.98]"
            >
                Batal
            </a>
        </div>
    </div>

    {{-- Error Banner --}}
    @if (isset($errors) && $errors->any())
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

    {{-- Banner Konversi dari Order Request --}}
    @if (!empty($selectedOrderRequest))
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-[#15803d] text-white flex items-center justify-center flex-shrink-0 shadow-xs">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <div>
                    <div class="font-bold flex items-center gap-2">
                        <span>Menerbitkan PO dari Permintaan: {{ $selectedOrderRequest->request_number }}</span>
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-[#14532d] uppercase">Terkunci Sesuai OR</span>
                    </div>
                    <div class="text-xs text-emerald-700">Item barang dan kuantitas dimuat otomatis sesuai sisa kuantitas yang disetujui.</div>
                </div>
            </div>
            <a href="{{ route('pembelian.or.show', $selectedOrderRequest) }}" target="_blank" class="text-xs font-semibold text-[#15803d] hover:text-[#14532d] underline self-start sm:self-auto flex items-center gap-1">
                <span>Lihat Dokumen OR</span>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
            </a>
        </div>
    @endif

    {{-- Form PO --}}
    <form action="{{ route('pembelian.po.store') }}" method="POST" id="poForm">
        @csrf
        @if (!empty($selectedOrderRequest))
            <input type="hidden" name="order_request_id" value="{{ $selectedOrderRequest->id }}">
        @endif

        {{-- Desktop 2-Kolom Layout (Kiri: Form Input & Items, Kanan: Sticky Summary) --}}
        <div class="flex flex-col lg:flex-row gap-6 items-start">
            
            {{-- Kolom Kiri: Input Utama, Items, Catatan --}}
            <div class="w-full lg:flex-1 min-w-0 space-y-6">

                {{-- Card 1: Informasi Header & Supplier --}}
                <div class="bg-white rounded-2xl border border-gray-200 p-5 sm:p-6 shadow-sm">
                    <div class="border-b border-gray-100 pb-3 mb-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <div class="flex items-center gap-2.5">
                            <span class="w-7 h-7 rounded-lg bg-emerald-50 text-[#15803d] inline-flex items-center justify-center font-extrabold text-sm">1</span>
                            <div>
                                <h2 class="text-base font-bold text-gray-900">Informasi Supplier & Logistik</h2>
                                <p class="text-xs text-gray-500">Tentukan rekanan vendor, cabang operasional, gudang penerimaan, dan termin pembayaran.</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 text-xs font-medium text-gray-700">
                            <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                <input type="checkbox" name="is_asset" value="1" {{ old('is_asset') ? 'checked' : '' }} class="rounded border-gray-300 text-[#15803d] focus:ring-[#15803d]">
                                <span>Pembelian Aset</span>
                            </label>
                            <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                <input type="checkbox" name="is_import" value="1" {{ old('is_import') ? 'checked' : '' }} class="rounded border-gray-300 text-[#15803d] focus:ring-[#15803d]">
                                <span>Pengadaan Impor</span>
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        {{-- Supplier Selector --}}
                        <div class="md:col-span-1" @change="if ($event.detail !== undefined) { supplierId = $event.detail; onSupplierChange(); }">
                            <x-searchable-select
                                name="supplier_id"
                                label="Supplier / Vendor Rekanan"
                                :options="$suppliers->map(fn($s) => [
                                    'id' => $s->id,
                                    'label' => ($s->code ? '(' . $s->code . ') ' : '') . $s->perusahaan,
                                    'subtext' => ($s->kontak_person ? 'U.p: ' . $s->kontak_person : '') . ($s->phone ? ' | Telp: ' . $s->phone : ''),
                                    'badge' => $s->code ?? 'SUP'
                                ])"
                                :value="old('supplier_id', $selectedSupplier?->id ?? '')"
                                placeholder="-- Cari / Pilih Rekanan Vendor --"
                                :required="true"
                            />

                            {{-- Supplier Quick Info Box --}}
                            <div x-show="selectedSupplierInfo" class="mt-2.5 p-3 rounded-xl bg-emerald-50/60 border border-emerald-100 text-xs text-gray-600 space-y-1">
                                <div class="font-bold text-[#14532d] flex items-center justify-between">
                                    <span x-text="selectedSupplierInfo ? selectedSupplierInfo.perusahaan : ''"></span>
                                    <span class="text-[10px] bg-emerald-200/70 text-emerald-900 px-1.5 py-0.2 rounded font-mono" x-text="selectedSupplierInfo ? selectedSupplierInfo.code : ''"></span>
                                </div>
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
                            <x-searchable-select
                                name="cabang_id"
                                label="Cabang Operasional"
                                :options="$cabangs->map(fn($c) => [
                                    'id' => $c->id,
                                    'label' => $c->nama,
                                    'subtext' => $c->kode ?? '',
                                    'badge' => $c->kode ?? ''
                                ])"
                                :value="old('cabang_id', $defaultCabangId)"
                                placeholder="-- Cari / Pilih Cabang --"
                                :required="true"
                            />
                        </div>

                        {{-- Gudang Tujuan --}}
                        <div>
                            <x-searchable-select
                                name="warehouse_id"
                                label="Gudang Penerimaan"
                                :options="$warehouses->map(fn($w) => [
                                    'id' => $w->id,
                                    'label' => $w->name,
                                    'subtext' => 'Kode: ' . ($w->kode ?? 'GDG'),
                                    'badge' => $w->kode ?? 'GDG'
                                ])"
                                :value="old('warehouse_id')"
                                placeholder="-- Cari / Pilih Gudang Tujuan --"
                                :required="false"
                            />
                        </div>

                        {{-- Nomor PO --}}
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1.5">Nomor PO (Otomatis)</label>
                            <input
                                type="text"
                                name="po_number"
                                value="{{ old('po_number', $defaultPoNumber) }}"
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
                                value="{{ old('order_date', $defaultDate) }}"
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
                                value="{{ old('expected_date') }}"
                                class="w-full py-2.5 px-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                            />
                        </div>

                        {{-- Termin Pembayaran (TOP) --}}
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1.5">Termin Pembayaran (TOP)</label>
                            <select 
                                name="top_type" 
                                x-model="topType" 
                                class="w-full py-2.5 px-3 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition font-medium text-gray-800"
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
                        <div @change="if ($event.detail !== undefined) { currencyId = $event.detail; onCurrencyChange(); }">
                            <x-searchable-select
                                name="currency_id"
                                label="Mata Uang Transaksi"
                                :options="$currencies->map(fn($c) => [
                                    'id' => $c->id,
                                    'label' => $c->code . ' - ' . $c->name,
                                    'subtext' => 'Simbol: ' . $c->symbol . ($c->to_rupiah > 1 ? ' | Kurs: ' . number_format($c->to_rupiah, 2, ',', '.') : ''),
                                    'badge' => $c->code
                                ])"
                                :value="old('currency_id', $defaultCurrency->id)"
                                placeholder="-- Cari / Pilih Mata Uang --"
                                :required="true"
                            />

                            <div x-show="isForeignCurrency" class="mt-2">
                                <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1">
                                    Kurs ke Rupiah <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="number"
                                    name="exchange_rate"
                                    x-model="exchangeRate"
                                    step="any"
                                    min="0.0001"
                                    placeholder="Kurs ke Rupiah"
                                    title="Kurs ke Rupiah"
                                    class="w-full py-2.5 px-3 border border-gray-300 rounded-lg text-sm text-right font-semibold text-gray-800 focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Card 2: Items Barang / Jasa (Server-Side AJAX & Filament Aligned) --}}
                <div class="bg-white rounded-2xl border border-gray-200 p-5 sm:p-6 shadow-sm space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-3 border-b border-gray-100 gap-2">
                        <div class="flex items-center gap-2.5">
                            <span class="w-7 h-7 rounded-lg bg-emerald-50 text-[#15803d] inline-flex items-center justify-center font-extrabold text-sm">2</span>
                            <div>
                                <h2 class="text-base font-bold text-gray-900">Rincian Barang / Jasa yang Dipesan</h2>
                                <p class="text-xs text-gray-500">Pencarian produk cepat server-side, otomatis mengisi harga supplier pivot, diskon, dan kalkulasi PPN presisi.</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 self-start sm:self-auto">
                            <button
                                type="button"
                                @click="collapseAll()"
                                class="inline-flex items-center gap-1 px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition cursor-pointer"
                                title="Tutup rincian semua barang"
                            >
                                <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                Tutup Semua
                            </button>
                            <button
                                type="button"
                                @click="expandAll()"
                                class="inline-flex items-center gap-1 px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition cursor-pointer"
                                title="Buka rincian semua barang"
                            >
                                <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                Buka Semua
                            </button>
                            <button
                                type="button"
                                @click="addRow(true)"
                                id="btnAddRow"
                                class="inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-emerald-50 hover:bg-emerald-100 text-[#15803d] text-xs font-bold rounded-xl transition active:scale-[0.98] cursor-pointer shadow-xs border border-emerald-200"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                                </svg>
                                + Tambah Baris Barang
                            </button>
                        </div>
                    </div>

                    <!-- Quick-Add Search Bar (Non-terikat ke form item baris) -->
                    <div class="relative bg-gradient-to-r from-emerald-50/70 to-teal-50/40 border border-emerald-200/80 rounded-2xl p-3.5 sm:p-4 transition shadow-xs" id="quickAddContainer">
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <label for="quickSearchInput" class="text-xs font-bold text-gray-800 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span>Pencarian Cepat / Scan Barcode Produk</span>
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 font-semibold">Scanner Ready</span>
                            </label>
                            <span x-text="quickFeedback"
                                  :class="quickFeedback ? 'opacity-100 translate-x-0' : 'opacity-0 translate-x-1'"
                                  id="quickAddFeedback"
                                  class="text-xs font-semibold text-[#15803d] transition-all duration-300"></span>
                        </div>

                        <div class="relative" @click.outside="quickSearchResults = []">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-emerald-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                            <input type="text"
                                   id="quickSearchInput"
                                   x-model="quickSearchQuery"
                                   @input="onQuickSearchInput()"
                                   @keydown.arrow-down.prevent="highlightNextQuick()"
                                   @keydown.arrow-up.prevent="highlightPrevQuick()"
                                   @keydown.enter.prevent="selectHighlightedQuick()"
                                   @keydown.escape.prevent="clearQuickSearch()"
                                   autocomplete="off"
                                   placeholder="Ketik SKU / nama barang atau tembak barcode scanner..."
                                   class="w-full pl-10 pr-28 py-2.5 text-xs font-medium text-gray-900 bg-white border border-emerald-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#15803d]/30 focus:border-[#15803d] placeholder:text-gray-400 shadow-xs transition">
                            
                            <div class="absolute inset-y-0 right-0 pr-2.5 flex items-center gap-1.5">
                                <button type="button"
                                        id="btnQuickAddClear"
                                        x-show="quickSearchQuery.length > 0"
                                        @click="clearQuickSearch()"
                                        class="p-1 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition"
                                        title="Bersihkan">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                                <span class="px-2 py-0.5 text-[10px] font-mono font-semibold text-emerald-800 bg-emerald-100/80 border border-emerald-200 rounded-md select-none hidden sm:inline-block">
                                    ENTER ↵
                                </span>
                            </div>

                            <!-- Dropdown Auto-Suggest Results (Floating) -->
                            <div id="quickSearchResults"
                                 x-show="quickSearchResults.length > 0 || isQuickSearching"
                                 x-transition
                                 class="absolute z-50 left-0 right-0 mt-1.5 bg-white border border-gray-200 rounded-2xl shadow-xl overflow-hidden max-h-72 overflow-y-auto divide-y divide-gray-100">
                                <template x-if="isQuickSearching">
                                    <div class="p-4 text-center text-xs text-gray-500 flex items-center justify-center gap-2">
                                        <svg class="animate-spin h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span>Mencari produk...</span>
                                    </div>
                                </template>

                                <template x-for="(p, pIdx) in quickSearchResults" :key="p.id">
                                    <div class="px-4 py-2.5 cursor-pointer flex items-center justify-between gap-3 text-xs transition"
                                         :class="pIdx === quickHighlightedIndex ? 'bg-emerald-50/90 text-emerald-950 font-semibold' : 'hover:bg-gray-50 text-gray-800'"
                                         :id="'quick-result-' + pIdx"
                                         @click="selectQuickProduct(p)">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <span class="font-mono font-bold text-emerald-700 shrink-0" x-text="'[' + (p.sku || '-') + ']'"></span>
                                                <span class="truncate font-medium text-gray-900" x-text="p.name"></span>
                                            </div>
                                            <template x-if="p.supplier_price">
                                                <div class="text-[10.5px] text-emerald-700 mt-0.5 truncate flex items-center gap-1">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                    <span>Harga Khusus Rekanan Terpilih</span>
                                                </div>
                                            </template>
                                        </div>
                                        <div class="flex items-center gap-3 shrink-0">
                                            <span class="px-2 py-0.5 rounded text-[10.5px] font-bold bg-gray-100 text-gray-700 border border-gray-200" x-text="p.uom || 'Pcs'"></span>
                                            <span class="font-mono font-bold text-[#15803d]" x-text="formatRupiah(p.supplier_price || p.cost_price || 0)"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <div class="mt-2 flex items-center justify-between text-[11px] text-gray-500">
                            <span>💡 <em>Klik produk atau tekan Enter untuk menambah/menaikkan kuantitas item di posisi paling atas (#1).</em></span>
                            <span class="text-[10px] text-gray-400 hidden md:inline">Gunakan panah ↑/↓ untuk navigasi</span>
                        </div>
                    </div>

                    {{-- Repeater Items Container (Structured Collapsible Card Layout - Filament Style) --}}
                    <div class="space-y-3">
                        <template x-for="(item, index) in items" :key="index">
                            <div class="border border-gray-200 rounded-2xl bg-white shadow-xs hover:border-emerald-300 transition overflow-hidden relative" :class="{'ring-1 ring-[#15803d]/30': item.isOpen}">
                                {{-- Row Top Header (Accordion Trigger & Summary) --}}
                                <div
                                    class="flex flex-wrap items-center justify-between gap-3 p-3.5 sm:px-5 cursor-pointer select-none transition"
                                    :class="item.isOpen ? 'bg-gray-50/80 border-b border-gray-100' : 'bg-white hover:bg-gray-50/60'"
                                    @click="item.isOpen = !item.isOpen"
                                >
                                    <div class="flex items-center gap-2.5 min-w-0 flex-1">
                                        <span class="px-2.5 py-1 rounded-lg bg-gray-100 text-gray-700 font-mono font-bold text-xs shrink-0">
                                            Item #<span x-text="index + 1"></span>
                                        </span>

                                        {{-- Filament-style itemLabel Summary --}}
                                        <div class="truncate text-xs font-semibold text-gray-800 flex items-center gap-2">
                                            <span x-text="getItemSummary(item, index)"></span>
                                        </div>

                                        {{-- Limit OR Badge if applicable --}}
                                        <template x-if="item.maxQuantity && item.isOpen">
                                            <span 
                                                class="hidden md:inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg text-xs font-semibold shrink-0"
                                                :class="item.quantity > item.maxQuantity ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-amber-50 text-amber-800 border border-amber-200'"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                <span>Maks OR: <strong x-text="item.maxQuantity"></strong></span>
                                            </span>
                                        </template>

                                        {{-- Supplier Linked Price Info Badge --}}
                                        <template x-if="item.isSupplierPriceLinked && item.isOpen">
                                            <span class="hidden md:inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg bg-emerald-50 text-[#15803d] border border-emerald-200 text-xs font-semibold shrink-0">
                                                <svg class="w-3 h-3 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                                <span>Harga Kontrak</span>
                                            </span>
                                        </template>
                                    </div>

                                    <div class="flex items-center gap-2 shrink-0" @click.stop>
                                        {{-- Accordion Toggle Chevron --}}
                                        <button
                                            type="button"
                                            @click="item.isOpen = !item.isOpen"
                                            class="p-1.5 rounded-lg text-gray-400 hover:text-[#15803d] hover:bg-emerald-50 transition cursor-pointer"
                                            :title="item.isOpen ? 'Tutup Rincian Item' : 'Buka Rincian Item'"
                                        >
                                            <svg class="w-4 h-4 transition-transform duration-200" :class="{'rotate-180': item.isOpen}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>

                                        {{-- Hapus Baris --}}
                                        <button
                                            type="button"
                                            @click="removeRow(index)"
                                            :disabled="items.length <= 1"
                                            class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 disabled:opacity-20 disabled:hover:bg-transparent disabled:hover:text-gray-400 transition cursor-pointer"
                                            title="Hapus Baris Barang Ini"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                {{-- Collapsible Body (Accordion Form Content) --}}
                                <div x-show="item.isOpen" x-collapse>
                                    <div class="p-4 sm:p-5 pt-3 space-y-4">

                                {{-- Level 1: Spesifikasi Produk Async Lookup --}}
                                <div class="grid grid-cols-1 md:grid-cols-12 gap-3.5">
                                    {{-- Produk Async Combobox --}}
                                    <div class="md:col-span-8 relative" @click.outside="item.dropdownOpen = false">
                                        <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 mb-1">
                                            Produk yang Dipesan <span class="text-red-500">*</span>
                                        </label>
                                        <div class="relative">
                                            <input 
                                                type="text" 
                                                x-model="item.searchQuery" 
                                                @focus="item.dropdownOpen = true"
                                                @input="onSearchInput(item)"
                                                placeholder="Ketik nama produk atau kode SKU..."
                                                class="w-full py-2 pl-3 pr-8 border border-gray-300 rounded-lg text-xs font-medium focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] bg-white transition"
                                            />
                                            <div class="absolute inset-y-0 right-0 pr-2 flex items-center">
                                                <template x-if="item.isSearching">
                                                    <svg class="animate-spin h-3.5 w-3.5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                                    </svg>
                                                </template>
                                                <template x-if="!item.isSearching && item.productId">
                                                    <button 
                                                        type="button" 
                                                        @click.stop="clearProduct(item)"
                                                        class="text-gray-400 hover:text-red-500 p-0.5"
                                                        title="Kosongkan Produk"
                                                    >
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>

                                        {{-- Suggestions Dropdown Server-Side --}}
                                        <div 
                                            x-show="item.dropdownOpen" 
                                            x-transition
                                            class="absolute left-0 top-full mt-1 w-full min-w-[320px] max-w-lg bg-white border border-gray-200 rounded-xl shadow-xl z-50 max-h-60 overflow-y-auto p-1 text-xs"
                                            style="display: none;"
                                        >
                                            <template x-for="p in item.searchResults" :key="p.id">
                                                <div 
                                                    @click="selectProduct(item, p)"
                                                    class="p-2.5 rounded-lg hover:bg-emerald-50 cursor-pointer flex flex-col gap-1 border-b border-gray-50 last:border-b-0 transition"
                                                    :class="{'bg-emerald-50/80 font-bold text-emerald-900': item.productId == p.id}"
                                                >
                                                    <div class="font-medium text-gray-800 flex items-center justify-between">
                                                        <span x-text="p.name" class="truncate"></span>
                                                        <span class="text-[10px] text-gray-400 font-mono ml-2 shrink-0" x-text="p.sku"></span>
                                                    </div>
                                                    <div class="text-[11px] text-gray-500 flex items-center justify-between">
                                                        <span>Satuan: <strong class="text-gray-700" x-text="p.uom"></strong></span>
                                                        <div class="flex items-center gap-1.5">
                                                            <template x-if="p.supplier_price !== null">
                                                                <span class="text-[10px] px-1.5 py-0.2 rounded bg-emerald-100 text-[#14532d] font-bold">Harga Rekanan: <span x-text="formatRupiah(p.supplier_price)"></span></span>
                                                            </template>
                                                            <template x-if="p.supplier_price === null">
                                                                <span class="text-gray-500 font-medium">Beli: <span x-text="formatRupiah(p.cost_price)"></span></span>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                            <div x-show="!item.isSearching && item.searchResults.length === 0" class="p-3 text-center text-gray-400 select-none">
                                                <span x-show="!item.searchQuery || item.searchQuery.trim().length === 0">Ketik minimal 1 karakter untuk mencari produk...</span>
                                                <span x-show="item.searchQuery && item.searchQuery.trim().length > 0">Tidak ada produk ditemukan.</span>
                                            </div>
                                        </div>

                                        {{-- Hidden Fields --}}
                                        <input type="hidden" :name="'items[' + index + '][product_id]'" :value="item.productId" required>
                                        <input type="hidden" :name="'items[' + index + '][order_request_item_id]'" :value="item.orderRequestItemId || ''">
                                    </div>

                                    {{-- Satuan UOM --}}
                                    <div class="md:col-span-2">
                                        <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 mb-1">
                                            Satuan
                                        </label>
                                        <div class="w-full py-2 px-2.5 bg-gray-50 border border-gray-200 rounded-lg text-xs font-bold text-center text-gray-700" x-text="item.uom || 'Pcs'"></div>
                                    </div>

                                    {{-- Mata Uang Item --}}
                                    <div class="md:col-span-2">
                                        <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 mb-1">
                                            Mata Uang Item
                                        </label>
                                        <select
                                            :name="'items[' + index + '][currency_id]'"
                                            x-model="item.currencyId"
                                            class="w-full py-2 px-2.5 border border-gray-300 rounded-lg text-xs bg-white font-medium text-gray-800 focus:outline-none focus:ring-1 focus:ring-[#15803d]"
                                        >
                                            <template x-for="c in currencies" :key="c.id">
                                                <option :value="c.id" x-text="c.code" :selected="c.id == item.currencyId"></option>
                                            </template>
                                        </select>
                                    </div>
                                </div>

                                {{-- Level 2: Komersial, Kuantitas, Harga Satuan, Diskon & Pajak --}}
                                <div class="bg-gray-50/70 rounded-xl p-3 border border-gray-100 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-6 gap-3 text-xs">
                                    {{-- Kuantitas Pesanan --}}
                                    <div>
                                        <label class="block text-[11px] font-bold text-gray-600 mb-1">
                                            Kuantitas <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="number"
                                            :name="'items[' + index + '][quantity]'"
                                            x-model.number="item.quantity"
                                            min="0.01"
                                            :max="item.maxQuantity || undefined"
                                            step="any"
                                            required
                                            class="w-full py-1.5 px-2.5 border rounded-lg text-sm font-bold text-gray-900 text-right focus:outline-none focus:ring-1 transition"
                                            :class="item.maxQuantity && item.quantity > item.maxQuantity ? 'border-red-400 bg-red-50/50 text-red-900 focus:ring-red-400' : 'border-gray-300 bg-white focus:ring-[#15803d] focus:border-[#15803d]'"
                                        />
                                        <template x-if="item.maxQuantity && item.quantity > item.maxQuantity">
                                            <p class="text-[10px] text-red-600 font-semibold mt-0.5">Melebihi sisa OR (<span x-text="item.maxQuantity"></span>)</p>
                                        </template>
                                    </div>

                                    {{-- Harga Satuan (Currency Masking) --}}
                                    <div>
                                        <label class="block text-[11px] font-bold text-gray-600 mb-1">
                                            Harga Satuan (<span x-text="getItemCurrencySymbol(item)"></span>) <span class="text-red-500">*</span>
                                        </label>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-xs font-bold text-gray-400" x-text="getItemCurrencySymbol(item)"></span>
                                            <input
                                                type="text"
                                                :value="item.unitPrice ? formatRupiah(item.unitPrice, false) : (item.unitPrice === 0 ? '0' : '')"
                                                @input="item.unitPrice = parseRupiah($event.target.value)"
                                                @blur="$event.target.value = (item.unitPrice ? formatRupiah(item.unitPrice, false) : (item.unitPrice === 0 ? '0' : ''))"
                                                @focus="if($event.target.value === '0') $event.target.value = ''"
                                                required
                                                class="w-full py-1.5 pl-8 pr-2.5 border border-gray-300 rounded-lg text-sm font-semibold text-gray-900 text-right bg-white focus:outline-none focus:ring-1 focus:ring-[#15803d] font-mono"
                                                placeholder="0"
                                            />
                                            <input type="hidden" :name="'items[' + index + '][unit_price]'" :value="item.unitPrice" required>
                                        </div>
                                    </div>

                                    {{-- Total Kotor (Qty x Unit Price) --}}
                                    <div>
                                        <label class="block text-[11px] font-bold text-gray-500 mb-1">Total Kotor</label>
                                        <div class="w-full py-1.5 px-2 bg-gray-100/70 border border-gray-200 rounded-lg text-xs font-semibold text-gray-700 text-right" x-text="formatCurrency(calcGross(item), item)"></div>
                                    </div>

                                    {{-- Diskon (%) --}}
                                    <div>
                                        <label class="block text-[11px] font-bold text-gray-600 mb-1">Diskon (%)</label>
                                        <input
                                            type="number"
                                            :name="'items[' + index + '][discount]'"
                                            x-model.number="item.discount"
                                            min="0"
                                            max="100"
                                            step="any"
                                            class="w-full py-1.5 px-2.5 border border-gray-300 rounded-lg text-xs font-semibold text-gray-800 text-right bg-white focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d]"
                                        />
                                    </div>

                                    {{-- Tipe Pajak (Pill Selector) --}}
                                    <div>
                                        <label class="block text-[11px] font-bold text-gray-600 mb-1">Tipe Pajak</label>
                                        <select
                                            :name="'items[' + index + '][tipe_pajak]'"
                                            x-model="item.taxType"
                                            @change="onTaxTypeChange(item)"
                                            class="w-full py-1.5 px-2 border border-gray-300 rounded-lg text-xs font-medium bg-white focus:outline-none focus:ring-1 focus:ring-[#15803d]"
                                        >
                                            <option value="none">Non Pajak (0%)</option>
                                            <option value="inklusif">Inklusif (PPN Termasuk)</option>
                                            <option value="eklusif">Eksklusif (+11% PPN)</option>
                                        </select>
                                        <input type="hidden" :name="'items[' + index + '][tax]'" :value="item.tax">
                                    </div>

                                    {{-- Subtotal Bersih --}}
                                    <div>
                                        <label class="block text-[11px] font-bold text-emerald-800 mb-1 text-right">Subtotal Bersih</label>
                                        <div class="w-full py-1.5 px-2 bg-emerald-50/80 border border-emerald-200 rounded-lg text-xs font-extrabold text-[#15803d] text-right" x-text="formatCurrency(calcSubtotal(item), item)"></div>
                                    </div>
                                </div>
                                    </div>
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
                    >{{ old('note') }}</textarea>
                    <p class="text-xs text-gray-400">Catatan ini akan tercetak otomatis pada lembar dokumen Purchase Order resmi.</p>
                </div>
            </div>

            {{-- Kolom Kanan: Ringkasan Sticky (Aside) --}}
            <aside class="w-full lg:w-[320px] lg:shrink-0 bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 flex flex-col gap-4 shadow-sm lg:sticky lg:top-[70px]">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <h2 class="text-base font-bold text-gray-900 m-0">Ringkasan Biaya</h2>
                    <span class="text-xs text-gray-500 font-semibold" x-text="items.length + ' Item'"></span>
                </div>

                <div class="space-y-2 text-xs text-gray-600">
                    <div class="flex justify-between py-0.5">
                        <span>Subtotal Kotor:</span>
                        <span class="font-semibold text-gray-800" x-text="formatHeaderCurrency(grossTotal())"></span>
                    </div>
                    <div class="flex justify-between py-0.5" x-show="totalDiscount() > 0">
                        <span>Total Diskon Item:</span>
                        <span class="font-semibold text-rose-600" x-text="'- ' + formatHeaderCurrency(totalDiscount())"></span>
                    </div>
                    <div class="flex justify-between py-0.5">
                        <span>Dasar Pengenaan Pajak (DPP):</span>
                        <span class="font-semibold text-gray-800" x-text="formatHeaderCurrency(dppTotal())"></span>
                    </div>
                    <div class="flex justify-between py-0.5">
                        <span>PPN:</span>
                        <span class="font-semibold text-gray-800" x-text="formatHeaderCurrency(totalTax())"></span>
                    </div>
                </div>

                <div class="pt-3 border-t border-gray-100 flex justify-between items-baseline">
                    <span class="text-sm font-bold text-gray-900">Grand Total:</span>
                    <div class="text-right">
                        <div class="font-extrabold text-xl text-[#15803d]" x-text="formatHeaderCurrency(grandTotal())"></div>
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
                        Simpan sebagai Draf
                    </button>
                </div>
            </aside>
        </div>
    </form>
</div>

<script>
function purchaseOrderForm(config) {
    return {
        suppliers: config.suppliers || [],
        warehouses: config.warehouses || [],
        cabangs: config.cabangs || [],
        currencies: config.currencies || [],
        supplierId: config.initialSupplierId || '',
        currencyId: config.initialCurrencyId || config.defaultCurrencyId || 1,
        topType: 'cod',
        tempoHutang: 0,
        exchangeRate: 1.0,
        items: [],
        productsSearchUrl: config.productsSearchUrl,
        preloadedProducts: config.preloadedProducts || [],

        // Quick-Add Search Bar State
        quickSearchQuery: '',
        quickSearchResults: [],
        isQuickSearching: false,
        quickHighlightedIndex: -1,
        quickSearchTimeout: null,
        quickFeedback: '',
        quickFeedbackTimeout: null,

        init() {
            // Inisialisasi items dari data awal / old items
            if (config.initialItems && config.initialItems.length > 0) {
                this.items = config.initialItems.map((item, idx) => this.createEmptyItem(item, idx === 0));
            } else {
                this.addRow(false);
            }

            if (this.supplierId) {
                this.onSupplierChange(false);
            }

            this.onCurrencyChange();
        },

        createEmptyItem(prefill = {}, isOpen = true) {
            const prodId = prefill.product_id || prefill.productId || '';
            const preloaded = this.preloadedProducts.find(p => String(p.id) === String(prodId));

            const name = prefill.product_name || (preloaded ? preloaded.name : '');
            const sku = prefill.sku || (preloaded ? preloaded.sku : '-');
            const uom = prefill.uom || (preloaded ? preloaded.uom : 'Pcs');
            const unitPrice = prefill.unit_price !== undefined 
                ? parseFloat(prefill.unit_price) 
                : (preloaded ? preloaded.cost_price : 0);
            const discount = prefill.discount !== undefined ? parseFloat(prefill.discount) : 0;
            const taxType = prefill.tipe_pajak || (preloaded ? preloaded.tipe_pajak : 'eklusif');
            const tax = prefill.tax !== undefined ? parseFloat(prefill.tax) : (taxType === 'none' ? 0 : 11);
            const currencyId = prefill.currency_id || this.currencyId;
            const maxQty = prefill.max_quantity !== undefined ? parseFloat(prefill.max_quantity) : null;
            const orderRequestItemId = prefill.order_request_item_id || '';

            return {
                isOpen: prefill.is_open !== undefined ? Boolean(prefill.is_open) : isOpen,
                productId: prodId,
                orderRequestItemId: orderRequestItemId,
                searchQuery: name ? (name + (sku && sku !== '-' ? ' (' + sku + ')' : '')) : '',
                dropdownOpen: false,
                isSearching: false,
                searchDebounceTimer: null,
                searchResults: preloaded ? [preloaded] : [],
                uom: uom,
                quantity: prefill.quantity !== undefined ? parseFloat(prefill.quantity) : 1,
                maxQuantity: maxQty,
                unitPrice: unitPrice,
                discount: discount,
                taxType: taxType,
                tax: tax,
                currencyId: currencyId,
                isSupplierPriceLinked: false
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

        getItemCurrencySymbol(item) {
            const curr = this.currencies.find(c => String(c.id) === String(item.currencyId));
            return curr ? curr.symbol : this.currentCurrencySymbol;
        },

        onSupplierChange(triggerItemRecalc = true) {
            const supplier = this.selectedSupplierInfo;
            if (supplier) {
                if (supplier.top_type) this.topType = supplier.top_type;
                if (supplier.tempo_hutang !== undefined) this.tempoHutang = supplier.tempo_hutang;

                // Jika supplier diganti dan ada item yang sudah terpilih, coba cek harga pivot supplier
                if (triggerItemRecalc && this.items.length > 0) {
                    this.refreshItemSupplierPrices();
                }
            }
        },

        refreshItemSupplierPrices() {
            if (!this.supplierId) return;
            this.items.forEach(item => {
                if (!item.productId) return;
                const url = this.productsSearchUrl + '?q=' + encodeURIComponent(item.productId) + '&supplier_id=' + this.supplierId;
                fetch(url)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.products && data.products.length > 0) {
                            const found = data.products.find(p => String(p.id) === String(item.productId));
                            if (found && found.supplier_price !== null && found.supplier_price > 0) {
                                item.unitPrice = found.supplier_price;
                                item.isSupplierPriceLinked = true;
                            }
                        }
                    })
                    .catch(() => {});
            });
        },

        onCurrencyChange() {
            const curr = this.currencies.find(c => String(c.id) === String(this.currencyId));
            if (curr) {
                this.exchangeRate = curr.rate > 0 ? curr.rate : 1.0;
            }
            // Sync item currencies that are using default
            this.items.forEach(it => {
                if (!it.currencyId) it.currencyId = this.currencyId;
            });
        },

        onSearchInput(item) {
            clearTimeout(item.searchDebounceTimer);
            item.dropdownOpen = true;

            const q = (item.searchQuery || '').trim();
            if (q.length < 1) {
                item.searchResults = [];
                item.isSearching = false;
                item.productId = '';
                return;
            }

            item.isSearching = true;
            item.searchDebounceTimer = setTimeout(() => {
                let url = this.productsSearchUrl + '?q=' + encodeURIComponent(q);
                if (this.supplierId) {
                    url += '&supplier_id=' + encodeURIComponent(this.supplierId);
                }

                fetch(url)
                    .then(res => res.json())
                    .then(data => {
                        item.isSearching = false;
                        if (data.success) {
                            item.searchResults = data.products || [];
                        }
                    })
                    .catch(() => {
                        item.isSearching = false;
                    });
            }, 300);
        },

        selectProduct(item, p) {
            item.productId = p.id;
            item.searchQuery = p.name + (p.sku && p.sku !== '-' ? ' (' + p.sku + ')' : '');
            item.uom = p.uom || 'Pcs';
            item.taxType = p.tipe_pajak || 'inklusif';
            item.tax = item.taxType === 'none' ? 0 : (p.default_tax_rate || 11);

            // Jika ada harga khusus supplier terpilih, pakai harga itu; fallback ke cost_price
            if (p.supplier_price !== null && p.supplier_price !== undefined && p.supplier_price > 0) {
                item.unitPrice = p.supplier_price;
                item.isSupplierPriceLinked = true;
            } else {
                item.unitPrice = p.cost_price || 0;
                item.isSupplierPriceLinked = false;
            }

            item.dropdownOpen = false;
        },

        clearProduct(item) {
            item.productId = '';
            item.searchQuery = '';
            item.uom = 'Pcs';
            item.unitPrice = 0;
            item.discount = 0;
            item.tax = 11;
            item.taxType = 'eklusif';
            item.isSupplierPriceLinked = false;
            item.dropdownOpen = false;
        },

        onTaxTypeChange(item) {
            if (item.taxType === 'none') {
                item.tax = 0;
            } else {
                item.tax = 11;
            }
        },

        addRow(atTop = true) {
            // Auto-collapse item sebelumnya agar halaman tetap rapi seperti Filament
            this.items.forEach(it => it.isOpen = false);
            const newItem = this.createEmptyItem({}, true);
            if (atTop) {
                this.items.unshift(newItem);
            } else {
                this.items.push(newItem);
            }
        },

        // --- Quick-Add Search Engine Methods ---
        showQuickFeedback(msg) {
            this.quickFeedback = msg;
            if (this.quickFeedbackTimeout) clearTimeout(this.quickFeedbackTimeout);
            this.quickFeedbackTimeout = setTimeout(() => {
                this.quickFeedback = '';
            }, 3000);
        },

        clearQuickSearch() {
            this.quickSearchQuery = '';
            this.quickSearchResults = [];
            this.isQuickSearching = false;
            this.quickHighlightedIndex = -1;
            if (this.quickSearchTimeout) clearTimeout(this.quickSearchTimeout);
            this.$nextTick(() => {
                const el = document.getElementById('quickSearchInput');
                if (el) el.focus();
            });
        },

        onQuickSearchInput() {
            if (this.quickSearchTimeout) clearTimeout(this.quickSearchTimeout);
            const q = (this.quickSearchQuery || '').trim();
            if (q.length < 1) {
                this.quickSearchResults = [];
                this.isQuickSearching = false;
                this.quickHighlightedIndex = -1;
                return;
            }

            this.isQuickSearching = true;
            this.quickSearchTimeout = setTimeout(() => {
                let url = this.productsSearchUrl + '?q=' + encodeURIComponent(q);
                if (this.supplierId) {
                    url += '&supplier_id=' + encodeURIComponent(this.supplierId);
                }
                fetch(url)
                    .then(res => res.json())
                    .then(data => {
                        this.isQuickSearching = false;
                        if (data.success) {
                            this.quickSearchResults = data.products || [];
                            this.quickHighlightedIndex = this.quickSearchResults.length > 0 ? 0 : -1;
                        }
                    })
                    .catch(() => {
                        this.isQuickSearching = false;
                    });
            }, 250);
        },

        highlightNextQuick() {
            if (this.quickSearchResults.length === 0) return;
            this.quickHighlightedIndex = (this.quickHighlightedIndex + 1) % this.quickSearchResults.length;
            this.scrollQuickItemIntoView();
        },

        highlightPrevQuick() {
            if (this.quickSearchResults.length === 0) return;
            this.quickHighlightedIndex = (this.quickHighlightedIndex - 1 + this.quickSearchResults.length) % this.quickSearchResults.length;
            this.scrollQuickItemIntoView();
        },

        scrollQuickItemIntoView() {
            this.$nextTick(() => {
                const el = document.getElementById(`quick-result-${this.quickHighlightedIndex}`);
                if (el) el.scrollIntoView({ block: 'nearest' });
            });
        },

        selectHighlightedQuick() {
            if (this.quickHighlightedIndex >= 0 && this.quickSearchResults[this.quickHighlightedIndex]) {
                this.selectQuickProduct(this.quickSearchResults[this.quickHighlightedIndex]);
            } else if (this.quickSearchResults.length > 0) {
                this.selectQuickProduct(this.quickSearchResults[0]);
            }
        },

        selectQuickProduct(p) {
            if (!p || !p.id) return;

            // Cari apakah produk sudah ada di daftar item
            const existingIndex = this.items.findIndex(it => String(it.productId) === String(p.id));

            if (existingIndex >= 0) {
                // Produk sudah ada -> Tambah kuantitas +1 dan PINDAHKAN KE POSISI PALING ATAS (Index 0 / #1)
                const existingItem = this.items[existingIndex];
                existingItem.quantity = (parseFloat(existingItem.quantity) || 0) + 1;

                this.items.splice(existingIndex, 1);
                this.collapseAll();
                existingItem.isOpen = true;
                this.items.unshift(existingItem);

                const uom = existingItem.uom || 'Pcs';
                this.showQuickFeedback(`+1 [${p.sku || '-'}] (Dipindahkan ke posisi #1 · Total: ${existingItem.quantity} ${uom})`);
            } else if (this.items.length === 1 && !this.items[0].productId) {
                // Ada 1 baris kosong bawaan di paling atas -> Pakai baris tersebut
                this.selectProduct(this.items[0], p);
                this.items[0].quantity = 1;
                this.items[0].isOpen = true;
                this.showQuickFeedback(`Item [${p.sku || '-'}] dimasukkan ke posisi teratas (#1)`);
            } else {
                // Item baru -> Buat item baru dan masukkan ke paling atas (unshift)
                this.collapseAll();
                const newItem = this.createEmptyItem({}, true);
                this.selectProduct(newItem, p);
                newItem.quantity = 1;
                newItem.isOpen = true;
                this.items.unshift(newItem);
                this.showQuickFeedback(`Item [${p.sku || '-'}] ditambahkan di posisi teratas (#1)`);
            }

            this.clearQuickSearch();
        },

        collapseAll() {
            this.items.forEach(it => it.isOpen = false);
        },

        expandAll() {
            this.items.forEach(it => it.isOpen = true);
        },

        getItemSummary(item, index) {
            const parts = [];
            if (item.searchQuery) {
                parts.push(item.searchQuery);
            } else {
                parts.push('Belum memilih produk');
            }
            const qty = parseFloat(item.quantity) || 0;
            parts.push(`${qty} ${item.uom || 'Pcs'}`);
            const subtotal = this.calcSubtotal(item);
            parts.push(`Subtotal: ${this.formatCurrency(subtotal, item)}`);
            return parts.join(' · ');
        },

        removeRow(index) {
            if (this.items.length > 1) {
                this.items.splice(index, 1);
            }
        },

        calcGross(item) {
            const qty = parseFloat(item.quantity) || 0;
            const price = parseFloat(item.unitPrice) || 0;
            return qty * price;
        },

        calcSubtotal(item) {
            const gross = this.calcGross(item);
            const disc = parseFloat(item.discount) || 0;
            const afterDisc = Math.max(0, gross - (gross * (disc / 100)));

            if (item.taxType === 'eklusif') {
                const taxRate = parseFloat(item.tax) || 0;
                return afterDisc * (1 + (taxRate / 100));
            } else {
                return afterDisc;
            }
        },

        grossTotal() {
            return this.items.reduce((sum, item) => sum + this.calcGross(item), 0);
        },

        totalDiscount() {
            return this.items.reduce((sum, item) => {
                const gross = this.calcGross(item);
                const disc = parseFloat(item.discount) || 0;
                return sum + (gross * (disc / 100));
            }, 0);
        },

        dppTotal() {
            return this.items.reduce((sum, item) => {
                const gross = this.calcGross(item);
                const disc = parseFloat(item.discount) || 0;
                const afterDisc = Math.max(0, gross - (gross * (disc / 100)));

                if (item.taxType === 'inklusif') {
                    const taxRate = parseFloat(item.tax) || 11;
                    return sum + (afterDisc / (1 + (taxRate / 100)));
                }
                return sum + afterDisc;
            }, 0);
        },

        totalTax() {
            return this.items.reduce((sum, item) => {
                const gross = this.calcGross(item);
                const disc = parseFloat(item.discount) || 0;
                const afterDisc = Math.max(0, gross - (gross * (disc / 100)));
                const taxRate = parseFloat(item.tax) || 0;

                if (item.taxType === 'eklusif') {
                    return sum + (afterDisc * (taxRate / 100));
                } else if (item.taxType === 'inklusif') {
                    const dpp = afterDisc / (1 + (taxRate / 100));
                    return sum + (afterDisc - dpp);
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

        formatCurrency(val, item) {
            const sym = this.getItemCurrencySymbol(item);
            return sym + ' ' + Math.round(val || 0).toLocaleString('id-ID');
        },

        formatHeaderCurrency(val) {
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
