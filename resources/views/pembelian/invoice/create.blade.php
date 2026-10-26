@extends('layouts.app')

@section('title', 'Buat Faktur Pembelian – Duta Tunggal ERP')

@section('content')
<div
    class="max-w-6xl mx-auto space-y-6"
    x-data="{
        selectedSupplierId: '{{ old('supplier_id', $supplierId ?? '') }}',
        supplierDetails: null,
        loadingReceipts: false,
        loadingItems: false,
        availableReceipts: [],
        selectedReceiptIds: {{ json_encode(array_map('intval', (array)old('purchase_receipts', ($receiptId ? [$receiptId] : [])))) }},
        items: [],
        otherFee: {{ (float)old('other_fee', 0) }},
        invoiceDate: '{{ old('invoice_date', date('Y-m-d')) }}',
        dueDate: '{{ old('due_date', date('Y-m-d', strtotime('+30 days'))) }}',
        notes: '{{ old('notes', '') }}',
        supplierInvoiceNumber: '{{ old('supplier_invoice_number', '') }}',
        taxInvoiceNumber: '{{ old('tax_invoice_number', '') }}',

        init() {
            if (this.selectedSupplierId) {
                this.loadAvailableReceipts(this.selectedSupplierId);
            }
        },

        onSupplierChange(supplierId) {
            this.selectedSupplierId = supplierId;
            this.selectedReceiptIds = [];
            this.items = [];
            if (supplierId) {
                this.loadAvailableReceipts(supplierId);
            } else {
                this.availableReceipts = [];
            }
        },

        async loadAvailableReceipts(supplierId) {
            this.loadingReceipts = true;
            try {
                const res = await fetch(`{{ url('pembelian/invoice-available-receipts') }}/${supplierId}`);
                const data = await res.json();
                if (data.success) {
                    this.availableReceipts = data.receipts;
                    this.supplierDetails = data.supplier;
                    if (data.supplier && data.supplier.tempo_hutang) {
                        const days = parseInt(data.supplier.tempo_hutang) || 30;
                        const d = new Date(this.invoiceDate);
                        d.setDate(d.getDate() + days);
                        this.dueDate = d.toISOString().split('T')[0];
                    }

                    // Jika ada default receipt terpilih
                    if (this.selectedReceiptIds.length > 0) {
                        this.loadReceiptDetails();
                    }
                }
            } catch (err) {
                console.error('Failed to load receipts:', err);
            } finally {
                this.loadingReceipts = false;
            }
        },

        toggleReceipt(receiptId) {
            receiptId = parseInt(receiptId);
            const idx = this.selectedReceiptIds.indexOf(receiptId);
            if (idx === -1) {
                this.selectedReceiptIds.push(receiptId);
            } else {
                this.selectedReceiptIds.splice(idx, 1);
            }
            this.loadReceiptDetails();
        },

        async loadReceiptDetails() {
            if (this.selectedReceiptIds.length === 0) {
                this.items = [];
                return;
            }

            this.loadingItems = true;
            try {
                const queryParams = this.selectedReceiptIds.map(id => `receipt_ids[]=${id}`).join('&');
                const res = await fetch(`{{ route('pembelian.invoices.receipt-details') }}?${queryParams}`);
                const json = await res.json();
                if (json.success && json.data) {
                    this.items = (json.data.invoice_items || []).map(item => {
                        return {
                            product_id: item.product_id,
                            product_name: item.product_name,
                            sku: item.sku,
                            uom: item.uom,
                            quantity: parseFloat(item.quantity) || 0,
                            po_price: parseFloat(item.po_price) || 0,
                            price: parseFloat(item.price) || parseFloat(item.po_price) || 0,
                            tax_rate: parseFloat(item.tax_rate) || 0,
                        };
                    });
                }
            } catch (err) {
                console.error('Failed to load receipt items:', err);
            } finally {
                this.loadingItems = false;
            }
        },

        updateDueDateFromInvoiceDate() {
            if (this.invoiceDate && this.supplierDetails && this.supplierDetails.tempo_hutang) {
                const days = parseInt(this.supplierDetails.tempo_hutang) || 30;
                const d = new Date(this.invoiceDate);
                d.setDate(d.getDate() + days);
                this.dueDate = d.toISOString().split('T')[0];
            }
        },

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
                <span class="text-[#15803d] font-bold">Buat Faktur Baru</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Tagihan Faktur Pembelian (Purchase Invoice)</h1>
            <p class="text-sm text-gray-500 mt-0.5">Terbitkan tagihan resmi dari pemasok berdasarkan penerimaan barang (GRN) yang telah disahkan di gudang.</p>
        </div>
        <div>
            <a
                href="{{ route('pembelian.invoices.index') }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 border border-gray-200 text-gray-700 bg-white hover:bg-gray-50 text-xs font-semibold rounded-lg shadow-sm transition"
            >
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Kembali ke Daftar
            </a>
        </div>
    </div>

    {{-- Form Utama --}}
    <form action="{{ route('pembelian.invoices.store') }}" method="POST" class="space-y-6">
        @csrf

        {{-- Kartu 1: Pilihan Pemasok & Informasi Header --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-6">
            <h2 class="text-base font-bold text-gray-900 border-b border-gray-100 pb-3 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-[#15803d]"></span>
                1. Informasi Pemasok &amp; Dokumen Tagihan
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                {{-- Dropdown Searchable Supplier --}}
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Pemasok (Supplier) <span class="text-red-500">*</span>
                    </label>
                    <div @change="onSupplierChange($event.detail)">
                        @php
                            $supplierOptions = $suppliers->map(function($s) {
                                return [
                                    'value' => (string)$s->id,
                                    'label' => $s->perusahaan ? $s->perusahaan . ' (' . $s->name . ')' : $s->name,
                                    'subtext' => 'Telp: ' . ($s->phone ?? $s->telephone ?? '-') . ' | Tempo: ' . ($s->tempo_hutang ?? 30) . ' hari',
                                ];
                            })->values()->all();
                        @endphp
                        <x-searchable-select
                            name="supplier_id"
                            :options="$supplierOptions"
                            :value="old('supplier_id', $supplierId ?? '')"
                            placeholder="-- Cari atau Pilih Pemasok --"
                            empty-message="Pemasok tidak ditemukan."
                        />
                    </div>
                    @error('supplier_id')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Nomor Faktur Pemasok --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        No. Faktur Vendor / Supplier
                    </label>
                    <input
                        type="text"
                        name="supplier_invoice_number"
                        x-model="supplierInvoiceNumber"
                        placeholder="Contoh: INV/2026/001"
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:border-transparent transition"
                    />
                    @error('supplier_invoice_number')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Nomor Faktur Pajak --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        No. Faktur Pajak (NSFP)
                    </label>
                    <input
                        type="text"
                        name="tax_invoice_number"
                        x-model="taxInvoiceNumber"
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
                        @change="updateDueDateFromInvoiceDate()"
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

                {{-- Cabang Operasional --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Cabang Operasional
                    </label>
                    <input
                        type="text"
                        readonly
                        value="{{ $activeCabang->name ?? 'Pusat' }}"
                        class="w-full px-3 py-2 border border-gray-200 bg-gray-50 rounded-lg text-xs text-gray-500 focus:outline-none"
                    />
                </div>
            </div>
        </div>

        {{-- Kartu 2: Checklist Penerimaan Barang (GRN) --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <h2 class="text-base font-bold text-gray-900 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-[#15803d]"></span>
                    2. Pilih Penerimaan Barang (Goods Receipt / GRN)
                    <span class="text-xs font-normal text-gray-500">(Wajib minimal 1 dokumen)</span>
                </h2>
                <span class="text-xs font-bold text-[#15803d]" x-show="selectedReceiptIds.length > 0">
                    <span x-text="selectedReceiptIds.length"></span> GRN dipilih
                </span>
            </div>

            {{-- Loading State --}}
            <div x-show="loadingReceipts" class="py-8 text-center text-gray-400 text-xs">
                <svg class="animate-spin h-5 w-5 mx-auto text-[#15803d] mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                Memuat daftar Penerimaan Barang untuk supplier terpilih...
            </div>

            {{-- Empty Supplier State --}}
            <div x-show="!selectedSupplierId && !loadingReceipts" class="py-8 text-center text-gray-400 text-xs">
                <svg class="w-10 h-10 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                Silakan pilih Pemasok terlebih dahulu di atas untuk melihat daftar GRN yang siap ditagihkan.
            </div>

            {{-- Empty Receipts State --}}
            <div x-show="selectedSupplierId && availableReceipts.length === 0 && !loadingReceipts" class="py-8 text-center text-gray-400 text-xs">
                <svg class="w-10 h-10 mx-auto text-amber-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                Tidak ditemukan dokumen Penerimaan Barang (GRN) aktif yang belum difakturkan untuk supplier ini.
            </div>

            {{-- Grid Checklist Receipts --}}
            <div x-show="availableReceipts.length > 0 && !loadingReceipts" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                <template x-for="rcp in availableReceipts" :key="rcp.id">
                    <label
                        class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition select-none"
                        :class="selectedReceiptIds.includes(rcp.id) ? 'border-[#15803d] bg-emerald-50/50 shadow-sm' : 'border-gray-200 hover:bg-gray-50'"
                    >
                        <input
                            type="checkbox"
                            name="purchase_receipts[]"
                            :value="rcp.id"
                            :checked="selectedReceiptIds.includes(rcp.id)"
                            @change="toggleReceipt(rcp.id)"
                            class="mt-0.5 rounded text-[#15803d] focus:ring-[#15803d] border-gray-300"
                        />
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-1">
                                <span class="font-bold text-xs text-gray-900" x-text="rcp.receipt_number"></span>
                                <span class="text-[10px] font-semibold text-emerald-700 bg-emerald-100 px-1.5 py-0.5 rounded" x-text="rcp.status_label"></span>
                            </div>
                            <div class="text-[11px] text-gray-500 mt-1">
                                Tgl. Terima: <span class="font-medium text-gray-700" x-text="rcp.receipt_date"></span>
                            </div>
                            <div class="text-[11px] text-gray-500">
                                PO Terkait: <span class="font-medium text-gray-700" x-text="rcp.po_number"></span>
                            </div>
                            <div class="text-[10px] text-gray-400 mt-1">
                                <span x-text="rcp.items_count"></span> item barang diterima
                            </div>
                        </div>
                    </label>
                </template>
            </div>
            @error('purchase_receipts')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Kartu 3: Baris Item Barang & 3-Way Price Match --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-gray-100 pb-3">
                <div>
                    <h2 class="text-base font-bold text-gray-900 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-[#15803d]"></span>
                        3. Rincian Barang &amp; Pemeriksaan 3-Way Matching
                    </h2>
                    <p class="text-xs text-gray-500 mt-0.5">Kuantitas diisi otomatis dari barang yang diterima. Anda dapat menyesuaikan harga tagihan vendor jika terdapat selisih.</p>
                </div>
            </div>

            {{-- Loading Items --}}
            <div x-show="loadingItems" class="py-8 text-center text-gray-400 text-xs">
                <svg class="animate-spin h-5 w-5 mx-auto text-[#15803d] mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                Mengkalkulasi rincian barang dari penerimaan terpilih...
            </div>

            {{-- Tabel Item Barang --}}
            <div x-show="items.length > 0 && !loadingItems" class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200 text-gray-500 uppercase tracking-wider font-semibold">
                            <th class="py-2.5 px-3">Produk / Barang</th>
                            <th class="py-2.5 px-3 text-right">Qty Terima</th>
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

            {{-- Empty Items Placeholder --}}
            <div x-show="items.length === 0 && !loadingItems" class="py-8 text-center text-gray-400 text-xs">
                Centang minimal satu Penerimaan Barang di atas untuk menampilkan rincian barang.
            </div>
        </div>

        {{-- Kartu 4: Catatan & Ringkasan Finansial --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            {{-- Catatan Faktur --}}
            <div class="lg:col-span-7 bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-3">
                <label class="block text-xs font-semibold text-gray-700">
                    Catatan / Keterangan Tagihan
                </label>
                <textarea
                    name="notes"
                    x-model="notes"
                    rows="4"
                    placeholder="Tuliskan catatan khusus terkait faktur atau termin pembayaran ini..."
                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:border-transparent transition"
                ></textarea>

                <div class="p-3 bg-blue-50 border border-blue-100 rounded-lg text-xs text-blue-700 space-y-1">
                    <div class="font-bold flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Informasi Pembukuan Otomatis (GL &amp; AP)
                    </div>
                    <p class="text-[11px] leading-relaxed">
                        Jika memilih <strong>Simpan &amp; Terbitkan Langsung</strong>, sistem secara otomatis memposting jurnal akuntansi (Debit Persediaan/PPN Masukan, Kredit Hutang Dagang) dan membentuk saldo hutang resmi di modul Accounts Payable.
                    </p>
                </div>
            </div>

            {{-- Ringkasan Finansial --}}
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
                        value="save_and_post"
                        :disabled="items.length === 0"
                        class="w-full py-2.5 px-4 bg-[#15803d] hover:bg-[#14532d] disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-bold rounded-lg shadow-sm transition active:scale-[0.98] flex items-center justify-center gap-2"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Simpan &amp; Terbitkan Langsung (Posting AP)
                    </button>

                    <button
                        type="submit"
                        name="action"
                        value="save_draft"
                        :disabled="items.length === 0"
                        class="w-full py-2 px-4 border border-gray-200 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed text-gray-700 text-xs font-semibold rounded-lg transition active:scale-[0.98]"
                    >
                        Simpan sebagai Draf Saja
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
