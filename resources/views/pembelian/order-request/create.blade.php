@extends('layouts.app')

@section('title', 'Buat Permintaan Pembelian (Order Request) – Duta Tunggal ERP')

@section('content')
<div 
    class="space-y-6 max-w-7xl mx-auto pb-16"
    x-data="orderRequestForm({
        cabangs: {{ Js::from($cabangs->map(fn($c) => ['id' => $c->id, 'nama' => $c->nama, 'kode' => $c->kode ?? ''])) }},
        currencies: {{ Js::from($currencies->map(fn($c) => ['id' => $c->id, 'code' => $c->code, 'name' => $c->name, 'symbol' => $c->symbol])) }},
        defaultCabangId: {{ (int) $defaultCabangId }},
        defaultCurrencyId: {{ (int) $defaultCurrency->id }},
        productsSearchUrl: '{{ route('pembelian.products.search') }}',
        suppliersSearchUrl: '{{ route('pembelian.suppliers.search') }}',
        oldItems: {{ Js::from(old('items', [])) }}
    })"
>
    {{-- Header Breadcrumb & Actions --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('pembelian.or.index') }}" class="hover:text-[#15803d] transition">Permintaan Barang (OR)</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Buat Baru</span>
            </div>
            <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Formulir Permintaan Pembelian</h1>
            <p class="text-sm text-gray-500 mt-0.5">Ajukan permohonan pengadaan barang untuk persediaan stok cabang atau keperluan operasional.</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('pembelian.or.index') }}"
                class="px-4 py-2 border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 text-sm font-semibold rounded-xl shadow-xs transition active:scale-[0.98]"
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

    {{-- Form OR --}}
    <form action="{{ route('pembelian.or.store') }}" method="POST" id="orForm" class="space-y-6">
        @csrf

        {{-- Card 1: Informasi Header Permintaan --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5 sm:p-6 shadow-sm">
            <div class="border-b border-gray-100 pb-3 mb-5 flex items-center gap-2.5">
                <span class="w-7 h-7 rounded-lg bg-emerald-50 text-[#15803d] inline-flex items-center justify-center font-extrabold text-sm">1</span>
                <div>
                    <h2 class="text-base font-bold text-gray-900">Informasi Permintaan</h2>
                    <p class="text-xs text-gray-500">Tentukan cabang pemohon, tanggal kebutuhan, mata uang utama, dan tujuan pengadaan.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- Nomor Request --}}
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1.5">
                        Nomor Request (Otomatis)
                    </label>
                    <input
                        type="text"
                        name="request_number"
                        value="{{ old('request_number', $defaultRequestNumber) }}"
                        readonly
                        class="w-full py-2.5 px-3 border border-gray-300 rounded-lg text-sm bg-gray-50 text-gray-600 font-mono font-medium focus:ring-0"
                    />
                </div>

                {{-- Tanggal Request --}}
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1.5">
                        Tanggal Permintaan <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="date"
                        name="request_date"
                        value="{{ old('request_date', $defaultDate) }}"
                        required
                        class="w-full py-2.5 px-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                    />
                </div>

                {{-- Tanggal Dibutuhkan --}}
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1.5">
                        Tanggal Dibutuhkan
                    </label>
                    <input
                        type="date"
                        name="required_date"
                        value="{{ old('required_date') }}"
                        class="w-full py-2.5 px-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                        placeholder="Perkiraan barang tiba"
                    />
                </div>

                {{-- Cabang Pemohon Searchable --}}
                <div>
                    <x-searchable-select
                        name="cabang_id"
                        label="Cabang Pemohon Utama"
                        :options="$cabangs->map(fn($c) => [
                            'id' => $c->id,
                            'label' => $c->nama,
                            'subtext' => $c->kode ?? ''
                        ])"
                        :value="old('cabang_id', $defaultCabangId)"
                        placeholder="-- Pilih Cabang Pemohon --"
                        :required="true"
                    />
                </div>

                {{-- Mata Uang Utama --}}
                <div @change="if ($event.detail !== undefined) { headerCurrencyId = $event.detail; }">
                    <x-searchable-select
                        name="currency_id"
                        label="Mata Uang Dokumen"
                        :options="$currencies->map(fn($c) => [
                            'id' => $c->id,
                            'label' => $c->code . ' - ' . $c->name,
                            'subtext' => 'Simbol: ' . $c->symbol . ($c->to_rupiah > 1 ? ' | Kurs: ' . number_format($c->to_rupiah, 2, ',', '.') : ''),
                            'badge' => $c->code
                        ])"
                        :value="old('currency_id', $defaultCurrency->id)"
                        placeholder="-- Pilih Mata Uang Dokumen --"
                        :required="true"
                    />
                </div>

                {{-- Keperluan / Divisi --}}
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1.5">
                        Keperluan / Divisi Peminta
                    </label>
                    <input
                        type="text"
                        name="purpose"
                        value="{{ old('purpose') }}"
                        placeholder="Contoh: Stok Menipis Cabang / Operasional"
                        class="w-full py-2.5 px-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                    />
                </div>

                {{-- Catatan Tambahan --}}
                <div class="md:col-span-3">
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1.5">
                        Catatan Operasional (Opsional)
                    </label>
                    <textarea
                        name="note"
                        rows="2"
                        placeholder="Tambahkan informasi khusus atau instruksi kepada bagian Purchasing..."
                        class="w-full py-2.5 px-3 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                    >{{ old('note') }}</textarea>
                </div>
            </div>
        </div>

        {{-- Card 2: Rincian Barang yang Diminta (16 Komponen Filament) --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5 sm:p-6 shadow-sm space-y-5">
            <div class="border-b border-gray-100 pb-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div class="flex items-center gap-2.5">
                    <span class="w-7 h-7 rounded-lg bg-emerald-50 text-[#15803d] inline-flex items-center justify-center font-extrabold text-sm">2</span>
                    <div>
                        <h2 class="text-base font-bold text-gray-900">Rincian Barang yang Diminta</h2>
                        <p class="text-xs text-gray-500">Pencarian cepat server-side, rekomendasi rekanan termurah, cabang per item, diskon, dan kalkulasi pajak terperinci.</p>
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
                                    <template x-if="p.recommended_supplier">
                                        <div class="text-[10.5px] text-gray-500 mt-0.5 truncate flex items-center gap-1">
                                            <span>Rekomendasi Rekanan:</span>
                                            <span class="font-medium text-gray-700" x-text="p.recommended_supplier.label"></span>
                                        </div>
                                    </template>
                                </div>
                                <div class="flex items-center gap-3 shrink-0">
                                    <span class="px-2 py-0.5 rounded text-[10.5px] font-bold bg-gray-100 text-gray-700 border border-gray-200" x-text="p.uom || 'Pcs'"></span>
                                    <span class="font-mono font-bold text-[#15803d]" x-text="formatRupiah(p.cost_price || (p.recommended_supplier ? p.recommended_supplier.price : 0))"></span>
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
                        {{-- Row Header (Accordion Trigger & Summary) --}}
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

                                {{-- Rekomendasi Supplier Badge --}}
                                <template x-if="item.recommendedSupplierText && item.isOpen">
                                    <div class="hidden md:inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg bg-emerald-50 text-[#15803d] border border-emerald-200 text-[11px] font-semibold shrink-0">
                                        <svg class="w-3 h-3 text-emerald-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                        </svg>
                                        <span>Rekomendasi: <strong x-text="item.recommendedSupplierText"></strong></span>
                                    </div>
                                </template>
                            </div>

                            <div class="flex items-center gap-2 shrink-0" @click.stop>
                                {{-- Cabang Per Item (Searchable Dropdown) --}}
                                <div class="flex items-center gap-1.5 relative" @click.outside="item.cabangDropdownOpen = false">
                                    <input type="hidden" :name="'items[' + index + '][cabang_id]'" :value="item.cabangId" required>
                                    <div class="relative">
                                        <button
                                            type="button"
                                            @click="item.cabangDropdownOpen = !item.cabangDropdownOpen; item.cabangSearchQuery = '';"
                                            class="py-1 px-2.5 border border-gray-300 rounded-lg text-xs bg-white font-medium text-gray-700 flex items-center justify-between gap-1.5 hover:border-gray-400 focus:outline-none focus:ring-1 focus:ring-[#15803d] transition"
                                        >
                                            <span x-text="getCabangLabel(item)" class="truncate max-w-[120px]"></span>
                                            <svg class="w-3.5 h-3.5 text-gray-400 shrink-0 transition-transform duration-150" :class="{'rotate-180 text-[#15803d]': item.cabangDropdownOpen}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>

                                        <div
                                            x-show="item.cabangDropdownOpen"
                                            x-transition
                                            class="absolute right-0 top-full mt-1 w-64 bg-white border border-gray-200 rounded-xl shadow-xl z-50 p-1.5 text-xs"
                                            style="display: none;"
                                        >
                                            <input
                                                type="text"
                                                x-model="item.cabangSearchQuery"
                                                placeholder="Cari cabang..."
                                                class="w-full px-2.5 py-1.5 mb-1 text-xs border border-gray-200 rounded-lg bg-gray-50 focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#15803d]"
                                                @click.stop
                                            >
                                            <div class="max-h-44 overflow-y-auto space-y-0.5 custom-scrollbar">
                                                <template x-for="c in filteredCabangs(item)" :key="c.id">
                                                    <div
                                                        @click="item.cabangId = c.id; item.cabangDropdownOpen = false;"
                                                        class="p-2 rounded-lg hover:bg-emerald-50 cursor-pointer flex items-center justify-between transition"
                                                        :class="{'bg-emerald-50/80 font-bold text-emerald-900': item.cabangId == c.id}"
                                                    >
                                                        <span x-text="c.nama" class="truncate"></span>
                                                        <span class="text-[10px] text-gray-400 font-mono ml-2 shrink-0" x-text="c.kode || ''"></span>
                                                    </div>
                                                </template>
                                                <div x-show="filteredCabangs(item).length === 0" class="p-2 text-center text-gray-400 select-none">
                                                    Cabang tidak ditemukan.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

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

                        {{-- Level 1: Spesifikasi Produk & Rekanan --}}
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-3.5">
                            {{-- Produk Async Combobox --}}
                            <div class="md:col-span-5 relative" @click.outside="item.dropdownOpen = false">
                                <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 mb-1">
                                    Produk yang Dipesan <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input 
                                        type="text" 
                                        x-model="item.searchQuery" 
                                        @focus="item.dropdownOpen = true"
                                        @input="onSearchInput(item)"
                                        placeholder="Ketik nama produk atau SKU..."
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

                                {{-- Suggestions Panel AJAX --}}
                                <div 
                                    x-show="item.dropdownOpen" 
                                    x-transition
                                    class="absolute left-0 top-full mt-1 w-full min-w-[320px] max-w-md bg-white border border-gray-200 rounded-xl shadow-xl z-50 max-h-60 overflow-y-auto p-1 text-xs"
                                    style="display: none;"
                                >
                                    <template x-for="p in item.searchResults" :key="p.id">
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
                                                <span class="text-[#15803d] font-semibold" x-text="formatRupiah(p.cost_price)"></span>
                                            </div>
                                        </div>
                                    </template>
                                    <div x-show="!item.isSearching && item.searchResults.length === 0" class="p-3 text-center text-gray-400 select-none">
                                        <span x-show="!item.searchQuery || item.searchQuery.trim().length === 0">Ketik minimal 1 karakter untuk mencari...</span>
                                        <span x-show="item.searchQuery && item.searchQuery.trim().length > 0">Tidak ada produk ditemukan.</span>
                                    </div>
                                </div>

                                <input type="hidden" :name="'items[' + index + '][product_id]'" :value="item.productId" required>
                            </div>

                            {{-- Satuan UOM --}}
                            <div class="md:col-span-1">
                                <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 mb-1">
                                    Satuan
                                </label>
                                <div class="w-full py-2 px-2.5 bg-gray-50 border border-gray-200 rounded-lg text-xs font-bold text-center text-gray-700" x-text="item.uom || 'Pcs'"></div>
                            </div>

                            {{-- Supplier Rekomendasi / Rekanan Pivot (Searchable Dropdown) --}}
                            <div class="md:col-span-3 relative" @click.outside="item.supplierDropdownOpen = false">
                                <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 mb-1">
                                    Pilihan Rekanan / Supplier
                                </label>
                                <input type="hidden" :name="'items[' + index + '][supplier_id]'" :value="item.supplierId">
                                <div class="relative">
                                    <button
                                        type="button"
                                        @click="item.supplierDropdownOpen = !item.supplierDropdownOpen; item.supplierSearchQuery = '';"
                                        class="w-full py-2 px-2.5 border border-gray-300 rounded-lg text-xs bg-white font-medium text-gray-800 flex items-center justify-between gap-1 text-left hover:border-gray-400 focus:outline-none focus:ring-1 focus:ring-[#15803d] transition"
                                    >
                                        <span class="truncate" x-text="getSupplierLabel(item)"></span>
                                        <div class="flex items-center gap-1 flex-shrink-0 text-gray-400">
                                            <template x-if="item.supplierId">
                                                <span
                                                    role="button"
                                                    @click.stop="item.supplierId = ''; onSupplierChange(item);"
                                                    class="p-0.5 rounded hover:bg-gray-100 hover:text-red-500 transition"
                                                    title="Kosongkan Pilihan Supplier"
                                                >
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                </span>
                                            </template>
                                            <svg class="w-3.5 h-3.5 shrink-0 transition-transform duration-150" :class="{'rotate-180 text-[#15803d]': item.supplierDropdownOpen}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </div>
                                    </button>

                                    {{-- Dropdown Panel --}}
                                    <div
                                        x-show="item.supplierDropdownOpen"
                                        x-transition
                                        class="absolute left-0 top-full mt-1 w-full min-w-[270px] bg-white border border-gray-200 rounded-xl shadow-xl z-50 p-1.5 text-xs"
                                        style="display: none;"
                                    >
                                        <input
                                            type="text"
                                            x-model="item.supplierSearchQuery"
                                            placeholder="Ketik untuk mencari supplier..."
                                            class="w-full px-2.5 py-1.5 mb-1 text-xs border border-gray-200 rounded-lg bg-gray-50 focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#15803d]"
                                            @click.stop
                                        >
                                        <div class="max-h-48 overflow-y-auto space-y-0.5 custom-scrollbar">
                                            <div
                                                @click="item.supplierId = ''; onSupplierChange(item); item.supplierDropdownOpen = false;"
                                                class="p-2 rounded-lg hover:bg-gray-100 cursor-pointer text-gray-500 italic transition"
                                                :class="{'bg-gray-100 font-bold': !item.supplierId}"
                                            >
                                                -- Bebas / Rekomendasi Purchasing --
                                            </div>
                                            <template x-for="s in filteredSuppliers(item)" :key="s.id">
                                                <div
                                                    @click="item.supplierId = s.id; onSupplierChange(item); item.supplierDropdownOpen = false;"
                                                    class="p-2 rounded-lg hover:bg-emerald-50 cursor-pointer flex items-center justify-between gap-1 transition"
                                                    :class="{'bg-emerald-50/80 font-bold text-emerald-900': item.supplierId == s.id}"
                                                >
                                                    <span x-text="s.label" class="truncate"></span>
                                                    <template x-if="s.price">
                                                        <span class="text-[10px] font-mono font-semibold text-[#15803d] shrink-0" x-text="formatRupiah(s.price)"></span>
                                                    </template>
                                                </div>
                                            </template>
                                            <div x-show="filteredSuppliers(item).length === 0" class="p-2 text-center text-gray-400 select-none">
                                                Tidak ada supplier yang cocok.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Catatan / Spek Item --}}
                            <div class="md:col-span-3">
                                <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 mb-1">
                                    Catatan / Spek Teknis
                                </label>
                                <input
                                    type="text"
                                    :name="'items[' + index + '][note]'"
                                    x-model="item.note"
                                    placeholder="Ukuran, merk, atau spesifikasi khusus..."
                                    class="w-full py-2 px-2.5 border border-gray-300 rounded-lg text-xs font-medium focus:outline-none focus:ring-1 focus:ring-[#15803d]"
                                />
                            </div>
                        </div>

                        {{-- Level 2: Komersial, Harga Acuan, Diskon & Pajak --}}
                        <div class="bg-gray-50/70 rounded-xl p-3 border border-gray-100 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-6 gap-3 text-xs">
                            {{-- Kuantitas --}}
                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-600 mb-1">
                                    Kuantitas <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="number"
                                    :name="'items[' + index + '][quantity]'"
                                    x-model.number="item.quantity"
                                    min="0.01"
                                    step="any"
                                    required
                                    class="w-full py-1.5 px-2.5 border border-gray-300 rounded-lg text-xs font-bold text-gray-900 bg-white focus:outline-none focus:ring-1 focus:ring-[#15803d]"
                                />
                            </div>

                            {{-- Harga Pokok Acuan (Original Price) --}}
                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-600 mb-1">
                                    Harga Acuan (Master/Pivot)
                                </label>
                                <div class="w-full py-1.5 px-2.5 bg-gray-100 border border-gray-200 rounded-lg text-xs font-medium text-gray-600 truncate" x-text="formatRupiah(item.originalPrice)"></div>
                                <input type="hidden" :name="'items[' + index + '][original_price]'" :value="item.originalPrice">
                            </div>

                            {{-- Estimasi Harga Satuan (Unit Price Diajukan - Rupiah Masking) --}}
                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-600 mb-1">
                                    Estimasi Harga Satuan <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-xs font-bold text-gray-400">Rp</span>
                                    <input
                                        type="text"
                                        :value="item.unitPrice ? formatRupiah(item.unitPrice, false) : (item.unitPrice === 0 ? '0' : '')"
                                        @input="item.unitPrice = parseRupiah($event.target.value)"
                                        @blur="$event.target.value = (item.unitPrice ? formatRupiah(item.unitPrice, false) : (item.unitPrice === 0 ? '0' : ''))"
                                        @focus="if($event.target.value === '0') $event.target.value = ''"
                                        required
                                        class="w-full py-1.5 pl-8 pr-2.5 border border-gray-300 rounded-lg text-xs font-bold text-gray-900 bg-white focus:outline-none focus:ring-1 focus:ring-[#15803d] font-mono text-right"
                                        placeholder="0"
                                    />
                                    <input type="hidden" :name="'items[' + index + '][unit_price]'" :value="item.unitPrice" required>
                                </div>
                            </div>

                            {{-- Diskon (%) --}}
                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-600 mb-1">
                                    Diskon Item (%)
                                </label>
                                <input
                                    type="number"
                                    :name="'items[' + index + '][discount]'"
                                    x-model.number="item.discount"
                                    min="0"
                                    max="100"
                                    step="any"
                                    class="w-full py-1.5 px-2.5 border border-gray-300 rounded-lg text-xs font-medium text-gray-900 bg-white focus:outline-none focus:ring-1 focus:ring-[#15803d]"
                                    placeholder="0"
                                />
                            </div>

                            {{-- Tipe Pajak (Pill Buttons) --}}
                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-600 mb-1">
                                    Perlakuan Pajak
                                </label>
                                <div class="flex items-center rounded-lg border border-gray-300 bg-white p-0.5 text-[11px] font-semibold">
                                    <button 
                                        type="button" 
                                        @click="onTipePajakChange(item, 'none')"
                                        class="flex-1 py-1 rounded-md text-center transition cursor-pointer"
                                        :class="item.tipePajak === 'none' ? 'bg-gray-800 text-white shadow-xs' : 'text-gray-600 hover:text-gray-900'"
                                    >
                                        Non
                                    </button>
                                    <button 
                                        type="button" 
                                        @click="onTipePajakChange(item, 'inklusif')"
                                        class="flex-1 py-1 rounded-md text-center transition cursor-pointer"
                                        :class="item.tipePajak === 'inklusif' ? 'bg-[#15803d] text-white shadow-xs' : 'text-gray-600 hover:text-gray-900'"
                                    >
                                        Inklusif
                                    </button>
                                    <button 
                                        type="button" 
                                        @click="onTipePajakChange(item, 'eklusif')"
                                        class="flex-1 py-1 rounded-md text-center transition cursor-pointer"
                                        :class="item.tipePajak === 'eklusif' ? 'bg-[#15803d] text-white shadow-xs' : 'text-gray-600 hover:text-gray-900'"
                                    >
                                        Eklusif
                                    </button>
                                </div>
                                <input type="hidden" :name="'items[' + index + '][tipe_pajak]'" :value="item.tipePajak">
                                <input type="hidden" :name="'items[' + index + '][tax]'" :value="item.taxRate">
                            </div>

                            {{-- Mata Uang Item (Inherit) --}}
                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-600 mb-1">
                                    Mata Uang Item
                                </label>
                                <select
                                    :name="'items[' + index + '][currency_id]'"
                                    x-model="item.currencyId"
                                    class="w-full py-1.5 px-2 border border-gray-300 rounded-lg text-xs bg-white font-medium text-gray-700 focus:outline-none focus:ring-1 focus:ring-[#15803d]"
                                >
                                    <template x-for="curr in currencies" :key="curr.id">
                                        <option :value="curr.id" x-text="curr.code + ' (' + curr.symbol + ')'"></option>
                                    </template>
                                </select>
                            </div>
                        </div>

                        {{-- Level 3: Kalkulasi & Ringkasan Baris --}}
                        <div class="flex flex-wrap items-center justify-between gap-3 pt-1 border-t border-gray-100 text-xs">
                            <div class="flex items-center gap-4 text-gray-500 text-[11px]">
                                <span>Total Kotor: <strong class="text-gray-700" x-text="formatRupiah(calculateItem(item).grossTotal)"></strong></span>
                                <template x-if="item.discount > 0">
                                    <span>Diskon: <strong class="text-amber-600" x-text="item.discount + '% (-' + formatRupiah(calculateItem(item).discountNominal) + ')'"></strong></span>
                                </template>
                                <span>PPN (<span x-text="item.tipePajak === 'none' ? '0%' : item.taxRate + '%'"></span>): <strong class="text-gray-700" x-text="formatRupiah(calculateItem(item).taxNominal)"></strong></span>
                            </div>

                            <div class="flex items-center gap-2">
                                <span class="text-gray-500 font-semibold">Subtotal Bersih:</span>
                                <span class="text-base font-extrabold text-[#15803d]" x-text="formatRupiah(calculateItem(item).subtotal)"></span>
                            </div>
                        </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Summary Bar Total --}}
            <div class="p-4 bg-gray-50 rounded-2xl border border-gray-200 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="text-xs text-gray-600 flex items-center gap-2">
                    <span>Total Baris: <strong class="text-gray-900 font-bold" x-text="items.length + ' item'"></strong></span>
                    <span>&bull;</span>
                    <span>Total Kuantitas: <strong class="text-gray-900 font-bold" x-text="totalQuantity()"></strong></span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Estimasi Total Pengadaan:</span>
                    <span class="text-xl font-extrabold text-[#15803d]" x-text="formatRupiah(totalEstimatedAmount())"></span>
                </div>
            </div>
        </div>

        {{-- Card 3: Tombol Aksi Simpan --}}
        <div class="flex items-center justify-end gap-3 pt-2">
            <a
                href="{{ route('pembelian.or.index') }}"
                class="px-5 py-2.5 border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 text-sm font-semibold rounded-xl shadow-xs transition active:scale-[0.98]"
            >
                Batal
            </a>

            <button
                type="submit"
                name="action"
                value="draft"
                class="px-5 py-2.5 border border-gray-300 bg-white hover:bg-gray-50 text-gray-800 text-sm font-semibold rounded-xl shadow-xs transition active:scale-[0.98] cursor-pointer"
            >
                Simpan sebagai Draf
            </button>

            <button
                type="submit"
                name="action"
                value="request_approve"
                class="px-5 py-2.5 bg-[#15803d] hover:bg-[#14532d] text-white text-sm font-bold rounded-xl shadow-xs transition active:scale-[0.98] cursor-pointer"
            >
                Simpan & Ajukan Persetujuan
            </button>
        </div>
    </form>
</div>

<script>
function orderRequestForm(config) {
    return {
        cabangs: config.cabangs || [],
        currencies: config.currencies || [],
        headerCurrencyId: config.defaultCurrencyId || '',
        headerCabangId: config.defaultCabangId || '',
        items: [],
        productsSearchUrl: config.productsSearchUrl,
        suppliersSearchUrl: config.suppliersSearchUrl,

        // Quick-Add Search Bar State
        quickSearchQuery: '',
        quickSearchResults: [],
        isQuickSearching: false,
        quickHighlightedIndex: -1,
        quickSearchTimeout: null,
        quickFeedback: '',
        quickFeedbackTimeout: null,

        init() {
            if (config.oldItems && config.oldItems.length > 0) {
                this.items = config.oldItems.map(item => this.createItem(item, false));
                if (this.items.length > 0) this.items[0].isOpen = true;
            } else {
                this.addRow(false);
            }
        },

        createItem(prefill = {}, isOpen = true) {
            const item = {
                isOpen: prefill.is_open !== undefined ? Boolean(prefill.is_open) : isOpen,
                productId: prefill.product_id || '',
                productName: prefill.product_name || '',
                productSku: prefill.product_sku || '',
                searchQuery: prefill.search_query || '',
                dropdownOpen: false,
                isSearching: false,
                searchResults: [],
                searchTimeout: null,

                uom: prefill.uom || 'Pcs',
                cabangId: prefill.cabang_id || this.headerCabangId || '',
                currencyId: prefill.currency_id || this.headerCurrencyId || '',

                // Supplier state
                supplierId: prefill.supplier_id || '',
                suppliersList: [],
                isLoadingSuppliers: false,
                recommendedSupplierText: prefill.recommended_supplier_text || '',
                recommendedSupplierId: prefill.recommended_supplier_id || null,
                supplierDropdownOpen: false,
                supplierSearchQuery: '',
                cabangDropdownOpen: false,
                cabangSearchQuery: '',

                // Commercial & Tax
                quantity: prefill.quantity !== undefined ? parseFloat(prefill.quantity) : 1,
                originalPrice: prefill.original_price !== undefined ? parseFloat(prefill.original_price) : 0,
                unitPrice: prefill.unit_price !== undefined ? parseFloat(prefill.unit_price) : 0,
                discount: prefill.discount !== undefined ? parseFloat(prefill.discount) : 0,
                tipePajak: prefill.tipe_pajak || 'eklusif',
                taxRate: prefill.tax !== undefined ? parseFloat(prefill.tax) : 11,
                note: prefill.note || '',
            };

            if (item.productId) {
                this.fetchSuppliersForItem(item);
            }

            return item;
        },

        onSearchInput(item) {
            item.dropdownOpen = true;
            if (item.searchTimeout) clearTimeout(item.searchTimeout);

            const q = (item.searchQuery || '').trim();
            if (q.length < 1) {
                item.searchResults = [];
                item.isSearching = false;
                return;
            }

            item.isSearching = true;
            item.searchTimeout = setTimeout(async () => {
                try {
                    const res = await fetch(`${this.productsSearchUrl}?q=${encodeURIComponent(q)}&cabang_id=${item.cabangId || ''}`);
                    const data = await res.json();
                    if (data.success) {
                        item.searchResults = data.products || [];
                    }
                } catch (e) {
                    console.error('Error searching products:', e);
                } finally {
                    item.isSearching = false;
                }
            }, 300);
        },

        selectProduct(item, p) {
            item.productId = p.id;
            item.productName = p.name;
            item.productSku = p.sku;
            item.searchQuery = p.label || (p.sku && p.sku !== '-' ? `(${p.sku}) ${p.name}` : p.name);
            item.uom = p.uom;
            item.originalPrice = p.cost_price;
            item.unitPrice = p.cost_price;
            item.taxRate = item.tipePajak === 'none' ? 0 : (p.default_tax_rate ?? 11);

            if (p.recommended_supplier) {
                item.recommendedSupplierText = p.recommended_supplier.label;
                item.recommendedSupplierId = p.recommended_supplier.id;
                item.supplierId = p.recommended_supplier.id;
                item.originalPrice = p.recommended_supplier.price;
                item.unitPrice = p.recommended_supplier.price;
            } else {
                item.recommendedSupplierText = '';
                item.recommendedSupplierId = null;
            }

            item.dropdownOpen = false;
            this.fetchSuppliersForItem(item);
        },

        clearProduct(item) {
            item.productId = '';
            item.productName = '';
            item.productSku = '';
            item.searchQuery = '';
            item.uom = 'Pcs';
            item.originalPrice = 0;
            item.unitPrice = 0;
            item.supplierId = '';
            item.suppliersList = [];
            item.recommendedSupplierText = '';
            item.recommendedSupplierId = null;
            item.dropdownOpen = false;
        },

        async fetchSuppliersForItem(item) {
            if (!item.productId) return;
            item.isLoadingSuppliers = true;
            try {
                const res = await fetch(`${this.suppliersSearchUrl}?product_id=${item.productId}`);
                const data = await res.json();
                if (data.success) {
                    item.suppliersList = data.suppliers || [];
                }
            } catch (e) {
                console.error('Error fetching suppliers:', e);
            } finally {
                item.isLoadingSuppliers = false;
            }
        },

        onSupplierChange(item) {
            if (!item.supplierId) return;
            const found = item.suppliersList.find(s => String(s.id) === String(item.supplierId));
            if (found && found.supplier_price !== null && found.supplier_price !== undefined) {
                item.originalPrice = found.supplier_price;
                item.unitPrice = found.supplier_price;
            }
        },

        getSupplierLabel(item) {
            if (!item.supplierId) return '-- Bebas / Rekomendasi Purchasing --';
            const found = (item.suppliersList || []).find(s => String(s.id) === String(item.supplierId));
            return found ? found.label : (item.recommendedSupplierText || 'Supplier #' + item.supplierId);
        },

        filteredSuppliers(item) {
            const list = item.suppliersList || [];
            if (!item.supplierSearchQuery || !item.supplierSearchQuery.trim()) {
                return list;
            }
            const q = item.supplierSearchQuery.toLowerCase();
            return list.filter(s => (s.label || '').toLowerCase().includes(q));
        },

        getCabangLabel(item) {
            const found = (this.cabangs || []).find(c => String(c.id) === String(item.cabangId));
            return found ? (found.nama + (found.kode ? ' (' + found.kode + ')' : '')) : '-- Pilih Cabang --';
        },

        filteredCabangs(item) {
            const list = this.cabangs || [];
            if (!item.cabangSearchQuery || !item.cabangSearchQuery.trim()) {
                return list;
            }
            const q = item.cabangSearchQuery.toLowerCase();
            return list.filter(c => (c.nama || '').toLowerCase().includes(q) || (c.kode || '').toLowerCase().includes(q));
        },

        onTipePajakChange(item, type) {
            item.tipePajak = type;
            if (type === 'none') {
                item.taxRate = 0;
            } else if (item.taxRate === 0) {
                item.taxRate = 11;
            }
        },

        calculateItem(item) {
            const qty = parseFloat(item.quantity) || 0;
            const price = parseFloat(item.unitPrice) || 0;
            const disc = Math.min(Math.max(parseFloat(item.discount) || 0, 0), 100);
            const taxPct = item.tipePajak === 'none' ? 0 : (parseFloat(item.taxRate) || 0);

            const grossTotal = qty * price;
            const discountNominal = grossTotal * (disc / 100);
            const baseAfterDisc = grossTotal - discountNominal;

            let taxNominal = 0;
            let subtotal = 0;

            if (item.tipePajak === 'none') {
                taxNominal = 0;
                subtotal = baseAfterDisc;
            } else if (item.tipePajak === 'inklusif') {
                taxNominal = baseAfterDisc - (baseAfterDisc / (1 + (taxPct / 100)));
                subtotal = baseAfterDisc;
            } else {
                taxNominal = baseAfterDisc * (taxPct / 100);
                subtotal = baseAfterDisc + taxNominal;
            }

            return {
                grossTotal: Math.round(grossTotal * 100) / 100,
                discountNominal: Math.round(discountNominal * 100) / 100,
                taxNominal: Math.round(taxNominal * 100) / 100,
                subtotal: Math.round(subtotal * 100) / 100,
            };
        },

        addRow(atTop = true) {
            // Auto-collapse item sebelumnya agar tetap rapi seperti Filament
            this.items.forEach(it => it.isOpen = false);
            const newItem = this.createItem({}, true);
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
            this.quickSearchTimeout = setTimeout(async () => {
                try {
                    const res = await fetch(`${this.productsSearchUrl}?q=${encodeURIComponent(q)}&cabang_id=${this.headerCabangId || ''}`);
                    const data = await res.json();
                    if (data.success) {
                        this.quickSearchResults = data.products || [];
                        this.quickHighlightedIndex = this.quickSearchResults.length > 0 ? 0 : -1;
                    }
                } catch (e) {
                    console.error('Error in Quick-Add search:', e);
                } finally {
                    this.isQuickSearching = false;
                }
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
                const newItem = this.createItem({}, true);
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
            if (item.productSku || item.productName) {
                const sku = item.productSku && item.productSku !== '-' ? `(${item.productSku}) ` : '';
                parts.push(`${sku}${item.productName}`);
            } else {
                parts.push('Belum memilih produk');
            }
            const qty = parseFloat(item.quantity) || 0;
            parts.push(`${qty} ${item.uom || 'Pcs'}`);
            const calc = this.calculateItem(item);
            parts.push(`Est: ${this.formatRupiah(calc.subtotal)}`);
            return parts.join(' · ');
        },

        removeRow(index) {
            if (this.items.length > 1) {
                this.items.splice(index, 1);
            }
        },

        totalQuantity() {
            return this.items.reduce((sum, item) => sum + (parseFloat(item.quantity) || 0), 0);
        },

        totalEstimatedAmount() {
            return this.items.reduce((sum, item) => sum + this.calculateItem(item).subtotal, 0);
        },

        formatRupiah(num) {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0,
                maximumFractionDigits: 2
            }).format(num || 0);
        }
    };
}
</script>
@endsection
