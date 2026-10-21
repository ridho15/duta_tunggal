@extends('layouts.app')

@section('title', 'Buat Transfer Stok – ERP Duta Tunggal')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    {{-- Header & Breadcrumbs --}}
    <div>
        <nav class="flex items-center gap-2 text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">
            <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Dashboard</a>
            <span class="text-gray-300">/</span>
            <a href="{{ route('gudang.transfer.index') }}" class="hover:text-gray-700">Pindah Stok</a>
            <span class="text-gray-300">/</span>
            <span class="text-emerald-700 font-bold">Transfer Baru</span>
        </nav>
        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Buat Transfer Stok Antar Gudang</h1>
        <p class="text-xs text-gray-500 mt-1">
            Pindahkan stok barang dari satu lokasi gudang ke gudang lainnya dengan verifikasi ketersediaan stok fisik.
        </p>
    </div>

    {{-- Error Alert --}}
    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs">
            <div class="font-bold mb-1 flex items-center gap-1.5">
                <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="9" stroke-width="2"></circle>
                    <path d="M12 8v4m0 4h.01" stroke-width="2" stroke-linecap="round"></path>
                </svg>
                <span>Terdapat kesalahan pada input:</span>
            </div>
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Form --}}
    <form action="{{ route('gudang.transfer.store') }}" method="POST" id="transferForm" class="space-y-6">
        @csrf

        {{-- Section 1: Informasi Dokumen & Lokasi --}}
        <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-2xs space-y-4">
            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2 border-b border-gray-100 pb-3">
                <span class="w-2 h-2 rounded-full bg-emerald-600"></span>
                <span>1. Informasi Dokumen & Lokasi Transfer</span>
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                {{-- No. Transfer --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Nomor Transfer
                    </label>
                    <input type="text"
                           name="transfer_number"
                           value="{{ old('transfer_number', $suggestedNumber) }}"
                           placeholder="Otomatis (contoh: ST-...)"
                           class="w-full px-3 py-2 text-xs font-mono font-bold bg-gray-50 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-emerald-500 text-gray-800">
                    <p class="text-[10px] text-gray-400 mt-1">Dapat diisi custom atau otomatis sistem</p>
                </div>

                {{-- Tanggal Transfer --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Tanggal Transfer <span class="text-rose-500">*</span>
                    </label>
                    <input type="date"
                           name="transfer_date"
                           value="{{ old('transfer_date', now()->format('Y-m-d')) }}"
                           required
                           class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-emerald-500 text-gray-800">
                </div>

                {{-- Gudang Asal --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Gudang Asal (Sumber Stok) <span class="text-rose-500">*</span>
                    </label>
                    <select name="from_warehouse_id"
                            id="fromWarehouseSelect"
                            required
                            onchange="onWarehouseChange()"
                            class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-emerald-500 text-gray-800 font-medium">
                        <option value="">-- Pilih Gudang Asal --</option>
                        @foreach ($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ old('from_warehouse_id') == $wh->id ? 'selected' : '' }}>
                                {{ $wh->name }} ({{ $wh->kode ?? 'WH' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Gudang Tujuan --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Gudang Tujuan <span class="text-rose-500">*</span>
                    </label>
                    <select name="to_warehouse_id"
                            id="toWarehouseSelect"
                            required
                            onchange="onDestinationWarehouseChange()"
                            class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-emerald-500 text-gray-800 font-medium">
                        <option value="">-- Pilih Gudang Tujuan --</option>
                        @foreach ($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ old('to_warehouse_id') == $wh->id ? 'selected' : '' }}>
                                {{ $wh->name }} ({{ $wh->kode ?? 'WH' }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- Section 2: Daftar Item Barang yang Dipindahkan --}}
        <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-2xs space-y-4">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-600"></span>
                    <span>2. Daftar Barang yang Dipindahkan</span>
                </h2>
                <button type="button"
                        onclick="addItemRow()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg hover:bg-emerald-100 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M12 4v16m8-8H4" stroke-width="2" stroke-linecap="round"></path>
                    </svg>
                    <span>Tambah Baris</span>
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse" id="itemsTable">
                    <thead>
                        <tr class="bg-gray-50 text-gray-500 uppercase tracking-wider text-[11px] font-semibold border-b border-gray-200">
                            <th class="py-2.5 px-3 w-5/12">Produk / Barang</th>
                            <th class="py-2.5 px-3 w-2/12 text-center">Stok Tersedia</th>
                            <th class="py-2.5 px-3 w-2/12">Rak Asal</th>
                            <th class="py-2.5 px-3 w-2/12">Rak Tujuan</th>
                            <th class="py-2.5 px-3 w-2/12 text-right">Qty Pindah</th>
                            <th class="py-2.5 px-2 w-10 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="itemsContainer" class="divide-y divide-gray-100">
                        {{-- Baris akan dibuat lewat JavaScript --}}
                    </tbody>
                </table>
            </div>

            <div id="emptyItemsNotice" class="py-8 text-center text-gray-400 bg-gray-50/50 rounded-lg border border-dashed border-gray-200">
                <p class="text-xs">Pilih <strong>Gudang Asal</strong> terlebih dahulu, lalu klik tombol <strong>Tambah Baris</strong> untuk memasukkan barang.</p>
            </div>
        </div>

        {{-- Form Actions --}}
        <div class="flex items-center justify-between pt-2">
            <a href="{{ route('gudang.transfer.index') }}"
               class="px-4 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                Batal
            </a>
            <div class="flex items-center gap-2.5">
                <button type="submit"
                        name="action"
                        value="save_draft"
                        class="px-4 py-2 text-xs font-semibold text-gray-700 bg-gray-100 border border-gray-300 rounded-lg hover:bg-gray-200 transition">
                    Simpan sebagai Draft
                </button>
                <button type="submit"
                        name="action"
                        value="submit_request"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition shadow-2xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M5 13l4 4L19 7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                    <span>Simpan & Ajukan Transfer</span>
                </button>
            </div>
        </div>
    </form>
</div>

<script>
    let availableProducts = [];
    let fromWarehouseRaks = [];
    let toWarehouseRaks = [];
    let rowIndex = 0;

    async function onWarehouseChange() {
        const fromWarehouseId = document.getElementById('fromWarehouseSelect').value;
        if (!fromWarehouseId) {
            availableProducts = [];
            fromWarehouseRaks = [];
            return;
        }

        try {
            // Fetch products with available stock in from_warehouse
            const prodRes = await fetch(`/gudang/transfer-available-products/${fromWarehouseId}`);
            availableProducts = await prodRes.json();

            // Fetch raks in from_warehouse
            const rakRes = await fetch(`/gudang/warehouse-raks/${fromWarehouseId}`);
            fromWarehouseRaks = await rakRes.json();

            // If table has rows, update their product selects
            updateAllRowOptions();

            // If empty, automatically add 1 row
            const container = document.getElementById('itemsContainer');
            if (container.children.length === 0) {
                addItemRow();
            }
        } catch (e) {
            console.error('Error fetching warehouse details', e);
        }
    }

    async function onDestinationWarehouseChange() {
        const toWarehouseId = document.getElementById('toWarehouseSelect').value;
        if (!toWarehouseId) {
            toWarehouseRaks = [];
            return;
        }

        try {
            const rakRes = await fetch(`/gudang/warehouse-raks/${toWarehouseId}`);
            toWarehouseRaks = await rakRes.json();
            updateAllDestinationRakOptions();
        } catch (e) {
            console.error('Error fetching destination raks', e);
        }
    }

    function addItemRow() {
        document.getElementById('emptyItemsNotice').classList.add('hidden');
        const container = document.getElementById('itemsContainer');
        const idx = rowIndex++;

        let productOptions = '<option value="">-- Pilih Barang --</option>';
        availableProducts.forEach(p => {
            productOptions += `<option value="${p.product_id}" data-stock="${p.qty_available}" data-uom="${p.uom_name}" data-rak="${p.rak_id || ''}">
                ${p.name} (${p.sku}) – Stok: ${p.qty_available} ${p.uom_name}
            </option>`;
        });

        let fromRakOptions = '<option value="">Default / Tanpa Rak</option>';
        fromWarehouseRaks.forEach(r => {
            fromRakOptions += `<option value="${r.id}">${r.name} (${r.code || ''})</option>`;
        });

        let toRakOptions = '<option value="">Default / Tanpa Rak</option>';
        toWarehouseRaks.forEach(r => {
            toRakOptions += `<option value="${r.id}">${r.name} (${r.code || ''})</option>`;
        });

        const tr = document.createElement('tr');
        tr.id = `row-${idx}`;
        tr.className = 'hover:bg-gray-50/60 transition';
        tr.innerHTML = `
            <td class="py-2.5 px-3">
                <select name="items[${idx}][product_id]"
                        id="prod-select-${idx}"
                        required
                        onchange="onProductSelectChange(${idx})"
                        class="w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-md focus:ring-1 focus:ring-emerald-500 text-gray-800">
                    ${productOptions}
                </select>
            </td>
            <td class="py-2.5 px-3 text-center">
                <span id="stock-display-${idx}" class="font-bold text-gray-700">-</span>
                <span id="uom-display-${idx}" class="text-[10px] text-gray-400 ml-0.5"></span>
            </td>
            <td class="py-2.5 px-3">
                <select name="items[${idx}][from_rak_id]"
                        id="from-rak-${idx}"
                        class="w-full px-2 py-1.5 text-xs border border-gray-300 rounded-md focus:ring-1 focus:ring-emerald-500 text-gray-700">
                    ${fromRakOptions}
                </select>
            </td>
            <td class="py-2.5 px-3">
                <select name="items[${idx}][to_rak_id]"
                        id="to-rak-${idx}"
                        class="w-full px-2 py-1.5 text-xs border border-gray-300 rounded-md focus:ring-1 focus:ring-emerald-500 text-gray-700">
                    ${toRakOptions}
                </select>
            </td>
            <td class="py-2.5 px-3 text-right">
                <input type="number"
                       name="items[${idx}][quantity]"
                       id="qty-input-${idx}"
                       min="0.01"
                       step="any"
                       value="1"
                       required
                       class="w-24 px-2 py-1.5 text-xs text-right font-bold border border-gray-300 rounded-md focus:ring-1 focus:ring-emerald-500 text-emerald-800">
            </td>
            <td class="py-2.5 px-2 text-center">
                <button type="button"
                        onclick="removeRow(${idx})"
                        class="p-1 text-gray-400 hover:text-rose-600 transition"
                        title="Hapus Baris">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </button>
            </td>
        `;
        container.appendChild(tr);
    }

    function removeRow(idx) {
        const row = document.getElementById(`row-${idx}`);
        if (row) {
            row.remove();
        }
        const container = document.getElementById('itemsContainer');
        if (container.children.length === 0) {
            document.getElementById('emptyItemsNotice').classList.remove('hidden');
        }
    }

    function onProductSelectChange(idx) {
        const select = document.getElementById(`prod-select-${idx}`);
        const selectedOpt = select.options[select.selectedIndex];
        const stockDisplay = document.getElementById(`stock-display-${idx}`);
        const uomDisplay = document.getElementById(`uom-display-${idx}`);
        const qtyInput = document.getElementById(`qty-input-${idx}`);
        const fromRakSelect = document.getElementById(`from-rak-${idx}`);

        if (selectedOpt && selectedOpt.value) {
            const stock = parseFloat(selectedOpt.dataset.stock || 0);
            const uom = selectedOpt.dataset.uom || 'Pcs';
            const rakId = selectedOpt.dataset.rak;

            stockDisplay.textContent = stock.toLocaleString('id-ID');
            uomDisplay.textContent = uom;
            qtyInput.max = stock;

            if (rakId && fromRakSelect) {
                fromRakSelect.value = rakId;
            }
        } else {
            stockDisplay.textContent = '-';
            uomDisplay.textContent = '';
            qtyInput.removeAttribute('max');
        }
    }

    function updateAllRowOptions() {
        const container = document.getElementById('itemsContainer');
        for (let row of container.children) {
            const rowId = row.id.replace('row-', '');
            const select = document.getElementById(`prod-select-${rowId}`);
            if (select) {
                const currentVal = select.value;
                let productOptions = '<option value="">-- Pilih Barang --</option>';
                availableProducts.forEach(p => {
                    const sel = (p.product_id == currentVal) ? 'selected' : '';
                    productOptions += `<option value="${p.product_id}" data-stock="${p.qty_available}" data-uom="${p.uom_name}" data-rak="${p.rak_id || ''}" ${sel}>
                        ${p.name} (${p.sku}) – Stok: ${p.qty_available} ${p.uom_name}
                    </option>`;
                });
                select.innerHTML = productOptions;
                onProductSelectChange(rowId);
            }

            const fromRak = document.getElementById(`from-rak-${rowId}`);
            if (fromRak) {
                let rOptions = '<option value="">Default / Tanpa Rak</option>';
                fromWarehouseRaks.forEach(r => {
                    rOptions += `<option value="${r.id}">${r.name} (${r.code || ''})</option>`;
                });
                fromRak.innerHTML = rOptions;
            }
        }
    }

    function updateAllDestinationRakOptions() {
        const container = document.getElementById('itemsContainer');
        for (let row of container.children) {
            const rowId = row.id.replace('row-', '');
            const toRak = document.getElementById(`to-rak-${rowId}`);
            if (toRak) {
                let rOptions = '<option value="">Default / Tanpa Rak</option>';
                toWarehouseRaks.forEach(r => {
                    rOptions += `<option value="${r.id}">${r.name} (${r.code || ''})</option>`;
                });
                toRak.innerHTML = rOptions;
            }
        }
    }

    // Init on page load if old warehouse exists
    document.addEventListener('DOMContentLoaded', () => {
        const fromWh = document.getElementById('fromWarehouseSelect').value;
        if (fromWh) {
            onWarehouseChange();
        }
        const toWh = document.getElementById('toWarehouseSelect').value;
        if (toWh) {
            onDestinationWarehouseChange();
        }
    });
</script>
@endsection
