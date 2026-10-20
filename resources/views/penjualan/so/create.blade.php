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

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-semibold text-gray-700">Customer <span class="text-red-500">*</span></label>
                        <select name="customer_id" id="customerSelect" required class="border border-gray-300 rounded-xl px-3 py-2.5 text-sm font-medium text-gray-800 focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 bg-white">
                            <option value="">-- Pilih Customer --</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}"
                                        data-tipe="{{ $c->tipe_pembayaran }}"
                                        data-limit="{{ (float)$c->kredit_limit }}"
                                        data-tempo="{{ (int)$c->tempo_kredit }}"
                                        data-address="{{ $c->address }}"
                                        data-deposit="{{ (float)($c->deposit?->remaining_amount ?? 0) }}"
                                        {{ old('customer_id') == $c->id ? 'selected' : '' }}>
                                    {{ $c->name }} ({{ $c->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-semibold text-gray-700">Cabang <span class="text-red-500">*</span></label>
                        <select name="cabang_id" required class="border border-gray-300 rounded-xl px-3 py-2.5 text-sm font-medium text-gray-800 focus:outline-none focus:border-emerald-600 bg-white">
                            @foreach($cabangs as $cb)
                                <option value="{{ $cb->id }}" {{ old('cabang_id', $user?->cabang_id) == $cb->id ? 'selected' : '' }}>
                                    {{ $cb->kode }} - {{ $cb->nama }}
                                </option>
                            @endforeach
                        </select>
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
                <div class="flex items-center justify-between pb-3.5 border-b border-gray-100">
                    <div class="flex items-center gap-3">
                        <span class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-700 inline-flex items-center justify-center font-extrabold text-sm">2</span>
                        <h2 class="m-0 text-base font-bold text-gray-900">Barang</h2>
                    </div>
                    <span class="text-xs text-gray-500" id="itemCountLabel">0 item</span>
                </div>

                <!-- Container Baris Barang -->
                <div id="itemsContainer" class="flex flex-col gap-3">
                    <!-- Dynamic Rows Rendered by JS -->
                </div>

                <button type="button" id="addItemBtn" class="w-full py-3 border border-dashed border-gray-300 hover:border-emerald-600 rounded-xl text-emerald-700 hover:bg-emerald-50/50 font-bold text-sm inline-flex items-center justify-center gap-2 transition-all cursor-pointer">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    + Tambah barang
                </button>

                <!-- Ongkir & Catatan Tambahan -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 pt-3 border-t border-gray-100">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-semibold text-gray-700">Ongkos Kirim (Rp)</label>
                        <input type="number" name="shipping_cost" id="shippingCostInput" value="{{ old('shipping_cost', 0) }}" min="0" step="1000" class="border border-gray-300 rounded-xl px-3 py-2 text-sm text-gray-800 focus:outline-none focus:border-emerald-600">
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-semibold text-gray-700">Mata Uang</label>
                        <select name="currency_id" class="border border-gray-300 rounded-xl px-3 py-2 text-sm text-gray-800 focus:outline-none focus:border-emerald-600 bg-white">
                            @foreach($currencies as $curr)
                                <option value="{{ $curr->id }}" {{ $curr->id == $defaultCurrencyId ? 'selected' : '' }}>
                                    {{ $curr->code }} - {{ $curr->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <details class="text-xs text-gray-600">
                    <summary class="cursor-pointer font-semibold text-emerald-700 hover:underline">Catatan tambahan</summary>
                    <div class="pt-2">
                        <textarea name="notes" rows="2" placeholder="Catatan internal pesanan..." class="w-full border border-gray-300 rounded-xl p-2.5 text-sm text-gray-800 focus:outline-none focus:border-emerald-600"></textarea>
                    </div>
                </details>
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

    function createItemRow(prefill = {}) {
        const idx = itemIndex++;
        const row = document.createElement('div');
        row.className = 'border border-gray-200 rounded-xl p-3.5 bg-white hover:border-gray-300 transition-colors flex flex-col gap-2.5 relative';
        row.id = `item-row-${idx}`;

        let productOptions = '<option value="">-- Pilih Produk --</option>';
        productsData.forEach(p => {
            const isSelected = prefill.product_id == p.id ? 'selected' : '';
            productOptions += `<option value="${p.id}" data-price="${p.sell_price}" data-bottom="${p.bottom_price}" data-stock="${p.free_stock}" data-uom="${p.uom}" data-sku="${p.sku}" ${isSelected}>${p.name} (${p.sku})</option>`;
        });

        row.innerHTML = `
            <div class="flex justify-between items-start gap-2">
                <div class="flex-1">
                    <label class="text-[11px] font-semibold text-gray-500">Produk</label>
                    <select name="items[${idx}][product_id]" required onchange="handleProductChange(${idx})" class="w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-sm font-semibold text-gray-800 mt-0.5 focus:outline-none focus:border-emerald-600 bg-white">
                        ${productOptions}
                    </select>
                </div>
                <button type="button" onclick="removeItemRow(${idx})" class="text-gray-400 hover:text-red-600 p-1 mt-4 transition-colors" title="Hapus item">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-2 text-xs" id="item-meta-${idx}">
                <span class="text-gray-500" id="item-sku-${idx}">Pilih produk terlebih dahulu</span>
                <span class="hidden rounded px-1.5 py-0.5 font-bold text-[11px]" id="item-stock-badge-${idx}"></span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 pt-1">
                <div>
                    <label class="text-[11px] font-semibold text-gray-500">Qty (<span id="item-uom-${idx}">PCS</span>)</label>
                    <input type="number" name="items[${idx}][quantity]" value="${prefill.quantity || 1}" min="0.01" step="any" required oninput="calculateTotals()" class="w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-sm font-bold text-gray-800 mt-0.5 focus:outline-none focus:border-emerald-600">
                    <div class="text-[10.5px] text-amber-700 font-semibold mt-0.5 hidden" id="item-stock-warning-${idx}"></div>
                </div>
                <div>
                    <label class="text-[11px] font-semibold text-gray-500">Harga Satuan (Rp)</label>
                    <input type="number" name="items[${idx}][unit_price]" value="${prefill.unit_price || 0}" min="0" step="100" required oninput="calculateTotals()" class="w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-sm font-bold text-gray-800 mt-0.5 focus:outline-none focus:border-emerald-600">
                    <div class="text-[10.5px] text-gray-500 mt-0.5" id="item-floor-info-${idx}">Min. Rp 0</div>
                </div>
                <div>
                    <label class="text-[11px] font-semibold text-gray-500">Subtotal</label>
                    <div class="text-sm font-extrabold text-gray-900 py-1.5" id="item-subtotal-${idx}">Rp 0</div>
                </div>
            </div>
        `;

        document.getElementById('itemsContainer').appendChild(row);
        if (prefill.product_id) {
            handleProductChange(idx);
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

    function handleProductChange(idx) {
        const row = document.getElementById(`item-row-${idx}`);
        if (!row) return;

        const select = row.querySelector(`select[name="items[${idx}][product_id]"]`);
        const opt = select.selectedOptions[0];
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

        const priceInput = row.querySelector(`input[name="items[${idx}][unit_price]"]`);
        if (!priceInput.value || parseFloat(priceInput.value) === 0) {
            priceInput.value = price;
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
            const priceInput = row.querySelector(`input[name="items[${idx}][unit_price]"]`);

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
                        floorInfo.className = 'text-[10.5px] text-red-600 font-bold mt-0.5';
                    }
                    if (priceInput) priceInput.className = 'w-full border-1.5 border-red-500 rounded-lg px-2.5 py-1.5 text-sm font-bold text-gray-800 mt-0.5 focus:outline-none';
                } else {
                    if (floorInfo) {
                        floorInfo.innerText = bottom > 0 ? `Min. ${formatRupiah(bottom)}` : 'Tanpa batas minimum';
                        floorInfo.className = 'text-[10.5px] text-gray-500 mt-0.5';
                    }
                    if (priceInput) priceInput.className = 'w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-sm font-bold text-gray-800 mt-0.5 focus:outline-none focus:border-emerald-600';
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
        const custSelect = document.getElementById('customerSelect');
        const custOpt = custSelect?.selectedOptions[0];
        const creditAlert = document.getElementById('creditStatusAlert');

        if (custOpt && custOpt.value) {
            const tipe = custOpt.getAttribute('data-tipe');
            const limit = parseFloat(custOpt.getAttribute('data-limit') || 0);
            const deposit = parseFloat(custOpt.getAttribute('data-deposit') || 0);

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
        }
    }

    document.getElementById('customerSelect').addEventListener('change', function () {
        const opt = this.selectedOptions[0];
        if (!opt || !opt.value) {
            document.getElementById('valKreditLimit').innerText = 'Rp 0';
            document.getElementById('valDepositBalance').innerText = 'Rp 0';
            document.getElementById('valTipePembayaran').innerText = '-';
            return;
        }

        const limit = parseFloat(opt.getAttribute('data-limit') || 0);
        const deposit = parseFloat(opt.getAttribute('data-deposit') || 0);
        const tipe = opt.getAttribute('data-tipe') || 'Tunai';
        const address = opt.getAttribute('data-address') || '';

        document.getElementById('valKreditLimit').innerText = formatRupiah(limit);
        document.getElementById('valDepositBalance').innerText = formatRupiah(deposit);
        document.getElementById('valTipePembayaran').innerText = tipe;

        const shippedInput = document.getElementById('shippedToInput');
        if (!shippedInput.value) {
            shippedInput.value = address;
        }

        calculateTotals();
    });

    document.getElementById('shippingCostInput').addEventListener('input', calculateTotals);
    document.getElementById('addItemBtn').addEventListener('click', () => createItemRow());

    function submitSo(actionType) {
        document.getElementById('formAction').value = actionType;
        document.getElementById('soForm').submit();
    }

    // Buat 1 baris item awal saat halaman pertama kali dibuka
    window.addEventListener('DOMContentLoaded', () => {
        createItemRow();
    });
</script>
@endsection
