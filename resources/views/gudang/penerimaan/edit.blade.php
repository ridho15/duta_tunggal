@extends('layouts.app')

@section('title', 'Edit Draf Penerimaan ' . ($receipt->receipt_number ?: 'GRN-' . $receipt->id) . ' – Duta Tunggal ERP')

@section('content')
<div class="space-y-6" x-data="{
    formAction: 'save_draft',
    items: @json($itemsData ?? []),
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
}">
    {{-- Header Formulir --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('gudang.penerimaan.index') }}" class="hover:underline">Penerimaan Barang</a>
                <span class="text-gray-300">/</span>
                <a href="{{ route('gudang.penerimaan.show', $receipt) }}" class="hover:underline font-mono">{{ $receipt->receipt_number }}</a>
                <span class="text-gray-300">/</span>
                <span class="text-amber-600 font-bold">Edit Draf</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Edit Draf Penerimaan Barang</h1>
            <p class="text-sm text-gray-500 mt-0.5">Koreksi rincian fisik barang, nomor surat jalan, atau kuantitas sebelum resmi masuk stok.</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('gudang.penerimaan.show', $receipt) }}"
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

    {{-- Form Edit --}}
    <form
        action="{{ route('gudang.penerimaan.update', $receipt) }}"
        method="POST"
        enctype="multipart/form-data"
        class="space-y-6"
    >
        @csrf
        @method('PUT')
        <input type="hidden" name="action" x-model="formAction">

        {{-- Card 1: Dokumen PO Terkait (Readonly) --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-3">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <h2 class="text-base font-bold text-gray-900">Pesanan Pembelian Terkait</h2>
                <span class="px-2.5 py-0.5 bg-emerald-50 text-[#15803d] border border-emerald-200 font-bold text-xs rounded-full">
                    No. Dokumen: {{ $receipt->receipt_number }}
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                <div>
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Nomor PO</span>
                    <span class="font-bold text-gray-900 block mt-0.5">{{ $receipt->purchaseOrder->po_number ?? '-' }}</span>
                </div>
                <div>
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Supplier</span>
                    <span class="font-bold text-gray-900 block mt-0.5">{{ $receipt->purchaseOrder->supplier->name ?? '-' }}</span>
                </div>
                <div>
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Status Penerimaan Saat Ini</span>
                    <span class="inline-block mt-0.5 px-2 py-0.5 rounded text-xs font-bold bg-amber-100 text-amber-800 uppercase">
                        {{ $receipt->status }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Card 2: Informasi Dokumen Vendor & Petugas --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">
            <div class="border-b border-gray-100 pb-3">
                <h2 class="text-base font-bold text-gray-900">Informasi Kedatangan & Petugas</h2>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                {{-- Tanggal Penerimaan --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Tanggal Penerimaan <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="date"
                        name="receipt_date"
                        value="{{ old('receipt_date', optional($receipt->receipt_date)->format('Y-m-d')) }}"
                        class="w-full py-2 px-3 text-sm rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 bg-white"
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
                        value="{{ old('supplier_delivery_note_number', $receipt->supplier_delivery_note_number) }}"
                        class="w-full py-2 px-3 text-sm rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 bg-white font-mono"
                    />
                </div>

                {{-- Petugas Penerima --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Petugas Penerima Gudang <span class="text-red-500">*</span>
                    </label>
                    <select
                        name="received_by"
                        class="w-full py-2 px-3 text-sm rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 bg-white"
                        required
                    >
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" {{ (string)old('received_by', $receipt->received_by) === (string)$user->id ? 'selected' : '' }}>
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Upload Foto Baru --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Tambah Foto Lampiran
                    </label>
                    <input
                        type="file"
                        name="photos[]"
                        multiple
                        accept="image/png,image/jpeg,image/webp"
                        class="w-full text-xs text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                    />
                </div>

                {{-- Catatan --}}
                <div class="sm:col-span-2 lg:col-span-4">
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Catatan Penerimaan Fisik
                    </label>
                    <textarea
                        name="notes"
                        rows="2"
                        class="w-full py-2 px-3 text-sm rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600"
                    >{{ old('notes', $receipt->clean_notes) }}</textarea>
                </div>
            </div>
        </div>

        {{-- Card 3: Rincian Kuantitas Barang --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">
            <div class="border-b border-gray-100 pb-3">
                <h2 class="text-base font-bold text-gray-900">Rincian Barang yang Diterima</h2>
                <p class="text-xs text-gray-500">Sesuaikan kuantitas fisik tiba, barang yang diterima baik, dan barang yang ditolak.</p>
            </div>

            <div class="overflow-x-auto -mx-5 px-5">
                <table class="w-full text-left text-sm text-gray-600">
                    <thead class="bg-gray-50 text-[11px] uppercase font-bold text-gray-500 border-y border-gray-200">
                        <tr>
                            <th class="py-3 px-3 min-w-[220px]">Nama Produk & SKU</th>
                            <th class="py-3 px-3 text-center min-w-[80px]">Qty PO</th>
                            <th class="py-3 px-3 text-center min-w-[80px]">Maks Sisa</th>
                            <th class="py-3 px-3 min-w-[130px]">Qty Tiba Fisik <span class="text-red-500">*</span></th>
                            <th class="py-3 px-3 min-w-[130px]">Qty Diterima (Baik) <span class="text-red-500">*</span></th>
                            <th class="py-3 px-3 min-w-[130px]">Qty Ditolak</th>
                            <th class="py-3 px-3 min-w-[200px]">Gudang & Alasan Tolak</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="(item, idx) in items" :key="item.id || item.purchase_order_item_id">
                            <tr class="hover:bg-blue-50/20 transition">
                                {{-- Produk Info --}}
                                <td class="py-3 px-3">
                                    <input type="hidden" :name="'items[' + idx + '][id]'" :value="item.id">
                                    <input type="hidden" :name="'items[' + idx + '][purchase_order_item_id]'" :value="item.purchase_order_item_id">
                                    <input type="hidden" :name="'items[' + idx + '][product_id]'" :value="item.product_id">
                                    <div class="font-bold text-gray-900 leading-tight" x-text="item.product_name"></div>
                                    <div class="text-xs text-gray-400 font-mono mt-0.5" x-text="'SKU: ' + item.product_sku + ' (' + item.unit_name + ')'"></div>
                                </td>

                                {{-- Qty PO --}}
                                <td class="py-3 px-3 text-center font-semibold text-gray-700 whitespace-nowrap" x-text="item.ordered_qty"></td>

                                {{-- Maks Sisa PO --}}
                                <td class="py-3 px-3 text-center whitespace-nowrap">
                                    <span class="px-2 py-0.5 bg-blue-100 text-blue-800 rounded font-bold text-xs" x-text="item.remaining_received"></span>
                                </td>

                                {{-- Qty Tiba Fisik --}}
                                <td class="py-3 px-3">
                                    <input
                                        type="number"
                                        step="any"
                                        min="0.01"
                                        :name="'items[' + idx + '][qty_received]'"
                                        x-model.number="item.qty_received"
                                        @input="handleQtyReceivedChange(idx)"
                                        class="w-full py-1.5 px-2.5 text-sm font-bold text-gray-900 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600"
                                        required
                                    />
                                </td>

                                {{-- Qty Diterima Baik --}}
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

                                {{-- Qty Ditolak --}}
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

                                {{-- Gudang & Alasan Tolak --}}
                                <td class="py-3 px-3 space-y-1.5">
                                    <select
                                        :name="'items[' + idx + '][warehouse_id]'"
                                        x-model="item.warehouse_id"
                                        class="w-full py-1.5 px-2 text-xs rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 bg-white"
                                        required
                                    >
                                        @foreach ($warehouses as $wh)
                                            <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->kode }})</option>
                                        @endforeach
                                    </select>

                                    <div x-show="item.qty_rejected > 0">
                                        <input
                                            type="text"
                                            :name="'items[' + idx + '][reason_rejected]'"
                                            x-model="item.reason_rejected"
                                            placeholder="Alasan penolakan..."
                                            class="w-full py-1 px-2 text-xs text-red-700 rounded border border-red-300 focus:outline-none focus:ring-1 focus:ring-red-500 bg-white"
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
                    href="{{ route('gudang.penerimaan.show', $receipt) }}"
                    class="px-4 py-2 text-sm font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition"
                >
                    Kembali ke Detail
                </a>
            </div>
            <div class="flex items-center gap-2">
                <button
                    type="submit"
                    @click="formAction = 'save_draft'"
                    class="px-4 py-2 text-sm font-semibold text-gray-800 bg-amber-100 hover:bg-amber-200 rounded-lg transition active:scale-[0.98]"
                >
                    Simpan Perubahan Draf
                </button>

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
@endsection
