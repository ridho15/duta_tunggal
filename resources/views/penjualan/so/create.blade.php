@extends('layouts.app')

@section('title', 'Buat Pesanan Penjualan – ERP Duta Tunggal')

@section('content')
<div class="max-w-[1080px] mx-auto w-full flex flex-col gap-5">
    <!-- Header Page -->
    <div class="pb-5 border-b border-gray-200">
        <div class="flex items-center gap-2 text-xs sm:text-sm text-gray-500 mb-1">
            <a href="{{ route('penjualan.so.index') }}" class="hover:text-emerald-700">Pesanan Penjualan</a>
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
            <section class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 flex flex-col gap-4 shadow-sm">
                <div class="flex items-center gap-3 pb-3.5 border-b border-gray-100">
                    <span class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-700 inline-flex items-center justify-center font-extrabold text-sm">1</span>
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
                    <div class="border border-emerald-200 bg-emerald-50/60 rounded-xl p-3" id="boxSisaLimit">
                        <div class="text-[11px] text-emerald-700 font-semibold">Tipe Pembayaran</div>
                        <div class="font-bold text-sm text-emerald-900 mt-0.5" id="valTipePembayaran">-</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5 pt-2 border-t border-gray-100">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-semibold text-gray-700">Tanggal Order <span class="text-red-500">*</span></label>
                        <input type="date" name="order_date" value="{{ old('order_date', now()->format('Y-m-d')) }}" required class="border border-gray-300 rounded-xl px-3 py-2 text-sm text-gray-800 focus:outline-none focus:border-emerald-600">
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-semibold text-gray-700">Estimasi Kirim</label>
                        <input type="date" name="delivery_date" value="{{ old('delivery_date', now()->addDays(7)->format('Y-m-d')) }}" class="border border-gray-300 rounded-xl px-3 py-2 text-sm text-gray-800 focus:outline-none focus:border-emerald-600">
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-semibold text-gray-700">Tipe Pengiriman <span class="text-red-500">*</span></label>
                        <select name="tipe_pengiriman" class="border border-gray-300 rounded-xl px-3 py-2 text-sm text-gray-800 focus:outline-none focus:border-emerald-600 bg-white">
                            <option value="Kirim Langsung" {{ old('tipe_pengiriman') === 'Kirim Langsung' ? 'selected' : '' }}>Kirim Langsung</option>
                            <option value="Ambil Sendiri" {{ old('tipe_pengiriman') === 'Ambil Sendiri' ? 'selected' : '' }}>Ambil Sendiri</option>
                        </select>
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-semibold text-gray-700">Alamat Kirim</label>
                    <input type="text" name="shipped_to" id="shippedToInput" value="{{ old('shipped_to') }}" placeholder="Otomatis dari data customer jika kosong" class="border border-gray-300 rounded-xl px-3 py-2 text-sm text-gray-800 focus:outline-none focus:border-emerald-600">
                </div>
            </section>

            <!-- Section 2: Barang -->
            <section class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 flex flex-col gap-4 shadow-sm">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3.5 border-b border-gray-100">
                    <div class="flex items-center gap-3">
                        <span class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-700 inline-flex items-center justify-center font-extrabold text-sm">2</span>
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
                            <span>Tambah Barang</span>
                        </button>
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
                                   class="w-full pl-9 pr-3 py-2 text-sm text-right font-mono font-semibold text-gray-800 bg-white border border-gray-300 rounded-xl focus:outline-none focus:border-emerald-600">
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
                    <button type="button" @click="openNotes = !openNotes" class="w-full flex items-center justify-between p-3 text-xs font-semibold text-gray-700 hover:text-emerald-700 select-none">
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            <span>Catatan Tambahan (Internal Pesanan)</span>
                        </span>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': openNotes }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="openNotes" x-collapse class="p-3 pt-0">
                        <textarea name="notes" rows="2" placeholder="Catatan internal pesanan..." class="w-full border border-gray-300 rounded-xl p-2.5 text-xs text-gray-800 bg-white focus:outline-none focus:border-emerald-600">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </section>
        </div>

        <!-- Kolom Kanan: Ringkasan Sticky (Aside) -->
        <aside class="w-full lg:w-[320px] shrink-0 bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 flex flex-col gap-3.5 shadow-sm sticky top-[70px]">
            <h2 class="m-0 text-base font-bold text-gray-900 border-b border-gray-100 pb-3">Ringkasan</h2>

            <div class="flex justify-between text-sm text-gray-600">
                <span>Subtotal barang</span>
                <span class="font-semibold text-gray-900" id="summarySubtotal">Rp 0</span>
            </div>
            <div class="flex justify-between text-sm text-gray-600">
                <span>Ongkos kirim</span>
                <span class="font-semibold text-gray-900" id="summaryOngkir">Rp 0</span>
            </div>

            <div class="flex justify-between text-lg font-extrabold text-gray-900 border-t border-gray-100 pt-3">
                <span>Total</span>
                <span id="summaryTotal" class="text-emerald-700">Rp 0</span>
            </div>

            <!-- Price Violation Warning Card -->
            <div id="priceViolationAlert" class="hidden bg-red-50 border border-red-200 rounded-xl p-3 text-xs text-red-900">
                <span class="font-bold">Harga di bawah batas.</span> SO tetap tersimpan, namun membutuhkan approve atasan (bukan kasir).
            </div>

            <!-- Credit Limit Status Card -->
            <div id="creditStatusAlert" class="bg-emerald-50 border border-emerald-200 rounded-xl p-3 text-xs text-emerald-900 font-medium">
                Pilih customer untuk melihat status limit kredit.
            </div>

            <button type="button" onclick="submitSo('request_approve')" class="w-full py-3 bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-sm rounded-xl min-h-[46px] shadow-sm transition-colors cursor-pointer mt-1">
                Simpan & Ajukan Approve
            </button>
            <button type="button" onclick="submitSo('draft')" class="w-full py-2.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 font-semibold text-sm rounded-xl min-h-[42px] transition-colors cursor-pointer">
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

    function updateSoItemSummary(idx) {
        const row = document.getElementById(`item-row-${idx}`);
        if (!row) return;

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
                summaryEl.innerText = `${sku ? '[' + sku + '] ' : ''}${name} · ${qty} ${uom} · ${subtotalText}`;
            } else {
                summaryEl.innerText = `Item #${idx + 1} — Pilih Produk...`;
            }
        }
    }

    function createItemRow(prefill = {}, isOpen = true) {
        if (isOpen && itemIndex > 0 && !prefill.product_id) {
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
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-50 text-emerald-700 font-bold text-[11px] border border-emerald-200 shrink-0">
                        ${idx + 1}
                    </span>
                    <div class="truncate">
                        <span id="item-summary-${idx}" class="text-xs font-bold text-gray-800">
                            Item #${idx + 1} — Pilih Produk...
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

        document.getElementById('itemsContainer').appendChild(row);
        if (prefill.product_id) {
            handleProductChange(idx, false);
        }
        calculateTotals();
    }

    function removeItemRow(idx) {
        const row = document.getElementById(`item-row-${idx}`);
        if (row) {
            row.remove();
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
                createItemRow(item, index === itemsList.length - 1);
            });
        @elseif($selectedQuotation && count($qoItemsData) > 0)
            if (quotationItems && quotationItems.length > 0) {
                quotationItems.forEach((item, idx) => createItemRow(item, idx === 0));
            } else {
                createItemRow({}, true);
            }
        @else
            createItemRow({}, true);
        @endif
    });
</script>
@endsection
