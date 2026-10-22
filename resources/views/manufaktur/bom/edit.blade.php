@extends('layouts.app')

@section('title', 'Ubah Resep Produksi: ' . $billOfMaterial->code . ' – Duta Tunggal ERP')

@section('content')
<div class="space-y-6 max-w-6xl mx-auto pb-12">
    {{-- Header & Tombol Navigasi --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('manufaktur.bom.index') }}" class="hover:text-gray-700">Resep BOM</a>
                <span class="text-gray-300">/</span>
                <a href="{{ route('manufaktur.bom.show', $billOfMaterial) }}" class="hover:text-gray-700">{{ $billOfMaterial->code }}</a>
                <span class="text-gray-300">/</span>
                <span class="text-amber-600 font-bold">Ubah Resep</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Ubah Resep BOM: {{ $billOfMaterial->code }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">Perbarui komponen bahan baku, kuantitas target, atau rincian biaya TKL dan overhead.</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('manufaktur.bom.show', $billOfMaterial) }}"
                class="px-4 py-2 border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-semibold rounded-xl transition"
            >
                Batal
            </a>
            <button
                type="submit"
                form="bomEditForm"
                class="inline-flex items-center gap-2 px-5 py-2 bg-[#15803d] hover:bg-[#166534] text-white text-sm font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Perbarui Resep BOM
            </button>
        </div>
    </div>

    {{-- Error Alert --}}
    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm shadow-sm space-y-1">
            <div class="font-bold flex items-center gap-2">
                <svg class="w-5 h-5 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Terdapat kesalahan pengisian data:
            </div>
            <ul class="list-disc list-inside text-xs pl-7 space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="bomEditForm" method="POST" action="{{ route('manufaktur.bom.update', $billOfMaterial) }}">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Kolom Kiri: Informasi Produk Jadi & Komponen Bahan --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Kartu 1: Info Produk Jadi --}}
                <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm space-y-4">
                    <div class="border-b border-gray-100 pb-3 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-[#15803d] flex items-center justify-center font-bold text-sm">
                                1
                            </div>
                            <h2 class="font-bold text-gray-900 text-base">Identitas Resep & Produk Jadi</h2>
                        </div>
                        <label class="flex items-center gap-2 text-xs font-semibold text-gray-700 cursor-pointer">
                            <input
                                type="checkbox"
                                name="is_active"
                                value="1"
                                {{ old('is_active', $billOfMaterial->is_active ? '1' : '0') == '1' ? 'checked' : '' }}
                                class="rounded border-gray-300 text-[#15803d] focus:ring-[#15803d]"
                            >
                            <span>Status Resep Aktif</span>
                        </label>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Kode BOM --}}
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                Kode BOM <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="text"
                                name="code"
                                id="bomCode"
                                value="{{ old('code', $billOfMaterial->code) }}"
                                required
                                class="w-full text-xs rounded-xl border-gray-300 focus:ring-[#15803d] focus:border-[#15803d] font-mono uppercase"
                            >
                        </div>

                        {{-- Nama Resep BOM --}}
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                Nama Formula / Resep <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="text"
                                name="nama_bom"
                                value="{{ old('nama_bom', $billOfMaterial->nama_bom) }}"
                                required
                                class="w-full text-xs rounded-xl border-gray-300 focus:ring-[#15803d] focus:border-[#15803d]"
                            >
                        </div>

                        {{-- Produk Jadi --}}
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                Produk Jadi (Output) <span class="text-rose-500">*</span>
                            </label>
                            <select
                                name="product_id"
                                id="finishedProductSelect"
                                required
                                onchange="onFinishedProductChange(this)"
                                class="w-full text-xs rounded-xl border-gray-300 focus:ring-[#15803d] focus:border-[#15803d]"
                            >
                                @foreach ($manufacturedProducts as $p)
                                    <option
                                        value="{{ $p->id }}"
                                        data-uom-id="{{ $p->uom_id }}"
                                        data-sku="{{ $p->sku }}"
                                        {{ old('product_id', $billOfMaterial->product_id) == $p->id ? 'selected' : '' }}
                                    >
                                        ({{ $p->sku }}) {{ $p->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Cabang --}}
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                Cabang / Pabrik <span class="text-rose-500">*</span>
                            </label>
                            <select
                                name="cabang_id"
                                required
                                class="w-full text-xs rounded-xl border-gray-300 focus:ring-[#15803d] focus:border-[#15803d]"
                            >
                                @foreach ($cabangs as $cb)
                                    <option value="{{ $cb->id }}" {{ old('cabang_id', $billOfMaterial->cabang_id) == $cb->id ? 'selected' : '' }}>
                                        ({{ $cb->kode }}) {{ $cb->nama }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Hasil Jadi (Qty Output) & Satuan --}}
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                Kuantitas Hasil Jadi <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="number"
                                step="any"
                                min="0.0001"
                                name="quantity"
                                id="finishedQuantity"
                                value="{{ old('quantity', rtrim(rtrim((string) $billOfMaterial->quantity, '0'), '.')) }}"
                                required
                                oninput="recalculateTotals()"
                                class="w-full text-xs rounded-xl border-gray-300 focus:ring-[#15803d] focus:border-[#15803d] font-bold"
                            >
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                Satuan Ukur (UOM) <span class="text-rose-500">*</span>
                            </label>
                            <select
                                name="uom_id"
                                id="finishedUomSelect"
                                required
                                class="w-full text-xs rounded-xl border-gray-300 focus:ring-[#15803d] focus:border-[#15803d]"
                            >
                                @foreach ($uoms as $u)
                                    <option value="{{ $u->id }}" {{ old('uom_id', $billOfMaterial->uom_id) == $u->id ? 'selected' : '' }}>
                                        {{ $u->name }} ({{ $u->abbreviation }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Catatan --}}
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                Catatan / Instruksi Khusus
                            </label>
                            <textarea
                                name="note"
                                rows="2"
                                class="w-full text-xs rounded-xl border-gray-300 focus:ring-[#15803d] focus:border-[#15803d]"
                            >{{ old('note', $billOfMaterial->note) }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- Kartu 2: Tabel Komponen Bahan Baku --}}
                <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm space-y-4">
                    <div class="border-b border-gray-100 pb-3 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-[#15803d] flex items-center justify-center font-bold text-sm">
                                2
                            </div>
                            <div>
                                <h2 class="font-bold text-gray-900 text-base">Komponen Bahan Baku (Raw Materials)</h2>
                                <p class="text-xs text-gray-400">Daftar bahan baku yang dikonsumsi per batch formula ini.</p>
                            </div>
                        </div>
                        <button
                            type="button"
                            onclick="addMaterialRow()"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-[#15803d] text-xs font-bold rounded-xl transition"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            + Tambah Bahan Baku
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs" id="materialsTable">
                            <thead class="bg-gray-50 border-b border-gray-200 text-[11px] font-bold text-gray-500 uppercase tracking-wider">
                                <tr>
                                    <th class="py-2.5 px-3 min-w-[200px]">Bahan Baku</th>
                                    <th class="py-2.5 px-3 w-28">Satuan</th>
                                    <th class="py-2.5 px-3 w-28 text-right">Kebutuhan Qty</th>
                                    <th class="py-2.5 px-3 w-32 text-right">Estimasi Biaya</th>
                                    <th class="py-2.5 px-3 w-36 text-right">Subtotal Biaya</th>
                                    <th class="py-2.5 px-3 w-10 text-center"></th>
                                </tr>
                            </thead>
                            <tbody id="materialsTbody" class="divide-y divide-gray-100 font-medium">
                                {{-- Baris diinjeksi via JavaScript --}}
                            </tbody>
                        </table>
                    </div>

                    <div id="emptyMaterialsState" class="hidden py-8 text-center text-gray-400 border border-dashed border-gray-200 rounded-xl">
                        <p class="text-xs font-semibold text-gray-600">Belum ada bahan baku yang ditambahkan</p>
                        <p class="text-[11px] text-gray-400 mt-0.5">Klik tombol "+ Tambah Bahan Baku" untuk menambahkan komponen formula.</p>
                    </div>
                </div>
            </div>

            {{-- Kolom Kanan: Rincian Biaya Produksi --}}
            <div class="space-y-6">
                {{-- Kartu 3: Biaya Produksi Tambahan --}}
                <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm space-y-4">
                    <div class="border-b border-gray-100 pb-3 flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-emerald-50 text-[#15803d] flex items-center justify-center font-bold text-sm">
                            3
                        </div>
                        <div>
                            <h2 class="font-bold text-gray-900 text-base">Biaya Produksi Tambahan</h2>
                            <p class="text-[11px] text-gray-400">Upah tenaga kerja dan beban pabrik.</p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                Biaya Tenaga Kerja (TKL)
                            </label>
                            <div class="relative rounded-xl shadow-sm">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 text-xs font-bold">Rp</span>
                                <input
                                    type="number"
                                    step="any"
                                    min="0"
                                    name="labor_cost"
                                    id="laborCostInput"
                                    value="{{ old('labor_cost', (float) $billOfMaterial->labor_cost) }}"
                                    oninput="recalculateTotals()"
                                    class="w-full text-xs rounded-xl border-gray-300 pl-10 pr-3 focus:ring-[#15803d] focus:border-[#15803d] font-mono text-right"
                                >
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                Biaya Overhead Pabrik (FOH)
                            </label>
                            <div class="relative rounded-xl shadow-sm">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 text-xs font-bold">Rp</span>
                                <input
                                    type="number"
                                    step="any"
                                    min="0"
                                    name="overhead_cost"
                                    id="overheadCostInput"
                                    value="{{ old('overhead_cost', (float) $billOfMaterial->overhead_cost) }}"
                                    oninput="recalculateTotals()"
                                    class="w-full text-xs rounded-xl border-gray-300 pl-10 pr-3 focus:ring-[#15803d] focus:border-[#15803d] font-mono text-right"
                                >
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Kartu 4: Ringkasan Estimasi Biaya (Live Summary) --}}
                <div class="bg-gradient-to-br from-[#f0fdf4] to-white rounded-2xl border border-[#bbf7d0] p-5 shadow-sm space-y-4">
                    <h3 class="text-xs font-extrabold uppercase tracking-wider text-[#14532d]">
                        Ringkasan Kalkulasi HPP Standar
                    </h3>

                    <div class="space-y-2 text-xs divide-y divide-emerald-100">
                        <div class="flex justify-between py-1 text-gray-600">
                            <span>Total Bahan Baku:</span>
                            <span class="font-mono font-bold text-gray-900" id="summaryMaterialCost">Rp 0</span>
                        </div>

                        <div class="flex justify-between py-1 text-gray-600">
                            <span>Biaya Tenaga Kerja:</span>
                            <span class="font-mono font-bold text-gray-900" id="summaryLaborCost">Rp 0</span>
                        </div>

                        <div class="flex justify-between py-1 text-gray-600">
                            <span>Biaya Overhead:</span>
                            <span class="font-mono font-bold text-gray-900" id="summaryOverheadCost">Rp 0</span>
                        </div>

                        <div class="flex justify-between pt-2.5 pb-1 text-sm font-extrabold text-[#15803d] border-t border-emerald-200">
                            <span>Grand Total Biaya BOM:</span>
                            <span class="font-mono text-base" id="summaryGrandTotal">Rp 0</span>
                        </div>

                        <div class="flex justify-between pt-2 text-xs font-bold text-gray-700">
                            <span>Estimasi HPP per Unit:</span>
                            <span class="font-mono text-emerald-800 font-extrabold" id="summaryCostPerUnit">Rp 0 / unit</span>
                        </div>
                    </div>

                    <div class="pt-2">
                        <button
                            type="submit"
                            form="bomEditForm"
                            class="w-full py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-xs font-bold rounded-xl shadow-sm transition active:scale-[0.98] text-center"
                        >
                            Perbarui Resep BOM
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    const RAW_MATERIALS = @json($rawMaterials);
    const UOMS = @json($uoms);
    let rowIndex = 0;

    function onFinishedProductChange(select) {
        const selectedOption = select.options[select.selectedIndex];
        if (selectedOption && selectedOption.dataset.uomId) {
            document.getElementById('finishedUomSelect').value = selectedOption.dataset.uomId;
        }
    }

    function addMaterialRow(data = null) {
        const tbody = document.getElementById('materialsTbody');
        document.getElementById('emptyMaterialsState').classList.add('hidden');

        const currentIdx = rowIndex++;
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-gray-50/70 transition';
        tr.id = `materialRow_${currentIdx}`;

        const selectedProductId = data ? data.product_id : '';
        const qty = data ? (data.quantity || 1) : 1;
        const selectedUomId = data ? data.uom_id : '';
        const unitPrice = data ? (data.unit_price || 0) : 0;
        const note = data ? (data.note || '') : '';

        let productOptions = '<option value="">-- Pilih Bahan Baku --</option>';
        RAW_MATERIALS.forEach(item => {
            const isSel = (item.id == selectedProductId) ? 'selected' : '';
            productOptions += `<option value="${item.id}" data-uom-id="${item.uom_id}" data-cost="${item.cost_price || 0}" ${isSel}>(${item.sku}) ${item.name}</option>`;
        });

        let uomOptions = '<option value="">-- Satuan --</option>';
        UOMS.forEach(u => {
            const isSel = (u.id == selectedUomId) ? 'selected' : '';
            uomOptions += `<option value="${u.id}" ${isSel}>${u.name} (${u.abbreviation})</option>`;
        });

        tr.innerHTML = `
            <td class="py-2.5 px-3">
                <select name="items[${currentIdx}][product_id]" required onchange="onMaterialProductChange(${currentIdx}, this)" class="w-full text-xs rounded-xl border-gray-300 focus:ring-[#15803d] focus:border-[#15803d]">
                    ${productOptions}
                </select>
                <input type="text" name="items[${currentIdx}][note]" value="${note}" placeholder="Catatan part..." class="w-full text-[11px] rounded-lg border-gray-200 mt-1 py-1 focus:ring-[#15803d] focus:border-[#15803d]">
            </td>
            <td class="py-2.5 px-3">
                <select name="items[${currentIdx}][uom_id]" id="itemUom_${currentIdx}" required class="w-full text-xs rounded-xl border-gray-300 focus:ring-[#15803d] focus:border-[#15803d]">
                    ${uomOptions}
                </select>
            </td>
            <td class="py-2.5 px-3 text-right">
                <input type="number" step="any" min="0.0001" name="items[${currentIdx}][quantity]" id="itemQty_${currentIdx}" value="${qty}" required oninput="calculateRowSubtotal(${currentIdx})" class="w-full text-xs text-right rounded-xl border-gray-300 font-bold focus:ring-[#15803d] focus:border-[#15803d]">
            </td>
            <td class="py-2.5 px-3 text-right">
                <input type="number" step="any" min="0" name="items[${currentIdx}][unit_price]" id="itemPrice_${currentIdx}" value="${unitPrice}" oninput="calculateRowSubtotal(${currentIdx})" class="w-full text-xs text-right rounded-xl border-gray-300 font-mono focus:ring-[#15803d] focus:border-[#15803d]">
            </td>
            <td class="py-2.5 px-3 text-right font-mono font-bold text-gray-900" id="itemSubtotal_${currentIdx}">
                Rp 0
            </td>
            <td class="py-2.5 px-3 text-center">
                <button type="button" onclick="removeMaterialRow(${currentIdx})" class="p-1.5 text-rose-500 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition" title="Hapus Baris">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </button>
            </td>
        `;

        tbody.appendChild(tr);
        calculateRowSubtotal(currentIdx);
    }

    function removeMaterialRow(idx) {
        const row = document.getElementById(`materialRow_${idx}`);
        if (row) {
            row.remove();
        }

        const tbody = document.getElementById('materialsTbody');
        if (tbody.children.length === 0) {
            document.getElementById('emptyMaterialsState').classList.remove('hidden');
        }

        recalculateTotals();
    }

    function onMaterialProductChange(idx, select) {
        const selectedOption = select.options[select.selectedIndex];
        if (selectedOption && selectedOption.value) {
            const uomId = selectedOption.dataset.uomId;
            const cost = parseFloat(selectedOption.dataset.cost) || 0;

            if (uomId) {
                document.getElementById(`itemUom_${idx}`).value = uomId;
            }
            document.getElementById(`itemPrice_${idx}`).value = cost;
        } else {
            document.getElementById(`itemPrice_${idx}`).value = 0;
        }

        calculateRowSubtotal(idx);
    }

    function calculateRowSubtotal(idx) {
        const qty = parseFloat(document.getElementById(`itemQty_${idx}`)?.value) || 0;
        const price = parseFloat(document.getElementById(`itemPrice_${idx}`)?.value) || 0;
        const subtotal = qty * price;

        const subtotalEl = document.getElementById(`itemSubtotal_${idx}`);
        if (subtotalEl) {
            subtotalEl.innerText = 'Rp ' + Math.round(subtotal).toLocaleString('id-ID');
        }

        recalculateTotals();
    }

    function recalculateTotals() {
        const tbody = document.getElementById('materialsTbody');
        let totalMaterial = 0;

        for (let i = 0; i < tbody.children.length; i++) {
            const tr = tbody.children[i];
            const idx = tr.id.replace('materialRow_', '');
            const qty = parseFloat(document.getElementById(`itemQty_${idx}`)?.value) || 0;
            const price = parseFloat(document.getElementById(`itemPrice_${idx}`)?.value) || 0;
            totalMaterial += (qty * price);
        }

        const labor = parseFloat(document.getElementById('laborCostInput')?.value) || 0;
        const overhead = parseFloat(document.getElementById('overheadCostInput')?.value) || 0;
        const grandTotal = totalMaterial + labor + overhead;

        const finishedQty = parseFloat(document.getElementById('finishedQuantity')?.value) || 1;
        const costPerUnit = finishedQty > 0 ? (grandTotal / finishedQty) : grandTotal;

        document.getElementById('summaryMaterialCost').innerText = 'Rp ' + Math.round(totalMaterial).toLocaleString('id-ID');
        document.getElementById('summaryLaborCost').innerText = 'Rp ' + Math.round(labor).toLocaleString('id-ID');
        document.getElementById('summaryOverheadCost').innerText = 'Rp ' + Math.round(overhead).toLocaleString('id-ID');
        document.getElementById('summaryGrandTotal').innerText = 'Rp ' + Math.round(grandTotal).toLocaleString('id-ID');
        document.getElementById('summaryCostPerUnit').innerText = 'Rp ' + Math.round(costPerUnit).toLocaleString('id-ID') + ' / unit';
    }

    document.addEventListener('DOMContentLoaded', function () {
        const oldItems = @json(old('items', null));
        if (oldItems && oldItems.length > 0) {
            oldItems.forEach(item => addMaterialRow(item));
        } else {
            const existingItems = @json($billOfMaterial->items);
            if (existingItems && existingItems.length > 0) {
                existingItems.forEach(item => addMaterialRow(item));
            } else {
                addMaterialRow();
            }
        }
    });
</script>
@endsection
