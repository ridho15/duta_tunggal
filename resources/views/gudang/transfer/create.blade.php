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
            <span class="text-[#15803d] font-bold">Transfer Baru</span>
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
        <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm space-y-4">
            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2 border-b border-gray-100 pb-3">
                <span class="w-2 h-2 rounded-full bg-[#15803d]"></span>
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
                           class="w-full px-3 py-2 text-xs font-mono font-bold bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] text-gray-800">
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
                           class="w-full px-3 py-2 text-xs border border-gray-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] text-gray-800">
                </div>

                {{-- Gudang Asal --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Gudang Asal (Sumber Stok) <span class="text-rose-500">*</span>
                    </label>
                    <x-searchable-select name="from_warehouse_id" id="fromWarehouseSelect" :options="collect($warehouses)->map(fn ($wh) => ['id' => $wh->id, 'label' => $wh->name . ' (' . ($wh->kode ?? 'WH') . ')'])->values()" :value="old('from_warehouse_id')" placeholder="-- Pilih Gudang Asal --" :required="true" size="sm" />
                </div>

                {{-- Gudang Tujuan --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Gudang Tujuan <span class="text-rose-500">*</span>
                    </label>
                    <x-searchable-select name="to_warehouse_id" id="toWarehouseSelect" :options="collect($warehouses)->map(fn ($wh) => ['id' => $wh->id, 'label' => $wh->name . ' (' . ($wh->kode ?? 'WH') . ')'])->values()" :value="old('to_warehouse_id')" placeholder="-- Pilih Gudang Tujuan --" :required="true" size="sm" />
                </div>
            </div>
        </div>

        {{-- Section 2: Daftar Item Barang yang Dipindahkan --}}
        <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-[#15803d]"></span>
                    <span>2. Daftar Barang yang Dipindahkan</span>
                </h2>
                <button type="button"
                        onclick="addItemRow(null, true)"
                        id="btnAddRow"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-[#15803d] bg-emerald-50 border border-emerald-200 rounded-xl hover:bg-emerald-100 transition shadow-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M12 4v16m8-8H4" stroke-width="2" stroke-linecap="round"></path>
                    </svg>
                    <span>Tambah Baris</span>
                </button>
            </div>

            <!-- Quick-Add Search Bar (Non-terikat ke form item baris) -->
            <div class="relative bg-gradient-to-r from-emerald-50/70 to-teal-50/40 border border-emerald-200/80 rounded-2xl p-3.5 sm:p-4 transition shadow-xs" id="quickAddContainer">
                <div class="flex items-center justify-between gap-2 mb-2">
                    <label for="quickSearchInput" class="text-xs font-bold text-gray-800 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>Pencarian Cepat / Scan Barcode Produk</span>
                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 font-semibold">Scanner Ready</span>
                    </label>
                    <span id="quickAddFeedback"
                          class="text-xs font-semibold text-[#15803d] opacity-0 transition-all duration-300"></span>
                </div>

                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-emerald-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <input type="text"
                           id="quickSearchInput"
                           oninput="onQuickSearchInput()"
                           onkeydown="handleQuickSearchKeydown(event)"
                           autocomplete="off"
                           placeholder="Ketik SKU / nama barang atau tembak barcode scanner..."
                           class="w-full pl-10 pr-28 py-2.5 text-xs font-medium text-gray-900 bg-white border border-emerald-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#15803d]/30 focus:border-[#15803d] placeholder:text-gray-400 shadow-xs transition">
                    
                    <div class="absolute inset-y-0 right-0 pr-2.5 flex items-center gap-1.5">
                        <button type="button"
                                id="btnQuickAddClear"
                                onclick="clearQuickSearch()"
                                style="display: none;"
                                class="p-1 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition"
                                title="Bersihkan">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                        <span class="px-2 py-0.5 text-[10px] font-mono font-semibold text-emerald-800 bg-emerald-100/80 border border-emerald-200 rounded-md select-none hidden sm:inline-block">
                            ENTER ↵
                        </span>
                    </div>

                    <!-- Dropdown Auto-Suggest Results (Floating) -->
                    <div id="quickSearchResults"
                         style="display: none;"
                         class="absolute z-50 left-0 right-0 mt-1.5 bg-white border border-gray-200 rounded-2xl shadow-xl overflow-hidden max-h-72 overflow-y-auto divide-y divide-gray-100">
                    </div>
                </div>
                <div class="mt-2 flex items-center justify-between text-[11px] text-gray-500">
                    <span>💡 <em>Klik produk atau tekan Enter untuk menambah/menaikkan kuantitas item di posisi paling atas (#1).</em></span>
                    <span class="text-[10px] text-gray-400 hidden md:inline">Gunakan panah ↑/↓ untuk navigasi</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse" id="itemsTable">
                    <thead>
                        <tr class="bg-gray-50/80 text-gray-500 uppercase tracking-wider text-[11px] font-semibold border-b border-gray-100">
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

            <div id="emptyItemsNotice" class="py-8 text-center text-gray-400 bg-gray-50/50 rounded-xl border border-dashed border-gray-200">
                <p class="text-xs">Pilih <strong>Gudang Asal</strong> terlebih dahulu, lalu klik tombol <strong>Tambah Baris</strong> untuk memasukkan barang.</p>
            </div>
        </div>

        {{-- Form Actions --}}
        <div class="flex items-center justify-between pt-2">
            <a href="{{ route('gudang.transfer.index') }}"
               class="px-4 py-2.5 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm">
                Batal
            </a>
            <div class="flex items-center gap-2.5">
                <button type="submit"
                        name="action"
                        value="save_draft"
                        class="px-4 py-2.5 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm">
                    Simpan sebagai Draft
                </button>
                <button type="submit"
                        name="action"
                        value="submit_request"
                        class="inline-flex items-center gap-1.5 px-4 py-2.5 text-xs font-semibold text-white bg-[#15803d] hover:bg-[#166534] rounded-xl transition shadow-sm active:scale-[0.98]">
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

    // Quick-Add State
    let quickHighlightedIndex = -1;
    let filteredQuickProducts = [];
    let quickFeedbackTimeout = null;

    function showQuickFeedback(msg) {
        const fb = document.getElementById('quickAddFeedback');
        if (!fb) return;
        fb.textContent = msg;
        fb.classList.remove('opacity-0');
        fb.classList.add('opacity-100');
        if (quickFeedbackTimeout) clearTimeout(quickFeedbackTimeout);
        quickFeedbackTimeout = setTimeout(() => {
            fb.classList.remove('opacity-100');
            fb.classList.add('opacity-0');
        }, 3000);
    }

    function clearQuickSearch() {
        const input = document.getElementById('quickSearchInput');
        if (input) input.value = '';
        const resultsEl = document.getElementById('quickSearchResults');
        if (resultsEl) {
            resultsEl.innerHTML = '';
            resultsEl.style.display = 'none';
        }
        const btnClear = document.getElementById('btnQuickAddClear');
        if (btnClear) btnClear.style.display = 'none';
        quickHighlightedIndex = -1;
        filteredQuickProducts = [];
        if (input) input.focus();
    }

    function onQuickSearchInput() {
        const input = document.getElementById('quickSearchInput');
        const q = (input ? input.value : '').trim().toLowerCase();
        const btnClear = document.getElementById('btnQuickAddClear');
        const resultsEl = document.getElementById('quickSearchResults');
        if (!input || !resultsEl) return;

        if (btnClear) {
            btnClear.style.display = q.length > 0 ? 'inline-flex' : 'none';
        }

        if (q.length < 1) {
            resultsEl.innerHTML = '';
            resultsEl.style.display = 'none';
            quickHighlightedIndex = -1;
            filteredQuickProducts = [];
            return;
        }

        const fromWh = document.getElementById('fromWarehouseSelect').value;
        if (!fromWh) {
            showQuickFeedback('Pilih Gudang Asal terlebih dahulu!');
            resultsEl.innerHTML = `
                <div class="p-3 text-center text-xs text-amber-700 bg-amber-50">
                    ⚠️ Silakan pilih Gudang Asal (Sumber Stok) terlebih dahulu.
                </div>
            `;
            resultsEl.style.display = 'block';
            return;
        }

        filteredQuickProducts = availableProducts.filter(p => {
            const name = (p.name || '').toLowerCase();
            const sku = (p.sku || '').toLowerCase();
            return name.includes(q) || sku.includes(q);
        });

        quickHighlightedIndex = filteredQuickProducts.length > 0 ? 0 : -1;
        renderQuickSearchResults();
    }

    function renderQuickSearchResults() {
        const resultsEl = document.getElementById('quickSearchResults');
        if (!resultsEl) return;

        if (filteredQuickProducts.length === 0) {
            resultsEl.innerHTML = `
                <div class="p-3 text-center text-xs text-gray-500">
                    Tidak ada barang ditemukan dengan stok tersedia di gudang asal ini.
                </div>
            `;
            resultsEl.style.display = 'block';
            return;
        }

        let html = '';
        filteredQuickProducts.forEach((p, idx) => {
            const isHighlighted = idx === quickHighlightedIndex;
            const bgClass = isHighlighted ? 'bg-emerald-50/90 text-emerald-950 font-semibold' : 'hover:bg-gray-50 text-gray-800';
            html += `
                <div id="quick-res-${idx}"
                     class="px-4 py-2.5 cursor-pointer flex items-center justify-between gap-3 text-xs transition ${bgClass}"
                     onclick="selectQuickProductByIndex(${idx})">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="font-mono font-bold text-emerald-700 shrink-0">[${p.sku || '-'}]</span>
                            <span class="truncate font-medium text-gray-900">${p.name}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        <span class="px-2 py-0.5 rounded text-[10.5px] font-bold bg-gray-100 text-gray-700 border border-gray-200">
                            Stok: ${Number(p.qty_available).toLocaleString('id-ID')} ${p.uom_name || 'Pcs'}
                        </span>
                    </div>
                </div>
            `;
        });

        resultsEl.innerHTML = html;
        resultsEl.style.display = 'block';
        scrollQuickIntoView();
    }

    function scrollQuickIntoView() {
        if (quickHighlightedIndex >= 0) {
            const el = document.getElementById(`quick-res-${quickHighlightedIndex}`);
            if (el) el.scrollIntoView({ block: 'nearest' });
        }
    }

    function handleQuickSearchKeydown(e) {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (filteredQuickProducts.length > 0) {
                quickHighlightedIndex = (quickHighlightedIndex + 1) % filteredQuickProducts.length;
                renderQuickSearchResults();
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (filteredQuickProducts.length > 0) {
                quickHighlightedIndex = (quickHighlightedIndex - 1 + filteredQuickProducts.length) % filteredQuickProducts.length;
                renderQuickSearchResults();
            }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (filteredQuickProducts.length > 0) {
                const targetIdx = quickHighlightedIndex >= 0 ? quickHighlightedIndex : 0;
                selectQuickProduct(filteredQuickProducts[targetIdx]);
            }
        } else if (e.key === 'Escape') {
            e.preventDefault();
            clearQuickSearch();
        }
    }

    function selectQuickProductByIndex(idx) {
        if (filteredQuickProducts[idx]) {
            selectQuickProduct(filteredQuickProducts[idx]);
        }
    }

    function selectQuickProduct(p) {
        if (!p || !p.product_id) return;

        const container = document.getElementById('itemsContainer');
        document.getElementById('emptyItemsNotice').classList.add('hidden');

        // Cari apakah produk sudah ada di baris tabel
        let existingTr = null;

        for (let tr of container.children) {
            const select = tr.querySelector('select[name^="items"][name$="[product_id]"]');
            if (select && String(select.value) === String(p.product_id)) {
                existingTr = tr;
                break;
            }
        }

        if (existingTr) {
            // Produk sudah ada -> Tambah kuantitas +1 dan PINDAHKAN KE POSISI PALING ATAS (#1)
            const qtyInput = existingTr.querySelector('input[name^="items"][name$="[quantity]"]');
            let newQty = 1;
            if (qtyInput) {
                newQty = (parseFloat(qtyInput.value) || 0) + 1;
                qtyInput.value = newQty;
            }

            container.prepend(existingTr);
            existingTr.classList.add('bg-emerald-50/80');
            setTimeout(() => existingTr.classList.remove('bg-emerald-50/80'), 1500);

            showQuickFeedback(`+1 [${p.sku || '-'}] (Dipindahkan ke posisi #1 · Total: ${newQty} ${p.uom_name || 'Pcs'})`);
        } else if (container.children.length === 1) {
            // Cek apakah ada 1 baris kosong yang belum dipilih produknya
            const firstTr = container.children[0];
            const select = firstTr.querySelector('select[name^="items"][name$="[product_id]"]');
            if (select && !select.value) {
                const rowId = firstTr.id.replace('row-', '');
                select.value = p.product_id;
                onProductSelectChange(rowId);
                const qtyInput = firstTr.querySelector('input[name^="items"][name$="[quantity]"]');
                if (qtyInput) qtyInput.value = 1;
                showQuickFeedback(`Item [${p.sku || '-'}] dimasukkan ke posisi teratas (#1)`);
            } else {
                // Tambah baris baru di posisi paling atas (#1)
                const newIdx = addItemRow(null, true);
                const select = document.getElementById(`prod-select-${newIdx}`);
                if (select) {
                    select.value = p.product_id;
                    onProductSelectChange(newIdx);
                }
                const qtyInput = document.getElementById(`qty-input-${newIdx}`);
                if (qtyInput) qtyInput.value = 1;
                showQuickFeedback(`Item [${p.sku || '-'}] ditambahkan di posisi teratas (#1)`);
            }
        } else {
            // Tambah baris baru di posisi paling atas (#1)
            const newIdx = addItemRow(null, true);
            const select = document.getElementById(`prod-select-${newIdx}`);
            if (select) {
                select.value = p.product_id;
                onProductSelectChange(newIdx);
            }
            const qtyInput = document.getElementById(`qty-input-${newIdx}`);
            if (qtyInput) qtyInput.value = 1;
            showQuickFeedback(`Item [${p.sku || '-'}] ditambahkan di posisi teratas (#1)`);
        }

        clearQuickSearch();
    }

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
                addItemRow(null, false);
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

    function addItemRow(initialData = null, atTop = true) {
        document.getElementById('emptyItemsNotice').classList.add('hidden');
        const container = document.getElementById('itemsContainer');
        const idx = rowIndex++;

        let productOptions = '<option value="">-- Pilih Barang --</option>';
        availableProducts.forEach(p => {
            const sel = (initialData && initialData.product_id == p.product_id) ? 'selected' : '';
            productOptions += `<option value="${p.product_id}" data-stock="${p.qty_available}" data-uom="${p.uom_name}" data-rak="${p.rak_id || ''}" ${sel}>
                ${p.name} (${p.sku}) – Stok: ${p.qty_available} ${p.uom_name}
            </option>`;
        });

        let fromRakOptions = '<option value="">Default / Tanpa Rak</option>';
        fromWarehouseRaks.forEach(r => {
            const sel = (initialData && initialData.from_rak_id == r.id) ? 'selected' : '';
            fromRakOptions += `<option value="${r.id}" ${sel}>${r.name} (${r.code || ''})</option>`;
        });

        let toRakOptions = '<option value="">Default / Tanpa Rak</option>';
        toWarehouseRaks.forEach(r => {
            const sel = (initialData && initialData.to_rak_id == r.id) ? 'selected' : '';
            toRakOptions += `<option value="${r.id}" ${sel}>${r.name} (${r.code || ''})</option>`;
        });

        const qtyVal = initialData ? initialData.quantity : 1;

        const tr = document.createElement('tr');
        tr.id = `row-${idx}`;
        tr.className = 'hover:bg-gray-50/60 transition';
        tr.innerHTML = `
            <td class="py-2.5 px-3">
                <select data-searchable name="items[${idx}][product_id]"
                        id="prod-select-${idx}"
                        required
                        onchange="onProductSelectChange(${idx})"
                        class="w-full px-2.5 py-1.5 text-xs border border-gray-200 rounded-xl focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] text-gray-800">
                    ${productOptions}
                </select>
            </td>
            <td class="py-2.5 px-3 text-center">
                <span id="stock-display-${idx}" class="font-bold font-mono text-gray-700">-</span>
                <span id="uom-display-${idx}" class="text-[10px] text-gray-400 ml-0.5"></span>
            </td>
            <td class="py-2.5 px-3">
                <select data-searchable name="items[${idx}][from_rak_id]"
                        id="from-rak-${idx}"
                        class="w-full px-2 py-1.5 text-xs border border-gray-200 rounded-xl focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] text-gray-700">
                    ${fromRakOptions}
                </select>
            </td>
            <td class="py-2.5 px-3">
                <select data-searchable name="items[${idx}][to_rak_id]"
                        id="to-rak-${idx}"
                        class="w-full px-2 py-1.5 text-xs border border-gray-200 rounded-xl focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] text-gray-700">
                    ${toRakOptions}
                </select>
            </td>
            <td class="py-2.5 px-3 text-right">
                <input type="number"
                       name="items[${idx}][quantity]"
                       id="qty-input-${idx}"
                       min="0.01"
                       step="any"
                       value="${qtyVal}"
                       required
                       class="w-24 px-2 py-1.5 text-xs text-right font-bold font-mono border border-gray-200 rounded-xl focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] text-[#15803d]">
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

        if (atTop) {
            container.prepend(tr);
        } else {
            container.appendChild(tr);
        }

        if (initialData) {
            onProductSelectChange(idx);
        }

        return idx;
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

    // Komponen x-searchable-select memicu event 'change' pada input tersembunyi
    document.getElementById('fromWarehouseSelect').addEventListener('change', onWarehouseChange);
    document.getElementById('toWarehouseSelect').addEventListener('change', onDestinationWarehouseChange);

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
