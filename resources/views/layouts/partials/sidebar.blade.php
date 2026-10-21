@php
    $user = auth()->user();
    $codes = \App\Support\MenuAccess::codesFor($user);
    $isSuper = \App\Support\MenuAccess::isSuper($user);

    // Helper untuk cek izin tampil menu
    $canSee = function (array $allowedCodes) use ($codes, $isSuper) {
        if ($isSuper) return true;
        return (bool) array_intersect($codes, $allowedCodes);
    };

    // Helper untuk route yang aman
    $to = function (string $name, string $fallback = '#') {
        return \Illuminate\Support\Facades\Route::has($name) ? route($name) : $fallback;
    };

    $activeRoute = request()->route() ? request()->route()->getName() : '';
    $currentPath = request()->path();

    $itemClass = function (bool $isActive) {
        if ($isActive) {
            return 'flex items-center gap-3 px-2.5 py-2 rounded-xl bg-[#ecfdf3] border border-[#bbf7d0] text-[#14532d] font-bold text-sm tracking-tight transition';
        }
        return 'flex items-center gap-3 px-2.5 py-2 rounded-xl border border-transparent text-gray-700 hover:text-gray-900 hover:bg-gray-100/80 font-medium text-sm tracking-tight transition';
    };
@endphp

<aside
    id="sidebar"
    class="fixed inset-y-0 left-0 z-40 w-60 bg-white border-r border-gray-200 flex flex-col transition-transform duration-200 -translate-x-full lg:translate-x-0"
    aria-label="Menu navigasi utama"
>
    {{-- Header Logo --}}
    <div class="h-[50px] flex items-center justify-between px-4 border-b border-[#eef0f2] flex-shrink-0">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2 text-decoration-none">
            <span class="font-extrabold text-[15px] tracking-wide text-gray-900">DUTA TUNGGAL</span>
            <span class="text-[9px] font-bold text-[#15803d] border border-[#86efac] bg-[#f0fdf4] rounded px-1.5 py-0.2">ERP</span>
        </a>

        {{-- Mobile Close Button --}}
        <button
            type="button"
            onclick="toggleSidebar()"
            class="lg:hidden p-1.5 rounded-lg text-gray-500 hover:bg-gray-100"
            aria-label="Tutup menu"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path d="M6 18L18 6M6 6l12 12" stroke-width="2" stroke-linecap="round"></path>
            </svg>
        </button>
    </div>

    {{-- Menu Scroll Container --}}
    <div class="flex-1 overflow-y-auto px-2 py-3 space-y-4 custom-scrollbar">

        {{-- BERANDA / DASHBOARD --}}
        @php
            $isExecutiveNav = $isSuper || in_array('OWN', $codes) || in_array('FIN', $codes);
        @endphp
        <div class="space-y-0.5">
            <a href="{{ route('dashboard') }}" class="{{ $itemClass(request()->routeIs('dashboard')) }}">
                @if ($isExecutiveNav)
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M3 3v18h18" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M7 15l4-4 3 3 5-6" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                    <span class="flex-1">Dashboard</span>
                @else
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M3 10.5 12 3l9 7.5V21h-6v-6H9v6H3z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                    <span class="flex-1">Beranda</span>
                @endif
                @if (isset($tugasCount) && $tugasCount > 0)
                    <span class="text-[11px] font-bold bg-[#15803d] text-white rounded-full px-2 py-0.2">
                        {{ $tugasCount }}
                    </span>
                @endif
            </a>
        </div>

        {{-- MODUL PENJUALAN --}}
        @if ($canSee(['OWN', 'SM', 'SLS', 'KSR', 'ADP']))
            <div class="space-y-0.5">
                <div class="text-[10.5px] tracking-wider text-gray-400 font-bold px-2.5 pt-2 pb-1 uppercase">
                    Penjualan
                </div>

                @if ($canSee(['OWN', 'SM', 'SLS']))
                    <a href="{{ route('penjualan.quotations.index') ?? '#' }}" class="{{ $itemClass(request()->is('penjualan/penawaran*')) }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M3 12V3h9l9 9-9 9z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                            <circle cx="7.5" cy="7.5" r="1.5"></circle>
                        </svg>
                        <span>Penawaran Harga</span>
                    </a>
                @endif

                <a href="{{ route('penjualan.so.index') ?? '#' }}" class="{{ $itemClass(request()->is('penjualan/so*') || request()->is('penjualan/pesanan*')) }}">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M14 3v6h6M8 13h8M8 17h5" stroke-width="1.8" stroke-linecap="round"></path>
                    </svg>
                    <span>Pesanan Penjualan</span>
                </a>

                @if ($canSee(['OWN', 'ADP', 'SLS', 'KSR']))
                    <a href="{{ route('penjualan.do.index') ?? '#' }}" class="{{ $itemClass(request()->is('pengiriman/do*') || request()->is('penjualan/do*')) }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M3 6h11v10H3zM14 9h4l3 3v4h-7" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                            <circle cx="7" cy="18" r="2" stroke-width="1.8"></circle>
                            <circle cx="17" cy="18" r="2" stroke-width="1.8"></circle>
                        </svg>
                        <span>Perintah Kirim (DO)</span>
                    </a>
                @endif

                @if ($canSee(['OWN', 'ADP']))
                    <a href="{{ route('penjualan.sj.index') ?? '#' }}" class="{{ $itemClass(request()->is('pengiriman/surat-jalan*') || request()->is('penjualan/sj*')) }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2" stroke-width="1.8"></path>
                            <rect x="9" y="3" width="6" height="4" rx="1" stroke-width="1.8"></rect>
                            <path d="m9 14 2 2 4-4" stroke-width="1.8" stroke-linecap="round"></path>
                        </svg>
                        <span>Surat Jalan</span>
                    </a>
                @endif

                @if ($canSee(['OWN', 'KSR', 'ADP', 'FIN']))
                    <a href="{{ route('penjualan.invoices.index') ?? '#' }}" class="{{ $itemClass(request()->is('penjualan/invoice*')) }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2z" stroke-width="1.8" stroke-linecap="round"></path>
                            <path d="M9 8h6M9 12h6" stroke-width="1.8" stroke-linecap="round"></path>
                        </svg>
                        <span>Invoice Penjualan</span>
                    </a>
                @endif

                @if ($canSee(['OWN', 'KSR', 'FIN']))
                    <a href="{{ route('penjualan.receipts.index') ?? '#' }}" class="{{ $itemClass(request()->is('penjualan/terima-pembayaran*')) }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <rect x="3" y="6" width="18" height="14" rx="2" stroke-width="1.8"></rect>
                            <path d="M3 10h18M16 15h2" stroke-width="1.8" stroke-linecap="round"></path>
                        </svg>
                        <span>Terima Pembayaran</span>
                    </a>
                @endif

                @if ($canSee(['OWN', 'SM', 'SLS', 'GDG', 'FIN']))
                    <a href="{{ route('penjualan.returns.index') ?? '#' }}" class="{{ $itemClass(request()->is('penjualan/retur*')) }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M9 14 4 9l5-5" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path d="M4 9h10a6 6 0 0 1 0 12h-3" stroke-width="1.8" stroke-linecap="round"></path>
                        </svg>
                        <span>Retur Penjualan</span>
                    </a>
                @endif

                @if ($canSee(['OWN', 'SM', 'KSR', 'FIN']))
                    <a href="{{ route('penjualan.credit-notes.index') ?? '#' }}" class="{{ $itemClass(request()->is('penjualan/nota-kredit*') || request()->is('penjualan/credit-notes*')) }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M16 15v-1a4 4 0 0 0-4-4H4m0 0l3-3m-3 3l3 3" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                            <rect x="2" y="4" width="20" height="16" rx="2" stroke-width="1.8"></rect>
                        </svg>
                        <span>Nota Kredit</span>
                    </a>
                @endif
            </div>
        @endif

        {{-- MODUL GUDANG --}}
        @if ($canSee(['OWN', 'GDG', 'PUR', 'FIN']))
            <div class="space-y-0.5">
                <div class="text-[10.5px] tracking-wider text-gray-400 font-bold px-2.5 pt-2 pb-1 uppercase">
                    Gudang & Stok
                </div>

                @if ($canSee(['OWN', 'GDG']))
                    <a href="{{ route('gudang.kiriman.index') ?? '#' }}" class="{{ $itemClass(request()->is('gudang/kiriman*')) }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M3 6h11v10H3zM14 9h4l3 3v4h-7" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                            <circle cx="7" cy="18" r="2" stroke-width="1.8"></circle>
                            <circle cx="17" cy="18" r="2" stroke-width="1.8"></circle>
                        </svg>
                        <span>Siapkan & Kirim</span>
                    </a>
                @endif

                @if ($canSee(['OWN', 'GDG', 'PUR']))
                    <a href="{{ route('gudang.penerimaan.index') ?? '#' }}" class="{{ $itemClass(request()->is('gudang/penerimaan*')) }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M22 12h-6l-2 3h-4l-2-3H2" stroke-width="1.8" stroke-linecap="round"></path>
                            <path d="M5.5 5h13L22 12v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-6z" stroke-width="1.8" stroke-linecap="round"></path>
                        </svg>
                        <span>Terima Barang</span>
                    </a>
                @endif

                <a href="{{ route('gudang.stok.index') ?? '#' }}" class="{{ $itemClass(request()->is('gudang/stok*') || request()->is('gudang/cek-stok*')) }}">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M21 8 12 3 3 8v8l9 5 9-5z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M3 8l9 5 9-5M12 13v8" stroke-width="1.8" stroke-linecap="round"></path>
                    </svg>
                    <span>Cek Stok</span>
                </a>

                @if ($canSee(['OWN', 'GDG']))
                    <a href="{{ route('gudang.transfer.index') ?? '#' }}" class="{{ $itemClass(request()->is('gudang/transfer*')) }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M7 7h13l-3-3M17 17H4l3 3" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                        <span>Pindah Stok</span>
                    </a>

                    <a href="{{ route('gudang.opname.index') ?? '#' }}" class="{{ $itemClass(request()->is('gudang/opname*')) }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2" stroke-width="1.8"></path>
                            <rect x="9" y="3" width="6" height="4" rx="1" stroke-width="1.8"></rect>
                            <path d="M9 12h6M9 16h4" stroke-width="1.8" stroke-linecap="round"></path>
                        </svg>
                        <span>Stock Opname</span>
                    </a>
                @endif
            </div>
        @endif

        {{-- MODUL PEMBELIAN --}}
        @if ($canSee(['OWN', 'PUR', 'GDG', 'FIN']))
            <div class="space-y-0.5">
                <div class="text-[10.5px] tracking-wider text-gray-400 font-bold px-2.5 pt-2 pb-1 uppercase">
                    Pembelian
                </div>

                @if ($canSee(['OWN', 'PUR', 'GDG', 'SLS']))
                    <a href="{{ route('pembelian.or.index') ?? '#' }}" class="{{ $itemClass(request()->is('pembelian/permintaan*')) }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path d="M14 3v6h6M12 12v6M9 15h6" stroke-width="1.8" stroke-linecap="round"></path>
                        </svg>
                        <span>Permintaan (OR)</span>
                    </a>
                @endif

                @if ($canSee(['OWN', 'PUR']))
                    <a href="{{ route('pembelian.po.index') ?? '#' }}" class="{{ $itemClass(request()->is('pembelian/po*') || request()->is('pembelian/pesanan*')) }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <rect x="2" y="4" width="20" height="16" rx="2" stroke-width="1.8"></rect>
                            <path d="M7 15h10M7 9h10" stroke-width="1.8" stroke-linecap="round"></path>
                        </svg>
                        <span>Pesanan (PO)</span>
                    </a>
                @endif

                @if ($canSee(['OWN', 'PUR', 'FIN']))
                    <a href="{{ route('pembelian.invoices.index') ?? '#' }}" class="{{ $itemClass(request()->is('pembelian/invoice*')) }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2z" stroke-width="1.8" stroke-linecap="round"></path>
                            <path d="M9 8h6M9 12h6" stroke-width="1.8" stroke-linecap="round"></path>
                        </svg>
                        <span>Invoice Pembelian</span>
                    </a>

                    <a href="{{ route('pembelian.payments.index') ?? '#' }}" class="{{ $itemClass(request()->is('pembelian/pembayaran*') || request()->is('pembelian/vendor-payments*')) }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <rect x="3" y="6" width="18" height="14" rx="2" stroke-width="1.8"></rect>
                            <path d="M3 10h18M16 15h2" stroke-width="1.8" stroke-linecap="round"></path>
                        </svg>
                        <span>Pembayaran Hutang</span>
                    </a>
                @endif
            </div>
        @endif

        {{-- MODUL KEUANGAN & LAPORAN --}}
        @if ($canSee(['OWN', 'FIN', 'KSR']))
            <div class="space-y-0.5">
                <div class="text-[10.5px] tracking-wider text-gray-400 font-bold px-2.5 pt-2 pb-1 uppercase">
                    Keuangan
                </div>

                <a href="{{ route('keuangan.cashbank.index') ?? '#' }}" class="{{ $itemClass(request()->is('keuangan/kas-bank*')) }}">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M3 10 12 4l9 6M5 10v8M19 10v8M9 10v8M15 10v8M3 20h18" stroke-width="1.8"></path>
                    </svg>
                    <span>Kas & Bank</span>
                </a>

                @if ($canSee(['OWN', 'FIN']))
                    <a href="{{ route('keuangan.journal.index') ?? '#' }}" class="{{ $itemClass(request()->is('keuangan/jurnal*')) }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z" stroke-width="1.8"></path>
                            <path d="M6 6h10M6 10h10" stroke-width="1.8" stroke-linecap="round"></path>
                        </svg>
                        <span>Buku Besar & Jurnal</span>
                    </a>

                    <a href="{{ route('laporan.financial.index') ?? '#' }}" class="{{ $itemClass(request()->is('laporan*')) }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M3 3v18h18" stroke-width="1.8" stroke-linecap="round"></path>
                            <path d="m19 9-5 5-4-4-3 3" stroke-width="1.8" stroke-linecap="round"></path>
                        </svg>
                        <span>Laporan Keuangan</span>
                    </a>
                @endif
            </div>
        @endif

        {{-- MASTER DATA --}}
        @if ($canSee(['OWN', 'PUR', 'FIN', 'SM', 'SLS', 'KSR']))
            <div class="space-y-0.5">
                <div class="text-[10.5px] tracking-wider text-gray-400 font-bold px-2.5 pt-2 pb-1 uppercase">
                    Master Data
                </div>

                @if ($canSee(['OWN', 'PUR']))
                    <a href="{{ route('master.products.index') ?? '#' }}" class="{{ $itemClass(request()->is('master/produk*')) }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="m20.5 8.5-8.5-5-8.5 5 8.5 5 8.5-5Z" stroke-width="1.8" stroke-linecap="round"></path>
                            <path d="M3.5 8.5v7l8.5 5 8.5-5v-7" stroke-width="1.8" stroke-linecap="round"></path>
                        </svg>
                        <span>Produk</span>
                    </a>
                @endif

                @if ($canSee(['OWN', 'SM', 'SLS', 'KSR', 'FIN']))
                    <a href="{{ route('master.customers.index') ?? '#' }}" class="{{ $itemClass(request()->is('master/customer*')) }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <circle cx="9" cy="8" r="3.5" stroke-width="1.8"></circle>
                            <path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6 6 0 0 1 3.5 6" stroke-width="1.8"></path>
                        </svg>
                        <span>Customer</span>
                    </a>
                @endif

                @if ($canSee(['OWN', 'PUR', 'FIN']))
                    <a href="{{ route('master.suppliers.index') ?? '#' }}" class="{{ $itemClass(request()->is('master/supplier*')) }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" stroke-width="1.8"></path>
                            <circle cx="9" cy="7" r="4" stroke-width="1.8"></circle>
                            <path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" stroke-width="1.8"></path>
                        </svg>
                        <span>Supplier</span>
                    </a>
                @endif
            </div>
        @endif

        {{-- PENGATURAN --}}
        @if ($canSee(['OWN', 'IT']))
            <div class="space-y-0.5">
                <div class="text-[10.5px] tracking-wider text-gray-400 font-bold px-2.5 pt-2 pb-1 uppercase">
                    Pengaturan
                </div>

                <a href="{{ route('settings.users.index') ?? '#' }}" class="{{ $itemClass(request()->is('pengaturan/pengguna*')) }}">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="3" stroke-width="1.8"></circle>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z" stroke-width="1.8"></path>
                    </svg>
                    <span>Pengguna & Peran</span>
                </a>
            </div>
        @endif

    </div>

    {{-- Footer Info --}}
    <div class="p-3 border-t border-[#eef0f2] text-xs text-gray-400 flex items-center justify-between">
        <span>v2.0 (Tailwind Native)</span>
        <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
    </div>
</aside>

{{-- Mobile Backdrop --}}
<div
    id="sidebarBackdrop"
    onclick="toggleSidebar()"
    class="fixed inset-0 z-30 bg-gray-900/40 backdrop-blur-xs hidden lg:hidden transition-opacity"
></div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        if (sidebar && backdrop) {
            const isHidden = sidebar.classList.contains('-translate-x-full');
            if (isHidden) {
                sidebar.classList.remove('-translate-x-full');
                backdrop.classList.remove('hidden');
            } else {
                sidebar.classList.add('-translate-x-full');
                backdrop.classList.add('hidden');
            }
        }
    }
</script>
