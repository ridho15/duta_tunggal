@extends('layouts.app')

@section('title', 'Buat Retur Pembelian – Duta Tunggal ERP')

@section('content')
<div
    class="max-w-7xl mx-auto space-y-6"
    x-data="{
        supplierId: '{{ old('supplier_id', $preselectedSupplierId ?? '') }}',
        receiptId: '{{ old('purchase_receipt_id', $preselectedReceiptId ?? '') }}',
        cabangId: '{{ old('cabang_id', auth()->user()?->cabang_id ?? '') }}',
        returnDate: '{{ old('return_date', date('Y-m-d')) }}',
        physicalReturnDate: '{{ old('physical_return_date', '') }}',
        deliveryNote: '{{ old('delivery_note', '') }}',
        shippingDetails: '{{ old('shipping_details', '') }}',
        notes: '{{ old('notes', '') }}',
        availableReceipts: [],
        receiptDetails: null,
        items: [],
        loadingReceipts: false,
        loadingItems: false,
        selectAll: false,

        async init() {
            if (this.supplierId) {
                await this.loadSupplierReceipts(this.supplierId);
                if (this.receiptId) {
                    await this.loadReceiptItems(this.receiptId);
                }
            }
        },

        async onSupplierChange(newSupplierId) {
            this.supplierId = newSupplierId;
            this.receiptId = '';
            this.availableReceipts = [];
            this.receiptDetails = null;
            this.items = [];
            this.selectAll = false;

            if (newSupplierId) {
                await this.loadSupplierReceipts(newSupplierId);
            }
        },

        async loadSupplierReceipts(supplierId) {
            this.loadingReceipts = true;
            try {
                const res = await fetch(`{{ url('pembelian/retur-supplier-receipts') }}/${supplierId}`);
                const data = await res.json();
                this.availableReceipts = data.receipts || [];
            } catch (e) {
                console.error('Gagal mengambil surat jalan penerimaan:', e);
                this.availableReceipts = [];
            } finally {
                this.loadingReceipts = false;
            }
        },

        async onReceiptChange(newReceiptId) {
            this.receiptId = newReceiptId;
            this.items = [];
            this.receiptDetails = null;
            this.selectAll = false;

            if (newReceiptId) {
                await this.loadReceiptItems(newReceiptId);
            }
        },

        async loadReceiptItems(receiptId) {
            this.loadingItems = true;
            try {
                const res = await fetch(`{{ url('pembelian/retur-receipt-items') }}/${receiptId}`);
                const data = await res.json();
                this.receiptDetails = data.receipt || null;

                if (this.receiptDetails && this.receiptDetails.cabang_id && !this.cabangId) {
                    this.cabangId = this.receiptDetails.cabang_id;
                }

                // Siapkan data item dengan properti interaktif
                this.items = (data.items || []).map((it, idx) => {
                    return {
                        purchase_receipt_item_id: it.purchase_receipt_item_id,
                        product_id: it.product_id,
                        product_code: it.product_code,
                        product_name: it.product_name,
                        uom_name: it.uom_name,
                        qty_accepted: parseFloat(it.qty_accepted) || 0,
                        already_returned: parseFloat(it.already_returned) || 0,
                        max_returnable: parseFloat(it.max_returnable) || 0,
                        unit_price: parseFloat(it.unit_price) || 0,
                        unit_price_formatted: it.unit_price_formatted,
                        qty_returned: 0,
                        reason: '',
                        selected: false,
                    };
                });
            } catch (e) {
                console.error('Gagal memuat item penerimaan:', e);
                this.items = [];
            } finally {
                this.loadingItems = false;
            }
        },

        toggleSelectAll() {
            this.items.forEach(item => {
                item.selected = this.selectAll;
                if (item.selected && (!item.qty_returned || item.qty_returned <= 0)) {
                    item.qty_returned = item.max_returnable;
                } else if (!item.selected) {
                    item.qty_returned = 0;
                }
            });
        },

        onItemToggle(item) {
            if (item.selected && (!item.qty_returned || item.qty_returned <= 0)) {
                item.qty_returned = item.max_returnable;
            } else if (!item.selected) {
                item.qty_returned = 0;
            }
            this.selectAll = this.items.length > 0 && this.items.every(i => i.selected);
        },

        validateItemQty(item) {
            const val = parseFloat(item.qty_returned) || 0;
            if (val > item.max_returnable) {
                item.qty_returned = item.max_returnable;
            } else if (val < 0) {
                item.qty_returned = 0;
            }

            if (val > 0) {
                item.selected = true;
            } else {
                item.selected = false;
            }
            this.selectAll = this.items.length > 0 && this.items.every(i => i.selected);
        },

        totalSelectedCount() {
            return this.items.filter(i => i.selected && i.qty_returned > 0).length;
        },

        totalQtySum() {
            return this.items.reduce((sum, i) => {
                return i.selected ? sum + (parseFloat(i.qty_returned) || 0) : sum;
            }, 0);
        },

        totalAmountSum() {
            return this.items.reduce((sum, i) => {
                return i.selected ? sum + ((parseFloat(i.qty_returned) || 0) * (parseFloat(i.unit_price) || 0)) : sum;
            }, 0);
        },

        formatRupiah(amount) {
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
                <a href="{{ route('pembelian.returns.index') }}" class="text-gray-500 hover:text-gray-900 transition">Retur Pembelian</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Buat Retur Baru</span>
            </div>
            <div class="flex items-center gap-2.5">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-100 text-[#15803d]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M9 14 4 9l5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M4 9h10a6 6 0 0 1 0 12h-3" stroke-width="2" stroke-linecap="round"></path>
                    </svg>
                </span>
                <div>
                    <h1 class="text-xl font-bold text-gray-900 tracking-tight">Buat Dokumen Retur Pembelian &amp; Debit Note</h1>
                    <p class="text-xs text-gray-500">Pilih dokumen penerimaan barang (GRN), tentukan barang yang dikembalikan ke vendor, dan buat nota debit pemotong hutang.</p>
                </div>
            </div>
        </div>
        <div>
            <a
                href="{{ route('pembelian.returns.index') }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 border border-gray-200 text-gray-700 bg-white hover:bg-gray-50 text-xs font-semibold rounded-lg shadow-sm transition"
            >
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Kembali ke Daftar
            </a>
        </div>
    </div>

    {{-- Alert Error Server Validation --}}
    @if ($errors->any())
        <div class="p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-xs space-y-1">
            <div class="font-bold flex items-center gap-1.5 text-sm">
                <svg class="w-4 h-4 text-red-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                Terdapat beberapa kesalahan pengisian form:
            </div>
            <ul class="list-disc list-inside space-y-0.5 pl-5">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Form Utama --}}
    <form action="{{ route('pembelian.returns.store') }}" method="POST" class="space-y-6">
        @csrf

        {{-- Kartu 1: Informasi Dokumen & Referensi Pengadaan --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-5">
            <h2 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-3 flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-[#15803d]"></span>
                1. Informasi Pemasok &amp; Referensi Surat Jalan Masuk (GRN)
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                {{-- Dropdown Searchable Supplier --}}
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Pemasok (Supplier / Vendor) <span class="text-red-500">*</span>
                    </label>
                    <div @change="onSupplierChange($event.detail)">
                        @php
                            $supplierOptions = $suppliers->map(function($s) {
                                return [
                                    'value' => (string)$s->id,
                                    'label' => $s->perusahaan ?: $s->name,
                                    'subtext' => 'Kode: ' . ($s->code ?: '-') . ' | Telp: ' . ($s->phone ?? $s->telephone ?? '-'),
                                ];
                            })->values()->all();
                        @endphp
                        <x-searchable-select
                            name="supplier_id"
                            :options="$supplierOptions"
                            :value="old('supplier_id', $preselectedSupplierId ?? '')"
                            placeholder="-- Cari atau Pilih Pemasok / Vendor --"
                            empty-message="Pemasok tidak ditemukan."
                            :required="true"
                        />
                    </div>
                    @error('supplier_id')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Dropdown Surat Jalan Penerimaan Barang --}}
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Surat Jalan Masuk / Penerimaan Barang (GRN) <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <select data-searchable
                            name="purchase_receipt_id"
                            x-model="receiptId"
                            @change="onReceiptChange($event.target.value)"
                            :disabled="!supplierId || loadingReceipts"
                            required
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-xs bg-white focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:border-transparent transition disabled:bg-gray-50 disabled:text-gray-400"
                        >
                            <option value="">-- Pilih Dokumen Penerimaan Barang --</option>
                            <template x-for="rcp in availableReceipts" :key="rcp.id">
                                <option :value="rcp.id" x-text="rcp.display_label"></option>
                            </template>
                        </select>
                        <div x-show="loadingReceipts" class="absolute right-3 top-2.5">
                            <svg class="animate-spin h-4 w-4 text-[#15803d]" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1">Hanya penerimaan barang berstatus Selesai (Completed) atau Parsial yang dapat diretur.</p>
                    @error('purchase_receipt_id')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Nomor Nota Retur (Auto-Generated) --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Nomor Nota Retur
                    </label>
                    <div class="relative">
                        <input
                            type="text"
                            name="nota_retur"
                            value="{{ old('nota_retur', $autoNotaRetur) }}"
                            readonly
                            class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-xs font-mono font-semibold text-gray-800 cursor-not-allowed"
                        />
                        <span class="absolute right-2.5 top-2 px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-[#15803d] border border-emerald-200">
                            Otomatis
                        </span>
                    </div>
                </div>

                {{-- Tanggal Dokumen Retur --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Tanggal Retur <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="date"
                        name="return_date"
                        x-model="returnDate"
                        required
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:border-transparent transition"
                    />
                    @error('return_date')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Cabang / Unit Kerja --}}
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Cabang / Unit Kerja <span class="text-red-500">*</span>
                    </label>
                    <select data-searchable
                        name="cabang_id"
                        x-model="cabangId"
                        required
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg text-xs bg-white focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:border-transparent transition"
                    >
                        <option value="">-- Pilih Cabang --</option>
                        @foreach ($cabangs as $cabang)
                            <option value="{{ $cabang->id }}">{{ $cabang->nama }} ({{ $cabang->kode }})</option>
                        @endforeach
                    </select>
                    @error('cabang_id')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Detail Header Receipt Terpilih --}}
            <template x-if="receiptDetails">
                <div class="p-3.5 bg-emerald-50/60 border border-emerald-100 rounded-lg flex flex-wrap items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-[#15803d]"></span>
                        <span class="font-bold text-gray-800" x-text="receiptDetails.receipt_number"></span>
                        <span class="text-gray-400">|</span>
                        <span class="text-gray-600">Tgl Diterima: <strong x-text="receiptDetails.receipt_date"></strong></span>
                    </div>
                    <div class="flex items-center gap-4 text-gray-600">
                        <div>Gudang: <strong x-text="receiptDetails.warehouse_name"></strong></div>
                        <div>Cabang: <strong x-text="receiptDetails.cabang_name"></strong></div>
                    </div>
                </div>
            </template>
        </div>

        {{-- Kartu 2: Pengiriman Fisik & Ekspedisi (Opsional) --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <h2 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                    2. Pengiriman Fisik &amp; Ekspedisi Pengembalian (Opsional)
                </h2>
                <span class="text-[11px] text-gray-400 font-medium">Dapat dilengkapi nanti saat barang dikirim</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- No Surat Jalan Pengembalian --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        No. Surat Jalan Pengembalian / Delivery Note
                    </label>
                    <input
                        type="text"
                        name="delivery_note"
                        x-model="deliveryNote"
                        placeholder="Contoh: SJ-RET-2026/004"
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:border-transparent transition"
                    />
                </div>

                {{-- Tanggal Pengembalian Fisik --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Tanggal Pengiriman Fisik
                    </label>
                    <input
                        type="date"
                        name="physical_return_date"
                        x-model="physicalReturnDate"
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:border-transparent transition"
                    />
                </div>

                {{-- Ekspedisi / Detail Kurir --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Ekspedisi / Nama Sopir / Plat Kendaraan
                    </label>
                    <input
                        type="text"
                        name="shipping_details"
                        x-model="shippingDetails"
                        placeholder="Contoh: Truk Sendiri (B 9876 DT) - Pak Joko"
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:border-transparent transition"
                    />
                </div>
            </div>
        </div>

        {{-- Kartu 3: Daftar Barang yang Diretur --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                        3. Rincian Barang yang Diretur
                    </h2>
                    <p class="text-xs text-gray-500 mt-0.5">Centang item yang ingin dikembalikan, tentukan kuantitas retur (maksimal sisa yang dapat diretur), dan isi alasan retur.</p>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <span class="text-gray-500">Terpilih:</span>
                    <span class="px-2.5 py-0.5 rounded-full font-bold bg-emerald-100 text-[#15803d]" x-text="totalSelectedCount() + ' Item'"></span>
                </div>
            </div>

            {{-- Loading State --}}
            <div x-show="loadingItems" class="p-12 text-center text-gray-500">
                <svg class="animate-spin h-8 w-8 text-[#15803d] mx-auto mb-3" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <div class="text-xs font-semibold text-gray-700">Sedang memuat data item barang penerimaan...</div>
            </div>

            {{-- Empty State Sebelum Pilih GRN --}}
            <div x-show="!loadingItems && items.length === 0 && !receiptId" class="p-12 text-center text-gray-400">
                <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                </svg>
                <div class="text-sm font-semibold text-gray-600">Surat Jalan Penerimaan Belum Dipilih</div>
                <div class="text-xs text-gray-400 mt-1">Silakan pilih Supplier dan Surat Jalan Masuk di atas untuk memuat daftar barang yang dapat diretur.</div>
            </div>

            {{-- Empty State Jika Tidak Ada Item yang Dapat Diretur --}}
            <div x-show="!loadingItems && items.length === 0 && receiptId" class="p-12 text-center text-gray-400">
                <svg class="w-12 h-12 mx-auto text-amber-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div class="text-sm font-semibold text-gray-700">Tidak Ada Item yang Dapat Diretur</div>
                <div class="text-xs text-gray-400 mt-1">Seluruh kuantitas barang pada dokumen penerimaan ini sudah pernah diajukan atau diproses retur sebelumnya.</div>
            </div>

            {{-- Tabel Rincian Item --}}
            <div x-show="!loadingItems && items.length > 0" class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-gray-50 border-y border-gray-200 text-gray-600 uppercase font-semibold text-[11px] tracking-wider">
                            <th class="py-3 px-3 w-10 text-center">
                                <input
                                    type="checkbox"
                                    x-model="selectAll"
                                    @change="toggleSelectAll()"
                                    class="rounded border-gray-300 text-[#15803d] focus:ring-[#15803d]"
                                    title="Pilih Semua Item"
                                />
                            </th>
                            <th class="py-3 px-3">Barang &amp; Kode</th>
                            <th class="py-3 px-3 text-center">Diterima</th>
                            <th class="py-3 px-3 text-center">Sudah Retur</th>
                            <th class="py-3 px-3 text-center">Sisa Retur</th>
                            <th class="py-3 px-3 w-36">Jml Diretur <span class="text-red-500">*</span></th>
                            <th class="py-3 px-3 text-right">Harga PO</th>
                            <th class="py-3 px-3 text-right">Subtotal Retur</th>
                            <th class="py-3 px-3 w-48">Alasan Pengembalian</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="(item, idx) in items" :key="item.purchase_receipt_item_id">
                            <tr
                                class="transition"
                                :class="item.selected ? 'bg-emerald-50/30 hover:bg-emerald-50/50' : 'hover:bg-gray-50'"
                            >
                                {{-- Checkbox --}}
                                <td class="py-3 px-3 text-center">
                                    <input
                                        type="checkbox"
                                        x-model="item.selected"
                                        @change="onItemToggle(item)"
                                        class="rounded border-gray-300 text-[#15803d] focus:ring-[#15803d]"
                                    />
                                    {{-- Hidden Inputs untuk Form Submission --}}
                                    <template x-if="item.selected && item.qty_returned > 0">
                                        <div>
                                            <input type="hidden" :name="`items[${idx}][purchase_receipt_item_id]`" :value="item.purchase_receipt_item_id">
                                            <input type="hidden" :name="`items[${idx}][product_id]`" :value="item.product_id">
                                            <input type="hidden" :name="`items[${idx}][qty_returned]`" :value="item.qty_returned">
                                            <input type="hidden" :name="`items[${idx}][unit_price]`" :value="item.unit_price">
                                            <input type="hidden" :name="`items[${idx}][reason]`" :value="item.reason">
                                        </div>
                                    </template>
                                </td>

                                {{-- Nama Barang & Kode --}}
                                <td class="py-3 px-3">
                                    <div class="font-bold text-gray-900" x-text="item.product_name"></div>
                                    <div class="text-[11px] text-gray-400 font-mono">
                                        Kode: <span x-text="item.product_code"></span> | Satuan: <span x-text="item.uom_name"></span>
                                    </div>
                                </td>

                                {{-- Qty Diterima --}}
                                <td class="py-3 px-3 text-center font-medium text-gray-700">
                                    <span x-text="item.qty_accepted"></span>
                                    <span class="text-[10px] text-gray-400" x-text="item.uom_name"></span>
                                </td>

                                {{-- Qty Sudah Retur --}}
                                <td class="py-3 px-3 text-center text-gray-500">
                                    <span x-text="item.already_returned"></span>
                                    <span class="text-[10px] text-gray-400" x-text="item.uom_name"></span>
                                </td>

                                {{-- Sisa Dapat Diretur --}}
                                <td class="py-3 px-3 text-center">
                                    <span class="inline-flex px-2 py-0.5 rounded text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                        <span x-text="item.max_returnable"></span>&nbsp;<span x-text="item.uom_name"></span>
                                    </span>
                                </td>

                                {{-- Input Qty Retur --}}
                                <td class="py-3 px-3">
                                    <div class="relative">
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            :max="item.max_returnable"
                                            x-model="item.qty_returned"
                                            @input="validateItemQty(item)"
                                            class="w-full px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs font-semibold text-gray-900 text-right focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:border-transparent transition"
                                            :class="{'border-red-400 ring-1 ring-red-400': item.qty_returned > item.max_returnable}"
                                            placeholder="0"
                                        />
                                    </div>
                                </td>

                                {{-- Harga Satuan PO --}}
                                <td class="py-3 px-3 text-right font-medium text-gray-700 whitespace-nowrap" x-text="item.unit_price_formatted"></td>

                                {{-- Subtotal Retur Baris --}}
                                <td class="py-3 px-3 text-right font-bold text-gray-900 whitespace-nowrap" x-text="formatRupiah((item.qty_returned || 0) * (item.unit_price || 0))"></td>

                                {{-- Alasan Retur --}}
                                <td class="py-3 px-3">
                                    <input
                                        type="text"
                                        x-model="item.reason"
                                        placeholder="Contoh: Rusak / Cacat fisik / Salah spek"
                                        class="w-full px-2.5 py-1.5 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:border-transparent transition"
                                    />
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- Summary Footer Tabel --}}
            <div x-show="!loadingItems && items.length > 0" class="bg-gray-50 p-4 border-t border-gray-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center gap-4 text-xs text-gray-600">
                    <div>Total Item Terpilih: <strong class="text-gray-900" x-text="totalSelectedCount()"></strong></div>
                    <div>Total Kuantitas Fisik: <strong class="text-gray-900" x-text="totalQtySum().toFixed(2)"></strong></div>
                </div>
                <div class="flex items-center justify-between sm:justify-end gap-3">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Subtotal Nilai Retur (DPP):</span>
                    <span class="text-base font-extrabold text-[#15803d]" x-text="formatRupiah(totalAmountSum())"></span>
                </div>
            </div>
        </div>

        {{-- Kartu 4: Catatan / Keterangan Dokumen --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-3">
            <h2 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-gray-400"></span>
                4. Catatan Dokumen Retur (Opsional)
            </h2>
            <div>
                <textarea
                    name="notes"
                    x-model="notes"
                    rows="3"
                    placeholder="Tuliskan catatan tambahan mengenai pengembalian barang ini, kesepakatan dengan pihak supplier, atau instruksi penerimaan..."
                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:border-transparent transition"
                ></textarea>
            </div>
        </div>

        {{-- Action Bar / Tombol Simpan --}}
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-3 sticky bottom-4 z-10">
            <div class="text-xs text-gray-500 text-center sm:text-left">
                Pastikan kuantitas retur fisik telah sesuai sebelum mengajukan persetujuan.
            </div>
            <div class="flex items-center gap-3 w-full sm:w-auto">
                <a
                    href="{{ route('pembelian.returns.index') }}"
                    class="flex-1 sm:flex-none text-center px-4 py-2 border border-gray-200 text-gray-700 bg-white hover:bg-gray-50 text-xs font-semibold rounded-lg shadow-sm transition"
                >
                    Batal
                </a>

                {{-- Simpan Sebagai Draft --}}
                <button
                    type="submit"
                    name="action"
                    value="save_draft"
                    :disabled="totalSelectedCount() === 0"
                    class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1.5 px-4 py-2 border border-emerald-600 text-[#15803d] hover:bg-emerald-50 text-xs font-semibold rounded-lg shadow-sm transition disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                    </svg>
                    Simpan Draft
                </button>

                {{-- Simpan & Ajukan Persetujuan --}}
                <button
                    type="submit"
                    name="action"
                    value="save_and_submit"
                    :disabled="totalSelectedCount() === 0"
                    class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1.5 px-5 py-2 bg-[#15803d] hover:bg-[#14532d] text-white text-xs font-bold rounded-lg shadow-sm transition disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Simpan &amp; Ajukan Persetujuan
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
