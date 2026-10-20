@extends('layouts.app', ['title' => 'Ajukan Retur Penjualan'])

@section('content')
<div
    x-data="customerReturnForm({
        initialCustomerId: '{{ $preselectedCustomerId ?? '' }}',
        initialInvoiceId: '{{ $preselectedInvoiceId ?? '' }}',
        invoicesUrl: '{{ url('penjualan/retur/customer-invoices') }}'
    })"
    class="space-y-6 max-w-6xl mx-auto"
>

    {{-- Breadcrumbs & Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-gray-400 mb-1">
                <a href="{{ route('penjualan.returns.index') }}" class="hover:text-gray-600 transition">Retur Penjualan</a>
                <span class="text-gray-300">/</span>
                <span class="text-gray-700 font-semibold">Formulir Pengajuan</span>
            </nav>
            <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Ajukan Retur Penjualan</h1>
            <p class="text-xs text-gray-500 mt-0.5">
                Pengajuan klaim pengembalian barang dari pelanggan atas faktur penjualan (invoice) resmi.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a
                href="{{ route('penjualan.returns.index') }}"
                class="px-4 py-2 rounded-xl border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-bold text-xs shadow-xs transition"
            >
                Batal & Kembali
            </a>
        </div>
    </div>

    {{-- Alert Galat Validasi --}}
    @if($errors->any())
        <div class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-xs shadow-xs">
            <div class="font-bold flex items-center gap-1.5 mb-1 text-sm">
                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <span>Mohon periksa kesalahan pengisian formulir:</span>
            </div>
            <ul class="list-disc list-inside space-y-0.5 text-red-700">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('penjualan.returns.store') }}" class="space-y-6">
        @csrf

        {{-- Kartu 1: Informasi Pelanggan & Dokumen Retur --}}
        <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-xs space-y-5">
            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2 border-b border-gray-100 pb-3">
                <span class="w-6 h-6 rounded-lg bg-emerald-100 text-[#15803d] flex items-center justify-center text-xs font-black">1</span>
                <span>Informasi Pelanggan & Rujukan Faktur</span>
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                {{-- Pilih Pelanggan --}}
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-gray-700">
                        Pelanggan (Customer) <span class="text-red-500">*</span>
                    </label>
                    <select
                        name="customer_id"
                        x-model="customerId"
                        @change="onCustomerChange"
                        required
                        class="w-full text-xs font-semibold py-2.5 px-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d] bg-white"
                    >
                        <option value="">-- Pilih Pelanggan --</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}">
                                {{ $c->name }} {{ $c->phone ? "({$c->phone})" : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Pilih Faktur Penjualan (Invoice) --}}
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-gray-700">
                        Faktur Penjualan (Invoice) <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <select
                            name="invoice_id"
                            x-model="invoiceId"
                            @change="onInvoiceChange"
                            :disabled="isLoadingInvoices || invoices.length === 0"
                            required
                            class="w-full text-xs font-semibold py-2.5 px-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d] bg-white disabled:bg-gray-100 disabled:cursor-not-allowed"
                        >
                            <option value="">-- <span x-text="invoicePlaceholder">Pilih Invoice</span> --</option>
                            <template x-for="inv in invoices" :key="inv.id">
                                <option :value="inv.id" x-text="inv.invoice_number + ' (' + inv.invoice_date + ') — Total: ' + inv.total_formatted"></option>
                            </template>
                        </select>
                        <div x-show="isLoadingInvoices" class="absolute right-3 top-3 pointer-events-none">
                            <svg class="animate-spin h-4 w-4 text-[#15803d]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                {{-- Tanggal Retur --}}
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-gray-700">
                        Tanggal Retur <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="date"
                        name="return_date"
                        x-model="returnDate"
                        required
                        class="w-full text-xs font-semibold py-2.5 px-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d] bg-white"
                    >
                </div>

                {{-- Gudang Penerima Fisik --}}
                <div class="space-y-1.5 md:col-span-1">
                    <label class="block text-xs font-bold text-gray-700">
                        Gudang Penerima Retur
                    </label>
                    <select
                        name="warehouse_id"
                        x-model="warehouseId"
                        class="w-full text-xs font-semibold py-2.5 px-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d] bg-white"
                    >
                        <option value="">-- Pilih Gudang (Opsional saat pengajuan) --</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}">
                                ({{ $wh->kode }}) {{ $wh->name }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-[10px] text-gray-400 mt-1">Gudang tempat barang retur akan diterima & disimpan.</p>
                </div>

                {{-- Alasan Retur --}}
                <div class="space-y-1.5 md:col-span-2">
                    <label class="block text-xs font-bold text-gray-700">
                        Alasan Retur Utama <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        name="reason"
                        x-model="reason"
                        rows="2"
                        required
                        placeholder="Contoh: Barang mengalami kerusakan kemasan saat ekspedisi / spesifikasi produk tidak sesuai pesanan."
                        class="w-full text-xs font-medium py-2 px-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                    ></textarea>
                </div>

                {{-- Catatan Tambahan --}}
                <div class="space-y-1.5 md:col-span-3">
                    <label class="block text-xs font-bold text-gray-700">
                        Catatan Tambahan (Opsional)
                    </label>
                    <input
                        type="text"
                        name="notes"
                        x-model="notes"
                        placeholder="Catatan internal pengajuan..."
                        class="w-full text-xs font-medium py-2 px-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                    >
                </div>
            </div>
        </div>

        {{-- Kartu 2: Pemilihan Barang yang Diretur --}}
        <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-xs space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-gray-100 pb-3">
                <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-emerald-100 text-[#15803d] flex items-center justify-center text-xs font-black">2</span>
                    <span>Pilih Produk yang Dikembalikan</span>
                </h2>

                <div x-show="items.length > 0" class="flex items-center gap-3 text-xs">
                    <button
                        type="button"
                        @click="selectAllItems(true)"
                        class="text-[#15803d] hover:underline font-bold"
                    >
                        Pilih Semua
                    </button>
                    <span class="text-gray-300">|</span>
                    <button
                        type="button"
                        @click="selectAllItems(false)"
                        class="text-gray-500 hover:underline font-medium"
                    >
                        Lepas Pilihan
                    </button>
                </div>
            </div>

            {{-- State Belum Pilih Customer / Invoice --}}
            <div x-show="!customerId" class="py-12 text-center text-gray-400">
                <svg class="w-10 h-10 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
                <p class="text-sm font-bold text-gray-700">Silakan pilih pelanggan terlebih dahulu</p>
                <p class="text-xs text-gray-400 mt-0.5">Daftar faktur penjualan aktif pelanggan akan dimuat otomatis.</p>
            </div>

            <div x-show="customerId && !invoiceId && !isLoadingInvoices" class="py-12 text-center text-gray-400">
                <svg class="w-10 h-10 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <template x-if="invoices.length > 0">
                    <div>
                        <p class="text-sm font-bold text-gray-700">Pilih salah satu Faktur Penjualan di atas</p>
                        <p class="text-xs text-gray-400 mt-0.5">Ditemukan <span class="font-bold text-gray-800" x-text="invoices.length"></span> faktur yang memiliki item dapat diretur.</p>
                    </div>
                </template>
                <template x-if="invoices.length === 0">
                    <div>
                        <p class="text-sm font-bold text-amber-700">Tidak ada faktur yang dapat diretur</p>
                        <p class="text-xs text-gray-400 mt-0.5">Pelanggan ini belum memiliki faktur sah atau semua item telah diretur sebelumnya.</p>
                    </div>
                </template>
            </div>

            {{-- Tabel Item Retur --}}
            <div x-show="items.length > 0" class="overflow-x-auto border border-gray-200 rounded-xl">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 font-bold uppercase tracking-wider">
                        <tr>
                            <th class="py-3 px-3 text-center w-10">Pilih</th>
                            <th class="py-3 px-4">Produk</th>
                            <th class="py-3 px-4 text-center">Qty Faktur</th>
                            <th class="py-3 px-4 text-center">Sudah Diretur</th>
                            <th class="py-3 px-4 text-center">Maks. Retur</th>
                            <th class="py-3 px-4 text-center w-36">Qty Retur</th>
                            <th class="py-3 px-4">Deskripsi Masalah / Kerusakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="(item, index) in items" :key="item.invoice_item_id">
                            <tr
                                :class="item.selected ? 'bg-emerald-50/40' : 'hover:bg-gray-50/50'"
                                class="transition"
                            >
                                {{-- Checkbox Pilih --}}
                                <td class="py-3 px-3 text-center">
                                    <input
                                        type="checkbox"
                                        x-model="item.selected"
                                        :disabled="item.max_returnable <= 0"
                                        class="w-4 h-4 text-[#15803d] rounded border-gray-300 focus:ring-[#15803d] cursor-pointer disabled:cursor-not-allowed"
                                    >
                                    {{-- Hidden Form Inputs when Selected --}}
                                    <input type="hidden" :disabled="!item.selected" :name="'items[' + index + '][invoice_item_id]'" :value="item.invoice_item_id">
                                    <input type="hidden" :disabled="!item.selected" :name="'items[' + index + '][product_id]'" :value="item.product_id">
                                </td>

                                {{-- Nama Produk & SKU --}}
                                <td class="py-3 px-4">
                                    <span class="font-bold text-gray-900 block" x-text="item.product_name"></span>
                                    <span class="text-[10px] text-gray-400 font-mono block mt-0.5" x-text="'SKU: ' + item.product_sku + ' • Satuan: ' + item.uom"></span>
                                </td>

                                {{-- Qty Faktur --}}
                                <td class="py-3 px-4 text-center font-medium text-gray-600" x-text="item.invoiced_qty + ' ' + item.uom"></td>

                                {{-- Sudah Diretur Sebelumnya --}}
                                <td class="py-3 px-4 text-center font-medium text-amber-700" x-text="item.already_returned + ' ' + item.uom"></td>

                                {{-- Maksimal Retur --}}
                                <td class="py-3 px-4 text-center font-extrabold text-blue-700" x-text="item.max_returnable + ' ' + item.uom"></td>

                                {{-- Input Qty Retur --}}
                                <td class="py-3 px-4 text-center">
                                    <div class="relative">
                                        <input
                                            type="number"
                                            step="any"
                                            min="0.01"
                                            :max="item.max_returnable"
                                            x-model.number="item.quantity"
                                            :disabled="!item.selected"
                                            :name="'items[' + index + '][quantity]'"
                                            class="w-full text-center py-1.5 px-2 font-bold text-xs border rounded-lg focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] disabled:bg-gray-100 disabled:text-gray-400 border-gray-300"
                                            placeholder="0"
                                        >
                                    </div>
                                    <span x-show="item.selected && item.quantity > item.max_returnable" class="text-[10px] text-red-600 font-bold block mt-1">
                                        Melebihi maks (<span x-text="item.max_returnable"></span>)
                                    </span>
                                </td>

                                {{-- Deskripsi Masalah --}}
                                <td class="py-3 px-4">
                                    <input
                                        type="text"
                                        x-model="item.problem_description"
                                        :disabled="!item.selected"
                                        :name="'items[' + index + '][problem_description]'"
                                        placeholder="Kondisi barang / alasan retur..."
                                        class="w-full text-xs py-1.5 px-2.5 border border-gray-300 rounded-lg focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] disabled:bg-gray-100 disabled:text-gray-400"
                                    >
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Kartu 3: Ringkasan Pengajuan & Tombol Eksekusi --}}
        <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <span class="text-xs font-bold text-gray-500 uppercase tracking-wider block">Ringkasan Retur</span>
                <div class="flex items-center gap-4 mt-1 text-xs">
                    <span class="text-gray-700">
                        Dipilih: <strong class="text-gray-900" x-text="selectedItemsCount()"></strong> jenis barang
                    </span>
                    <span class="text-gray-300">|</span>
                    <span class="text-gray-700">
                        Total Kuantitas: <strong class="text-gray-900" x-text="totalReturnQty()"></strong> pcs
                    </span>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <a
                    href="{{ route('penjualan.returns.index') }}"
                    class="px-4 py-2.5 rounded-xl border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-bold text-xs transition"
                >
                    Batal
                </a>

                <button
                    type="submit"
                    :disabled="!canSubmit()"
                    class="bg-[#15803d] hover:bg-[#14532d] disabled:bg-gray-300 disabled:cursor-not-allowed text-white px-6 py-2.5 rounded-xl font-bold text-xs shadow-sm transition flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <span>Simpan Pengajuan Retur</span>
                </button>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('customerReturnForm', (config) => ({
        customerId: config.initialCustomerId || '',
        invoiceId: config.initialInvoiceId || '',
        warehouseId: '',
        returnDate: new Date().toISOString().split('T')[0],
        reason: '',
        notes: '',
        invoices: [],
        items: [],
        isLoadingInvoices: false,
        invoicePlaceholder: 'Pilih Invoice',

        init() {
            if (this.customerId) {
                this.loadCustomerInvoices(this.customerId, this.invoiceId);
            }
        },

        async onCustomerChange() {
            this.invoiceId = '';
            this.invoices = [];
            this.items = [];
            if (!this.customerId) return;
            await this.loadCustomerInvoices(this.customerId);
        },

        async loadCustomerInvoices(customerId, autoSelectInvoiceId = null) {
            this.isLoadingInvoices = true;
            this.invoicePlaceholder = 'Memuat faktur...';
            try {
                const res = await fetch(`${config.invoicesUrl}/${customerId}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                this.invoices = data.invoices || [];
                this.invoicePlaceholder = this.invoices.length > 0 ? 'Pilih Invoice' : 'Tidak ada faktur retur';

                if (autoSelectInvoiceId) {
                    const match = this.invoices.find(inv => String(inv.id) === String(autoSelectInvoiceId));
                    if (match) {
                        this.invoiceId = match.id;
                        this.onInvoiceChange();
                    }
                }
            } catch (e) {
                console.error('Gagal memuat invoice customer:', e);
                this.invoicePlaceholder = 'Gagal memuat faktur';
            } finally {
                this.isLoadingInvoices = false;
            }
        },

        onInvoiceChange() {
            this.items = [];
            if (!this.invoiceId) return;

            const selectedInv = this.invoices.find(inv => String(inv.id) === String(this.invoiceId));
            if (selectedInv && selectedInv.items) {
                this.items = selectedInv.items.map(item => ({
                    ...item,
                    selected: false,
                    quantity: item.max_returnable > 0 ? item.max_returnable : 0,
                    problem_description: ''
                }));
            }
        },

        selectAllItems(val) {
            this.items.forEach(item => {
                if (item.max_returnable > 0) {
                    item.selected = val;
                }
            });
        },

        selectedItemsCount() {
            return this.items.filter(i => i.selected).length;
        },

        totalReturnQty() {
            return this.items
                .filter(i => i.selected)
                .reduce((sum, i) => sum + (parseFloat(i.quantity) || 0), 0);
        },

        canSubmit() {
            if (!this.customerId || !this.invoiceId || !this.returnDate || this.reason.trim().length < 5) {
                return false;
            }
            const selected = this.items.filter(i => i.selected);
            if (selected.length === 0) return false;

            // Pastikan qty > 0 dan tidak melebihi max_returnable
            for (const item of selected) {
                const qty = parseFloat(item.quantity) || 0;
                if (qty <= 0 || qty > item.max_returnable) {
                    return false;
                }
            }
            return true;
        }
    }));
});
</script>
@endsection
