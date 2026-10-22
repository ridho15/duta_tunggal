@extends('layouts.app')

@section('title', 'Terbitkan Surat Perintah Kerja (SPK) – Duta Tunggal ERP')

@section('content')
<div class="space-y-6 max-w-6xl mx-auto pb-12">
    {{-- Header & Navigasi --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('manufaktur.spk.index') }}" class="hover:text-gray-700">Perintah Kerja (SPK)</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Form Terbitkan SPK</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Terbitkan Surat Perintah Kerja (SPK) Baru</h1>
            <p class="text-sm text-gray-500 mt-0.5">Instruksi resmi bagi tim lantai produksi dan checker gudang bahan baku.</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('manufaktur.spk.index') }}"
                class="px-4 py-2 border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-semibold rounded-xl transition"
            >
                Batal
            </a>
            <button
                type="submit"
                form="spkForm"
                class="inline-flex items-center gap-2 px-5 py-2 bg-[#15803d] hover:bg-[#166534] text-white text-sm font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Terbitkan SPK
            </button>
        </div>
    </div>

    {{-- Alert Error --}}
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

    <form id="spkForm" method="POST" action="{{ route('manufaktur.spk.store') }}">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Kolom Kiri: Form Input Parameter SPK --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Kartu 1: Parameter Perintah Kerja --}}
                <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm space-y-4">
                    <div class="border-b border-gray-100 pb-3 flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-emerald-50 text-[#15803d] flex items-center justify-center font-bold text-sm">
                            1
                        </div>
                        <h2 class="font-bold text-gray-900 text-base">Identitas Perintah Kerja (SPK)</h2>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Nomor MO --}}
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                Nomor SPK / MO <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="text"
                                name="mo_number"
                                id="moNumberInput"
                                value="{{ old('mo_number', $defaultMoNumber) }}"
                                required
                                class="w-full text-xs rounded-xl border-gray-300 font-mono uppercase focus:ring-[#15803d] focus:border-[#15803d]"
                            >
                        </div>

                        {{-- Cabang Pabrik --}}
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                Cabang / Pabrik <span class="text-rose-500">*</span>
                            </label>
                            <select
                                name="cabang_id"
                                id="cabangSelect"
                                required
                                class="w-full text-xs rounded-xl border-gray-300 focus:ring-[#15803d] focus:border-[#15803d]"
                            >
                                @foreach ($cabangs as $cb)
                                    <option value="{{ $cb->id }}" {{ old('cabang_id', auth()->user()?->cabang_id) == $cb->id ? 'selected' : '' }}>
                                        ({{ $cb->kode }}) {{ $cb->nama }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Sumber Resep BOM --}}
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                Pilih Resep Formula (BOM) <span class="text-rose-500">*</span>
                            </label>
                            <select
                                name="bill_of_material_id"
                                id="bomSelect"
                                required
                                onchange="onBomChange(this)"
                                class="w-full text-xs rounded-xl border-gray-300 focus:ring-[#15803d] focus:border-[#15803d]"
                            >
                                <option value="">-- Pilih Resep Produksi Aktif --</option>
                                @foreach ($boms as $b)
                                    <option
                                        value="{{ $b->id }}"
                                        data-product-name="{{ $b->product?->name }}"
                                        data-product-sku="{{ $b->product?->sku }}"
                                        data-uom-name="{{ $b->uom?->name ?? 'Pcs' }}"
                                        data-base-qty="{{ $b->quantity }}"
                                        {{ (old('bill_of_material_id', $selectedBomId) == $b->id) ? 'selected' : '' }}
                                    >
                                        {{ $b->code }} — {{ $b->nama_bom }} (Hasil: {{ rtrim(rtrim((string)$b->quantity, '0'), '.') }} {{ $b->uom?->name ?? 'Pcs' }})
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-[11px] text-gray-400 mt-1">Formula acuan kebutuhan bahan baku dan kalkulasi biaya standar.</p>
                        </div>

                        {{-- Kuantitas Target --}}
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                Kuantitas Target Dibuat <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative rounded-xl shadow-sm">
                                <input
                                    type="number"
                                    step="any"
                                    min="0.0001"
                                    name="quantity"
                                    id="targetQuantityInput"
                                    value="{{ old('quantity', '1.00') }}"
                                    required
                                    oninput="recalculateMaterialNeeds()"
                                    class="w-full text-xs rounded-xl border-gray-300 pr-16 focus:ring-[#15803d] focus:border-[#15803d] font-bold text-gray-900"
                                >
                                <span class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 text-xs font-semibold" id="targetUomLabel">
                                    Pcs
                                </span>
                            </div>
                        </div>

                        {{-- Gudang Bahan Baku --}}
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                Gudang Pengambilan Bahan <span class="text-rose-500">*</span>
                            </label>
                            <select
                                name="warehouse_id"
                                id="warehouseSelect"
                                onchange="recalculateMaterialNeeds()"
                                class="w-full text-xs rounded-xl border-gray-300 focus:ring-[#15803d] focus:border-[#15803d]"
                            >
                                @foreach ($warehouses as $wh)
                                    <option value="{{ $wh->id }}" {{ old('warehouse_id') == $wh->id ? 'selected' : '' }}>
                                        {{ $wh->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Tanggal Mulai & Selesai --}}
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                Target Tanggal Mulai
                            </label>
                            <input
                                type="date"
                                name="start_date"
                                value="{{ old('start_date', date('Y-m-d')) }}"
                                class="w-full text-xs rounded-xl border-gray-300 focus:ring-[#15803d] focus:border-[#15803d]"
                            >
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                Target Tanggal Selesai
                            </label>
                            <input
                                type="date"
                                name="end_date"
                                value="{{ old('end_date', date('Y-m-d', strtotime('+3 days'))) }}"
                                class="w-full text-xs rounded-xl border-gray-300 focus:ring-[#15803d] focus:border-[#15803d]"
                            >
                        </div>

                        {{-- Catatan SPK --}}
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                Instruksi Kerja Tambahan (Catatan)
                            </label>
                            <textarea
                                name="notes"
                                rows="2"
                                class="w-full text-xs rounded-xl border-gray-300 focus:ring-[#15803d] focus:border-[#15803d]"
                                placeholder="Misal: Penugasan Shift Pagi, Mesin Line 2, Prioritaskan pesanan SO..."
                            >{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- Kartu 2: Tabel Kebutuhan Material & Kesiapan Stok Gudang --}}
                <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm space-y-4">
                    <div class="border-b border-gray-100 pb-3 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-[#15803d] flex items-center justify-center font-bold text-sm">
                                2
                            </div>
                            <div>
                                <h2 class="font-bold text-gray-900 text-base">Kebutuhan Bahan Baku & Cek Kesiapan Stok</h2>
                                <p class="text-xs text-gray-400">Pemeriksaan ketersediaan stok fisik bebas di gudang secara otomatis.</p>
                            </div>
                        </div>
                        <span id="readinessBadge" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-600">
                            Pilih Resep
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs" id="spkMaterialsTable">
                            <thead class="bg-gray-50 border-b border-gray-200 text-[11px] font-bold text-gray-500 uppercase tracking-wider">
                                <tr>
                                    <th class="py-2.5 px-3">Bahan Baku</th>
                                    <th class="py-2.5 px-3 text-center">Dibutuhkan</th>
                                    <th class="py-2.5 px-3 text-center">Stok Bebas Gudang</th>
                                    <th class="py-2.5 px-3 text-center">Status Stok</th>
                                </tr>
                            </thead>
                            <tbody id="spkMaterialsTbody" class="divide-y divide-gray-100 font-medium">
                                <tr>
                                    <td colspan="4" class="py-6 text-center text-gray-400">
                                        Pilih Resep BOM di atas untuk memuat daftar kebutuhan bahan baku.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Kolom Kanan: Ringkasan SPK & Panduan Operator --}}
            <div class="space-y-6">
                {{-- Kartu Ringkasan SPK --}}
                <div class="bg-gradient-to-br from-[#f0fdf4] to-white rounded-2xl border border-[#bbf7d0] p-5 shadow-sm space-y-4">
                    <h3 class="text-xs font-extrabold uppercase tracking-wider text-[#14532d]">
                        Ringkasan Instruksi Kerja
                    </h3>

                    <div class="space-y-2.5 text-xs divide-y divide-emerald-100">
                        <div class="flex justify-between py-1 text-gray-600">
                            <span>Produk Jadi Target:</span>
                            <span class="font-bold text-gray-900 text-right" id="summaryProductName">-</span>
                        </div>

                        <div class="flex justify-between py-1 text-gray-600">
                            <span>Target Kuantitas:</span>
                            <span class="font-bold text-gray-900" id="summaryTargetQty">-</span>
                        </div>

                        <div class="flex justify-between py-1 text-gray-600">
                            <span>Gudang Bahan:</span>
                            <span class="font-bold text-gray-900" id="summaryWarehouseName">-</span>
                        </div>

                        <div class="flex justify-between py-1 text-gray-600">
                            <span>Status Bahan Baku:</span>
                            <span class="font-bold text-emerald-800" id="summaryMaterialsStatus">-</span>
                        </div>
                    </div>

                    <div class="p-3 bg-white/80 rounded-xl border border-emerald-100 text-[11px] text-emerald-800 space-y-1">
                        <div class="font-bold flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Alur Setelah SPK Disimpan
                        </div>
                        <p class="text-gray-600">
                            SPK akan tersimpan dengan status <strong>Draft</strong>. Operator dapat mencetak lembar kerja PDF dan memulai proses produksi saat seluruh material telah disiapkan.
                        </p>
                    </div>

                    <div class="pt-2">
                        <button
                            type="submit"
                            form="spkForm"
                            class="w-full py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-xs font-bold rounded-xl shadow-sm transition active:scale-[0.98] text-center"
                        >
                            Terbitkan Surat Perintah Kerja
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    let currentBomData = null;

    function onBomChange(select) {
        const bomId = select.value;
        const selectedOption = select.options[select.selectedIndex];

        if (selectedOption && selectedOption.dataset.uomName) {
            document.getElementById('targetUomLabel').innerText = selectedOption.dataset.uomName;
            document.getElementById('summaryProductName').innerText = selectedOption.dataset.productName || '-';
        }

        if (!bomId) {
            currentBomData = null;
            document.getElementById('spkMaterialsTbody').innerHTML = `
                <tr>
                    <td colspan="4" class="py-6 text-center text-gray-400">
                        Pilih Resep BOM di atas untuk memuat daftar kebutuhan bahan baku.
                    </td>
                </tr>
            `;
            document.getElementById('readinessBadge').className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-600';
            document.getElementById('readinessBadge').innerText = 'Pilih Resep';
            return;
        }

        const warehouseId = document.getElementById('warehouseSelect').value;
        const url = `{{ url('manufaktur/spk/api/bom-details') }}/${bomId}?warehouse_id=${warehouseId}`;

        fetch(url)
            .then(res => res.json())
            .then(data => {
                currentBomData = data;
                recalculateMaterialNeeds();
            })
            .catch(err => {
                console.error('Error fetching BOM details:', err);
            });
    }

    function recalculateMaterialNeeds() {
        if (!currentBomData || !currentBomData.items) return;

        const targetQty = parseFloat(document.getElementById('targetQuantityInput').value) || 0;
        const baseQty = parseFloat(currentBomData.base_quantity) || 1;
        const ratio = baseQty > 0 ? (targetQty / baseQty) : targetQty;

        const tbody = document.getElementById('spkMaterialsTbody');
        tbody.innerHTML = '';

        let allAvailable = true;

        currentBomData.items.forEach((item, idx) => {
            const neededQty = item.quantity * ratio;
            const available = parseFloat(item.available_stock) || 0;
            const isSufficient = available >= neededQty;

            if (!isSufficient) {
                allAvailable = false;
            }

            const tr = document.createElement('tr');
            tr.className = 'hover:bg-gray-50/70 transition';
            tr.innerHTML = `
                <td class="py-2.5 px-3">
                    <div class="font-bold text-gray-900">${item.product_name}</div>
                    <div class="text-[11px] text-gray-400 font-mono">SKU: ${item.product_sku}</div>
                    <input type="hidden" name="items[${idx}][product_id]" value="${item.product_id}">
                    <input type="hidden" name="items[${idx}][uom_id]" value="${item.uom_id}">
                    <input type="hidden" name="items[${idx}][quantity]" value="${neededQty}">
                </td>
                <td class="py-2.5 px-3 text-center font-bold text-gray-900">
                    ${neededQty.toFixed(2).replace(/\.00$/, '')} ${item.uom_name}
                </td>
                <td class="py-2.5 px-3 text-center font-mono ${isSufficient ? 'text-emerald-700' : 'text-rose-600 font-bold'}">
                    ${available.toFixed(2).replace(/\.00$/, '')} ${item.uom_name}
                </td>
                <td class="py-2.5 px-3 text-center">
                    ${isSufficient
                        ? '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Cukup</span>'
                        : '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200"><span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>Kurang</span>'
                    }
                </td>
            `;
            tbody.appendChild(tr);
        });

        const badge = document.getElementById('readinessBadge');
        if (allAvailable) {
            badge.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200';
            badge.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Semua Bahan Siap';
            document.getElementById('summaryMaterialsStatus').innerText = '✅ Lengkap di Gudang';
        } else {
            badge.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200';
            badge.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>Stok Bahan Kurang';
            document.getElementById('summaryMaterialsStatus').innerText = '⚠️ Sebagian Kurang';
        }

        document.getElementById('summaryTargetQty').innerText = `${targetQty} ${currentBomData.uom_name || 'Pcs'}`;

        const whSelect = document.getElementById('warehouseSelect');
        document.getElementById('summaryWarehouseName').innerText = whSelect.options[whSelect.selectedIndex]?.text || '-';
    }

    document.addEventListener('DOMContentLoaded', function () {
        const bomSelect = document.getElementById('bomSelect');
        if (bomSelect.value) {
            onBomChange(bomSelect);
        }
    });
</script>
@endsection
