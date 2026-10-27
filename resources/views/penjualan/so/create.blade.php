@extends('layouts.app')

@section('title', 'Buat Pesanan Penjualan – ERP Duta Tunggal')

@section('content')
<div class="max-w-[1080px] mx-auto w-full flex flex-col gap-5">
    <!-- Header Page -->
    <div class="pb-5 border-b border-gray-200">
        <div class="flex items-center gap-2 text-xs sm:text-sm text-gray-500 mb-1">
            <a href="{{ route('penjualan.so.index') }}" class="hover:text-[#15803d] transition-colors">Pesanan Penjualan</a>
            <span class="text-gray-300">/</span>
            <span class="font-semibold text-gray-800">Buat baru</span>
        </div>
        <h1 class="text-2xl lg:text-[28px] font-extrabold text-gray-900 m-0">Buat Pesanan Penjualan</h1>
        <div class="text-sm text-gray-500 mt-1">Nomor SO, cabang, dan tanggal terisi otomatis saat disimpan.</div>
    </div>

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-800">
            <div class="font-bold mb-1">Terdapat kesalahan pengisian formulir:</div>
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('penjualan.so.store') }}" id="soForm" class="flex flex-wrap lg:flex-nowrap gap-5 items-start">
        @csrf
        <input type="hidden" name="action" id="formAction" value="request_approve">

        <!-- Kolom Kiri: Form Input -->
        <div class="w-full lg:flex-1 flex flex-col gap-4 min-w-0">
            
            <!-- Section 1: Customer -->
            <section class="bg-white border border-gray-100 rounded-2xl p-5 sm:p-6 flex flex-col gap-4 shadow-sm">
                <div class="flex items-center gap-3 pb-3.5 border-b border-gray-100">
                    <span class="w-7 h-7 rounded-lg bg-emerald-50 text-[#15803d] border border-[#15803d]/20 inline-flex items-center justify-center font-extrabold text-sm">1</span>
                    <h2 class="m-0 text-base font-bold text-gray-900">Customer & Pengiriman</h2>
                </div>

                @if(isset($quotations) && $quotations->isNotEmpty())
                    <div class="flex flex-col gap-1.5 pb-2 border-b border-gray-100">
                        <x-searchable-select
                            name="quotation_id"
                            id="quotationSelect"
                            label="Berdasarkan Penawaran Harga (Quotation) - Opsional"
                            :options="$quotations->map(fn($qo) => [
                                'id' => $qo->id,
                                'label' => $qo->quotation_number . ' – ' . ($qo->customer->name ?? '-'),
                                'subtext' => 'Total: Rp ' . number_format($qo->total_amount, 0, ',', '.'),
                                'badge' => 'Penawaran'
                            ])"
                            :value="old('quotation_id', $selectedQuotationId ?? null)"
                            placeholder="-- Tanpa Penawaran (SO Langsung) --"
                            :required="false"
                        />
                    </div>
                @else
                    <input type="hidden" name="quotation_id" value="{{ old('quotation_id', $selectedQuotationId ?? '') }}">
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div class="flex flex-col gap-1.5" @change="if ($event.detail !== undefined) handleCustomerSelectChange($event.detail)">
                        <x-searchable-select
                            name="customer_id"
                            id="customerSelect"
                            label="Customer"
                            :options="$customers->map(fn($c) => [
                                'id' => $c->id,
                                'label' => $c->name . ($c->code ? ' (' . $c->code . ')' : ''),
                                'subtext' => ($c->address ? \Illuminate\Support\Str::limit($c->address, 35) : '') . ' | Limit: Rp ' . number_format($c->kredit_limit, 0, ',', '.') . ' | Tipe: ' . $c->tipe_pembayaran,
                                'badge' => $c->tipe_pembayaran ?? 'Customer'
                            ])"
                            :value="old('customer_id', $selectedQuotation->customer_id ?? null)"
                            placeholder="-- Pilih Customer --"
                            :required="true"
                        />
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <x-searchable-select
                            name="cabang_id"
                            label="Cabang"
                            :options="$cabangs->map(fn($cb) => [
                                'id' => $cb->id,
                                'label' => $cb->nama,
                                'subtext' => $cb->kode ?? '',
                                'badge' => $cb->kode ?? ''
                            ])"
                            :value="old('cabang_id', $user?->cabang_id)"
                            placeholder="-- Pilih Cabang --"
                            :required="true"
                        />
                    </div>
                </div>

                <!-- Info Limit Kredit Customer -->
                <div class="grid grid-cols-3 gap-2.5 pt-1" id="creditSummaryCard">
                    <div class="border border-dashed border-gray-300 rounded-xl p-3 bg-gray-50/50">
                        <div class="text-[11px] text-gray-500">Limit kredit</div>
                        <div class="font-bold text-sm text-gray-800 mt-0.5" id="valKreditLimit">Rp 0</div>
                    </div>
                    <div class="border border-dashed border-gray-300 rounded-xl p-3 bg-gray-50/50">
                        <div class="text-[11px] text-gray-500">Saldo Titip / Terpakai</div>
                        <div class="font-bold text-sm text-gray-800 mt-0.5" id="valDepositBalance">Rp 0</div>
                    </div>
                    <div class="border border-[#15803d]/30 bg-emerald-50/60 rounded-xl p-3" id="boxSisaLimit">
                        <div class="text-[11px] text-[#15803d] font-semibold">Tipe Pembayaran</div>
                        <div class="font-bold text-sm text-[#15803d] mt-0.5" id="valTipePembayaran">-</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5 pt-2 border-t border-gray-100">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-semibold text-gray-700">Tanggal Order <span class="text-red-500">*</span></label>
                        <input type="date" name="order_date" value="{{ old('order_date', now()->format('Y-m-d')) }}" required class="border border-gray-300 rounded-xl px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d]">
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-semibold text-gray-700">Estimasi Kirim</label>
                        <input type="date" name="delivery_date" value="{{ old('delivery_date', now()->addDays(7)->format('Y-m-d')) }}" class="border border-gray-300 rounded-xl px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d]">
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-semibold text-gray-700">Tipe Pengiriman <span class="text-red-500">*</span></label>
                        <select name="tipe_pengiriman" class="border border-gray-300 rounded-xl px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] bg-white">
                            <option value="Kirim Langsung" {{ old('tipe_pengiriman') === 'Kirim Langsung' ? 'selected' : '' }}>Kirim Langsung</option>
                            <option value="Ambil Sendiri" {{ old('tipe_pengiriman') === 'Ambil Sendiri' ? 'selected' : '' }}>Ambil Sendiri</option>
                        </select>
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-semibold text-gray-700">Alamat Kirim</label>
                    <input type="text" name="shipped_to" id="shippedToInput" value="{{ old('shipped_to') }}" placeholder="Otomatis dari data customer jika kosong" class="border border-gray-300 rounded-xl px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d]">
                </div>
            </section>

            <!-- Section 2: Barang -->
            <section class="bg-white border border-gray-100 rounded-2xl p-5 sm:p-6 flex flex-col gap-4 shadow-sm">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3.5 border-b border-gray-100">
                    <div class="flex items-center gap-3">
                        <span class="w-7 h-7 rounded-lg bg-emerald-50 text-[#15803d] border border-[#15803d]/20 inline-flex items-center justify-center font-extrabold text-sm">2</span>
                        <h2 class="m-0 text-base font-bold text-gray-900">Barang</h2>
                        <span class="text-xs text-gray-500" id="itemCountLabel">0 item</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="collapseAllSoItems()"
                                class="px-2.5 py-1.5 text-xs font-semibold text-gray-600 bg-gray-50 hover:bg-gray-100 rounded-xl border border-gray-200 transition">
                            Tutup Semua
                        </button>
                        <button type="button" onclick="expandAllSoItems()"
                                class="px-2.5 py-1.5 text-xs font-semibold text-gray-600 bg-gray-50 hover:bg-gray-100 rounded-xl border border-gray-200 transition">
                            Buka Semua
                        </button>
                        <button type="button" id="addItemBtn"
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
                                    class="hidden px-2 py-1 text-[11px] font-semibold text-gray-500 hover:text-gray-800 hover:bg-gray-100 rounded-lg transition"
                                    title="Bersihkan pencarian">
                                Bersihkan
                            </button>
                            <span class="hidden sm:inline-flex items-center gap-1 px-2 py-0.5 text-[10.5px] font-mono font-semibold text-emerald-800 bg-emerald-100/80 border border-emerald-200 rounded-md">
                                <svg class="w-3 h-3 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                                </svg>
                                Scanner Ready
                            </span>
                        </div>
                    </div>

                    <!-- Dropdown List Hasil Pencarian (Floating) -->
                    <div id="quickSearchResults"
                         class="hidden absolute left-3 right-3 sm:left-4 sm:right-4 top-full mt-1.5 max-h-72 overflow-y-auto bg-white border border-gray-200 rounded-2xl shadow-xl z-50 divide-y divide-gray-100">
                        <!-- Populated dynamically by JS -->
                    </div>

                    <div class="mt-2 text-[11px] text-gray-500 flex flex-wrap items-center justify-between gap-2 px-1">
                        <span>Pilih produk / tekan <b>Enter</b>: otomatis menambah baris atau menambah <b>Qty (+1)</b> bila item sudah ada.</span>
                        <span class="text-gray-400 font-mono text-[10px]">Navigasi: ↑ ↓ Enter · Esc untuk tutup</span>
                    </div>
                </div>

                <!-- Container Baris Barang -->
                <div id="itemsContainer" class="flex flex-col gap-3">
                    <!-- Dynamic Rows Rendered by JS -->
                </div>

                <!-- Ongkir & Catatan Tambahan -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 pt-3 border-t border-gray-100">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-semibold text-gray-700">Ongkos Kirim</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-xs font-semibold text-gray-400 pointer-events-none">Rp</span>
                            <input type="text" id="shippingCostDisplay" value="{{ old('shipping_cost') ? number_format(old('shipping_cost'), 0, ',', '.') : '0' }}"
                                   oninput="handleShippingCostInput(this.value)"
                                   placeholder="0"
                                   class="w-full pl-9 pr-3 py-2 text-sm text-right font-mono font-semibold text-gray-800 bg-white border border-gray-300 rounded-xl focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d]">
                            <input type="hidden" name="shipping_cost" id="shippingCostInput" value="{{ old('shipping_cost', 0) }}">
                        </div>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <x-searchable-select
                            name="currency_id"
                            label="Mata Uang"
                            :options="$currencies->map(fn($curr) => [
                                'id' => $curr->id,
                                'label' => $curr->code . ' - ' . $curr->name,
                                'badge' => $curr->code
                            ])"
                            :value="old('currency_id', $defaultCurrencyId)"
                            placeholder="-- Pilih Mata Uang --"
                        />
                    </div>
                </div>

                <div x-data="{ openNotes: false }" class="border border-gray-200 rounded-xl overflow-hidden bg-gray-50/50">
                    <button type="button" @click="openNotes = !openNotes" class="w-full flex items-center justify-between p-3 text-xs font-semibold text-gray-700 hover:text-[#15803d] select-none">
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-[#15803d]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            <span>Catatan Tambahan (Internal Pesanan)</span>
                        </span>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': openNotes }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="openNotes" x-collapse class="p-3 pt-0">
                        <textarea name="notes" rows="2" placeholder="Catatan internal pesanan..." class="w-full border border-gray-300 rounded-xl p-2.5 text-xs text-gray-800 bg-white focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d]">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </section>
        </div>

        <!-- Kolom Kanan: Ringkasan Sticky (Aside) -->
        <aside class="w-full lg:w-[320px] shrink-0 bg-white border border-gray-100 rounded-2xl p-5 sm:p-6 flex flex-col gap-3.5 shadow-sm sticky top-[70px]">
            <h2 class="m-0 text-base font-bold text-gray-900 border-b border-gray-100 pb-3">Ringkasan</h2>

            <div class="flex justify-between text-sm text-gray-600">
                <span>Subtotal barang</span>
                <span class="font-mono font-semibold text-gray-900" id="summarySubtotal">Rp 0</span>
            </div>
            <div class="flex justify-between text-sm text-gray-600">
                <span>Ongkos kirim</span>
                <span class="font-mono font-semibold text-gray-900" id="summaryOngkir">Rp 0</span>
            </div>

            <div class="flex justify-between text-lg font-extrabold text-gray-900 border-t border-gray-100 pt-3">
                <span>Total</span>
                <span id="summaryTotal" class="font-mono text-[#15803d]">Rp 0</span>
            </div>

            <!-- Price Violation Warning Card -->
            <div id="priceViolationAlert" class="hidden bg-red-50 border border-red-200 rounded-xl p-3 text-xs text-red-900">
                <span class="font-bold">Harga di bawah batas.</span> SO tetap tersimpan, namun membutuhkan approve atasan (bukan kasir).
            </div>

            <!-- Credit Limit Status Card -->
            <div id="creditStatusAlert" class="bg-emerald-50 border border-[#15803d]/30 rounded-xl p-3 text-xs text-[#15803d] font-medium">
                Pilih customer untuk melihat status limit kredit.
            </div>

            <button type="button" onclick="submitSo('request_approve')" class="w-full py-3 bg-[#15803d] hover:bg-[#166534] text-white font-bold text-sm rounded-xl min-h-[46px] shadow-sm transition-colors cursor-pointer mt-1">
                Simpan & Ajukan Approve
            </button>
            <button type="button" onclick="submitSo('draft')" class="w-full py-2.5 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 font-semibold text-sm rounded-xl min-h-[42px] shadow-sm transition-colors cursor-pointer">
                Simpan sebagai draft
            </button>
        </aside>
    </form>
</div>

<!-- Template Script & Interaktivitas Form -->
<script>
    const productsData = @json($products);
    let itemIndex = 0;

    function formatRupiah(amount) {
        return 'Rp ' + Number(amount || 0).toLocaleString('id-ID');
    }

    function toggleSoItem(idx) {
        const body = document.getElementById(`item-body-${idx}`);
        const chevron = document.getElementById(`chevron-${idx}`);
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

    function collapseAllSoItems() {
        document.querySelectorAll('[id^="item-body-"]').forEach(el => el.classList.add('hidden'));
        document.querySelectorAll('[id^="chevron-"]').forEach(el => el.classList.remove('rotate-180'));
    }

    function expandAllSoItems() {
        document.querySelectorAll('[id^="item-body-"]').forEach(el => el.classList.remove('hidden'));
        document.querySelectorAll('[id^="chevron-"]').forEach(el => el.classList.add('rotate-180'));
    }

    function handleShippingCostInput(rawVal) {
        const cleanDigits = String(rawVal).replace(/\D/g, '');
        const numVal = parseFloat(cleanDigits) || 0;
        document.getElementById('shippingCostInput').value = numVal;
        document.getElementById('shippingCostDisplay').value = numVal > 0 ? Number(numVal).toLocaleString('id-ID') : '0';
        calculateTotals();
    }

    function handleSoPriceInput(idx, rawVal) {
        const cleanDigits = String(rawVal).replace(/\D/g, '');
        const numVal = parseFloat(cleanDigits) || 0;
        document.getElementById(`unit-price-${idx}`).value = numVal;
        document.getElementById(`display-price-${idx}`).value = numVal > 0 ? Number(numVal).toLocaleString('id-ID') : '';
        calculateTotals();
    }

    function renumberItemRows() {
        const rows = document.querySelectorAll('#itemsContainer > [id^="item-row-"]');
        rows.forEach((row, i) => {
            const visualNum = i + 1;
            const badge = row.querySelector('.item-badge-number');
            if (badge) {
                badge.innerText = visualNum;
            }
            const idx = row.id.replace('item-row-', '');
            updateSoItemSummary(idx, visualNum);
        });
    }

    function updateSoItemSummary(idx, visualNum = null) {
        const row = document.getElementById(`item-row-${idx}`);
        if (!row) return;

        if (visualNum === null) {
            const rows = Array.from(document.querySelectorAll('#itemsContainer > [id^="item-row-"]'));
            const foundIdx = rows.indexOf(row);
            visualNum = foundIdx >= 0 ? foundIdx + 1 : parseInt(idx) + 1;
        }

        const select = row.querySelector(`select[name="items[${idx}][product_id]"]`);
        const opt = select?.selectedOptions[0];
        const qty = parseFloat(row.querySelector(`input[name="items[${idx}][quantity]"]`)?.value || 0);
        const uom = document.getElementById(`item-uom-${idx}`)?.innerText || 'PCS';
        const subtotalText = document.getElementById(`item-subtotal-${idx}`)?.innerText || 'Rp 0';
        const summaryEl = document.getElementById(`item-summary-${idx}`);

        if (summaryEl) {
            if (opt && opt.value) {
                const sku = opt.getAttribute('data-sku') || '';
                const name = opt.text.split('–')[1]?.trim() || opt.text.split('(')[0]?.trim() || opt.text;
                summaryEl.innerText = `#${visualNum} · ${sku ? '[' + sku + '] ' : ''}${name} · ${qty} ${uom} · ${subtotalText}`;
            } else {
                summaryEl.innerText = `Item #${visualNum} — Pilih Produk...`;
            }
        }
    }

    function createItemRow(prefill = {}, isOpen = true, atTop = true) {
        if (isOpen && itemIndex > 0) {
            collapseAllSoItems();
        }

        const idx = itemIndex++;
        const row = document.createElement('div');
        row.className = 'border border-gray-200 rounded-2xl overflow-hidden bg-white hover:border-gray-300 transition-all shadow-xs';
        row.id = `item-row-${idx}`;

        let productOptions = '<option value="">-- Pilih Produk --</option>';
        productsData.forEach(p => {
            const isSelected = prefill.product_id == p.id ? 'selected' : '';
            productOptions += `<option value="${p.id}" data-price="${p.sell_price}" data-bottom="${p.bottom_price}" data-stock="${p.free_stock}" data-uom="${p.uom}" data-sku="${p.sku}" ${isSelected}>${p.sku} – ${p.name}</option>`;
        });

        const rawPrice = parseFloat(prefill.unit_price) || 0;

        row.innerHTML = `
            {{-- Header Accordion --}}
            <div class="flex items-center justify-between px-4 py-3 bg-gray-50/80 cursor-pointer select-none border-b border-gray-100 hover:bg-gray-100/60 transition" onclick="toggleSoItem(${idx})">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="item-badge-number inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-50 text-emerald-700 font-bold text-[11px] border border-emerald-200 shrink-0">
                        1
                    </span>
                    <div class="truncate">
                        <span id="item-summary-${idx}" class="text-xs font-bold text-gray-800">
                            Item #1 — Pilih Produk...
                        </span>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <span id="item-subtotal-${idx}" class="text-xs font-mono font-bold text-emerald-700 mr-1">
                        Rp 0
                    </span>
                    <button type="button" onclick="event.stopPropagation(); removeItemRow(${idx})" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="Hapus item">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                    <div id="chevron-${idx}" class="text-gray-400 transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                </div>
            </div>

            {{-- Form Body --}}
            <div id="item-body-${idx}" class="p-4 flex flex-col gap-3 ${isOpen ? '' : 'hidden'}">
                <div class="flex flex-col gap-1.5">
                    <label class="text-[11px] font-bold text-gray-700">Produk <span class="text-red-500">*</span></label>
                    <select data-searchable name="items[${idx}][product_id]" required onchange="handleProductChange(${idx})" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs font-semibold text-gray-800 focus:outline-none focus:border-emerald-600 bg-white">
                        ${productOptions}
                    </select>
                </div>

                <div class="flex flex-wrap items-center gap-2 text-xs" id="item-meta-${idx}">
                    <span class="text-gray-500 text-[11px]" id="item-sku-${idx}">Pilih produk terlebih dahulu</span>
                    <span class="hidden rounded px-2 py-0.5 font-bold text-[11px]" id="item-stock-badge-${idx}"></span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-gray-100">
                    <div>
                        <label class="text-[11px] font-bold text-gray-700">Qty (<span id="item-uom-${idx}">PCS</span>) <span class="text-red-500">*</span></label>
                        <input type="number" name="items[${idx}][quantity]" value="${prefill.quantity || 1}" min="0.01" step="any" required oninput="calculateTotals()" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs font-bold text-gray-800 text-right font-mono focus:outline-none focus:border-emerald-600">
                        <div class="text-[10.5px] text-amber-700 font-semibold mt-1 hidden" id="item-stock-warning-${idx}"></div>
                    </div>
                    <div>
                        <label class="text-[11px] font-bold text-gray-700">Harga Satuan <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-xs font-semibold text-gray-400 pointer-events-none">Rp</span>
                            <input type="text" id="display-price-${idx}" value="${rawPrice > 0 ? Number(rawPrice).toLocaleString('id-ID') : ''}"
                                   oninput="handleSoPriceInput(${idx}, this.value)"
                                   placeholder="0"
                                   class="w-full pl-9 pr-3 py-2 text-xs font-bold text-gray-800 text-right font-mono border border-gray-300 rounded-xl focus:outline-none focus:border-emerald-600">
                            <input type="hidden" name="items[${idx}][unit_price]" id="unit-price-${idx}" value="${rawPrice}">
                        </div>
                        <div class="text-[10.5px] text-gray-500 mt-1" id="item-floor-info-${idx}">Min. Rp 0</div>
                    </div>
                </div>
            </div>
        `;

        const container = document.getElementById('itemsContainer');
        if (atTop && container.firstChild) {
            container.prepend(row);
        } else {
            container.appendChild(row);
        }

        if (prefill.product_id) {
            handleProductChange(idx, false);
        }
        renumberItemRows();
        calculateTotals();
    }

    function removeItemRow(idx) {
        const row = document.getElementById(`item-row-${idx}`);
        if (row) {
            row.remove();
            renumberItemRows();
            calculateTotals();
        }
    }

    function handleProductChange(idx, resetPrice = true) {
        const row = document.getElementById(`item-row-${idx}`);
        if (!row) return;

        const select = row.querySelector(`select[name="items[${idx}][product_id]"]`);
        const opt = select?.selectedOptions[0];
        if (!opt || !opt.value) {
            document.getElementById(`item-sku-${idx}`).innerText = 'Pilih produk terlebih dahulu';
            document.getElementById(`item-stock-badge-${idx}`).className = 'hidden';
            return;
        }

        const price = parseFloat(opt.getAttribute('data-price') || 0);
        const bottom = parseFloat(opt.getAttribute('data-bottom') || 0);
        const stock = parseFloat(opt.getAttribute('data-stock') || 0);
        const uom = opt.getAttribute('data-uom') || 'PCS';
        const sku = opt.getAttribute('data-sku') || '-';

        document.getElementById(`item-sku-${idx}`).innerText = sku;
        document.getElementById(`item-uom-${idx}`).innerText = uom;

        const badge = document.getElementById(`item-stock-badge-${idx}`);
        badge.className = 'inline-block rounded px-2 py-0.5 font-bold text-[11px] bg-emerald-50 text-emerald-800 border border-emerald-200';
        badge.innerText = `Stok bebas ${stock} ${uom}`;

        const priceHidden = document.getElementById(`unit-price-${idx}`);
        const priceDisplay = document.getElementById(`display-price-${idx}`);
        if (resetPrice && (!priceHidden.value || parseFloat(priceHidden.value) === 0)) {
            priceHidden.value = price;
            priceDisplay.value = price > 0 ? Number(price).toLocaleString('id-ID') : '';
        }

        document.getElementById(`item-floor-info-${idx}`).innerText = bottom > 0 ? `Min. ${formatRupiah(bottom)}` : 'Tanpa batas minimum';

        calculateTotals();
    }

    function calculateTotals() {
        let subtotalBarang = 0;
        let hasPriceViolation = false;
        const rows = document.querySelectorAll('[id^="item-row-"]');

        rows.forEach(row => {
            const idx = row.id.replace('item-row-', '');
            const select = row.querySelector(`select[name="items[${idx}][product_id]"]`);
            const opt = select?.selectedOptions[0];
            const qtyInput = row.querySelector(`input[name="items[${idx}][quantity]"]`);
            const priceInput = document.getElementById(`unit-price-${idx}`);
            const displayPriceInput = document.getElementById(`display-price-${idx}`);

            const qty = parseFloat(qtyInput?.value || 0);
            const price = parseFloat(priceInput?.value || 0);
            const lineSubtotal = qty * price;

            const subtotalEl = document.getElementById(`item-subtotal-${idx}`);
            if (subtotalEl) {
                subtotalEl.innerText = formatRupiah(lineSubtotal);
            }

            subtotalBarang += lineSubtotal;

            if (opt && opt.value) {
                const bottom = parseFloat(opt.getAttribute('data-bottom') || 0);
                const stock = parseFloat(opt.getAttribute('data-stock') || 0);
                const floorInfo = document.getElementById(`item-floor-info-${idx}`);
                const warningEl = document.getElementById(`item-stock-warning-${idx}`);

                if (bottom > 0 && price < bottom) {
                    hasPriceViolation = true;
                    if (floorInfo) {
                        floorInfo.innerText = `Di bawah min. ${formatRupiah(bottom)}`;
                        floorInfo.className = 'text-[10.5px] text-red-600 font-bold mt-1';
                    }
                    if (displayPriceInput) {
                        displayPriceInput.className = 'w-full pl-9 pr-3 py-2 text-xs font-bold text-gray-800 text-right font-mono border-1.5 border-red-500 rounded-xl focus:outline-none';
                    }
                } else {
                    if (floorInfo) {
                        floorInfo.innerText = bottom > 0 ? `Min. ${formatRupiah(bottom)}` : 'Tanpa batas minimum';
                        floorInfo.className = 'text-[10.5px] text-gray-500 mt-1';
                    }
                    if (displayPriceInput) {
                        displayPriceInput.className = 'w-full pl-9 pr-3 py-2 text-xs font-bold text-gray-800 text-right font-mono border border-gray-300 rounded-xl focus:outline-none focus:border-emerald-600';
                    }
                }

                if (warningEl) {
                    if (qty > stock) {
                        const selisih = qty - stock;
                        warningEl.innerText = `Stok kurang ${selisih} · Sisa dikirim menyusul`;
                        warningEl.classList.remove('hidden');
                    } else {
                        warningEl.classList.add('hidden');
                    }
                }
            }

            updateSoItemSummary(idx);
        });

        const ongkir = parseFloat(document.getElementById('shippingCostInput').value || 0);
        const grandTotal = subtotalBarang + ongkir;

        document.getElementById('summarySubtotal').innerText = formatRupiah(subtotalBarang);
        document.getElementById('summaryOngkir').innerText = formatRupiah(ongkir);
        document.getElementById('summaryTotal').innerText = formatRupiah(grandTotal);
        document.getElementById('itemCountLabel').innerText = `${rows.length} item`;

        // Update Price Violation Alert
        const alertEl = document.getElementById('priceViolationAlert');
        if (hasPriceViolation) {
            alertEl.classList.remove('hidden');
        } else {
            alertEl.classList.add('hidden');
        }

        // Cek Limit Kredit
        const custId = document.getElementById('customerSelect')?.value;
        const cust = customersData[custId];
        const creditAlert = document.getElementById('creditStatusAlert');

        if (cust) {
            const tipe = cust.tipe;
            const limit = cust.limit;
            const deposit = cust.deposit;

            if (tipe === 'Kredit' && limit > 0) {
                if (grandTotal > limit) {
                    creditAlert.className = 'bg-red-50 border border-red-200 rounded-xl p-3 text-xs text-red-900 font-bold';
                    creditAlert.innerText = `Total pesanan melebihi limit kredit customer (${formatRupiah(limit)})!`;
                } else {
                    creditAlert.className = 'bg-emerald-50 border border-emerald-200 rounded-xl p-3 text-xs text-emerald-900 font-medium';
                    creditAlert.innerText = `Masih dalam limit kredit customer (Sisa: ${formatRupiah(limit - grandTotal)}).`;
                }
            } else if (tipe && tipe.includes('COD')) {
                creditAlert.className = 'bg-blue-50 border border-blue-200 rounded-xl p-3 text-xs text-blue-900 font-medium';
                creditAlert.innerText = `Customer bertipe COD. Saldo titip tersedia: ${formatRupiah(deposit)}.`;
            } else {
                creditAlert.className = 'bg-gray-50 border border-gray-200 rounded-xl p-3 text-xs text-gray-700';
                creditAlert.innerText = `Tipe Pembayaran: ${tipe || 'Cash'}.`;
            }
        } else {
            creditAlert.className = 'bg-emerald-50 border border-emerald-200 rounded-xl p-3 text-xs text-emerald-900 font-medium';
            creditAlert.innerText = 'Pilih customer untuk melihat status limit kredit.';
        }
    }

    const customersData = {
        @foreach($customers as $c)
            "{{ $c->id }}": {
                id: "{{ $c->id }}",
                name: "{{ addslashes($c->name) }}",
                tipe: "{{ $c->tipe_pembayaran ?? 'Tunai' }}",
                limit: {{ (float)$c->kredit_limit }},
                tempo: {{ (int)$c->tempo_kredit }},
                address: "{{ addslashes($c->address ?? '') }}",
                deposit: {{ (float)($c->deposit?->remaining_amount ?? 0) }}
            },
        @endforeach
    };

    function handleCustomerSelectChange(custId) {
        const cust = customersData[custId];
        if (!cust) {
            document.getElementById('valKreditLimit').innerText = 'Rp 0';
            document.getElementById('valDepositBalance').innerText = 'Rp 0';
            document.getElementById('valTipePembayaran').innerText = '-';
            calculateTotals();
            return;
        }

        document.getElementById('valKreditLimit').innerText = formatRupiah(cust.limit);
        document.getElementById('valDepositBalance').innerText = formatRupiah(cust.deposit);
        document.getElementById('valTipePembayaran').innerText = cust.tipe;

        const shippedInput = document.getElementById('shippedToInput');
        if (shippedInput && !shippedInput.value) {
            shippedInput.value = cust.address;
        }

        calculateTotals();
    }

    document.getElementById('customerSelect')?.addEventListener('change', function () {
        handleCustomerSelectChange(this.value);
    });

    document.getElementById('addItemBtn').addEventListener('click', () => createItemRow({}, true));

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
            const stockBadge = p.free_stock > 0
                ? `<span class="px-2 py-0.5 rounded text-[10.5px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Stok: ${p.free_stock} ${p.uom}</span>`
                : `<span class="px-2 py-0.5 rounded text-[10.5px] font-bold bg-amber-50 text-amber-700 border border-amber-200">Stok: 0 ${p.uom}</span>`;

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
                        ${stockBadge}
                        <span class="font-mono font-bold text-gray-800">${formatRupiah(p.sell_price)}</span>
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
        const row = document.getElementById(`item-row-${idx}`);
        if (!row) return;
        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        row.classList.add('ring-2', 'ring-emerald-500', 'bg-emerald-50/60');
        setTimeout(() => {
            row.classList.remove('ring-2', 'ring-emerald-500', 'bg-emerald-50/60');
        }, 1200);
    }

    function selectQuickProduct(productId) {
        const product = productsData.find(p => p.id == productId);
        if (!product) return;

        const container = document.getElementById('itemsContainer');
        const rows = document.querySelectorAll('#itemsContainer > [id^="item-row-"]');
        let targetRowIdx = null;
        let targetRowEl = null;
        let isExistingProduct = false;
        let emptyRowIdx = null;
        let emptyRowEl = null;

        rows.forEach(row => {
            const idx = row.id.replace('item-row-', '');
            const sel = row.querySelector(`select[name="items[${idx}][product_id]"]`);
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
            const qtyInput = document.querySelector(`input[name="items[${targetRowIdx}][quantity]"]`);
            if (qtyInput) {
                const curQty = parseFloat(qtyInput.value) || 0;
                const newQty = curQty + 1;
                qtyInput.value = newQty;
            }

            // PINDAHKAN KARTU KE PALING ATAS
            collapseAllSoItems();
            container.prepend(targetRowEl);
            const body = document.getElementById(`item-body-${targetRowIdx}`);
            const chevron = document.getElementById(`chevron-${targetRowIdx}`);
            body?.classList.remove('hidden');
            chevron?.classList.add('rotate-180');

            renumberItemRows();
            calculateTotals();
            flashRowHighlight(targetRowIdx);
            const uom = document.getElementById(`item-uom-${targetRowIdx}`)?.innerText || product.uom;
            const finalQty = qtyInput ? qtyInput.value : 1;
            showQuickFeedback(`+1 ${product.sku} (Dipindahkan ke posisi #1 · Total: ${finalQty} ${uom})`, true);

        } else if (emptyRowIdx !== null) {
            // Ada baris kosong bawaan -> Isi dan letakkan di posisi teratas
            collapseAllSoItems();
            container.prepend(emptyRowEl);
            const body = document.getElementById(`item-body-${emptyRowIdx}`);
            const chevron = document.getElementById(`chevron-${emptyRowIdx}`);
            body?.classList.remove('hidden');
            chevron?.classList.add('rotate-180');

            const sel = document.querySelector(`select[name="items[${emptyRowIdx}][product_id]"]`);
            if (sel) {
                sel.value = product.id;
                handleProductChange(emptyRowIdx, true);
            }
            renumberItemRows();
            flashRowHighlight(emptyRowIdx);
            showQuickFeedback(`Item [${product.sku}] dimasukkan ke posisi teratas (#1)`, true);

        } else {
            // Tambahkan baris baru di posisi paling atas (atTop = true)
            createItemRow({
                product_id: product.id,
                quantity: 1,
                unit_price: product.sell_price
            }, true, true);

            renumberItemRows();
            const newIdx = itemIndex - 1;
            flashRowHighlight(newIdx);
            showQuickFeedback(`Item [${product.sku}] ditambahkan di posisi teratas (#1)`, true);
        }

        clearQuickSearch();
    }

    // Event listener untuk input pencarian
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

            const matches = productsData.filter(p => {
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
                // 1. Cek jika ada produk yang match persis SKU (Barcode Scanner case)
                const exactSku = productsData.find(p => (p.sku || '').toLowerCase() === query);
                if (exactSku) {
                    selectQuickProduct(exactSku.id);
                    return;
                }
                // 2. Cek item yang sedang di-highlight
                if (quickHighlightedIndex >= 0 && currentFilteredProducts[quickHighlightedIndex]) {
                    selectQuickProduct(currentFilteredProducts[quickHighlightedIndex].id);
                    return;
                }
                // 3. Fallback: pilih item pertama jika ada hasil
                if (currentFilteredProducts.length > 0) {
                    selectQuickProduct(currentFilteredProducts[0].id);
                }
            } else if (e.key === 'Escape') {
                e.preventDefault();
                clearQuickSearch();
            }
        });
    }

    // Klik di luar container untuk menutup dropdown
    document.addEventListener('click', function (e) {
        if (quickAddContainer && !quickAddContainer.contains(e.target)) {
            if (quickSearchResults) quickSearchResults.classList.add('hidden');
        }
    });

    document.getElementById('addItemBtn').addEventListener('click', () => createItemRow({}, true, true));

    function submitSo(actionType) {
        document.getElementById('formAction').value = actionType;
        document.getElementById('soForm').submit();
    }

    @php
        $qoItemsData = $selectedQuotation ? $selectedQuotation->quotationItem->map(fn($item) => [
            'product_id' => $item->product_id,
            'quantity' => (float)$item->quantity,
            'unit_price' => (float)$item->unit_price,
        ])->values()->all() : [];
    @endphp
    const quotationItems = @json($qoItemsData);

    // Buat item awal saat halaman pertama kali dibuka
    window.addEventListener('DOMContentLoaded', () => {
        const initialCustId = document.getElementById('customerSelect')?.value;
        if (initialCustId) {
            handleCustomerSelectChange(initialCustId);
        }

        @if(old('items'))
            const oldItems = @json(old('items'));
            const itemsList = Object.values(oldItems);
            itemsList.forEach((item, index) => {
                createItemRow(item, index === itemsList.length - 1, false);
            });
        @elseif($selectedQuotation && count($qoItemsData) > 0)
            if (quotationItems && quotationItems.length > 0) {
                quotationItems.forEach((item, idx) => createItemRow(item, idx === 0, false));
            } else {
                createItemRow({}, true, false);
            }
        @else
            createItemRow({}, true, false);
        @endif
        renumberItemRows();
    });
</script>
@endsection
