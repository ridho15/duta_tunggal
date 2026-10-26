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
                        <span>Tambah Baris Produk</span>
                    </button>
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

{{-- Product dataset in JSON for JavaScript --}}
<script>
    const productsCatalog = @json($products);
    const existingItems = @json($quotation->quotationItem->map(fn($item) => [
        'product_id' => $item->product_id,
        'quantity' => (float)$item->quantity,
        'unit_price' => (float)$item->unit_price,
        'discount' => (float)$item->discount,
        'tax_type' => $item->tax_type,
        'notes' => $item->notes,
        'uom' => $item->product?->uom?->abbreviation ?? 'PCS'
    ]));

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

    function updateSummary(idx) {
        const tr = document.getElementById(`row_${idx}`);
        if (!tr) return;
        const select = tr.querySelector('.product-select');
        const selectedOption = select.options[select.selectedIndex];
        const qty = parseFloat(tr.querySelector('.item-qty')?.value) || 0;
        const uom = document.getElementById(`uom_${idx}`)?.innerText || 'PCS';
        const subtotalText = document.getElementById(`badge_subtotal_${idx}`)?.innerText || 'Rp 0';

        const summaryEl = document.getElementById(`summary_${idx}`);
        if (summaryEl) {
            if (selectedOption && selectedOption.value) {
                const sku = selectedOption.dataset.sku ? `[${selectedOption.dataset.sku}] ` : '';
                const name = selectedOption.text.split('–')[1]?.trim() || selectedOption.text;
                summaryEl.innerText = `${sku}${name} · ${qty} ${uom} · ${subtotalText}`;
            } else {
                summaryEl.innerText = `Item #${idx} — Pilih Produk...`;
            }
        }
    }

    function addRow(data = {}, isOpen = true) {
        // Auto-collapse older rows when user explicitly clicks add row
        if (isOpen && rowCounter > 0 && !data.product_id) {
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
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-[#f0fdf4] text-[#15803d] font-bold text-[11px] border border-[#86efac] shrink-0">
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

        container.appendChild(card);

        if (data.product_id) {
            onProductChange(idx, false);
        }
        calculateRow(idx);
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

    document.getElementById('btnAddRow').addEventListener('click', () => addRow({}, true));

    // Initialize with existing items or default row
    document.addEventListener('DOMContentLoaded', () => {
        @if(old('items'))
            const oldItems = @json(old('items'));
            const itemsList = Object.values(oldItems);
            itemsList.forEach((item, index) => {
                addRow(item, index === itemsList.length - 1);
            });
        @elseif(isset($quotation) && $quotation->quotationItem->count() > 0)
            if (existingItems && existingItems.length > 0) {
                // Keep first row open, collapse the rest for compact clarity
                existingItems.forEach((item, idx) => addRow(item, idx === 0));
            } else {
                addRow({}, true);
            }
        @else
            addRow({}, true);
        @endif
    });
</script>
@endsection
