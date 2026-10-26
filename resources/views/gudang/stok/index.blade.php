@extends('layouts.app')

@section('title', 'Cek Stok Real-Time – ERP Duta Tunggal')

@section('content')
<div class="space-y-6">
    {{-- Header & Breadcrumbs --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <nav class="flex items-center gap-2 text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">
                <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Dashboard</a>
                <span class="text-gray-300">/</span>
                <span class="text-gray-500">Gudang & Stok</span>
                <span class="text-gray-300">/</span>
                <span class="text-emerald-700 font-bold">Cek Stok Real-Time</span>
            </nav>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Cek Stok & Ketersediaan Barang</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                Monitoring posisi stok fisik, kuantitas terpesan (reserved), stok bebas siap jual (free stock), serta nilai persediaan di setiap gudang.
            </p>
        </div>
        <div class="flex items-center gap-2.5">
            <a href="{{ route('gudang.stok.inventory-card') }}"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm active:scale-[0.98]">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.5L17 7.5V19a2 2 0 0 1-2 2z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
                <span>Laporan Kartu Persediaan</span>
            </a>
            @if ($kpi['critical_count'] > 0)
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                    <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
                    {{ $kpi['critical_count'] }} Produk Menipis/Habis
                </span>
            @endif
        </div>
    </div>

    {{-- Tabs Switcher --}}
    @include('gudang.stok.partials.tabs', ['activeTab' => 'index'])

    {{-- KPI Stat Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total SKU Aktif --}}
        <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm">
            <div class="flex items-center justify-between text-gray-500 mb-2">
                <span class="text-xs font-medium uppercase tracking-wider">Katalog Produk Aktif</span>
                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="m20.5 8.5-8.5-5-8.5 5 8.5 5 8.5-5Z" stroke-width="1.8" stroke-linecap="round"></path>
                        <path d="M3.5 8.5v7l8.5 5 8.5-5v-7" stroke-width="1.8" stroke-linecap="round"></path>
                    </svg>
                </div>
            </div>
            <div class="text-2xl font-bold text-gray-900 tracking-tight">
                {{ number_format($kpi['total_products'], 0, ',', '.') }}
            </div>
            <p class="text-[11px] text-gray-500 mt-1">Item SKU barang terdaftar aktif</p>
        </div>

        {{-- Total Stok Fisik --}}
        <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm">
            <div class="flex items-center justify-between text-gray-500 mb-2">
                <span class="text-xs font-medium uppercase tracking-wider">Total Fisik di Gudang</span>
                <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-700 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M21 8 12 3 3 8v8l9 5 9-5z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M3 8l9 5 9-5M12 13v8" stroke-width="1.8" stroke-linecap="round"></path>
                    </svg>
                </div>
            </div>
            <div class="text-2xl font-bold text-blue-700 tracking-tight">
                {{ number_format($kpi['total_physical_qty'], 0, ',', '.') }}
            </div>
            <p class="text-[11px] text-gray-500 mt-1">Unit persediaan fisik tersedia</p>
        </div>

        {{-- Total Stok Siap Kirim (Free Qty) --}}
        <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm">
            <div class="flex items-center justify-between text-gray-500 mb-2">
                <span class="text-xs font-medium uppercase tracking-wider">Stok Bebas (Siap Kirim)</span>
                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M5 13l4 4L19 7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </div>
            </div>
            <div class="text-2xl font-bold text-emerald-700 tracking-tight">
                {{ number_format($kpi['total_free_qty'], 0, ',', '.') }}
            </div>
            <p class="text-[11px] text-gray-500 mt-1">Setelah dikurangi kuantitas pesanan/DO</p>
        </div>

        {{-- Estimasi Nilai Aset Persediaan --}}
        <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm">
            <div class="flex items-center justify-between text-gray-500 mb-2">
                <span class="text-xs font-medium uppercase tracking-wider">Estimasi Nilai Aset</span>
                <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <rect x="2" y="4" width="20" height="16" rx="2" stroke-width="1.8"></rect>
                        <path d="M12 8v8M8 12h8" stroke-width="1.8" stroke-linecap="round"></path>
                    </svg>
                </div>
            </div>
            <div class="text-xl font-bold text-gray-900 tracking-tight">
                Rp {{ number_format($kpi['total_asset_value'], 0, ',', '.') }}
            </div>
            <p class="text-[11px] text-gray-500 mt-1">Valuasi berbasis HPP asli produk</p>
        </div>
    </div>

    {{-- Filter Panel --}}
    <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm">
        <form method="GET" action="{{ route('gudang.stok.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            {{-- Search Input --}}
            <div class="lg:col-span-2">
                <label for="search" class="block text-xs font-medium text-gray-700 mb-1">Cari Produk / SKU</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="8" stroke-width="1.8"></circle>
                            <path d="m21 21-4.35-4.35" stroke-width="1.8" stroke-linecap="round"></path>
                        </svg>
                    </div>
                    <input type="text"
                           id="search"
                           name="search"
                           value="{{ $filters['search'] }}"
                           placeholder="Ketik nama produk atau kode SKU..."
                           class="w-full pl-9 pr-3 py-2 text-xs border border-gray-200 rounded-xl focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] outline-none transition">
                </div>
            </div>

            {{-- Kategori --}}
            <div>
                <label for="category_id" class="block text-xs font-medium text-gray-700 mb-1">Kategori Produk</label>
                <x-searchable-select name="category_id" :options="collect($categories)->map(fn ($cat) => ['id' => $cat->id, 'label' => $cat->name])->values()" :value="$filters['category_id']" placeholder="Semua Kategori" size="sm" id="category_id" :auto-submit="true" />
            </div>

            {{-- Gudang --}}
            <div>
                <label for="warehouse_id" class="block text-xs font-medium text-gray-700 mb-1">Gudang Penyimpanan</label>
                <x-searchable-select name="warehouse_id" :options="collect($warehouses)->map(fn ($wh) => ['id' => $wh->id, 'label' => $wh->name])->values()" :value="$filters['warehouse_id']" placeholder="Semua Gudang (Konsolidasian)" size="sm" id="warehouse_id" :auto-submit="true" />
            </div>

            {{-- Status Stok & Submit Buttons --}}
            <div class="flex items-end gap-2">
                <div class="flex-1">
                    <label for="status" class="block text-xs font-medium text-gray-700 mb-1">Status Ketersediaan</label>
                    <select name="status" id="status" class="w-full py-2 px-2.5 text-xs border border-gray-200 rounded-xl focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] outline-none bg-white text-gray-700 transition">
                        <option value="all" {{ $filters['status'] === 'all' ? 'selected' : '' }}>Semua Status</option>
                        <option value="in_stock" {{ $filters['status'] === 'in_stock' ? 'selected' : '' }}>Tersedia (> 0)</option>
                        <option value="low_stock" {{ $filters['status'] === 'low_stock' ? 'selected' : '' }}>Menipis (&le; Min)</option>
                        <option value="out_of_stock" {{ $filters['status'] === 'out_of_stock' ? 'selected' : '' }}>Habis (0)</option>
                    </select>
                </div>
                <button type="submit"
                        class="px-4 py-2 text-xs font-semibold text-white bg-gray-900 hover:bg-black rounded-xl transition shadow-sm flex-shrink-0">
                    Filter
                </button>
                @if ($filters['search'] || $filters['category_id'] || $filters['warehouse_id'] || $filters['status'] !== 'all')
                    <a href="{{ route('gudang.stok.index') }}"
                       class="px-2.5 py-2 text-xs font-medium text-gray-600 hover:text-gray-900 border border-gray-200 rounded-xl hover:bg-gray-50 transition"
                       title="Reset Filter">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M4 4v5h.582m15.356 2A8.001 8.001 0 0 0 4.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 0 1-15.357-2m15.357 2H15" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Products Stock Table --}}
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm">
        <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
            <div class="text-xs font-semibold text-gray-800">
                Daftar Persediaan Produk ({{ $products->total() }} barang ditemukan)
            </div>
            <div class="text-[11px] text-gray-500">
                Klik tombol <span class="font-semibold text-emerald-700">Kartu Stok</span> untuk melihat audit trail mutasi kronologis.
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-gray-600">
                <thead class="bg-gray-50/80 text-[11px] uppercase tracking-wider text-gray-500 font-semibold border-b border-gray-100">
                    <tr>
                        <th class="py-3 px-4 w-12 text-center">#</th>
                        <th class="py-3 px-4 min-w-[220px]">Produk & SKU</th>
                        <th class="py-3 px-3">Kategori</th>
                        <th class="py-3 px-3 text-right">Stok Fisik</th>
                        <th class="py-3 px-3 text-right">Terpesan</th>
                        <th class="py-3 px-3 text-right">Siap Kirim (Free)</th>
                        <th class="py-3 px-3 text-right">Min. Stok</th>
                        <th class="py-3 px-4 text-right">Nilai Aset</th>
                        <th class="py-3 px-3 text-center">Status</th>
                        <th class="py-3 px-4 text-center w-36">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-xs text-gray-700">
                    @forelse ($products as $index => $product)
                        @php
                            $stocks = $product->inventoryStock;
                            $totalPhysical = $stocks->sum('qty_available');
                            $totalReserved = $stocks->sum('qty_reserved');
                            $totalFree = $totalPhysical - $totalReserved;
                            $minStock = $stocks->max('qty_min') ?? 0;
                            $unitCost = (float) $product->cost_price;
                            $assetVal = $totalPhysical * $unitCost;

                            // Determine status
                            if ($totalPhysical <= 0) {
                                $statusLabel = 'Habis';
                                $statusBadge = 'bg-rose-50 text-rose-700 border-rose-200';
                                $statusDot = 'bg-rose-500';
                            } elseif ($totalPhysical <= $minStock) {
                                $statusLabel = 'Menipis';
                                $statusBadge = 'bg-amber-50 text-amber-700 border-amber-200';
                                $statusDot = 'bg-amber-500';
                            } else {
                                $statusLabel = 'Aman';
                                $statusBadge = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                                $statusDot = 'bg-emerald-500';
                            }
                        @endphp
                        <tr x-data="{ open: false }" class="hover:bg-gray-50/60 transition-colors">
                            <td class="py-3.5 px-4 text-center text-gray-400">
                                {{ $products->firstItem() + $index }}
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-gray-900 leading-snug">
                                    {{ $product->name }}
                                </div>
                                <div class="flex items-center gap-2 mt-1 text-[11px] text-gray-500">
                                    <span class="inline-block bg-gray-100 text-gray-700 px-1.5 py-0.5 rounded text-[11px] font-semibold font-mono">
                                        {{ $product->sku ?: 'NO-SKU' }}
                                    </span>
                                    <span>•</span>
                                    <span>Satuan: {{ $product->uom?->name ?? 'Pcs' }}</span>
                                </div>
                            </td>
                            <td class="py-3.5 px-3 text-gray-600">
                                {{ $product->productCategory?->name ?? '-' }}
                            </td>
                            <td class="py-3.5 px-3 text-right font-mono font-bold {{ $totalPhysical > 0 ? 'text-gray-900' : 'text-rose-600' }}">
                                {{ number_format($totalPhysical, 0, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-3 text-right font-mono text-gray-500">
                                {{ number_format($totalReserved, 0, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-3 text-right font-mono font-bold text-emerald-700">
                                {{ number_format($totalFree, 0, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-3 text-right font-mono text-gray-400">
                                {{ number_format($minStock, 0, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono">
                                <div class="font-bold text-gray-900">
                                    Rp {{ number_format($assetVal, 0, ',', '.') }}
                                </div>
                                <div class="text-[10px] text-gray-400">
                                    @ Rp {{ number_format($unitCost, 0, ',', '.') }}
                                </div>
                            </td>
                            <td class="py-3.5 px-3 text-center">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold border {{ $statusBadge }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $statusDot }}"></span>
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <a href="{{ route('gudang.stok.card', $product->id) }}"
                                       class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded-lg border border-emerald-200 transition"
                                       title="Buka Kartu Persediaan">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                             <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z" stroke-width="1.8"></path>
                                            <path d="M6 6h10M6 10h10" stroke-width="1.8" stroke-linecap="round"></path>
                                        </svg>
                                        <span>Kartu Stok</span>
                                    </a>

                                    @if ($stocks->count() > 1)
                                        <button type="button"
                                                @click="open = !open"
                                                class="p-1.5 text-gray-400 hover:text-gray-700 rounded-lg hover:bg-gray-100 transition"
                                                :title="open ? 'Tutup Rincian Gudang' : 'Lihat Rincian per Gudang'">
                                            <svg class="w-4 h-4 transform transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path d="m19 9-7 7-7-7" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>

                        {{-- Accordion Breakdown per Gudang --}}
                        @if ($stocks->count() > 1)
                            <tr x-show="open" x-cloak class="bg-gray-50/80">
                                <td colspan="10" class="py-2.5 px-6 border-y border-gray-200/60">
                                    <div class="text-[11px] font-semibold text-gray-700 uppercase tracking-wider mb-1.5">
                                        Distribusi Stok di Setiap Gudang:
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2.5">
                                        @foreach ($stocks as $stock)
                                            <div class="p-2.5 bg-white border border-gray-100 rounded-xl shadow-sm">
                                                <div class="font-bold text-xs text-gray-900 truncate">
                                                    {{ $stock->warehouse?->name ?? 'Gudang #' . $stock->warehouse_id }}
                                                </div>
                                                <div class="text-[11px] text-gray-500 mb-1">
                                                    Rak: {{ $stock->rak?->name ?? '-' }}
                                                </div>
                                                <div class="flex items-center justify-between text-xs font-mono pt-1 border-t border-gray-100">
                                                    <span class="text-gray-600">Fisik: <strong>{{ number_format($stock->qty_available, 0, ',', '.') }}</strong></span>
                                                    <span class="text-emerald-700">Bebas: <strong>{{ number_format($stock->free_qty, 0, ',', '.') }}</strong></span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="10" class="py-12 text-center text-gray-400">
                                <svg class="w-10 h-10 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                                <div class="text-sm font-semibold text-gray-600">Tidak ada produk ditemukan</div>
                                <div class="text-xs text-gray-400 mt-0.5">Coba ubah kata kunci pencarian atau bersihkan filter di atas.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($products->hasPages())
            <div class="px-5 py-3 border-t border-gray-100 bg-gray-50/50">
                {{ $products->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
