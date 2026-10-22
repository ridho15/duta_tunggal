@extends('layouts.app')

@section('title', 'Ubah Permintaan Pembelian (' . $orderRequest->request_number . ') – Duta Tunggal ERP')

@section('content')
<div 
    class="space-y-6 max-w-7xl mx-auto"
    x-data="orderRequestForm({
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
            'code' => $s->code ?? 'SUP'
        ])) }},
        oldItems: {{ Js::from(old('items', $orderRequest->orderRequestItem->map(fn($item) => [
            'product_id' => $item->product_id,
            'supplier_id' => $item->supplier_id,
            'quantity' => (float)$item->quantity,
            'unit_price' => (float)$item->unit_price,
            'note' => $item->note,
        ]))) }}
    })"
>
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('pembelian.or.index') }}" class="hover:text-[#15803d] transition">Permintaan Barang (OR)</a>
                <span class="text-gray-300">/</span>
                <a href="{{ route('pembelian.or.show', $orderRequest) }}" class="hover:text-[#15803d] transition">{{ $orderRequest->request_number }}</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Ubah</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Ubah Permintaan Pembelian</h1>
            <p class="text-sm text-gray-500 mt-0.5">Perbarui rincian permohonan belanja barang sebelum diajukan kembali ke atasan.</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('pembelian.or.show', $orderRequest) }}"
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

    {{-- Form Edit OR --}}
    <form action="{{ route('pembelian.or.update', $orderRequest) }}" method="POST" id="orEditForm" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Card 1: Informasi Header Permintaan --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
            <div class="border-b border-gray-100 pb-3 mb-5">
                <h2 class="text-base font-bold text-gray-900">1. Informasi Permintaan</h2>
                <p class="text-xs text-gray-500">Nomor dokumen, cabang pemohon, dan tanggal kebutuhan.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- Nomor Request --}}
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1">
                        Nomor Request
                    </label>
                    <input
                        type="text"
                        name="request_number"
                        value="{{ $orderRequest->request_number }}"
                        readonly
                        class="w-full py-2 px-3 border border-gray-300 rounded-lg text-sm bg-gray-50 text-gray-600 focus:ring-0"
                    />
                </div>

                {{-- Tanggal Request --}}
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1">
                        Tanggal Permintaan <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="date"
                        name="request_date"
                        value="{{ old('request_date', $orderRequest->request_date ? $orderRequest->request_date->format('Y-m-d') : date('Y-m-d')) }}"
                        required
                        class="w-full py-2 px-3 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                    />
                </div>

                {{-- Tanggal Dibutuhkan --}}
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1">
                        Tanggal Dibutuhkan
                    </label>
                    <input
                        type="date"
                        name="required_date"
                        value="{{ old('required_date', $orderRequest->required_date ? $orderRequest->required_date->format('Y-m-d') : '') }}"
                        class="w-full py-2 px-3 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                    />
                </div>

                {{-- Cabang Pemohon --}}
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1">
                        Cabang Pemohon <span class="text-red-500">*</span>
                    </label>
                    <select
                        name="cabang_id"
                        required
                        class="w-full py-2 px-3 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                    >
                        @foreach ($cabangs as $c)
                            <option value="{{ $c->id }}" {{ old('cabang_id', $orderRequest->cabang_id) == $c->id ? 'selected' : '' }}>
                                {{ $c->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Keperluan / Divisi --}}
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1">
                        Keperluan / Divisi Peminta
                    </label>
                    <input
                        type="text"
                        name="purpose"
                        value="{{ old('purpose', $orderRequest->purpose) }}"
                        placeholder="Contoh: Stok Menipis Cabang / Kebutuhan Proyek Renovasi"
                        class="w-full py-2 px-3 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                    />
                </div>

                {{-- Catatan Tambahan --}}
                <div class="md:col-span-3">
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1">
                        Catatan Operasional (Opsional)
                    </label>
                    <textarea
                        name="note"
                        rows="2"
                        class="w-full py-2 px-3 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                    >{{ old('note', $orderRequest->note) }}</textarea>
                </div>
            </div>
        </div>

        {{-- Card 2: Rincian Barang yang Diminta --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-4">
            <div class="border-b border-gray-100 pb-3 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-gray-900">2. Daftar Barang yang Diminta</h2>
                    <p class="text-xs text-gray-500">Sesuaikan pilihan produk, kuantitas, atau harga satuan.</p>
                </div>
                <button
                    type="button"
                    @click="addRow()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-300 text-xs font-bold rounded-lg transition"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Tambah Baris Barang
                </button>
            </div>

            {{-- Table Repeater --}}
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200 text-gray-600 font-bold uppercase tracking-wider">
                            <th class="py-2.5 px-3 w-10 text-center">#</th>
                            <th class="py-2.5 px-3 min-w-[240px]">Produk <span class="text-red-500">*</span></th>
                            <th class="py-2.5 px-3 w-24">Satuan</th>
                            <th class="py-2.5 px-3 w-28 text-right">Kuantitas <span class="text-red-500">*</span></th>
                            <th class="py-2.5 px-3 w-36 text-right">Estimasi Harga</th>
                            <th class="py-2.5 px-3 min-w-[180px]">Supplier Rekomendasi</th>
                            <th class="py-2.5 px-3 min-w-[160px]">Catatan / Spek</th>
                            <th class="py-2.5 px-3 w-32 text-right">Subtotal</th>
                            <th class="py-2.5 px-2 w-10 text-center"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="(item, index) in items" :key="index">
                            <tr class="hover:bg-gray-50/50">
                                {{-- Index --}}
                                <td class="py-2.5 px-3 text-center text-gray-400 font-mono" x-text="index + 1"></td>

                                {{-- Produk --}}
                                <td class="py-2.5 px-3">
                                    <select
                                        :name="'items[' + index + '][product_id]'"
                                        x-model="item.productId"
                                        @change="onProductSelect(index)"
                                        required
                                        class="w-full py-1.5 px-2 border border-gray-300 rounded-lg text-xs focus:ring-1 focus:ring-[#15803d]"
                                    >
                                        <option value="">-- Pilih Produk --</option>
                                        @foreach ($products as $p)
                                            <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku ?? '-' }})</option>
                                        @endforeach
                                    </select>
                                </td>

                                {{-- Satuan --}}
                                <td class="py-2.5 px-3 text-gray-600 font-medium" x-text="item.uom || '-'"></td>

                                {{-- Kuantitas --}}
                                <td class="py-2.5 px-3 text-right">
                                    <input
                                        type="number"
                                        :name="'items[' + index + '][quantity]'"
                                        x-model.number="item.quantity"
                                        min="0.01"
                                        step="any"
                                        required
                                        class="w-full py-1.5 px-2 border border-gray-300 rounded-lg text-xs text-right focus:ring-1 focus:ring-[#15803d]"
                                    />
                                </td>

                                {{-- Estimasi Harga Satuan --}}
                                <td class="py-2.5 px-3 text-right">
                                    <input
                                        type="number"
                                        :name="'items[' + index + '][unit_price]'"
                                        x-model.number="item.unitPrice"
                                        min="0"
                                        step="any"
                                        class="w-full py-1.5 px-2 border border-gray-300 rounded-lg text-xs text-right focus:ring-1 focus:ring-[#15803d]"
                                    />
                                </td>

                                {{-- Supplier Rekomendasi --}}
                                <td class="py-2.5 px-3">
                                    <select
                                        :name="'items[' + index + '][supplier_id]'"
                                        x-model="item.supplierId"
                                        class="w-full py-1.5 px-2 border border-gray-300 rounded-lg text-xs focus:ring-1 focus:ring-[#15803d]"
                                    >
                                        <option value="">-- Bebas / Rekomendasi --</option>
                                        @foreach ($suppliers as $s)
                                            <option value="{{ $s->id }}">{{ $s->perusahaan }}</option>
                                        @endforeach
                                    </select>
                                </td>

                                {{-- Catatan Item --}}
                                <td class="py-2.5 px-3">
                                    <input
                                        type="text"
                                        :name="'items[' + index + '][note]'"
                                        x-model="item.note"
                                        class="w-full py-1.5 px-2 border border-gray-300 rounded-lg text-xs focus:ring-1 focus:ring-[#15803d]"
                                    />
                                </td>

                                {{-- Subtotal --}}
                                <td class="py-2.5 px-3 text-right font-medium text-gray-800" x-text="formatRupiah((item.quantity || 0) * (item.unitPrice || 0))"></td>

                                {{-- Hapus Baris --}}
                                <td class="py-2.5 px-2 text-center">
                                    <button
                                        type="button"
                                        @click="removeRow(index)"
                                        :disabled="items.length <= 1"
                                        class="p-1 rounded text-gray-400 hover:text-red-600 disabled:opacity-30 disabled:hover:text-gray-400 transition"
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

            {{-- Summary Bar --}}
            <div class="p-4 bg-gray-50 rounded-xl border border-gray-200 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="text-xs text-gray-500">
                    Total: <span class="font-bold text-gray-800" x-text="items.length + ' item barang'"></span>
                    &bull; Kuantitas: <span class="font-bold text-gray-800" x-text="totalQuantity()"></span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs font-bold uppercase text-gray-500">Perkiraan Nilai Pengadaan:</span>
                    <span class="text-base font-extrabold text-[#15803d]" x-text="formatRupiah(totalEstimatedAmount())"></span>
                </div>
            </div>
        </div>

        {{-- Card 3: Tombol Aksi Simpan --}}
        <div class="flex items-center justify-end gap-3 pt-2">
            <a
                href="{{ route('pembelian.or.show', $orderRequest) }}"
                class="px-5 py-2.5 border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 text-sm font-semibold rounded-lg shadow-sm transition active:scale-[0.98]"
            >
                Batal
            </a>

            <button
                type="submit"
                name="action"
                value="draft"
                class="px-5 py-2.5 border border-gray-300 bg-white hover:bg-gray-50 text-gray-800 text-sm font-semibold rounded-lg shadow-sm transition active:scale-[0.98]"
            >
                Simpan Perubahan (Draf)
            </button>

            <button
                type="submit"
                name="action"
                value="request_approve"
                class="px-5 py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-sm font-semibold rounded-lg shadow-sm transition active:scale-[0.98]"
            >
                Simpan & Ajukan Persetujuan
            </button>
        </div>
    </form>
</div>

<script>
function orderRequestForm(config) {
    return {
        products: config.products || [],
        suppliers: config.suppliers || [],
        items: [],

        init() {
            if (config.oldItems && config.oldItems.length > 0) {
                this.items = config.oldItems.map(item => ({
                    productId: item.product_id || '',
                    supplierId: item.supplier_id || '',
                    quantity: parseFloat(item.quantity) || 1,
                    unitPrice: parseFloat(item.unit_price) || 0,
                    note: item.note || '',
                    uom: this.findProductUom(item.product_id),
                    sku: this.findProductSku(item.product_id)
                }));
            } else {
                this.addRow();
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
                supplierId: '',
                quantity: 1,
                unitPrice: 0,
                note: '',
                uom: 'Pcs',
                sku: '-'
            });
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
            return this.items.reduce((sum, item) => sum + ((parseFloat(item.quantity) || 0) * (parseFloat(item.unitPrice) || 0)), 0);
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
