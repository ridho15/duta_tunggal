@extends('layouts.app', ['title' => 'Kasir Cepat (POS)'])

@section('content')
<div class="space-y-4">


    @if($errors->any())
        <div class="p-3.5 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl text-sm shadow-xs">
            <div class="font-bold mb-1">Terjadi kesalahan validasi:</div>
            <ul class="list-disc list-inside text-xs space-y-0.5">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Top Shift & Status Bar --}}
    <div class="bg-white border border-gray-200 rounded-2xl p-4 shadow-xs flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-700 font-extrabold flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <rect x="2" y="3" width="20" height="14" rx="2" stroke-width="1.8"></rect>
                    <line x1="8" y1="21" x2="16" y2="21" stroke-width="1.8" stroke-linecap="round"></line>
                    <line x1="12" y1="17" x2="12" y2="21" stroke-width="1.8" stroke-linecap="round"></line>
                </svg>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-lg font-extrabold text-gray-900 tracking-tight">Kasir POS Ritel</h1>
                    @if($activeShift)
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            Shift Aktif: {{ $activeShift->shift_number }}
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-200">
                            Shift Belum Dibuka
                        </span>
                    @endif
                </div>
                <div class="text-xs text-gray-500 mt-0.5 flex flex-wrap items-center gap-2">
                    <span>Kasir: <strong class="text-gray-700">{{ Auth::user()->name }}</strong></span>
                    <span>•</span>
                    <span>Gudang: <strong class="text-gray-700">{{ $activeShift?->warehouse?->name ?? 'Gudang Toko' }}</strong></span>
                    @if($activeShift)
                        <span>•</span>
                        <span>Modal Awal: <strong class="text-emerald-700">Rp {{ number_format($activeShift->starting_cash, 0, ',', '.') }}</strong></span>
                        <span>•</span>
                        <span>Buka: <strong class="text-gray-700">{{ $activeShift->opened_at->format('H:i') }} WIB</strong></span>
                    @endif
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a
                href="{{ route('kasir.pos.history') }}"
                class="inline-flex items-center gap-1.5 px-3 py-2 border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 rounded-xl text-xs font-bold transition shadow-xs"
            >
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" stroke-width="1.8"></circle>
                    <polyline points="12 6 12 12 16 14" stroke-width="1.8"></polyline>
                </svg>
                <span>Riwayat Transaksi</span>
            </a>

            @if($activeShift)
                <button
                    type="button"
                    onclick="openCloseShiftModal()"
                    class="inline-flex items-center gap-1.5 px-3 py-2 border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 rounded-xl text-xs font-bold transition shadow-xs"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                    <span>Tutup Shift (Z-Report)</span>
                </button>
            @else
                <button
                    type="button"
                    onclick="openOpenShiftModal()"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#15803d] hover:bg-[#14532d] text-white rounded-xl text-xs font-bold transition shadow-sm"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M12 4v16m8-8H4" stroke-width="2" stroke-linecap="round"></path>
                    </svg>
                    <span>Buka Shift Baru</span>
                </button>
            @endif
        </div>
    </div>

    {{-- Main POS Working Layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

        {{-- Left 7 Cols: Scanner Barcode, Quick Search, Product Grid --}}
        <div class="lg:col-span-7 space-y-4">

            {{-- Barcode Scan & Quick Search Bar --}}
            <div class="bg-white border border-gray-200 rounded-2xl p-4 shadow-xs space-y-3">
                <div class="flex items-center justify-between">
                    <label for="barcodeInput" class="text-xs font-bold text-gray-700 uppercase tracking-wider flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M4 7V4h16v3M4 11h16M4 17h16M4 20h16" stroke-width="2" stroke-linecap="round"></path>
                        </svg>
                        <span>Scan Barcode / Cari Produk Cepat</span>
                    </label>
                    <span class="text-[11px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-md flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        Autofokus Siap
                    </span>
                </div>

                <div class="relative">
                    <input
                        type="text"
                        id="barcodeInput"
                        placeholder="Scan barcode dengan scanner atau ketik SKU / Nama Produk..."
                        class="w-full pl-11 pr-24 py-3 bg-gray-50 border-2 border-emerald-500 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-200 rounded-xl text-sm font-semibold text-gray-900 transition placeholder:text-gray-400 placeholder:font-normal"
                        autocomplete="off"
                        autofocus
                    >
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-emerald-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                    </div>
                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center gap-1 text-[11px] font-bold text-gray-400">
                        <kbd class="px-1.5 py-0.5 bg-gray-200 rounded border border-gray-300 text-gray-600">Enter</kbd>
                    </div>

                    {{-- Live Autocomplete Dropdown --}}
                    <div id="searchDropdown" class="absolute left-0 right-0 top-full mt-1.5 bg-white border border-gray-200 rounded-xl shadow-xl z-30 hidden max-h-72 overflow-y-auto divide-y divide-gray-100">
                        {{-- Injected dynamically --}}
                    </div>
                </div>

                {{-- Price Protection Banner Note --}}
                <div class="flex items-center gap-2 p-2.5 bg-amber-50/70 border border-amber-200 rounded-xl text-xs text-amber-800">
                    <svg class="w-4 h-4 text-amber-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                    <span><strong>Proteksi Batas Harga (harga_batas):</strong> Kasir dilarang menjual di bawah batas minimum produk. Sistem akan otomatis memblokir transaksi jika harga atau diskon berada di bawah batas.</span>
                </div>
            </div>

            {{-- Quick Product Catalog Grid --}}
            <div class="bg-white border border-gray-200 rounded-2xl p-4 shadow-xs space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-xs font-bold text-gray-700 uppercase tracking-wider">Katalog Produk Cepat</h2>
                    <span class="text-xs text-gray-400">Klik produk untuk tambah ke keranjang</span>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 max-h-[500px] overflow-y-auto pr-1">
                    @forelse($initialProducts as $prod)
                        <button
                            type="button"
                            onclick="addToCart({{ json_encode($prod) }})"
                            class="text-left p-3 rounded-xl border border-gray-200 hover:border-emerald-500 hover:bg-emerald-50/30 transition group flex flex-col justify-between h-28 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        >
                            <div>
                                <div class="text-xs font-bold text-gray-900 line-clamp-2 group-hover:text-emerald-700 transition">
                                    {{ $prod['name'] }}
                                </div>
                                <div class="text-[10.5px] text-gray-400 font-mono mt-0.5">
                                    {{ $prod['sku'] }}
                                </div>
                            </div>
                            <div class="flex items-end justify-between mt-2 pt-1.5 border-t border-gray-100">
                                <div>
                                    <div class="text-xs font-extrabold text-emerald-700">
                                        Rp {{ number_format($prod['sell_price'], 0, ',', '.') }}
                                    </div>
                                    @if($prod['harga_batas'] > 0)
                                        <div class="text-[9.5px] text-gray-400">
                                            Min: Rp {{ number_format($prod['harga_batas'], 0, ',', '.') }}
                                        </div>
                                    @endif
                                </div>
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded {{ $prod['available_stock'] > 0 ? 'bg-gray-100 text-gray-700' : 'bg-rose-100 text-rose-700' }}">
                                    Stok: {{ number_format($prod['available_stock']) }}
                                </span>
                            </div>
                        </button>
                    @empty
                        <div class="col-span-full py-8 text-center text-xs text-gray-400">
                            Belum ada data produk aktif pada cabang ini.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Right 5 Cols: Cart, Customer, Settlement --}}
        <div class="lg:col-span-5 space-y-4">
            <div class="bg-white border border-gray-200 rounded-2xl shadow-xs overflow-hidden flex flex-col">

                {{-- Cart Header --}}
                <div class="p-4 border-b border-gray-100 bg-gray-50/70 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-extrabold text-gray-900 tracking-tight">Keranjang Belanja</h2>
                        <span id="cartCountBadge" class="text-xs text-gray-500">0 item produk</span>
                    </div>
                    <button
                        type="button"
                        onclick="clearCart()"
                        class="text-xs font-semibold text-rose-600 hover:text-rose-800 transition"
                    >
                        Kosongkan
                    </button>
                </div>

                {{-- Customer Info --}}
                <div class="p-3 bg-white border-b border-gray-100 grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-[10.5px] font-bold text-gray-500 uppercase mb-1">Nama Pelanggan</label>
                        <input
                            type="text"
                            id="customerNameInput"
                            value="Pelanggan Umum"
                            placeholder="Ketik nama pelanggan..."
                            class="w-full px-2.5 py-1.5 text-xs font-semibold border border-gray-300 rounded-lg focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                        >
                    </div>
                    <div>
                        <label class="block text-[10.5px] font-bold text-gray-500 uppercase mb-1">Pelanggan Terdaftar</label>
                        <select data-searchable
                            id="customerSelect"
                            onchange="selectCustomer(this)"
                            class="w-full px-2 py-1.5 text-xs border border-gray-300 rounded-lg focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-gray-700"
                        >
                            <option value="">-- Umum (Walk-in) --</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" data-name="{{ $c->name }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Cart Items Table / List --}}
                <div class="p-3 divide-y divide-gray-100 max-h-[300px] overflow-y-auto" id="cartContainer">
                    <div id="cartEmptyState" class="py-12 text-center text-gray-400">
                        <svg class="w-10 h-10 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" stroke-width="1.5"></path>
                        </svg>
                        <div class="text-xs font-medium">Keranjang masih kosong</div>
                        <div class="text-[11px] text-gray-400 mt-0.5">Scan barcode atau klik katalog untuk mulai</div>
                    </div>
                </div>

                {{-- Price Boundary Violation Alert Box --}}
                <div id="priceBoundaryWarning" class="hidden m-3 p-3 bg-rose-50 border-2 border-rose-300 rounded-xl text-rose-800 text-xs">
                    <div class="flex items-center gap-1.5 font-bold text-rose-900 mb-1">
                        <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10" stroke-width="2"></circle>
                            <line x1="12" y1="8" x2="12" y2="12" stroke-width="2"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16" stroke-width="2"></line>
                        </svg>
                        <span>Peringatan Batas Harga Minimum:</span>
                    </div>
                    <div id="priceBoundaryMessage" class="text-xs"></div>
                </div>

                {{-- Calculations & Totals --}}
                <div class="p-4 bg-gray-50/80 border-t border-gray-200 space-y-2">
                    <div class="flex justify-between text-xs text-gray-600">
                        <span>Subtotal</span>
                        <span id="labelSubtotal" class="font-bold text-gray-900">Rp 0</span>
                    </div>
                    <div class="flex justify-between items-center text-xs text-gray-600">
                        <span>Diskon Tambahan (Rp)</span>
                        <input
                            type="number"
                            id="inputGlobalDiscount"
                            value="0"
                            min="0"
                            oninput="recalculateCart()"
                            class="w-28 px-2 py-1 text-right text-xs font-bold border border-gray-300 rounded-lg focus:border-emerald-500"
                        >
                    </div>
                    <div class="flex justify-between items-center text-xs text-gray-600">
                        <span>Pajak (PPN 11%)</span>
                        <label class="inline-flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" id="taxCheckbox" onchange="recalculateCart()" class="rounded text-emerald-600 focus:ring-emerald-500">
                            <span id="labelTax" class="font-bold text-gray-900">Rp 0</span>
                        </label>
                    </div>
                    <div class="pt-2 border-t border-gray-200 flex justify-between items-baseline">
                        <span class="text-xs font-extrabold text-gray-900 uppercase tracking-wider">Total Tagihan</span>
                        <span id="labelGrandTotal" class="text-2xl font-black text-emerald-700 tracking-tight">Rp 0</span>
                    </div>
                </div>

                {{-- Payment Method & Settlement Section --}}
                <div class="p-4 bg-white border-t border-gray-200 space-y-3">
                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 uppercase mb-1.5">Metode Pembayaran</label>
                        <div class="grid grid-cols-4 gap-1.5">
                            <label class="cursor-pointer">
                                <input type="radio" name="payment_method" value="cash" checked onchange="changePaymentMethod('cash')" class="peer sr-only">
                                <div class="p-2 text-center rounded-xl border border-gray-200 peer-checked:border-emerald-600 peer-checked:bg-emerald-50 peer-checked:text-emerald-800 text-xs font-bold transition">
                                    Tunai
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="payment_method" value="qris" onchange="changePaymentMethod('qris')" class="peer sr-only">
                                <div class="p-2 text-center rounded-xl border border-gray-200 peer-checked:border-emerald-600 peer-checked:bg-emerald-50 peer-checked:text-emerald-800 text-xs font-bold transition">
                                    QRIS
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="payment_method" value="transfer" onchange="changePaymentMethod('transfer')" class="peer sr-only">
                                <div class="p-2 text-center rounded-xl border border-gray-200 peer-checked:border-emerald-600 peer-checked:bg-emerald-50 peer-checked:text-emerald-800 text-xs font-bold transition">
                                    Transfer
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="payment_method" value="debit" onchange="changePaymentMethod('debit')" class="peer sr-only">
                                <div class="p-2 text-center rounded-xl border border-gray-200 peer-checked:border-emerald-600 peer-checked:bg-emerald-50 peer-checked:text-emerald-800 text-xs font-bold transition">
                                    Debit/EDC
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- Cash Specific Options --}}
                    <div id="cashPaymentGroup" class="space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <label class="font-bold text-gray-600 uppercase text-[11px]">Nominal Uang Diterima</label>
                            <div class="flex gap-1">
                                <button type="button" onclick="setCashAmount('pas')" class="px-2 py-0.5 rounded bg-gray-100 hover:bg-gray-200 text-[10.5px] font-bold text-gray-700">Pas</button>
                                <button type="button" onclick="setCashAmount(50000)" class="px-2 py-0.5 rounded bg-gray-100 hover:bg-gray-200 text-[10.5px] font-bold text-gray-700">50rb</button>
                                <button type="button" onclick="setCashAmount(100000)" class="px-2 py-0.5 rounded bg-gray-100 hover:bg-gray-200 text-[10.5px] font-bold text-gray-700">100rb</button>
                                <button type="button" onclick="setCashAmount(200000)" class="px-2 py-0.5 rounded bg-gray-100 hover:bg-gray-200 text-[10.5px] font-bold text-gray-700">200rb</button>
                            </div>
                        </div>

                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 font-bold text-xs">Rp</span>
                            <input
                                type="number"
                                id="cashReceivedInput"
                                placeholder="0"
                                oninput="recalculateChange()"
                                class="w-full pl-9 pr-3 py-2 text-base font-extrabold text-gray-900 border-2 border-gray-300 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 rounded-xl text-right"
                            >
                        </div>

                        {{-- Change Display --}}
                        <div class="p-3 rounded-xl bg-gray-50 border border-gray-200 flex items-center justify-between">
                            <span class="text-xs font-bold text-gray-600">Kembalian</span>
                            <span id="labelChangeAmount" class="text-xl font-black text-emerald-700">Rp 0</span>
                        </div>
                    </div>

                    {{-- Submit POS Checkout Button --}}
                    <button
                        type="button"
                        id="btnCheckout"
                        onclick="processCheckout()"
                        disabled
                        class="w-full py-3.5 px-4 rounded-xl font-extrabold text-sm text-white bg-[#15803d] hover:bg-[#14532d] disabled:bg-gray-300 disabled:cursor-not-allowed shadow-md hover:shadow-lg transition flex items-center justify-center gap-2"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M5 13l4 4L19 7" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                        <span>Selesaikan Transaksi & Cetak Struk (F9)</span>
                    </button>
                </div>

            </div>
        </div>

    </div>
</div>

{{-- MODAL BUKA SHIFT --}}
<div id="modalOpenShift" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 {{ $activeShift ? 'hidden' : '' }}">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-200">
        <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center mb-4">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <rect x="2" y="3" width="20" height="14" rx="2" stroke-width="1.8"></rect>
                <line x1="8" y1="21" x2="16" y2="21" stroke-width="1.8" stroke-linecap="round"></line>
                <line x1="12" y1="17" x2="12" y2="21" stroke-width="1.8" stroke-linecap="round"></line>
            </svg>
        </div>
        <h3 class="text-lg font-extrabold text-gray-900 tracking-tight">Buka Shift Kasir Baru</h3>
        <p class="text-xs text-gray-500 mt-1 mb-4">
            Masukkan modal awal uang tunai di laci kasir untuk mulai melayani transaksi penjualan ritel.
        </p>

        <form action="{{ route('kasir.shift.open') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Modal Awal Laci Kasir (Rp) <span class="text-rose-500">*</span></label>
                <input
                    type="number"
                    name="starting_cash"
                    required
                    min="0"
                    step="1000"
                    placeholder="Contoh: 500000"
                    class="w-full px-3 py-2.5 text-base font-extrabold text-gray-900 border border-gray-300 rounded-xl focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                    autofocus
                >
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Gudang Pengeluaran Barang</label>
                <x-searchable-select name="warehouse_id" :options="collect($warehouses)->map(fn ($w) => ['id' => $w->id, 'label' => $w->name])->values()" :value="collect($warehouses)->first()?->id" size="sm" />
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Catatan Shift (Opsional)</label>
                <input
                    type="text"
                    name="notes"
                    placeholder="Shift Pagi / Shift Sore..."
                    class="w-full px-3 py-2 text-xs border border-gray-300 rounded-xl focus:border-emerald-500"
                >
            </div>

            <div class="pt-2 flex justify-end gap-2">
                <button
                    type="submit"
                    class="w-full py-2.5 px-4 bg-[#15803d] hover:bg-[#14532d] text-white font-bold text-xs rounded-xl shadow-sm transition"
                >
                    Buka Shift & Masuk Terminal Kasir
                </button>
            </div>
        </form>
    </div>
</div>

{{-- MODAL TUTUP SHIFT --}}
<div id="modalCloseShift" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 hidden">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-200">
        <div class="w-12 h-12 rounded-2xl bg-rose-100 text-rose-700 flex items-center justify-center mb-4">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
        </div>
        <h3 class="text-lg font-extrabold text-gray-900 tracking-tight">Tutup Shift Kasir (Z-Report)</h3>
        <p class="text-xs text-gray-500 mt-1 mb-4">
            Hitung uang fisik di laci kasir dan masukkan nominal aktual di bawah ini.
        </p>

        @if($activeShift)
            <div class="p-3 bg-gray-50 rounded-xl border border-gray-200 text-xs space-y-1 mb-4">
                <div class="flex justify-between text-gray-600">
                    <span>Modal Awal:</span>
                    <strong>Rp {{ number_format($activeShift->starting_cash, 0, ',', '.') }}</strong>
                </div>
                <div class="flex justify-between text-gray-600">
                    <span>Penjualan Tunai:</span>
                    <strong>Rp {{ number_format($activeShift->cash_sales_total, 0, ',', '.') }}</strong>
                </div>
                <div class="flex justify-between text-emerald-800 font-bold pt-1 border-t border-gray-200">
                    <span>Total Uang Seharusnya:</span>
                    <span>Rp {{ number_format($activeShift->starting_cash + $activeShift->cash_sales_total, 0, ',', '.') }}</span>
                </div>
            </div>
        @endif

        <form action="{{ route('kasir.shift.close') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Uang Fisik Kasir di Laci (Rp) <span class="text-rose-500">*</span></label>
                <input
                    type="number"
                    name="cash_actual"
                    id="closeShiftCashActual"
                    required
                    min="0"
                    placeholder="Hitung uang fisik di laci..."
                    class="w-full px-3 py-2.5 text-base font-extrabold text-gray-900 border border-gray-300 rounded-xl focus:border-rose-500 focus:ring-1 focus:ring-rose-500"
                >
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Catatan Penutupan</label>
                <input
                    type="text"
                    name="notes"
                    placeholder="Keterangan selisih kas jika ada..."
                    class="w-full px-3 py-2 text-xs border border-gray-300 rounded-xl focus:border-emerald-500"
                >
            </div>

            <div class="pt-2 flex justify-end gap-2">
                <button
                    type="button"
                    onclick="closeCloseShiftModal()"
                    class="px-4 py-2 border border-gray-300 text-gray-700 text-xs font-bold rounded-xl hover:bg-gray-50"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    class="py-2.5 px-4 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs rounded-xl shadow-sm transition"
                >
                    Tutup Shift & Lihat Z-Report
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // State Keranjang Belanja Kasir
    let cart = [];
    let paymentMethod = 'cash';
    let globalDiscount = 0;
    let isTaxEnabled = false;

    const barcodeInput = document.getElementById('barcodeInput');
    const searchDropdown = document.getElementById('searchDropdown');
    const cartContainer = document.getElementById('cartContainer');
    const cartEmptyState = document.getElementById('cartEmptyState');
    const cartCountBadge = document.getElementById('cartCountBadge');
    const labelSubtotal = document.getElementById('labelSubtotal');
    const labelTax = document.getElementById('labelTax');
    const labelGrandTotal = document.getElementById('labelGrandTotal');
    const cashReceivedInput = document.getElementById('cashReceivedInput');
    const labelChangeAmount = document.getElementById('labelChangeAmount');
    const btnCheckout = document.getElementById('btnCheckout');
    const priceBoundaryWarning = document.getElementById('priceBoundaryWarning');
    const priceBoundaryMessage = document.getElementById('priceBoundaryMessage');

    // Shortcut Keyboard
    window.addEventListener('keydown', function(e) {
        // F9 untuk checkout
        if (e.key === 'F9') {
            e.preventDefault();
            if (!btnCheckout.disabled) {
                processCheckout();
            }
        }
        // Escape untuk menutup dropdown pencarian
        if (e.key === 'Escape') {
            searchDropdown.classList.add('hidden');
        }
    });

    // Barcode Scanner & Search Handler
    let searchDebounceTimer = null;
    barcodeInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const val = this.value.trim();
            if (!val) return;

            // Coba temukan langsung via barcode/sku
            fetchProductByBarcode(val);
        }
    });

    barcodeInput.addEventListener('input', function() {
        clearTimeout(searchDebounceTimer);
        const query = this.value.trim();
        if (query.length < 2) {
            searchDropdown.classList.add('hidden');
            return;
        }

        searchDebounceTimer = setTimeout(() => {
            fetchProductSuggestions(query);
        }, 250);
    });

    function fetchProductByBarcode(barcode) {
        fetch(`{{ route('kasir.pos.search') }}?barcode=${encodeURIComponent(barcode)}`)
            .then(res => res.json())
            .then(data => {
                if (data.found && data.product) {
                    addToCart(data.product);
                    barcodeInput.value = '';
                    searchDropdown.classList.add('hidden');
                } else {
                    // Coba cari biasa
                    fetchProductSuggestions(barcode, true);
                }
            })
            .catch(err => console.error('Error scanning barcode:', err));
    }

    function fetchProductSuggestions(keyword, autoSelectIfSingle = false) {
        fetch(`{{ route('kasir.pos.search') }}?keyword=${encodeURIComponent(keyword)}`)
            .then(res => res.json())
            .then(data => {
                const results = data.results || [];
                if (autoSelectIfSingle && results.length === 1) {
                    addToCart(results[0]);
                    barcodeInput.value = '';
                    searchDropdown.classList.add('hidden');
                    return;
                }

                renderDropdownResults(results);
            })
            .catch(err => console.error('Error fetching suggestions:', err));
    }

    function renderDropdownResults(products) {
        if (!products || products.length === 0) {
            searchDropdown.innerHTML = `<div class="p-3 text-xs text-gray-400 text-center">Produk tidak ditemukan</div>`;
            searchDropdown.classList.remove('hidden');
            return;
        }

        let html = '';
        products.forEach(p => {
            html += `
                <div
                    onclick="selectDropdownItem(${JSON.stringify(p).replace(/"/g, '&quot;')})"
                    class="p-2.5 hover:bg-emerald-50 cursor-pointer flex items-center justify-between text-xs transition"
                >
                    <div>
                        <div class="font-bold text-gray-900">${p.name}</div>
                        <div class="text-[10.5px] text-gray-400 font-mono">${p.sku} • Stok: ${p.available_stock}</div>
                    </div>
                    <div class="text-right">
                        <div class="font-extrabold text-emerald-700">Rp ${formatNumber(p.sell_price)}</div>
                        ${p.harga_batas > 0 ? `<div class="text-[10px] text-gray-400">Min: Rp ${formatNumber(p.harga_batas)}</div>` : ''}
                    </div>
                </div>
            `;
        });

        searchDropdown.innerHTML = html;
        searchDropdown.classList.remove('hidden');
    }

    function selectDropdownItem(product) {
        addToCart(product);
        barcodeInput.value = '';
        searchDropdown.classList.add('hidden');
        barcodeInput.focus();
    }

    // Cart Management
    function addToCart(product) {
        const existing = cart.find(item => item.product_id === product.id);
        if (existing) {
            existing.quantity += 1;
        } else {
            cart.push({
                product_id: product.id,
                name: product.name,
                sku: product.sku,
                unit_price: product.sell_price,
                cost_price: product.cost_price,
                harga_batas: product.harga_batas,
                effective_min_price: product.effective_min_price,
                available_stock: product.available_stock,
                quantity: 1,
                discount_amount: 0,
                uom: product.uom || 'Pcs',
            });
        }
        renderCart();
    }

    function updateCartQty(productId, newQty) {
        const item = cart.find(i => i.product_id === productId);
        if (!item) return;

        item.quantity = Math.max(0.01, parseFloat(newQty) || 1);
        renderCart();
    }

    function updateCartPrice(productId, newPrice) {
        const item = cart.find(i => i.product_id === productId);
        if (!item) return;

        item.unit_price = Math.max(0, parseFloat(newPrice) || 0);
        renderCart();
    }

    function updateCartDiscount(productId, newDiscount) {
        const item = cart.find(i => i.product_id === productId);
        if (!item) return;

        item.discount_amount = Math.max(0, parseFloat(newDiscount) || 0);
        renderCart();
    }

    function removeFromCart(productId) {
        cart = cart.filter(item => item.product_id !== productId);
        renderCart();
    }

    function clearCart() {
        cart = [];
        renderCart();
    }

    function selectCustomer(selectEl) {
        const selectedOption = selectEl.options[selectEl.selectedIndex];
        const name = selectedOption.getAttribute('data-name');
        if (name) {
            document.getElementById('customerNameInput').value = name;
        } else {
            document.getElementById('customerNameInput').value = 'Pelanggan Umum';
        }
    }

    function renderCart() {
        if (cart.length === 0) {
            cartContainer.innerHTML = '';
            cartContainer.appendChild(cartEmptyState);
            cartCountBadge.innerText = '0 item produk';
            priceBoundaryWarning.classList.add('hidden');
            recalculateCart();
            return;
        }

        let html = '';
        let hasPriceBoundaryViolation = false;
        let violationMessages = [];

        cart.forEach((item, index) => {
            const lineSubtotal = Math.max(0, (item.unit_price * item.quantity) - item.discount_amount);
            const effectiveUnitPrice = item.quantity > 0 ? (lineSubtotal / item.quantity) : item.unit_price;
            const isBelowBoundary = effectiveUnitPrice < (item.effective_min_price - 0.01);

            if (isBelowBoundary) {
                hasPriceBoundaryViolation = true;
                violationMessages.push(`${item.name}: Rp ${formatNumber(effectiveUnitPrice)} < Min Rp ${formatNumber(item.effective_min_price)}`);
            }

            html += `
                <div class="py-2.5 space-y-1.5 ${isBelowBoundary ? 'bg-rose-50/80 -mx-3 px-3 rounded-lg border border-rose-200' : ''}">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="text-xs font-bold text-gray-900">${item.name}</div>
                            <div class="text-[10.5px] text-gray-400 font-mono">${item.sku}</div>
                        </div>
                        <button type="button" onclick="removeFromCart(${item.product_id})" class="text-gray-400 hover:text-rose-600 text-xs p-1">
                            ✕
                        </button>
                    </div>

                    <div class="grid grid-cols-12 gap-1.5 items-center">
                        {{-- Qty Controls --}}
                        <div class="col-span-4 flex items-center border border-gray-300 rounded-lg overflow-hidden bg-white">
                            <button type="button" onclick="updateCartQty(${item.product_id}, ${item.quantity - 1})" class="px-2 py-1 text-xs text-gray-600 hover:bg-gray-100 font-bold">-</button>
                            <input
                                type="number"
                                value="${item.quantity}"
                                min="0.01"
                                step="1"
                                onchange="updateCartQty(${item.product_id}, this.value)"
                                class="w-full text-center text-xs font-bold border-0 p-0 focus:ring-0"
                            >
                            <button type="button" onclick="updateCartQty(${item.product_id}, ${item.quantity + 1})" class="px-2 py-1 text-xs text-gray-600 hover:bg-gray-100 font-bold">+</button>
                        </div>

                        {{-- Harga Satuan --}}
                        <div class="col-span-4">
                            <input
                                type="number"
                                value="${item.unit_price}"
                                min="0"
                                onchange="updateCartPrice(${item.product_id}, this.value)"
                                title="Harga Jual Satuan"
                                class="w-full px-2 py-1 text-xs font-bold border rounded-lg text-right ${isBelowBoundary ? 'border-rose-400 bg-rose-50 text-rose-700' : 'border-gray-300 focus:border-emerald-500'}"
                            >
                        </div>

                        {{-- Subtotal Line --}}
                        <div class="col-span-4 text-right">
                            <div class="text-xs font-extrabold ${isBelowBoundary ? 'text-rose-700' : 'text-gray-900'}">
                                Rp ${formatNumber(lineSubtotal)}
                            </div>
                        </div>
                    </div>

                    {{-- Diskon & Boundary Indicator --}}
                    <div class="flex items-center justify-between text-[10.5px]">
                        <div class="flex items-center gap-1">
                            <span class="text-gray-400">Diskon (Rp):</span>
                            <input
                                type="number"
                                value="${item.discount_amount}"
                                min="0"
                                onchange="updateCartDiscount(${item.product_id}, this.value)"
                                class="w-16 px-1.5 py-0.5 text-[10.5px] border border-gray-300 rounded text-right"
                            >
                        </div>

                        <div>
                            @if(true)
                                ${item.harga_batas > 0 ? `
                                    <span class="text-[10px] ${isBelowBoundary ? 'font-bold text-rose-600 bg-rose-100 px-1 py-0.5 rounded' : 'text-gray-400'}">
                                        Batas Min: Rp ${formatNumber(item.harga_batas)}
                                    </span>
                                ` : ''}
                            @endif
                        </div>
                    </div>
                </div>
            `;
        });

        cartContainer.innerHTML = html;
        cartCountBadge.innerText = `${cart.length} item produk`;

        if (hasPriceBoundaryViolation) {
            priceBoundaryMessage.innerHTML = violationMessages.join('<br>');
            priceBoundaryWarning.classList.remove('hidden');
        } else {
            priceBoundaryWarning.classList.add('hidden');
        }

        recalculateCart(hasPriceBoundaryViolation);
    }

    function recalculateCart(hasPriceBoundaryViolation = false) {
        let subtotal = 0;
        cart.forEach(item => {
            subtotal += Math.max(0, (item.unit_price * item.quantity) - item.discount_amount);
        });

        const globalDiscountInput = parseFloat(document.getElementById('inputGlobalDiscount').value) || 0;
        const subtotalAfterDiscount = Math.max(0, subtotal - globalDiscountInput);

        const taxChecked = document.getElementById('taxCheckbox').checked;
        const taxAmount = taxChecked ? Math.round(subtotalAfterDiscount * 0.11) : 0;

        const grandTotal = subtotalAfterDiscount + taxAmount;

        labelSubtotal.innerText = `Rp ${formatNumber(subtotal)}`;
        labelTax.innerText = `Rp ${formatNumber(taxAmount)}`;
        labelGrandTotal.innerText = `Rp ${formatNumber(grandTotal)}`;

        recalculateChange();

        // Tombol checkout di-disable jika keranjang kosong atau melanggar batas harga
        const isCartValid = cart.length > 0 && !hasPriceBoundaryViolation;
        btnCheckout.disabled = !isCartValid;
    }

    function changePaymentMethod(method) {
        paymentMethod = method;
        const cashGroup = document.getElementById('cashPaymentGroup');
        if (method === 'cash') {
            cashGroup.classList.remove('hidden');
        } else {
            cashGroup.classList.add('hidden');
        }
        recalculateChange();
    }

    function setCashAmount(typeOrVal) {
        const grandTotal = getGrandTotal();
        if (typeOrVal === 'pas') {
            cashReceivedInput.value = grandTotal;
        } else {
            cashReceivedInput.value = typeOrVal;
        }
        recalculateChange();
    }

    function recalculateChange() {
        const grandTotal = getGrandTotal();
        const received = parseFloat(cashReceivedInput.value) || 0;

        if (paymentMethod !== 'cash') {
            labelChangeAmount.innerText = 'Rp 0';
            labelChangeAmount.className = 'text-xl font-black text-emerald-700';
            return;
        }

        const diff = received - grandTotal;
        if (diff >= 0) {
            labelChangeAmount.innerText = `Rp ${formatNumber(diff)}`;
            labelChangeAmount.className = 'text-xl font-black text-emerald-700';
        } else {
            labelChangeAmount.innerText = `- Rp ${formatNumber(Math.abs(diff))} (Kurang)`;
            labelChangeAmount.className = 'text-xl font-black text-rose-600';
        }
    }

    function getGrandTotal() {
        let subtotal = 0;
        cart.forEach(item => {
            subtotal += Math.max(0, (item.unit_price * item.quantity) - item.discount_amount);
        });
        const globalDiscount = parseFloat(document.getElementById('inputGlobalDiscount').value) || 0;
        const subAfterDisc = Math.max(0, subtotal - globalDiscount);
        const taxChecked = document.getElementById('taxCheckbox').checked;
        const tax = taxChecked ? Math.round(subAfterDisc * 0.11) : 0;
        return subAfterDisc + tax;
    }

    function processCheckout() {
        if (cart.length === 0) return;

        const grandTotal = getGrandTotal();
        const received = paymentMethod === 'cash' ? (parseFloat(cashReceivedInput.value) || 0) : grandTotal;

        if (paymentMethod === 'cash' && received < grandTotal) {
            alert('Uang yang diterima kurang dari total tagihan!');
            cashReceivedInput.focus();
            return;
        }

        btnCheckout.disabled = true;
        btnCheckout.innerHTML = `
            <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
            </svg>
            <span>Memproses Transaksi...</span>
        `;

        const customerName = document.getElementById('customerNameInput').value.trim() || 'Pelanggan Umum';
        const customerId = document.getElementById('customerSelect').value || null;
        const globalDiscount = parseFloat(document.getElementById('inputGlobalDiscount').value) || 0;
        const taxChecked = document.getElementById('taxCheckbox').checked;
        const taxAmount = taxChecked ? Math.round(Math.max(0, getSubtotalOnly() - globalDiscount) * 0.11) : 0;

        const payload = {
            customer_name: customerName,
            customer_id: customerId,
            payment_method: paymentMethod,
            payment_amount: received,
            discount_amount: globalDiscount,
            tax_amount: taxAmount,
            items: cart.map(i => ({
                product_id: i.product_id,
                quantity: i.quantity,
                unit_price: i.unit_price,
                discount_amount: i.discount_amount,
            })),
        };

        fetch('{{ route('kasir.pos.store') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.receipt_url) {
                window.location.href = data.receipt_url;
            } else {
                alert(data.message || 'Gagal menyimpan transaksi POS.');
                btnCheckout.disabled = false;
                btnCheckout.innerHTML = `<span>Selesaikan Transaksi & Cetak Struk (F9)</span>`;
            }
        })
        .catch(err => {
            console.error('Checkout error:', err);
            alert('Terjadi kesalahan koneksi server.');
            btnCheckout.disabled = false;
            btnCheckout.innerHTML = `<span>Selesaikan Transaksi & Cetak Struk (F9)</span>`;
        });
    }

    function getSubtotalOnly() {
        let sub = 0;
        cart.forEach(i => sub += Math.max(0, (i.unit_price * i.quantity) - i.discount_amount));
        return sub;
    }

    function formatNumber(num) {
        return Math.round(num).toLocaleString('id-ID');
    }

    function openOpenShiftModal() {
        document.getElementById('modalOpenShift').classList.remove('hidden');
    }
    function openCloseShiftModal() {
        document.getElementById('modalCloseShift').classList.remove('hidden');
    }
    function closeCloseShiftModal() {
        document.getElementById('modalCloseShift').classList.add('hidden');
    }
</script>
@endsection
