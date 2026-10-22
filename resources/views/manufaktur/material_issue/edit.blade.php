@extends('layouts.app')

@section('title', 'Edit Pengeluaran / Retur Bahan Baku – Duta Tunggal ERP')

@section('content')
<div class="space-y-6 max-w-5xl mx-auto pb-16">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('manufaktur.material-issue.index') }}" class="hover:text-[#15803d]">Pengeluaran Bahan</a>
                <span class="text-gray-300">/</span>
                <a href="{{ route('manufaktur.material-issue.show', $materialIssue) }}" class="hover:text-[#15803d] font-mono">{{ $materialIssue->issue_number }}</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Edit Draft</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Edit Dokumen Pengeluaran / Retur Bahan</h1>
            <p class="text-sm text-gray-500 mt-0.5">Penyesuaian kuantitas atau komponen bahan baku sebelum diajukan ke Gudang.</p>
        </div>
        <a
            href="{{ route('manufaktur.material-issue.show', $materialIssue) }}"
            class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 shadow-sm transition"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Kembali
        </a>
    </div>

    {{-- Alert Error --}}
    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm shadow-sm">
            <div class="flex items-center gap-2 font-semibold mb-1">
                <svg class="w-5 h-5 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Mohon periksa kesalahan input berikut:</span>
            </div>
            <ul class="list-disc pl-7 text-xs space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Form --}}
    <form id="materialIssueEditForm" action="{{ route('manufaktur.material-issue.update', $materialIssue) }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Section 1: Informasi Dokumen --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5 sm:p-6 shadow-sm space-y-5">
            <h2 class="text-base font-bold text-gray-900 border-b border-gray-100 pb-3 flex items-center gap-2">
                <span class="w-6 h-6 rounded-lg bg-emerald-50 text-[#15803d] flex items-center justify-center text-xs font-extrabold">1</span>
                Informasi Dokumen
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                {{-- Tipe Transaksi --}}
                <div class="sm:col-span-2 lg:col-span-1">
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">
                        Tipe Dokumen <span class="text-rose-500">*</span>
                    </label>
                    <div class="grid grid-cols-2 gap-2 p-1 bg-gray-100 rounded-xl">
                        <label class="flex items-center justify-center gap-2 py-2 px-3 rounded-lg text-xs font-bold cursor-pointer transition has-[:checked]:bg-white has-[:checked]:text-emerald-700 has-[:checked]:shadow-sm text-gray-600">
                            <input
                                type="radio"
                                name="type"
                                value="issue"
                                class="hidden"
                                {{ old('type', $materialIssue->type) === 'issue' ? 'checked' : '' }}
                            >
                            <span>Ambil Bahan</span>
                        </label>
                        <label class="flex items-center justify-center gap-2 py-2 px-3 rounded-lg text-xs font-bold cursor-pointer transition has-[:checked]:bg-white has-[:checked]:text-amber-700 has-[:checked]:shadow-sm text-gray-600">
                            <input
                                type="radio"
                                name="type"
                                value="return"
                                class="hidden"
                                {{ old('type', $materialIssue->type) === 'return' ? 'checked' : '' }}
                            >
                            <span>Retur Sisa Bahan</span>
                        </label>
                    </div>
                </div>

                {{-- Nomor Bukti --}}
                <div>
                    <label for="issue_number" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">
                        Nomor Bukti <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="issue_number"
                        id="issue_number"
                        value="{{ old('issue_number', $materialIssue->issue_number) }}"
                        required
                        class="w-full py-2 px-3 text-xs sm:text-sm font-mono border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d]"
                    >
                </div>

                {{-- Tanggal Transaksi --}}
                <div>
                    <label for="issue_date" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">
                        Tanggal <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="date"
                        name="issue_date"
                        id="issue_date"
                        value="{{ old('issue_date', $materialIssue->issue_date?->format('Y-m-d')) }}"
                        required
                        max="{{ now()->format('Y-m-d') }}"
                        class="w-full py-2 px-3 text-xs sm:text-sm border border-gray-200 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d]"
                    >
                </div>

                {{-- Gudang Utama --}}
                <div>
                    <label for="warehouse_id" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">
                        Gudang Pengeluaran/Penerimaan <span class="text-rose-500">*</span>
                    </label>
                    <select
                        name="warehouse_id"
                        id="warehouse_id"
                        required
                        class="w-full py-2 px-3 text-xs sm:text-sm border border-gray-200 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d]"
                    >
                        <option value="">-- Pilih Gudang --</option>
                        @foreach ($warehouses as $wh)
                            <option
                                value="{{ $wh->id }}"
                                {{ old('warehouse_id', $materialIssue->warehouse_id) == $wh->id ? 'selected' : '' }}
                            >
                                ({{ $wh->kode }}) {{ $wh->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Pilihan SPK (Manufacturing Order) --}}
                <div class="sm:col-span-2">
                    <label for="manufacturing_order_id" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">
                        Perintah Kerja (SPK / MO)
                    </label>
                    <select
                        name="manufacturing_order_id"
                        id="manufacturing_order_id"
                        class="w-full py-2 px-3 text-xs sm:text-sm border border-gray-200 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d]"
                    >
                        <option value="">-- Pilih SPK Terkait (Opsional) --</option>
                        @foreach ($manufacturingOrders as $mo)
                            @php
                                $pp = $mo->productionPlan;
                                $productName = $pp?->product?->name ?? 'Produk';
                                $targetQty = $pp?->quantity ?? $mo->quantity;
                                $uomName = $pp?->product?->uom?->name ?? 'Unit';
                            @endphp
                            <option
                                value="{{ $mo->id }}"
                                {{ old('manufacturing_order_id', $materialIssue->manufacturing_order_id) == $mo->id ? 'selected' : '' }}
                            >
                                {{ $mo->mo_number }} – {{ $productName }} (Target: {{ number_format($targetQty, 0, ',', '.') }} {{ $uomName }})
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Catatan Umum --}}
                <div class="sm:col-span-2 lg:col-span-3">
                    <label for="notes" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">
                        Catatan Transaksi
                    </label>
                    <textarea
                        name="notes"
                        id="notes"
                        rows="2"
                        class="w-full py-2 px-3 text-xs sm:text-sm border border-gray-200 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d]"
                    >{{ old('notes', $materialIssue->notes) }}</textarea>
                </div>
            </div>
        </div>

        {{-- Section 2: Daftar Item Bahan Baku --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5 sm:p-6 shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-gray-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-emerald-50 text-[#15803d] flex items-center justify-center text-xs font-extrabold">2</span>
                    <div>
                        <h2 class="text-base font-bold text-gray-900">Rincian Bahan Baku</h2>
                        <p class="text-xs text-gray-500">Sesuaikan kuantitas atau harga pokok material jika diperlukan.</p>
                    </div>
                </div>
                <button
                    type="button"
                    id="btnAddItemRow"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-800 text-xs font-semibold rounded-lg transition active:scale-[0.98]"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Tambah Bahan
                </button>
            </div>

            {{-- Table Repeater --}}
            <div class="overflow-x-auto border border-gray-200 rounded-xl">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-gray-50 text-gray-600 font-semibold uppercase tracking-wider text-[10.5px]">
                            <th class="py-2.5 px-3">Bahan Baku (Produk)</th>
                            <th class="py-2.5 px-3 w-28">Satuan</th>
                            <th class="py-2.5 px-3 w-32">Kuantitas</th>
                            <th class="py-2.5 px-3 w-36">Harga Pokok (Rp)</th>
                            <th class="py-2.5 px-3 w-36 text-right">Subtotal</th>
                            <th class="py-2.5 px-2 text-center w-12">Hapus</th>
                        </tr>
                    </thead>
                    <tbody id="itemsTableBody" class="divide-y divide-gray-100">
                        {{-- Baris dimuat via JS --}}
                    </tbody>
                </table>
            </div>

            {{-- Empty State Row --}}
            <div id="emptyItemsNotice" class="text-center py-8 text-gray-400 bg-gray-50/50 rounded-xl border border-dashed border-gray-200 hidden">
                <svg class="w-8 h-8 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
                <p class="text-xs font-semibold text-gray-600">Belum ada bahan baku dalam daftar</p>
            </div>
        </div>

        {{-- Sticky Footer Summary & Actions --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-4 sm:p-5 shadow-lg flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 sticky bottom-4 z-10">
            <div class="flex items-center gap-6">
                <div>
                    <span class="text-[11px] text-gray-400 uppercase tracking-wider block font-semibold">Total Item</span>
                    <span id="displayTotalRows" class="text-lg font-bold text-gray-800">0 baris</span>
                </div>
                <div class="h-8 w-px bg-gray-200"></div>
                <div>
                    <span class="text-[11px] text-gray-400 uppercase tracking-wider block font-semibold">Total Nilai BDP</span>
                    <span id="displayTotalCost" class="text-xl font-extrabold text-[#15803d]">Rp 0</span>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a
                    href="{{ route('manufaktur.material-issue.show', $materialIssue) }}"
                    class="px-4 py-2 text-xs font-semibold text-gray-600 hover:text-gray-900 transition"
                >
                    Batal
                </a>
                <button
                    type="submit"
                    name="action"
                    value="save_draft"
                    class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-800 text-xs font-semibold rounded-xl transition active:scale-[0.98]"
                >
                    Simpan Perubahan
                </button>
                <button
                    type="submit"
                    name="action"
                    value="submit_approval"
                    class="px-5 py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-xs font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
                >
                    Simpan & Ajukan ke Gudang
                </button>
            </div>
        </div>
    </form>
</div>

<script>
    const RAW_PRODUCTS = {!! json_encode($rawMaterials->map(function($p) {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku,
            'uom_id' => $p->uom_id,
            'uom_name' => $p->uom ? $p->uom->name : 'Pcs',
            'cost_price' => (float)($p->cost_price ?? 0),
        ];
    })->toArray()) !!};

    const EXISTING_ITEMS = {!! json_encode($materialIssue->items->map(function($item) {
        return [
            'product_id' => $item->product_id,
            'uom_id' => $item->uom_id,
            'uom_name' => $item->uom ? $item->uom->name : ($item->product && $item->product->uom ? $item->product->uom->name : 'Pcs'),
            'quantity' => (float)$item->quantity,
            'cost_per_unit' => (float)$item->cost_per_unit,
        ];
    })->toArray()) !!};

    let rowIndex = 0;

    function createRowHtml(item = {}) {
        const idx = rowIndex++;
        const productId = item.product_id || '';
        const uomId = item.uom_id || '';
        const uomName = item.uom_name || '';
        const quantity = item.quantity || 1;
        const costPerUnit = item.cost_per_unit !== undefined ? item.cost_per_unit : 0;
        const subtotal = quantity * costPerUnit;

        let productOptions = '<option value="">-- Pilih Produk --</option>';
        RAW_PRODUCTS.forEach(p => {
            const isSel = String(p.id) === String(productId) ? 'selected' : '';
            productOptions += `<option value="${p.id}" data-uom-id="${p.uom_id}" data-uom-name="${p.uom_name}" data-cost="${p.cost_price}" ${isSel}>(${p.sku}) ${p.name}</option>`;
        });

        return `
            <tr id="row-${idx}" class="hover:bg-gray-50/75 transition item-row">
                <td class="py-2 px-3">
                    <select
                        name="items[${idx}][product_id]"
                        class="w-full py-1.5 px-2 text-xs border border-gray-200 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-[#15803d]"
                        required
                        onchange="handleProductSelect(${idx}, this)"
                    >
                        ${productOptions}
                    </select>
                </td>
                <td class="py-2 px-3">
                    <input type="hidden" name="items[${idx}][uom_id]" id="uom_id_${idx}" value="${uomId}">
                    <span id="uom_display_${idx}" class="text-gray-700 font-medium">${uomName || '-'}</span>
                </td>
                <td class="py-2 px-3">
                    <input
                        type="number"
                        step="0.0001"
                        min="0.0001"
                        name="items[${idx}][quantity]"
                        id="qty_${idx}"
                        value="${quantity}"
                        required
                        class="w-full py-1.5 px-2 text-xs border border-gray-200 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-[#15803d] qty-input font-mono"
                        oninput="recalculateRow(${idx})"
                    >
                </td>
                <td class="py-2 px-3">
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="items[${idx}][cost_per_unit]"
                        id="cost_${idx}"
                        value="${costPerUnit}"
                        class="w-full py-1.5 px-2 text-xs border border-gray-200 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-[#15803d] cost-input font-mono"
                        oninput="recalculateRow(${idx})"
                    >
                </td>
                <td class="py-2 px-3 text-right font-mono font-bold text-gray-800" id="subtotal_${idx}">
                    Rp ${formatRupiah(subtotal)}
                </td>
                <td class="py-2 px-2 text-center">
                    <button
                        type="button"
                        onclick="removeRow(${idx})"
                        class="p-1 text-gray-400 hover:text-rose-600 rounded hover:bg-gray-100 transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </td>
            </tr>
        `;
    }

    function addRow(itemData = {}) {
        const tbody = document.getElementById('itemsTableBody');
        tbody.insertAdjacentHTML('beforeend', createRowHtml(itemData));
        document.getElementById('emptyItemsNotice').classList.add('hidden');
        recalculateTotals();
    }

    function removeRow(idx) {
        const row = document.getElementById(`row-${idx}`);
        if (row) {
            row.remove();
        }
        const remaining = document.querySelectorAll('.item-row');
        if (remaining.length === 0) {
            document.getElementById('emptyItemsNotice').classList.remove('hidden');
        }
        recalculateTotals();
    }

    function handleProductSelect(idx, selectEl) {
        const opt = selectEl.options[selectEl.selectedIndex];
        if (!opt || !opt.value) return;

        const uomId = opt.getAttribute('data-uom-id');
        const uomName = opt.getAttribute('data-uom-name');
        const cost = parseFloat(opt.getAttribute('data-cost')) || 0;

        document.getElementById(`uom_id_${idx}`).value = uomId;
        document.getElementById(`uom_display_${idx}`).innerText = uomName;
        document.getElementById(`cost_${idx}`).value = cost;

        recalculateRow(idx);
    }

    function recalculateRow(idx) {
        const qtyEl = document.getElementById(`qty_${idx}`);
        const costEl = document.getElementById(`cost_${idx}`);
        if (!qtyEl || !costEl) return;

        const qty = parseFloat(qtyEl.value) || 0;
        const cost = parseFloat(costEl.value) || 0;
        const subtotal = qty * cost;

        const subtotalEl = document.getElementById(`subtotal_${idx}`);
        if (subtotalEl) {
            subtotalEl.innerText = 'Rp ' + formatRupiah(subtotal);
        }

        recalculateTotals();
    }

    function recalculateTotals() {
        let totalCost = 0;
        let count = 0;

        document.querySelectorAll('.item-row').forEach(row => {
            count++;
            const qty = parseFloat(row.querySelector('.qty-input')?.value) || 0;
            const cost = parseFloat(row.querySelector('.cost-input')?.value) || 0;
            totalCost += (qty * cost);
        });

        document.getElementById('displayTotalRows').innerText = `${count} baris`;
        document.getElementById('displayTotalCost').innerText = 'Rp ' + formatRupiah(totalCost);
    }

    function formatRupiah(number) {
        return new Intl.NumberFormat('id-ID').format(Math.round(number));
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('btnAddItemRow').addEventListener('click', function () {
            addRow();
        });

        // Load existing items
        if (EXISTING_ITEMS && EXISTING_ITEMS.length > 0) {
            EXISTING_ITEMS.forEach(item => {
                addRow(item);
            });
        } else {
            addRow();
        }
    });
</script>
@endsection
