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
                                class="w-full text-sm rounded-lg border border-gray-300 py-2.5 px-3 bg-white font-mono uppercase focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d]"
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
                                class="w-full text-sm rounded-lg border border-gray-300 py-2.5 px-3 bg-white focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d]"
                            >
                        </div>

                        {{-- Produk Jadi --}}
                        <div>
                            <x-searchable-select
                                name="product_id"
                                id="finishedProductSelect"
                                label="Produk Jadi (Output)"
                                :options="$manufacturedProducts->map(fn($p) => ['id' => $p->id, 'label' => $p->name, 'subtext' => $p->sku])->all()"
                                :value="old('product_id', $billOfMaterial->product_id)"
                                placeholder="-- Pilih Produk Manufaktur --"
                                :required="true"
                                hint="Hanya menampilkan produk yang ditandai sebagai barang hasil produksi."
                            />
                        </div>

                        {{-- Cabang --}}
                        <div>
                            <x-searchable-select
                                name="cabang_id"
                                label="Cabang / Pabrik"
                                :options="$cabangs->map(fn($cb) => ['id' => $cb->id, 'label' => $cb->nama, 'subtext' => $cb->kode])->all()"
                                :value="old('cabang_id', $billOfMaterial->cabang_id)"
                                placeholder="-- Pilih Cabang --"
                                :required="true"
                            />
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
                                class="w-full text-sm rounded-lg border border-gray-300 py-2.5 px-3 bg-white font-bold text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d]"
                            >
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                Satuan Ukur (UOM) <span class="text-rose-500">*</span>
                            </label>
                            <select data-searchable
                                name="uom_id"
                                id="finishedUomSelect"
                                required
                                class="w-full text-sm rounded-lg border border-gray-300 py-2.5 px-3 bg-white font-medium text-gray-800 focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d]"
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
                                class="w-full text-sm rounded-lg border border-gray-300 py-2 px-3 bg-white focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d]"
                            >{{ old('note', $billOfMaterial->note) }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- Kartu 2: Tabel Komponen Bahan Baku --}}
                <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm space-y-4">
                    <div class="border-b border-gray-100 pb-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-[#15803d] flex items-center justify-center font-bold text-sm">
                                2
                            </div>
                            <div>
                                <h2 class="font-bold text-gray-900 text-base">Komponen Bahan Baku (Raw Materials)</h2>
                                <p class="text-xs text-gray-400">Daftar bahan baku yang dikonsumsi per batch formula ini.</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                onclick="collapseAllMaterials()"
                                class="px-2.5 py-1.5 text-xs font-semibold text-gray-600 bg-gray-50 hover:bg-gray-100 rounded-xl border border-gray-200 transition"
                            >
                                Tutup Semua
                            </button>
                            <button
                                type="button"
                                onclick="expandAllMaterials()"
                                class="px-2.5 py-1.5 text-xs font-semibold text-gray-600 bg-gray-50 hover:bg-gray-100 rounded-xl border border-gray-200 transition"
                            >
                                Buka Semua
                            </button>
                            <button
                                type="button"
                                onclick="addMaterialRow()"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#f0fdf4] border border-[#86efac] hover:bg-[#dcfce7] text-[#15803d] text-xs font-bold rounded-xl transition shadow-sm"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Tambah Bahan Baku
                            </button>
                        </div>
                    </div>

                    {{-- Container Accordion Baris Bahan Baku --}}
                    <div id="materialsContainer" class="space-y-3">
                        {{-- Baris akan diinjeksi via JavaScript atau old data --}}
                    </div>

                    {{-- Empty State jika belum ada bahan --}}
                    <div id="emptyMaterialsState" class="py-8 text-center text-gray-400 border border-dashed border-gray-200 rounded-xl">
                        <svg class="w-8 h-8 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        <p class="text-xs font-semibold text-gray-600">Belum ada bahan baku yang ditambahkan</p>
                        <p class="text-[11px] text-gray-400 mt-0.5">Klik tombol "Tambah Bahan Baku" untuk menambahkan komponen formula.</p>
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
                            <div class="relative rounded-lg shadow-xs">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 text-xs font-bold pointer-events-none">Rp</span>
                                <input
                                    type="text"
                                    id="laborCostDisplay"
                                    value="{{ old('labor_cost', (float)$billOfMaterial->labor_cost) > 0 ? number_format(old('labor_cost', (float)$billOfMaterial->labor_cost), 0, ',', '.') : '0' }}"
                                    oninput="handleLaborCostInput(this.value)"
                                    class="w-full text-sm rounded-lg border border-gray-300 py-2.5 pl-10 pr-3 bg-white font-mono text-right focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d]"
                                    placeholder="0"
                                >
                                <input
                                    type="hidden"
                                    name="labor_cost"
                                    id="laborCostInput"
                                    value="{{ old('labor_cost', (float)$billOfMaterial->labor_cost) }}"
                                >
                            </div>
                            <p class="text-[10px] text-gray-400 mt-1">Total upah operator/buruh langsung untuk batch ini.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                Biaya Overhead Pabrik (FOH)
                            </label>
                            <div class="relative rounded-lg shadow-xs">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 text-xs font-bold pointer-events-none">Rp</span>
                                <input
                                    type="text"
                                    id="overheadCostDisplay"
                                    value="{{ old('overhead_cost', (float)$billOfMaterial->overhead_cost) > 0 ? number_format(old('overhead_cost', (float)$billOfMaterial->overhead_cost), 0, ',', '.') : '0' }}"
                                    oninput="handleOverheadCostInput(this.value)"
                                    class="w-full text-sm rounded-lg border border-gray-300 py-2.5 pl-10 pr-3 bg-white font-mono text-right focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d]"
                                    placeholder="0"
                                >
                                <input
                                    type="hidden"
                                    name="overhead_cost"
                                    id="overheadCostInput"
                                    value="{{ old('overhead_cost', (float)$billOfMaterial->overhead_cost) }}"
                                >
                            </div>
                            <p class="text-[10px] text-gray-400 mt-1">Biaya listrik, bahan bakar, mesin, dan penyusutan per batch.</p>
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
    const MANUFACTURED_PRODUCTS = @json($manufacturedProducts->keyBy('id'));
    const UOMS = @json($uoms);
    let rowIndex = 0;

    function onFinishedProductChange(val) {
        const prodId = typeof val === 'object' ? (val.value || val.id) : val;
        if (prodId && MANUFACTURED_PRODUCTS[prodId]) {
            const uomId = MANUFACTURED_PRODUCTS[prodId].uom_id;
            if (uomId) {
                document.getElementById('finishedUomSelect').value = uomId;
            }
        }
    }

    function handleLaborCostInput(rawVal) {
        const cleanDigits = String(rawVal).replace(/\D/g, '');
        const numVal = parseFloat(cleanDigits) || 0;
        document.getElementById('laborCostInput').value = numVal;
        document.getElementById('laborCostDisplay').value = numVal > 0 ? Number(numVal).toLocaleString('id-ID') : '0';
        recalculateTotals();
    }

    function handleOverheadCostInput(rawVal) {
        const cleanDigits = String(rawVal).replace(/\D/g, '');
        const numVal = parseFloat(cleanDigits) || 0;
        document.getElementById('overheadCostInput').value = numVal;
        document.getElementById('overheadCostDisplay').value = numVal > 0 ? Number(numVal).toLocaleString('id-ID') : '0';
        recalculateTotals();
    }

    function handleMaterialPriceInput(idx, rawVal) {
        const cleanDigits = String(rawVal).replace(/\D/g, '');
        const numVal = parseFloat(cleanDigits) || 0;
        document.getElementById(`itemPrice_${idx}`).value = numVal;
        document.getElementById(`display_itemPrice_${idx}`).value = numVal > 0 ? Number(numVal).toLocaleString('id-ID') : '';
        calculateRowSubtotal(idx);
    }

    function toggleMaterialRow(idx) {
        const body = document.getElementById(`materialBody_${idx}`);
        const chevron = document.getElementById(`chevron_${idx}`);
        if (!body) return;
        const isHidden = body.classList.contains('hidden');
        if (isHidden) {
            body.classList.remove('hidden');
            chevron?.classList.add('rotate-180');
        } else {
            body.classList.add('hidden');
            chevron?.classList.remove('rotate-180');
        }
    }

    function collapseAllMaterials() {
        document.querySelectorAll('[id^="materialBody_"]').forEach(el => el.classList.add('hidden'));
        document.querySelectorAll('[id^="chevron_"]').forEach(el => el.classList.remove('rotate-180'));
    }

    function expandAllMaterials() {
        document.querySelectorAll('[id^="materialBody_"]').forEach(el => el.classList.remove('hidden'));
        document.querySelectorAll('[id^="chevron_"]').forEach(el => el.classList.add('rotate-180'));
    }

    function updateMaterialSummary(idx) {
        const row = document.getElementById(`materialRow_${idx}`);
        if (!row) return;

        const select = row.querySelector('.material-select');
        const opt = select?.selectedOptions[0];
        const qty = parseFloat(document.getElementById(`itemQty_${idx}`)?.value || 0);
        const uomSelect = document.getElementById(`itemUom_${idx}`);
        const uomText = uomSelect?.selectedOptions[0]?.text || '';
        const subtotalText = document.getElementById(`itemSubtotal_${idx}`)?.innerText || 'Rp 0';
        const summaryEl = document.getElementById(`materialSummary_${idx}`);

        if (summaryEl) {
            if (opt && opt.value) {
                const name = opt.text;
                summaryEl.innerText = `${name} · ${qty} ${uomText} · ${subtotalText}`;
            } else {
                summaryEl.innerText = `Komponen #${idx + 1} — Pilih Bahan Baku...`;
            }
        }
    }

    function addMaterialRow(data = null, isOpen = true) {
        if (isOpen && rowIndex > 0 && !data) {
            collapseAllMaterials();
        }

        const container = document.getElementById('materialsContainer');
        document.getElementById('emptyMaterialsState').classList.add('hidden');

        const currentIdx = rowIndex++;
        const card = document.createElement('div');
        card.className = 'border border-gray-200 rounded-2xl overflow-hidden bg-white hover:border-gray-300 transition-all shadow-xs';
        card.id = `materialRow_${currentIdx}`;

        const selectedProductId = data ? data.product_id : '';
        const qty = data ? data.quantity : 1;
        const selectedUomId = data ? data.uom_id : '';
        const unitPrice = parseFloat(data ? data.unit_price : 0) || 0;
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

        card.innerHTML = `
            {{-- Header Accordion --}}
            <div class="flex items-center justify-between px-4 py-3 bg-gray-50/80 cursor-pointer select-none border-b border-gray-100 hover:bg-gray-100/60 transition" onclick="toggleMaterialRow(${currentIdx})">
                <div class="flex items-center gap-3 min-w-0 pr-2">
                    <span class="w-6 h-6 rounded-lg bg-emerald-100 text-[#15803d] flex items-center justify-center font-bold text-xs shrink-0">
                        ${currentIdx + 1}
                    </span>
                    <div class="truncate">
                        <span id="materialSummary_${currentIdx}" class="text-xs font-bold text-gray-800 truncate block">
                            Komponen #${currentIdx + 1} — Pilih Bahan Baku...
                        </span>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <span id="itemSubtotal_${currentIdx}" class="text-xs font-mono font-bold text-[#15803d] mr-1">
                        Rp 0
                    </span>
                    <button type="button" onclick="event.stopPropagation(); removeMaterialRow(${currentIdx})" class="p-1.5 text-rose-500 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition cursor-pointer" title="Hapus Baris">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                    <div id="chevron_${currentIdx}" class="text-gray-400 transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                </div>
            </div>

            {{-- Form Body --}}
            <div id="materialBody_${currentIdx}" class="p-4 space-y-3 ${isOpen ? '' : 'hidden'}">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                    <div class="md:col-span-8">
                        <label class="block text-[11px] font-bold text-gray-700 mb-1">
                            Bahan Baku <span class="text-rose-500">*</span>
                        </label>
                        <select data-searchable name="items[${currentIdx}][product_id]" required onchange="onMaterialProductChange(${currentIdx}, this)" class="w-full text-xs rounded-xl border border-gray-300 py-2 px-3 bg-white font-medium text-gray-800 focus:outline-none focus:ring-1 focus:ring-[#15803d] material-select">
                            ${productOptions}
                        </select>
                    </div>
                    <div class="md:col-span-4">
                        <label class="block text-[11px] font-bold text-gray-700 mb-1">Catatan Part / Spesifikasi</label>
                        <input type="text" name="items[${currentIdx}][note]" value="${note}" placeholder="Misal: Warna khusus, grade A..." class="w-full text-xs rounded-xl border border-gray-300 py-2 px-3 bg-white focus:outline-none focus:ring-1 focus:ring-[#15803d]">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2 border-t border-gray-100">
                    <div>
                        <label class="block text-[11px] font-bold text-gray-700 mb-1">Satuan <span class="text-rose-500">*</span></label>
                        <select data-searchable name="items[${currentIdx}][uom_id]" id="itemUom_${currentIdx}" required onchange="updateMaterialSummary(${currentIdx})" class="w-full text-xs rounded-xl border border-gray-300 py-2 px-3 bg-white font-medium text-gray-800 focus:outline-none focus:ring-1 focus:ring-[#15803d]">
                            ${uomOptions}
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-700 mb-1">Kebutuhan Qty <span class="text-rose-500">*</span></label>
                        <input type="number" step="any" min="0.0001" name="items[${currentIdx}][quantity]" id="itemQty_${currentIdx}" value="${qty}" required oninput="calculateRowSubtotal(${currentIdx})" class="w-full text-xs text-right rounded-xl border border-gray-300 py-2 px-3 bg-white font-bold font-mono text-gray-900 focus:outline-none focus:ring-1 focus:ring-[#15803d]">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-700 mb-1">Estimasi Biaya Satuan</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-xs font-semibold text-gray-400 pointer-events-none">Rp</span>
                            <input type="text" id="display_itemPrice_${currentIdx}" value="${unitPrice > 0 ? Number(unitPrice).toLocaleString('id-ID') : ''}"
                                   oninput="handleMaterialPriceInput(${currentIdx}, this.value)"
                                   placeholder="0"
                                   class="w-full pl-9 pr-3 py-2 text-xs font-bold font-mono text-right border border-gray-300 rounded-xl focus:outline-none focus:ring-1 focus:ring-[#15803d]">
                            <input type="hidden" name="items[${currentIdx}][unit_price]" id="itemPrice_${currentIdx}" value="${unitPrice}">
                        </div>
                    </div>
                </div>
            </div>
        `;

        container.appendChild(card);
        calculateRowSubtotal(currentIdx);
    }

    function removeMaterialRow(idx) {
        const row = document.getElementById(`materialRow_${idx}`);
        if (row) {
            row.remove();
        }

        const container = document.getElementById('materialsContainer');
        if (container.children.length === 0) {
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
            document.getElementById(`display_itemPrice_${idx}`).value = cost > 0 ? Number(cost).toLocaleString('id-ID') : '';
        } else {
            document.getElementById(`itemPrice_${idx}`).value = 0;
            document.getElementById(`display_itemPrice_${idx}`).value = '';
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

        updateMaterialSummary(idx);
        recalculateTotals();
    }

    function recalculateTotals() {
        const rows = document.querySelectorAll('#materialsContainer [id^="materialRow_"]');
        let totalMaterial = 0;

        rows.forEach(tr => {
            const idx = tr.id.replace('materialRow_', '');
            const qty = parseFloat(document.getElementById(`itemQty_${idx}`)?.value) || 0;
            const price = parseFloat(document.getElementById(`itemPrice_${idx}`)?.value) || 0;
            totalMaterial += (qty * price);
        });

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
        const finishedProductInput = document.getElementById('finishedProductSelect');
        if (finishedProductInput) {
            finishedProductInput.addEventListener('change', function () {
                onFinishedProductChange(this.value);
            });
            if (finishedProductInput.value) {
                onFinishedProductChange(finishedProductInput.value);
            }
        }

        const oldItems = @json(old('items', null));
        if (oldItems && oldItems.length > 0) {
            const itemsList = Object.values(oldItems);
            itemsList.forEach((item, index) => {
                addMaterialRow(item, index === itemsList.length - 1);
            });
        } else {
            const existingItems = @json($billOfMaterial->items);
            if (existingItems && existingItems.length > 0) {
                existingItems.forEach((item, idx) => {
                    addMaterialRow(item, idx === 0);
                });
            } else {
                addMaterialRow(null, true);
            }
        }
    });
</script>
@endsection
