@extends('layouts.app')

@section('title', 'Edit Penawaran Harga ' . $quotation->quotation_number . ' – ERP Duta Tunggal')

@section('content')
<div class="max-w-[1100px] mx-auto space-y-6">
    {{-- Header & Breadcrumbs --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-4 border-b border-gray-200">
        <div>
            <nav class="flex items-center gap-2 text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('penjualan.quotations.index') }}" class="hover:text-[#15803d]">Penawaran Harga</a>
                <span class="text-gray-300">/</span>
                <a href="{{ route('penjualan.quotations.show', $quotation) }}" class="hover:text-[#15803d]">{{ $quotation->quotation_number }}</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Edit</span>
            </nav>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Edit Penawaran: {{ $quotation->quotation_number }}</h1>
            <p class="text-xs text-gray-500 mt-0.5">
                Perbarui rincian harga, kuantitas, atau data pelanggan sebelum diajukan kembali.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('penjualan.quotations.show', $quotation) }}"
               class="px-3.5 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm">
                Kembali ke Detail
            </a>
        </div>
    </div>

    {{-- Error Alert --}}
    @if ($errors->any())
        <div class="p-4 bg-red-50 border border-red-200 rounded-2xl text-xs font-medium text-red-800 shadow-sm">
            <div class="font-bold mb-1 flex items-center gap-1.5">
                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                Periksa kembali data formulir penawaran:
            </div>
            <ul class="list-disc list-inside space-y-0.5 text-red-700 ml-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('penjualan.quotations.update', $quotation) }}" id="quotationForm" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Section 1: Informasi Pelanggan & Cabang --}}
        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-4">
            <div class="flex items-center gap-2 pb-3 border-b border-gray-100">
                <div class="w-2 h-2 rounded-full bg-[#15803d]"></div>
                <h2 class="text-sm font-bold text-gray-900 tracking-tight">1. Informasi Pelanggan & Penugasan Cabang</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- Cabang --}}
                <div>
                    @if($canAccessAll)
                        <x-searchable-select
                            name="cabang_id"
                            label="Cabang Penjual"
                            :options="$cabangs->map(fn($cb) => [
                                'id' => $cb->id,
                                'label' => $cb->nama,
                                'subtext' => $cb->kode ?? '',
                                'badge' => $cb->kode ?? ''
                            ])"
                            :value="old('cabang_id', $quotation->cabang_id)"
                            placeholder="-- Pilih Cabang --"
                            :required="true"
                        />
                    @else
                        <label class="block text-xs font-bold text-gray-700 mb-1">
                            Cabang Penjual <span class="text-red-500">*</span>
                        </label>
                        <input type="hidden" name="cabang_id" value="{{ $quotation->cabang_id }}">
                        <input type="text" readonly
                               value="{{ $quotation->cabang->nama ?? 'Cabang Terpilih' }}"
                               class="w-full py-2 px-3 text-xs bg-gray-50 border border-gray-200 rounded-xl text-gray-700 font-semibold cursor-not-allowed">
                    @endif
                </div>

                {{-- Pelanggan --}}
                <div class="md:col-span-2" @change="if ($event.detail) handleCustomerChange($event.detail)">
                    <x-searchable-select
                        name="customer_id"
                        id="customer_id"
                        label="Pelanggan (Customer)"
                        :options="$customers->map(fn($c) => [
                            'id' => $c->id,
                            'label' => $c->name . ($c->perusahaan ? ' — ' . $c->perusahaan : ''),
                            'subtext' => ($c->address ? \Illuminate\Support\Str::limit($c->address, 35) : '') . ($c->phone ?? $c->telephone ? ' | Telp: ' . ($c->phone ?? $c->telephone) : '') . ' | Tempo: ' . ($c->tempo_kredit ?? 30) . ' hari',
                            'badge' => $c->tipe_pembayaran ?? 'Customer'
                        ])"
                        :value="old('customer_id', $quotation->customer_id)"
                        placeholder="-- Cari atau Pilih Pelanggan --"
                        :required="true"
                    />
                </div>

                {{-- Nomor Penawaran (Readonly) --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">
                        No. Penawaran
                    </label>
                    <input type="text" readonly value="{{ $quotation->quotation_number }}"
                           class="w-full py-2 px-3 text-xs bg-gray-100 border border-gray-200 rounded-xl text-gray-600 font-mono font-bold cursor-not-allowed">
                </div>

                {{-- Tanggal Penawaran --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">
                        Tanggal Penawaran <span class="text-red-500">*</span>
                    </label>
                    <input type="date" name="date" id="date" value="{{ old('date', \Carbon\Carbon::parse($quotation->date)->format('Y-m-d')) }}" required
                           class="w-full py-2 px-3 text-xs bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d]">
                </div>

                {{-- Masa Berlaku / Valid Until --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">
                        Berlaku Sampai (Valid Until) <span class="text-red-500">*</span>
                    </label>
                    <input type="date" name="valid_until" id="valid_until"
                           value="{{ old('valid_until', $quotation->valid_until ? \Carbon\Carbon::parse($quotation->valid_until)->format('Y-m-d') : '') }}" required
                           class="w-full py-2 px-3 text-xs bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d]">
                </div>
            </div>
        </div>

        {{-- Section 2: Parameter Finansial & Pengiriman --}}
        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-4">
            <div class="flex items-center gap-2 pb-3 border-b border-gray-100">
                <div class="w-2 h-2 rounded-full bg-[#15803d]"></div>
                <h2 class="text-sm font-bold text-gray-900 tracking-tight">2. Syarat Pembayaran & Alamat Kirim</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                {{-- Mata Uang --}}
                <div @change="if ($event.detail) handleCurrencyChange($event.detail)">
                    <x-searchable-select
                        name="currency_id"
                        id="currency_id"
                        label="Mata Uang"
                        :options="$currencies->map(fn($curr) => [
                            'id' => $curr->id,
                            'label' => $curr->code . ' – ' . $curr->name,
                            'subtext' => 'Simbol: ' . ($curr->symbol ?? '') . ($curr->to_rupiah > 1 ? ' | Kurs: ' . number_format($curr->to_rupiah, 2, ',', '.') : ''),
                            'badge' => $curr->code
                        ])"
                        :value="old('currency_id', $quotation->currency_id)"
                        placeholder="-- Pilih Mata Uang --"
                        :required="true"
                    />
                </div>

                {{-- Kurs --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">
                        Nilai Kurs (Exchange Rate)
                    </label>
                    <input type="number" step="any" name="exchange_rate" id="exchange_rate"
                           value="{{ old('exchange_rate', $quotation->exchange_rate ?? 1) }}"
                           class="w-full py-2 px-3 text-xs bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] text-right font-mono">
                </div>

                {{-- Tempo Pembayaran --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">
                        Tempo Pembayaran (Hari)
                    </label>
                    <input type="number" name="tempo_pembayaran" id="tempo_pembayaran"
                           value="{{ old('tempo_pembayaran', $quotation->tempo_pembayaran ?? 30) }}" min="0"
                           class="w-full py-2 px-3 text-xs bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] text-right font-mono">
                </div>

                {{-- Alamat Kirim --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">
                        Tujuan Pengiriman (Shipped To)
                    </label>
                    <input type="text" name="shipped_to" id="shipped_to" value="{{ old('shipped_to', $quotation->shipped_to) }}"
                           placeholder="Alamat gudang/proyek pelanggan"
                           class="w-full py-2 px-3 text-xs bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d]">
                </div>

                {{-- Catatan Penawaran --}}
                <div class="md:col-span-4">
                    <label class="block text-xs font-bold text-gray-700 mb-1">
                        Catatan / Syarat Khusus Penawaran
                    </label>
                    <textarea name="notes" rows="2"
                              class="w-full py-2 px-3 text-xs bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d]">{{ old('notes', $quotation->notes) }}</textarea>
                </div>
            </div>
        </div>

        {{-- Section 3: Rincian Produk Penawaran --}}
        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-gray-100">
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-[#15803d]"></div>
                    <h2 class="text-sm font-bold text-gray-900 tracking-tight">3. Rincian Item Barang & Harga Penawaran</h2>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="collapseAllRows()"
                            class="px-2.5 py-1.5 text-xs font-semibold text-gray-600 bg-gray-50 hover:bg-gray-100 rounded-xl border border-gray-200 transition">
                        Tutup Semua
                    </button>
                    <button type="button" onclick="expandAllRows()"
                            class="px-2.5 py-1.5 text-xs font-semibold text-gray-600 bg-gray-50 hover:bg-gray-100 rounded-xl border border-gray-200 transition">
                        Buka Semua
                    </button>
                    <button type="button" id="btnAddRow"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-[#15803d] bg-[#f0fdf4] border border-[#86efac] rounded-xl hover:bg-[#dcfce7] transition shadow-sm">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>Tambah Baris Manual</span>
                    </button>
                </div>
            </div>

            <!-- Quick-Add Search Bar (Non-terikat ke form item baris) -->
            <div class="relative bg-gradient-to-r from-emerald-50/70 to-teal-50/40 border border-emerald-200/80 rounded-2xl p-3.5 sm:p-4 transition shadow-xs" id="quickAddContainer">
                <div class="flex items-center justify-between gap-2 mb-2">
                    <label for="quickSearchInput" class="text-xs font-bold text-gray-800 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>Pencarian Cepat / Scan Barcode Produk</span>
                    </label>
                    <span id="quickAddFeedback" class="text-xs font-semibold text-[#15803d] transition-all duration-300 opacity-0 translate-x-1"></span>
                </div>

                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-emerald-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <input type="text"
                           id="quickSearchInput"
                           autocomplete="off"
                           placeholder="Ketik SKU / nama barang atau tembak barcode scanner..."
                           class="w-full pl-10 pr-28 py-2.5 text-xs font-medium text-gray-900 bg-white border border-emerald-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#15803d]/30 focus:border-[#15803d] placeholder:text-gray-400 shadow-xs transition">
                    
                    <div class="absolute inset-y-0 right-0 pr-2.5 flex items-center gap-1.5">
                        <button type="button"
                                id="btnQuickAddClear"
                                onclick="clearQuickSearch()"
                                class="hidden p-1 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition"
                                title="Bersihkan">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                        <span class="px-2 py-0.5 text-[10px] font-mono font-semibold text-emerald-800 bg-emerald-100/80 border border-emerald-200 rounded-md select-none hidden sm:inline-block">
                            ENTER ↵
                        </span>
                    </div>

                    <!-- Dropdown Auto-Suggest Results (Floating) -->
                    <div id="quickSearchResults"
                         class="absolute z-50 left-0 right-0 mt-1.5 bg-white border border-gray-200 rounded-2xl shadow-xl overflow-hidden max-h-72 overflow-y-auto divide-y divide-gray-100 hidden">
                        <!-- Items rendered via JS -->
                    </div>
                </div>
                <div class="mt-2 flex items-center justify-between text-[11px] text-gray-500">
                    <span>💡 <em>Klik produk atau tekan Enter untuk menambah/menaikkan kuantitas item di posisi paling atas (#1).</em></span>
                    <span class="text-[10px] text-gray-400 hidden md:inline">Gunakan panah ↑/↓ untuk memilih</span>
                </div>
            </div>

            <div id="itemsContainer" class="space-y-3">
                {{-- Repeater cards injected via JS --}}
            </div>

            {{-- Summary Calculation Box --}}
            <div class="flex flex-col md:flex-row justify-end pt-4 border-t border-gray-100">
                <div class="w-full md:w-80 bg-gray-50 rounded-2xl p-4 border border-gray-200 space-y-2 text-xs shadow-sm">
                    <div class="flex justify-between text-gray-600 font-medium">
                        <span>Subtotal Kotor:</span>
                        <span id="txtGrossSubtotal" class="font-semibold text-gray-900 font-mono">Rp 0</span>
                    </div>
                    <div class="flex justify-between text-gray-600 font-medium">
                        <span>Total Diskon:</span>
                        <span id="txtTotalDiscount" class="text-red-600 font-semibold font-mono">- Rp 0</span>
                    </div>
                    <div class="flex justify-between text-gray-600 font-medium">
                        <span>Dasar Pengenaan Pajak (DPP):</span>
                        <span id="txtDpp" class="font-semibold text-gray-900 font-mono">Rp 0</span>
                    </div>
                    <div class="flex justify-between text-gray-600 font-medium">
                        <span>Total PPN:</span>
                        <span id="txtTotalTax" class="font-semibold text-gray-900 font-mono">Rp 0</span>
                    </div>
                    <div class="pt-2 border-t border-gray-200 flex justify-between text-sm font-black text-gray-900">
                        <span>Grand Total:</span>
                        <span id="txtGrandTotal" class="text-[#15803d] font-mono">Rp 0</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Section 4: Action Buttons --}}
        <div class="flex items-center justify-between p-4 bg-white border border-gray-100 rounded-2xl shadow-sm">
            <a href="{{ route('penjualan.quotations.show', $quotation) }}"
               class="px-4 py-2 text-xs font-semibold text-gray-600 hover:text-gray-800">
                &larr; Batalkan
            </a>
            <div class="flex items-center gap-2.5">
                <button type="submit" name="action" value="draft"
                        class="px-4 py-2 text-xs font-bold text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm">
                    Simpan Perubahan
                </button>
                <button type="submit" name="action" value="request_approve"
                        class="px-5 py-2 text-xs font-bold text-white bg-[#15803d] hover:bg-[#166534] rounded-xl transition shadow-sm flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Perbarui & Ajukan Persetujuan</span>
                </button>
            </div>
        </div>
    </form>
</div>

@php
    $existingItemsData = $quotation->quotationItem->map(function($item) {
        return [
            'product_id' => $item->product_id,
            'quantity' => (float)$item->quantity,
            'unit_price' => (float)$item->unit_price,
            'discount' => (float)$item->discount,
            'tax_type' => $item->tax_type,
            'notes' => $item->notes,
            'uom' => $item->product?->uom?->abbreviation ?? 'PCS'
        ];
    });
@endphp

{{-- Product dataset in JSON for JavaScript --}}
<script>
    const productsCatalog = @json($products);
    const existingItems = @json($existingItemsData);

    let rowCounter = 0;

    function formatNumber(num) {
        return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(num || 0);
    }

    function toggleRow(idx) {
        const body = document.getElementById(`card_body_${idx}`);
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

    function collapseAllRows() {
        document.querySelectorAll('[id^="card_body_"]').forEach(el => el.classList.add('hidden'));
        document.querySelectorAll('[id^="chevron_"]').forEach(el => el.classList.remove('rotate-180'));
    }

    function expandAllRows() {
        document.querySelectorAll('[id^="card_body_"]').forEach(el => el.classList.remove('hidden'));
        document.querySelectorAll('[id^="chevron_"]').forEach(el => el.classList.add('rotate-180'));
    }

    function handlePriceInput(idx, rawVal) {
        const cleanDigits = String(rawVal).replace(/\D/g, '');
        const numVal = parseFloat(cleanDigits) || 0;
        document.getElementById(`unit_price_${idx}`).value = numVal;
        document.getElementById(`display_price_${idx}`).value = numVal > 0 ? formatNumber(numVal) : '';
        calculateRow(idx);
    }

    function renumberItemRows() {
        const rows = document.querySelectorAll('#itemsContainer > [id^="row_"]');
        rows.forEach((row, i) => {
            const visualNum = i + 1;
            const badge = row.querySelector('.item-badge-number');
            if (badge) {
                badge.innerText = visualNum;
            }
            const idx = row.id.replace('row_', '');
            updateSummary(idx, visualNum);
        });
    }

    function updateSummary(idx, visualNum = null) {
        const tr = document.getElementById(`row_${idx}`);
        if (!tr) return;

        if (visualNum === null) {
            const rows = Array.from(document.querySelectorAll('#itemsContainer > [id^="row_"]'));
            const foundIdx = rows.indexOf(tr);
            visualNum = foundIdx >= 0 ? foundIdx + 1 : parseInt(idx);
        }

        const select = tr.querySelector('.product-select');
        const selectedOption = select?.options[select.selectedIndex];
        const qty = parseFloat(tr.querySelector('.item-qty')?.value) || 0;
        const uom = document.getElementById(`uom_${idx}`)?.innerText || 'PCS';
        const subtotalText = document.getElementById(`badge_subtotal_${idx}`)?.innerText || 'Rp 0';

        const summaryEl = document.getElementById(`summary_${idx}`);
        if (summaryEl) {
            if (selectedOption && selectedOption.value) {
                const sku = selectedOption.dataset.sku ? `[${selectedOption.dataset.sku}] ` : '';
                const name = selectedOption.text.split('–')[1]?.trim() || selectedOption.text;
                summaryEl.innerText = `#${visualNum} · ${sku}${name} · ${qty} ${uom} · ${subtotalText}`;
            } else {
                summaryEl.innerText = `Item #${visualNum} — Pilih Produk...`;
            }
        }
    }

    function addRow(data = {}, isOpen = true, atTop = true) {
        // Auto-collapse older rows when user explicitly clicks add row
        if (isOpen && rowCounter > 0) {
            collapseAllRows();
        }

        rowCounter++;
        const idx = rowCounter;
        const container = document.getElementById('itemsContainer');

        const card = document.createElement('div');
        card.className = 'border border-gray-200 bg-white rounded-2xl overflow-hidden shadow-xs hover:border-gray-300 transition-all';
        card.id = `row_${idx}`;

        let productOptions = '<option value="">-- Pilih Produk --</option>';
        productsCatalog.forEach(p => {
            const isSelected = (data.product_id && parseInt(data.product_id) === p.id) ? 'selected' : '';
            productOptions += `<option value="${p.id}" data-price="${p.sell_price}" data-uom="${p.uom}" data-sku="${p.sku}" ${isSelected}>
                ${p.sku} – ${p.name}
            </option>`;
        });

        const rawPrice = parseFloat(data.unit_price) || 0;

        card.innerHTML = `
            {{-- Header Accordion --}}
            <div class="flex items-center justify-between px-4 py-3 bg-gray-50/80 cursor-pointer select-none border-b border-gray-100 hover:bg-gray-100/60 transition" onclick="toggleRow(${idx})">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="item-badge-number inline-flex items-center justify-center w-6 h-6 rounded-full bg-[#f0fdf4] text-[#15803d] font-bold text-[11px] border border-[#86efac] shrink-0">
                        ${idx}
                    </span>
                    <div class="truncate">
                        <span id="summary_${idx}" class="text-xs font-bold text-gray-800">
                            Item #${idx} — Pilih Produk...
                        </span>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <span id="badge_subtotal_${idx}" class="text-xs font-mono font-bold text-[#15803d] mr-1">
                        Rp 0
                    </span>
                    <button type="button" onclick="event.stopPropagation(); removeRow(${idx})" class="p-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition" title="Hapus Baris">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                    <div id="chevron_${idx}" class="text-gray-400 transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                </div>
            </div>

            {{-- Body Form --}}
            <div id="card_body_${idx}" class="p-4 space-y-3 ${isOpen ? '' : 'hidden'}">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                    {{-- Produk --}}
                    <div class="md:col-span-8">
                        <label class="block text-[11px] font-bold text-gray-700 mb-1">
                            Produk / Barang <span class="text-red-500">*</span>
                        </label>
                        <select data-searchable name="items[${idx}][product_id]" class="w-full py-2 px-3 text-xs bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] product-select" required onchange="onProductChange(${idx})">
                            ${productOptions}
                        </select>
                    </div>
                    {{-- Catatan / Spesifikasi --}}
                    <div class="md:col-span-4">
                        <label class="block text-[11px] font-bold text-gray-700 mb-1">
                            Catatan / Spesifikasi Khusus
                        </label>
                        <input type="text" name="items[${idx}][notes]" value="${data.notes || ''}" placeholder="Misal: Warna khusus, garansi 1 thn" class="w-full py-2 px-3 text-xs bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d]">
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-6 gap-3 pt-2 border-t border-gray-100">
                    {{-- Qty --}}
                    <div>
                        <label class="block text-[11px] font-bold text-gray-700 mb-1">
                            Qty <span class="text-red-500">*</span>
                        </label>
                        <input type="number" step="any" min="0.01" name="items[${idx}][quantity]" value="${data.quantity || 1}" required
                               class="w-full py-2 px-3 text-xs text-right bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] item-qty font-mono" oninput="calculateRow(${idx})">
                    </div>

                    {{-- Satuan --}}
                    <div>
                        <label class="block text-[11px] font-bold text-gray-700 mb-1">Satuan</label>
                        <div class="py-2 px-3 text-xs text-center font-bold text-gray-600 bg-gray-50 border border-gray-200 rounded-xl">
                            <span id="uom_${idx}">${data.uom || 'PCS'}</span>
                        </div>
                    </div>

                    {{-- Harga Satuan (Masked Currency) --}}
                    <div class="col-span-2">
                        <label class="block text-[11px] font-bold text-gray-700 mb-1">
                            Harga Satuan <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-xs font-semibold text-gray-400 pointer-events-none">Rp</span>
                            <input type="text" id="display_price_${idx}" value="${rawPrice > 0 ? formatNumber(rawPrice) : ''}"
                                   class="w-full pl-9 pr-3 py-2 text-xs text-right font-mono font-semibold bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d]"
                                   oninput="handlePriceInput(${idx}, this.value)" placeholder="0">
                            <input type="hidden" name="items[${idx}][unit_price]" id="unit_price_${idx}" value="${rawPrice}" class="item-price">
                        </div>
                    </div>

                    {{-- Diskon (%) --}}
                    <div>
                        <label class="block text-[11px] font-bold text-gray-700 mb-1">Diskon (%)</label>
                        <input type="number" step="any" min="0" max="100" name="items[${idx}][discount]" value="${data.discount || 0}"
                               class="w-full py-2 px-3 text-xs text-right bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] item-disc font-mono" oninput="calculateRow(${idx})">
                    </div>

                    {{-- PPN --}}
                    <div>
                        <label class="block text-[11px] font-bold text-gray-700 mb-1">Pajak (PPN)</label>
                        <select name="items[${idx}][tax_type]" class="w-full py-2 px-3 text-xs bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] item-tax-type" onchange="calculateRow(${idx})">
                            <option value="None" ${(!data.tax_type || data.tax_type === 'None') ? 'selected' : ''}>0% (None)</option>
                            <option value="PPN Excluded" ${(data.tax_type === 'PPN Excluded' || data.tax_type === 'Eksklusif') ? 'selected' : ''}>11% Eksklusif</option>
                            <option value="PPN Included" ${(data.tax_type === 'PPN Included' || data.tax_type === 'Inklusif') ? 'selected' : ''}>11% Inklusif</option>
                        </select>
                        <input type="hidden" name="items[${idx}][tax]" value="11" class="item-tax-rate">
                    </div>
                </div>
            </div>
        `;

        if (atTop && container.firstChild) {
            container.prepend(card);
        } else {
            container.appendChild(card);
        }

        if (data.product_id) {
            onProductChange(idx, false);
        }
        renumberItemRows();
        calculateRow(idx);
    }

    function removeRow(idx) {
        const row = document.getElementById(`row_${idx}`);
        if (row) {
            row.remove();
            renumberItemRows();
            calculateAll();
        }
    }

    function onProductChange(idx, resetPrice = true) {
        const tr = document.getElementById(`row_${idx}`);
        if (!tr) return;
        const select = tr.querySelector('.product-select');
        const selectedOption = select.options[select.selectedIndex];
        const uomSpan = document.getElementById(`uom_${idx}`);
        const priceHidden = document.getElementById(`unit_price_${idx}`);
        const priceDisplay = document.getElementById(`display_price_${idx}`);

        if (selectedOption && selectedOption.value) {
            uomSpan.innerText = selectedOption.dataset.uom || 'PCS';
            if (resetPrice && selectedOption.dataset.price) {
                const p = parseFloat(selectedOption.dataset.price) || 0;
                priceHidden.value = p;
                priceDisplay.value = p > 0 ? formatNumber(p) : '';
            }
        } else {
            uomSpan.innerText = 'PCS';
            if (resetPrice) {
                priceHidden.value = 0;
                priceDisplay.value = '';
            }
        }

        calculateRow(idx);
    }

    function calculateRow(idx) {
        const tr = document.getElementById(`row_${idx}`);
        if (!tr) return;

        const qty = parseFloat(tr.querySelector('.item-qty')?.value) || 0;
        const price = parseFloat(document.getElementById(`unit_price_${idx}`)?.value) || 0;
        const disc = parseFloat(tr.querySelector('.item-disc')?.value) || 0;
        const taxType = tr.querySelector('.item-tax-type')?.value;

        const gross = qty * price;
        const discAmount = gross * (disc / 100);
        const netBeforeTax = gross - discAmount;

        let rowTotal = netBeforeTax;
        if (taxType === 'PPN Excluded' || taxType === 'Eksklusif') {
            rowTotal = netBeforeTax + (netBeforeTax * 0.11);
        } else if (taxType === 'PPN Included' || taxType === 'Inklusif') {
            rowTotal = netBeforeTax; // PPN sudah tercakup di dalam harga
        }

        const formattedSubtotal = 'Rp ' + formatNumber(rowTotal);
        const badgeSubtotal = document.getElementById(`badge_subtotal_${idx}`);
        if (badgeSubtotal) badgeSubtotal.innerText = formattedSubtotal;

        updateSummary(idx);
        calculateAll();
    }

    function calculateAll() {
        let totalGross = 0;
        let totalDiscount = 0;
        let totalDpp = 0;
        let totalTax = 0;
        let grandTotal = 0;

        const rows = document.querySelectorAll('#itemsContainer [id^="row_"]');
        rows.forEach(tr => {
            const qty = parseFloat(tr.querySelector('.item-qty')?.value) || 0;
            const price = parseFloat(tr.querySelector('.item-price')?.value) || 0;
            const disc = parseFloat(tr.querySelector('.item-disc')?.value) || 0;
            const taxType = tr.querySelector('.item-tax-type')?.value;

            const gross = qty * price;
            const discAmount = gross * (disc / 100);
            const net = gross - discAmount;

            totalGross += gross;
            totalDiscount += discAmount;

            if (taxType === 'PPN Excluded' || taxType === 'Eksklusif') {
                const tax = net * 0.11;
                totalDpp += net;
                totalTax += tax;
                grandTotal += (net + tax);
            } else if (taxType === 'PPN Included' || taxType === 'Inklusif') {
                const dpp = net / 1.11;
                const tax = net - dpp;
                totalDpp += dpp;
                totalTax += tax;
                grandTotal += net;
            } else {
                totalDpp += net;
                grandTotal += net;
            }
        });

        document.getElementById('txtGrossSubtotal').innerText = 'Rp ' + formatNumber(totalGross);
        document.getElementById('txtTotalDiscount').innerText = '- Rp ' + formatNumber(totalDiscount);
        document.getElementById('txtDpp').innerText = 'Rp ' + formatNumber(totalDpp);
        document.getElementById('txtTotalTax').innerText = 'Rp ' + formatNumber(totalTax);
        document.getElementById('txtGrandTotal').innerText = 'Rp ' + formatNumber(grandTotal);
    }

    const customersData = {
        @foreach($customers as $c)
            "{{ $c->id }}": {
                address: "{{ addslashes($c->address ?? '') }}",
                tempo: {{ (int)($c->tempo_kredit ?? 30) }}
            },
        @endforeach
    };

    const currenciesData = {
        @foreach($currencies as $curr)
            "{{ $curr->id }}": {
                rate: {{ (float)($curr->to_rupiah ?? 1) }},
                code: "{{ $curr->code }}"
            },
        @endforeach
    };

    function handleCustomerChange(custId) {
        const c = customersData[custId];
        if (c) {
            const shippedTo = document.getElementById('shipped_to');
            if (shippedTo && c.address) {
                shippedTo.value = c.address;
            }
            const tempoEl = document.getElementById('tempo_pembayaran');
            if (tempoEl && c.tempo) {
                tempoEl.value = c.tempo;
            }
        }
    }

    function handleCurrencyChange(currId) {
        const curr = currenciesData[currId];
        if (curr) {
            const rateInput = document.getElementById('exchange_rate');
            if (rateInput) {
                rateInput.value = curr.rate || 1;
            }
        }
    }

    document.getElementById('customer_id')?.addEventListener('change', function() {
        handleCustomerChange(this.value);
    });

    // --- QUICK-ADD SEARCH PRODUCT ENGINE ---
    let quickHighlightedIndex = -1;
    let currentFilteredProducts = [];

    const quickSearchInput = document.getElementById('quickSearchInput');
    const quickSearchResults = document.getElementById('quickSearchResults');
    const btnQuickAddClear = document.getElementById('btnQuickAddClear');
    const quickAddFeedback = document.getElementById('quickAddFeedback');
    const quickAddContainer = document.getElementById('quickAddContainer');

    function showQuickFeedback(message, isSuccess = true) {
        if (!quickAddFeedback) return;
        quickAddFeedback.innerText = message;
        quickAddFeedback.className = `text-xs font-semibold ${isSuccess ? 'text-[#15803d]' : 'text-amber-700'} transition-all duration-300 opacity-100 translate-x-0`;
        clearTimeout(quickAddFeedback._timeout);
        quickAddFeedback._timeout = setTimeout(() => {
            quickAddFeedback.className = 'text-xs font-semibold text-[#15803d] transition-all duration-300 opacity-0 translate-x-1';
        }, 3000);
    }

    function clearQuickSearch() {
        if (!quickSearchInput) return;
        quickSearchInput.value = '';
        if (quickSearchResults) {
            quickSearchResults.innerHTML = '';
            quickSearchResults.classList.add('hidden');
        }
        if (btnQuickAddClear) btnQuickAddClear.classList.add('hidden');
        currentFilteredProducts = [];
        quickHighlightedIndex = -1;
        quickSearchInput.focus();
    }

    function renderQuickSearchResults(matches) {
        currentFilteredProducts = matches;
        quickHighlightedIndex = matches.length > 0 ? 0 : -1;

        if (!quickSearchResults) return;
        if (matches.length === 0) {
            quickSearchResults.innerHTML = `
                <div class="p-4 text-center text-xs text-gray-500">
                    Tidak ditemukan produk yang cocok dengan pencarian Anda.
                </div>
            `;
            quickSearchResults.classList.remove('hidden');
            return;
        }

        let html = '';
        matches.slice(0, 25).forEach((p, index) => {
            const isHighlighted = index === quickHighlightedIndex;
            html += `
                <div class="px-4 py-2.5 cursor-pointer flex items-center justify-between gap-3 text-xs transition ${isHighlighted ? 'bg-emerald-50/90 text-emerald-950 font-semibold' : 'hover:bg-gray-50 text-gray-800'}"
                     id="quick-item-${index}"
                     onclick="selectQuickProduct(${p.id})">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="font-mono font-bold text-emerald-700 shrink-0">[${p.sku}]</span>
                            <span class="truncate font-medium text-gray-900">${p.name}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        <span class="px-2 py-0.5 rounded text-[10.5px] font-bold bg-gray-100 text-gray-700 border border-gray-200">${p.uom || 'PCS'}</span>
                        <span class="font-mono font-bold text-[#15803d]">Rp ${formatNumber(p.sell_price)}</span>
                    </div>
                </div>
            `;
        });

        quickSearchResults.innerHTML = html;
        quickSearchResults.classList.remove('hidden');
    }

    function highlightQuickItem(index) {
        if (!quickSearchResults || currentFilteredProducts.length === 0) return;
        const total = Math.min(currentFilteredProducts.length, 25);
        if (index < 0) index = total - 1;
        if (index >= total) index = 0;
        quickHighlightedIndex = index;

        for (let i = 0; i < total; i++) {
            const el = document.getElementById(`quick-item-${i}`);
            if (el) {
                if (i === quickHighlightedIndex) {
                    el.className = 'px-4 py-2.5 cursor-pointer flex items-center justify-between gap-3 text-xs transition bg-emerald-50/90 text-emerald-950 font-semibold';
                    el.scrollIntoView({ block: 'nearest' });
                } else {
                    el.className = 'px-4 py-2.5 cursor-pointer flex items-center justify-between gap-3 text-xs transition hover:bg-gray-50 text-gray-800';
                }
            }
        }
    }

    function flashRowHighlight(idx) {
        const row = document.getElementById(`row_${idx}`);
        if (!row) return;
        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        row.classList.add('ring-2', 'ring-emerald-500', 'bg-emerald-50/60');
        setTimeout(() => {
            row.classList.remove('ring-2', 'ring-emerald-500', 'bg-emerald-50/60');
        }, 1200);
    }

    function selectQuickProduct(productId) {
        const product = productsCatalog.find(p => p.id == productId);
        if (!product) return;

        const container = document.getElementById('itemsContainer');
        const rows = document.querySelectorAll('#itemsContainer > [id^="row_"]');
        let targetRowIdx = null;
        let targetRowEl = null;
        let isExistingProduct = false;
        let emptyRowIdx = null;
        let emptyRowEl = null;

        rows.forEach(row => {
            const idx = row.id.replace('row_', '');
            const sel = row.querySelector('.product-select');
            if (sel) {
                if (sel.value == product.id) {
                    targetRowIdx = idx;
                    targetRowEl = row;
                    isExistingProduct = true;
                } else if (!sel.value && emptyRowIdx === null) {
                    emptyRowIdx = idx;
                    emptyRowEl = row;
                }
            }
        });

        if (isExistingProduct && targetRowIdx !== null) {
            // Produk sudah ada -> Naikkan kuantitas +1
            const qtyInput = targetRowEl.querySelector('.item-qty');
            if (qtyInput) {
                const curQty = parseFloat(qtyInput.value) || 0;
                const newQty = curQty + 1;
                qtyInput.value = newQty;
            }

            // PINDAHKAN KARTU KE PALING ATAS
            collapseAllRows();
            container.prepend(targetRowEl);
            const body = document.getElementById(`card_body_${targetRowIdx}`);
            const chevron = document.getElementById(`chevron_${targetRowIdx}`);
            body?.classList.remove('hidden');
            chevron?.classList.add('rotate-180');

            renumberItemRows();
            calculateRow(targetRowIdx);
            flashRowHighlight(targetRowIdx);
            const uom = document.getElementById(`uom_${targetRowIdx}`)?.innerText || product.uom;
            const finalQty = qtyInput ? qtyInput.value : 1;
            showQuickFeedback(`+1 ${product.sku} (Dipindahkan ke posisi #1 · Total: ${finalQty} ${uom})`, true);

        } else if (emptyRowIdx !== null) {
            // Ada baris kosong bawaan -> Isi dan letakkan di posisi teratas
            collapseAllRows();
            container.prepend(emptyRowEl);
            const body = document.getElementById(`card_body_${emptyRowIdx}`);
            const chevron = document.getElementById(`chevron_${emptyRowIdx}`);
            body?.classList.remove('hidden');
            chevron?.classList.add('rotate-180');

            const sel = emptyRowEl.querySelector('.product-select');
            if (sel) {
                sel.value = product.id;
                onProductChange(emptyRowIdx, true);
            }
            renumberItemRows();
            flashRowHighlight(emptyRowIdx);
            showQuickFeedback(`Item [${product.sku}] dimasukkan ke posisi teratas (#1)`, true);

        } else {
            // Tambahkan baris baru di posisi paling atas (atTop = true)
            addRow({
                product_id: product.id,
                quantity: 1,
                unit_price: product.sell_price,
                uom: product.uom
            }, true, true);

            renumberItemRows();
            const newIdx = rowCounter;
            flashRowHighlight(newIdx);
            showQuickFeedback(`Item [${product.sku}] ditambahkan di posisi teratas (#1)`, true);
        }

        clearQuickSearch();
    }

    if (quickSearchInput) {
        quickSearchInput.addEventListener('input', function () {
            const query = this.value.trim().toLowerCase();
            if (btnQuickAddClear) {
                btnQuickAddClear.classList.toggle('hidden', query.length === 0);
            }
            if (!query) {
                if (quickSearchResults) quickSearchResults.classList.add('hidden');
                currentFilteredProducts = [];
                quickHighlightedIndex = -1;
                return;
            }

            const matches = productsCatalog.filter(p => {
                const sku = (p.sku || '').toLowerCase();
                const name = (p.name || '').toLowerCase();
                return sku.includes(query) || name.includes(query);
            });

            renderQuickSearchResults(matches);
        });

        quickSearchInput.addEventListener('keydown', function (e) {
            const query = this.value.trim().toLowerCase();

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                highlightQuickItem(quickHighlightedIndex + 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                highlightQuickItem(quickHighlightedIndex - 1);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                const exactSku = productsCatalog.find(p => (p.sku || '').toLowerCase() === query);
                if (exactSku) {
                    selectQuickProduct(exactSku.id);
                    return;
                }
                if (quickHighlightedIndex >= 0 && currentFilteredProducts[quickHighlightedIndex]) {
                    selectQuickProduct(currentFilteredProducts[quickHighlightedIndex].id);
                    return;
                }
                if (currentFilteredProducts.length > 0) {
                    selectQuickProduct(currentFilteredProducts[0].id);
                }
            } else if (e.key === 'Escape') {
                e.preventDefault();
                clearQuickSearch();
            }
        });
    }

    document.addEventListener('click', function (e) {
        if (quickAddContainer && !quickAddContainer.contains(e.target)) {
            if (quickSearchResults) quickSearchResults.classList.add('hidden');
        }
    });

    document.getElementById('btnAddRow').addEventListener('click', () => addRow({}, true, true));

    // Initialize with existing items or default row
    document.addEventListener('DOMContentLoaded', () => {
        @if(old('items'))
            const oldItems = @json(old('items'));
            const itemsList = Object.values(oldItems);
            itemsList.forEach((item, index) => {
                addRow(item, index === itemsList.length - 1, false);
            });
        @elseif(isset($quotation) && $quotation->quotationItem->count() > 0)
            if (existingItems && existingItems.length > 0) {
                // Keep first row open, collapse the rest for compact clarity
                existingItems.forEach((item, idx) => addRow(item, idx === 0, false));
            } else {
                addRow({}, true, false);
            }
        @else
            addRow({}, true, false);
        @endif
        renumberItemRows();
    });
</script>
@endsection
