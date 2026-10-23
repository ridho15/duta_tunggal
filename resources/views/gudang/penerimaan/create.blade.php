@extends('layouts.app')

@section('title', 'Pencatatan Surat Jalan Masuk (Penerimaan Barang) – Duta Tunggal ERP')

@section('content')
<div class="space-y-6" x-data="goodsReceiptCreate({
    selectedPoId: '{{ $selectedPoId ?? '' }}',
    initialItems: @json($poItemsData ?? []),
    poItemsUrl: '{{ url('gudang/penerimaan-po-items') }}'
})">
    {{-- Header Formulir --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('gudang.penerimaan.index') }}" class="hover:underline">Penerimaan Barang</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Surat Jalan Masuk Baru</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Pencatatan Penerimaan Barang</h1>
            <p class="text-sm text-gray-500 mt-0.5">Catat kedatangan fisik barang dari supplier berdasarkan nomor PO resmi, periksa kuantitas, dan terima ke gudang.</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('gudang.penerimaan.index') }}"
                class="px-4 py-2 text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 border border-gray-300 rounded-lg shadow-sm transition"
            >
                Batal
            </a>
        </div>
    </div>

    {{-- Error Banner --}}
    @if ($errors->any())
        <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-xl shadow-sm">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="space-y-1">
                    <h3 class="text-sm font-bold text-red-800">Terdapat kesalahan pada input formulir:</h3>
                    <ul class="text-xs text-red-700 list-disc list-inside space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    {{-- Form Utama --}}
    <form
        action="{{ route('gudang.penerimaan.store') }}"
        method="POST"
        enctype="multipart/form-data"
        class="space-y-6"
    >
        @csrf
        <input type="hidden" name="action" x-model="formAction">

        {{-- Card 1: Pilihan Pesanan Pembelian (PO) --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="p-1.5 bg-emerald-50 text-[#15803d] rounded-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </span>
                    <div>
                        <h2 class="text-base font-bold text-gray-900">Pesanan Pembelian (Purchase Order)</h2>
                        <p class="text-xs text-gray-500">Pilih dokumen PO supplier yang barang fisiknya sedang diterima.</p>
                    </div>
                </div>
                <div x-show="loadingPo" class="flex items-center gap-2 text-xs text-[#15803d] font-semibold">
                    <svg class="animate-spin w-4 h-4 text-[#15803d]" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span>Memuat rincian PO...</span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- Select PO --}}
                <div class="md:col-span-2">
                    <div @change="selectedPoId = $event.detail; handlePoSelection($event.detail)">
                        <x-searchable-select
                            name="purchase_order_id"
                            label="Pilih Dokumen PO"
                            :options="$purchaseOrders->map(fn($po) => [
                                'id' => $po->id,
                                'label' => $po->po_number,
                                'subtext' => ($po->supplier->perusahaan ?? $po->supplier->name ?? 'Supplier') . ' (' . optional($po->order_date)->format('d/m/Y') . ')'
                            ])"
                            :value="old('purchase_order_id', $selectedPoId)"
                            placeholder="-- Cari atau Pilih Purchase Order yang Disetujui --"
                            :required="true"
                        />
                    </div>
                    <span class="text-[11px] text-gray-400 mt-1 block">Hanya menampilkan PO berstatus Approved atau Sebagian Diterima.</span>
                </div>

                {{-- Status PO Badge / Info --}}
                <div class="bg-gray-50 rounded-lg p-3 border border-gray-200/70 flex flex-col justify-center">
                    <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Supplier Terkait</span>
                    <span class="text-sm font-bold text-gray-900 mt-0.5 block truncate" x-text="poInfo.supplier_name || '{{ $selectedPo->supplier->name ?? '-' }}'"></span>
                    <span class="text-xs text-gray-500 block" x-text="'Gudang Tujuan: ' + (poInfo.warehouse_name || '{{ $selectedPo->warehouse->name ?? '-' }}')"></span>
                </div>
            </div>
        </div>

        {{-- Card 2: Informasi Dokumen Pengiriman & Penerimaan Fisik --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">
            <div class="flex items-center gap-2 border-b border-gray-100 pb-3">
                <span class="p-1.5 bg-emerald-50 text-emerald-600 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </span>
                <div>
                    <h2 class="text-base font-bold text-gray-900">Informasi Kedatangan & Surat Jalan Vendor</h2>
                    <p class="text-xs text-gray-500">Data fisik pengiriman, petugas penerima gudang, dan lampiran foto.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                {{-- Tanggal Terima --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Tanggal Penerimaan <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="date"
                        name="receipt_date"
                        value="{{ old('receipt_date', date('Y-m-d')) }}"
                        class="w-full py-2 px-3 text-sm rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] bg-white"
                        required
                    />
                </div>

                {{-- No. Surat Jalan Vendor --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        No. Surat Jalan Vendor
                    </label>
                    <input
                        type="text"
                        name="supplier_delivery_note_number"
                        value="{{ old('supplier_delivery_note_number') }}"
                        placeholder="Contoh: SJ-VENDOR-9988"
                        class="w-full py-2 px-3 text-sm rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] bg-white font-mono"
                    />
                </div>

                {{-- Petugas Penerima --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Petugas Penerima Gudang <span class="text-red-500">*</span>
                    </label>
                    <select
                        name="received_by"
                        class="w-full py-2 px-3 text-sm rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] bg-white"
                        required
                    >
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" {{ (string)old('received_by', auth()->id()) === (string)$user->id ? 'selected' : '' }}>
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Gudang Penyimpanan Default --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Gudang Penyimpanan
                    </label>
                    <select
                        name="warehouse_id"
                        x-model="defaultWarehouseId"
                        class="w-full py-2 px-3 text-sm rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] bg-white"
                    >
                        @foreach ($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ (string)old('warehouse_id', $selectedPo->warehouse_id ?? '') === (string)$wh->id ? 'selected' : '' }}>
                                {{ $wh->name }} ({{ $wh->kode }})
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Catatan / Keterangan --}}
                <div class="sm:col-span-2 lg:col-span-3">
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Catatan Penerimaan Fisik
                    </label>
                    <textarea
                        name="notes"
                        rows="2"
                        placeholder="Kondisi kemasan saat tiba, armada pengangkut, plat nomor truk, atau catatan khusus lainnya..."
                        class="w-full py-2 px-3 text-sm rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d]"
                    >{{ old('notes') }}</textarea>
                </div>

                {{-- Upload Foto SJ / Barang --}}
                <div class="sm:col-span-2 lg:col-span-1">
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Foto SJ Vendor / Fisik
                    </label>
                    <input
                        type="file"
                        name="photos[]"
                        multiple
                        accept="image/png,image/jpeg,image/webp"
                        class="w-full text-xs text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-[#15803d] hover:file:bg-emerald-100"
                    />
                    <span class="text-[10px] text-gray-400 mt-1 block">JPG/PNG/WebP maks 2MB per foto.</span>
                </div>
            </div>
        </div>

        {{-- Card 3: Tabel Kuantitas Penerimaan Barang --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-gray-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="p-1.5 bg-indigo-50 text-indigo-600 rounded-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                    </span>
                    <div>
                        <h2 class="text-base font-bold text-gray-900">Rincian Barang yang Diterima</h2>
                        <p class="text-xs text-gray-500">Periksa kuantitas fisik datang, tentukan barang yang disetujui (diterima) dan barang ditolak.</p>
                    </div>
                </div>
                <div class="text-xs font-semibold text-gray-500">
                    Total: <span class="text-gray-900 font-bold" x-text="items.length"></span> Baris Barang
                </div>
            </div>

            {{-- Empty State jika belum pilih PO --}}
            <div x-show="items.length === 0" class="py-12 text-center text-gray-400">
                <svg class="w-12 h-12 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <div class="text-sm font-semibold text-gray-700">Belum Ada Item Terpilih</div>
                <p class="text-xs text-gray-400 mt-0.5">Silakan pilih dokumen Purchase Order (PO) di bagian atas untuk memuat daftar barang yang siap diterima.</p>
            </div>

            {{-- Tabel Item --}}
            <div x-show="items.length > 0" class="overflow-x-auto -mx-5 px-5">
                <table class="w-full text-left text-sm text-gray-600">
                    <thead class="bg-gray-50 text-[11px] uppercase font-bold text-gray-500 border-y border-gray-200">
                        <tr>
                            <th class="py-3 px-3 min-w-[220px]">Nama Produk & SKU</th>
                            <th class="py-3 px-3 text-center min-w-[80px]">Qty PO</th>
                            <th class="py-3 px-3 text-center min-w-[90px]">Sdh Terima</th>
                            <th class="py-3 px-3 text-center min-w-[80px]">Sisa PO</th>
                            <th class="py-3 px-3 min-w-[130px]">Qty Tiba Fisik <span class="text-red-500">*</span></th>
                            <th class="py-3 px-3 min-w-[130px]">Qty Diterima (Baik) <span class="text-red-500">*</span></th>
                            <th class="py-3 px-3 min-w-[130px]">Qty Ditolak</th>
                            <th class="py-3 px-3 min-w-[200px]">Gudang & Alasan Tolak</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="(item, idx) in items" :key="item.purchase_order_item_id">
                            <tr class="hover:bg-emerald-50/20 transition">
                                {{-- Produk Info --}}
                                <td class="py-3 px-3">
                                    <input type="hidden" :name="'items[' + idx + '][purchase_order_item_id]'" :value="item.purchase_order_item_id">
                                    <input type="hidden" :name="'items[' + idx + '][product_id]'" :value="item.product_id">
                                    <div class="font-bold text-gray-900 leading-tight" x-text="item.product_name"></div>
                                    <div class="text-xs text-gray-400 font-mono mt-0.5" x-text="'SKU: ' + item.product_sku + ' (' + item.unit_name + ')'"></div>
                                </td>

                                {{-- Qty PO --}}
                                <td class="py-3 px-3 text-center font-semibold text-gray-700 whitespace-nowrap" x-text="item.ordered_qty"></td>

                                {{-- Sudah Diterima --}}
                                <td class="py-3 px-3 text-center text-gray-500 whitespace-nowrap" x-text="item.received_qty"></td>

                                {{-- Sisa PO --}}
                                <td class="py-3 px-3 text-center whitespace-nowrap">
                                    <span class="px-2 py-0.5 bg-emerald-100 text-[#14532d] rounded font-bold text-xs" x-text="item.remaining_received"></span>
                                </td>

                                {{-- Input Qty Tiba Fisik --}}
                                <td class="py-3 px-3">
                                    <input
                                        type="number"
                                        step="any"
                                        min="0.01"
                                        :name="'items[' + idx + '][qty_received]'"
                                        x-model.number="item.qty_received"
                                        @input="handleQtyReceivedChange(idx)"
                                        class="w-full py-1.5 px-2.5 text-sm font-bold text-gray-900 rounded-lg border border-gray-300 focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d]"
                                        :class="{ 'border-red-500 bg-red-50': item.qty_received > item.remaining_received }"
                                        required
                                    />
                                    <span
                                        x-show="item.qty_received > item.remaining_received"
                                        class="text-[10px] text-red-600 font-bold block mt-0.5"
                                    >
                                        Melebihi sisa PO!
                                    </span>
                                </td>

                                {{-- Input Qty Diterima Baik (Accepted) --}}
                                <td class="py-3 px-3">
                                    <input
                                        type="number"
                                        step="any"
                                        min="0"
                                        :name="'items[' + idx + '][qty_accepted]'"
                                        x-model.number="item.qty_accepted"
                                        @input="handleQtyAcceptedChange(idx)"
                                        class="w-full py-1.5 px-2.5 text-sm font-bold text-emerald-700 rounded-lg border border-gray-300 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 bg-emerald-50/20"
                                        required
                                    />
                                </td>

                                {{-- Input Qty Ditolak (Rejected) --}}
                                <td class="py-3 px-3">
                                    <input
                                        type="number"
                                        step="any"
                                        min="0"
                                        :name="'items[' + idx + '][qty_rejected]'"
                                        x-model.number="item.qty_rejected"
                                        @input="handleQtyRejectedChange(idx)"
                                        class="w-full py-1.5 px-2.5 text-sm font-bold text-red-600 rounded-lg border border-gray-300 focus:ring-2 focus:ring-red-500/20 focus:border-red-600 bg-red-50/20"
                                    />
                                </td>

                                {{-- Gudang Penyimpanan & Alasan Tolak --}}
                                <td class="py-3 px-3 space-y-1.5">
                                    <select
                                        :name="'items[' + idx + '][warehouse_id]'"
                                        x-model="item.warehouse_id"
                                        class="w-full py-1.5 px-2 text-xs rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] bg-white"
                                        required
                                    >
                                        @foreach ($warehouses as $wh)
                                            <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->kode }})</option>
                                        @endforeach
                                    </select>

                                    {{-- Input Alasan Ditolak jika ada reject --}}
                                    <div x-show="item.qty_rejected > 0">
                                        <input
                                            type="text"
                                            :name="'items[' + idx + '][reason_rejected]'"
                                            x-model="item.reason_rejected"
                                            placeholder="Alasan tolak (pecah, cacat, expired)..."
                                            class="w-full py-1 px-2 text-xs text-red-700 rounded border border-red-300 focus:outline-none focus:ring-1 focus:ring-red-500 bg-white placeholder-red-300"
                                            :required="item.qty_rejected > 0"
                                        />
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Bottom Actions Bar --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sticky bottom-4 z-10">
            <div class="flex items-center gap-2">
                <a
                    href="{{ route('gudang.penerimaan.index') }}"
                    class="px-4 py-2 text-sm font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition"
                >
                    Kembali
                </a>
                <span class="text-xs text-gray-400 hidden sm:inline">Periksa kuantitas fisik sebelum menyimpan.</span>
            </div>
            <div class="flex items-center gap-2">
                {{-- Simpan Draf --}}
                <button
                    type="submit"
                    @click="formAction = 'save_draft'"
                    class="px-4 py-2 text-sm font-semibold text-gray-800 bg-amber-100 hover:bg-amber-200 rounded-lg transition active:scale-[0.98]"
                >
                    Simpan Draf (Belum Masuk Stok)
                </button>

                {{-- Simpan & Konfirmasi Masuk Stok --}}
                <button
                    type="submit"
                    @click="formAction = 'save_and_confirm'"
                    class="inline-flex items-center gap-2 px-5 py-2 text-sm font-bold text-white bg-[#15803d] hover:bg-[#14532d] rounded-lg shadow-sm transition active:scale-[0.98]"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Simpan & Konfirmasi Masuk Stok
                </button>
            </div>
        </div>
    </form>
</div>

<script>
function goodsReceiptCreate(config) {
    return {
        selectedPoId: config.selectedPoId || '',
        items: config.initialItems || [],
        poItemsUrl: config.poItemsUrl || '',
        loadingPo: false,
        formAction: 'save_draft',
        defaultWarehouseId: '{{ $warehouses->first()?->id ?? 1 }}',
        poInfo: {
            supplier_name: '{{ $selectedPo?->supplier?->name ?? '' }}',
            warehouse_name: '{{ $selectedPo?->warehouse?->name ?? '' }}',
        },

        init() {
            if (this.selectedPoId) {
                this.$nextTick(() => {
                    const el = document.querySelector('select[name="purchase_order_id"]');
                    if (el && this.selectedPoId) {
                        el.value = this.selectedPoId;
                    }
                    if (this.items.length === 0) {
                        this.handlePoSelection(this.selectedPoId);
                    }
                });
            }

            this.$watch('selectedPoId', (newVal, oldVal) => {
                if (newVal !== undefined && newVal !== oldVal && newVal !== '') {
                    this.handlePoSelection(newVal);
                }
            });
        },

        handlePoSelection(poId) {
            if (!poId) {
                this.items = [];
                this.poInfo = { supplier_name: '', warehouse_name: '' };
                return;
            }

            this.loadingPo = true;
            fetch(`${this.poItemsUrl}/${poId}`)
                .then(res => res.json())
                .then(data => {
                    this.loadingPo = false;
                    if (data.success) {
                        this.poInfo = {
                            supplier_name: data.purchase_order.supplier_name,
                            warehouse_name: data.purchase_order.warehouse_name,
                        };
                        this.defaultWarehouseId = data.purchase_order.warehouse_id || this.defaultWarehouseId;
                        
                        this.items = data.items.map(item => ({
                            purchase_order_item_id: item.purchase_order_item_id,
                            product_id: item.product_id,
                            product_name: item.product_name,
                            product_sku: item.product_sku,
                            unit_name: item.unit_name,
                            ordered_qty: item.ordered_qty,
                            received_qty: item.received_qty,
                            accepted_qty: item.accepted_qty,
                            remaining_received: item.remaining_received,
                            remaining_accepted: item.remaining_accepted,
                            qty_received: item.default_qty,
                            qty_accepted: item.default_qty,
                            qty_rejected: 0,
                            reason_rejected: '',
                            warehouse_id: item.warehouse_id || this.defaultWarehouseId,
                            rak_id: null,
                        }));
                    }
                })
                .catch(err => {
                    this.loadingPo = false;
                    console.error('Failed to fetch PO items:', err);
                });
        },

        handleQtyReceivedChange(idx) {
            const item = this.items[idx];
            if (!item) return;

            const received = parseFloat(item.qty_received) || 0;
            const rejected = parseFloat(item.qty_rejected) || 0;
            item.qty_accepted = Math.max(0, received - rejected);
        },

        handleQtyAcceptedChange(idx) {
            const item = this.items[idx];
            if (!item) return;

            const received = parseFloat(item.qty_received) || 0;
            const accepted = parseFloat(item.qty_accepted) || 0;
            if (accepted > received) {
                item.qty_accepted = received;
            }
            item.qty_rejected = Math.max(0, received - item.qty_accepted);
        },

        handleQtyRejectedChange(idx) {
            const item = this.items[idx];
            if (!item) return;

            const received = parseFloat(item.qty_received) || 0;
            const rejected = parseFloat(item.qty_rejected) || 0;
            if (rejected > received) {
                item.qty_rejected = received;
            }
            item.qty_accepted = Math.max(0, received - item.qty_rejected);
        }
    };
}
</script>
@endsection
