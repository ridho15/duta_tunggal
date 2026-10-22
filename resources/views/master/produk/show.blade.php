@extends('layouts.app')

@section('title', 'Detail Produk: ' . $product->name . ' – Duta Tunggal ERP')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    {{-- Header & Action Buttons --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('master.products.index') }}" class="hover:text-gray-700">Master Produk</a>
                <span class="text-gray-300">/</span>
                <span class="text-gray-500 font-mono">{{ $product->sku }}</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ $product->name }}</h1>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $product->is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-gray-100 text-gray-500 border border-gray-200' }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $product->is_active ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>
                    {{ $product->is_active ? 'Aktif' : 'Non-Aktif' }}
                </span>
            </div>
            <p class="text-xs text-gray-500 mt-1 flex items-center gap-2 font-mono">
                <span>SKU: {{ $product->sku }}</span>
                <span>•</span>
                <span>Merk: {{ $product->kode_merk ?: '-' }}</span>
                <span>•</span>
                <span>Kategori: {{ $product->productCategory?->name ?? 'Umum' }}</span>
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a
                href="{{ route('master.products.edit', $product) }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg shadow-sm transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Edit Produk
            </a>
            <a
                href="{{ route('master.products.index') }}"
                class="inline-flex items-center gap-2 px-3.5 py-2 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition"
            >
                Kembali
            </a>
        </div>
    </div>

    {{-- Highlight Metrics --}}
    @php
        $marginRp = $product->sell_price - $product->cost_price;
        $marginPct = $product->cost_price > 0 ? ($marginRp / $product->cost_price) * 100 : 0;
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Harga Beli (Modal)</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">Rp {{ number_format($product->cost_price, 0, ',', '.') }}</p>
            <p class="text-[11px] text-gray-400 mt-1">Biaya tambahan: Rp {{ number_format($product->biaya, 0, ',', '.') }}</p>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Harga Jual Resmi</p>
            <p class="text-2xl font-bold text-[#15803d] mt-1">Rp {{ number_format($product->sell_price, 0, ',', '.') }}</p>
            <p class="text-[11px] text-gray-400 mt-1">Pajak: {{ $product->tipe_pajak }} ({{ $product->pajak }}%)</p>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Estimasi Margin</p>
            <p class="text-2xl font-bold {{ $marginRp >= 0 ? 'text-blue-600' : 'text-rose-600' }} mt-1">
                Rp {{ number_format($marginRp, 0, ',', '.') }}
            </p>
            <p class="text-[11px] {{ $marginPct >= 0 ? 'text-emerald-600' : 'text-rose-500' }} font-semibold mt-1">
                {{ number_format($marginPct, 1) }}% dari modal
            </p>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Stok Tersedia</p>
            <p class="text-2xl font-bold text-indigo-600 mt-1">
                {{ number_format($totalStock, 0, ',', '.') }} <span class="text-xs font-medium text-gray-500">{{ $product->uom?->abbreviation ?? 'PCS' }}</span>
            </p>
            <p class="text-[11px] text-gray-400 mt-1">Akumulasi seluruh gudang</p>
        </div>
    </div>

    {{-- Detail Panels Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Kolom Kiri: Informasi Teknis & Parameter (2 Col) --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Spesifikasi & Aturan Diskon --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm space-y-4">
                <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Spesifikasi & Aturan Penjualan
                </h3>

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3 text-xs">
                    <div>
                        <dt class="font-medium text-gray-500">Satuan Dasar (UOM)</dt>
                        <dd class="font-semibold text-gray-900 mt-0.5">{{ $product->uom?->name ?? '-' }} ({{ $product->uom?->abbreviation ?? '-' }})</dd>
                    </div>

                    <div>
                        <dt class="font-medium text-gray-500">Cabang Pemilik</dt>
                        <dd class="font-semibold text-gray-900 mt-0.5">{{ $product->cabang?->nama ? "({$product->cabang->kode}) {$product->cabang->nama}" : 'Semua Cabang (Global)' }}</dd>
                    </div>

                    <div>
                        <dt class="font-medium text-gray-500">Aturan Batas Diskon Kasir</dt>
                        <dd class="mt-0.5">
                            @if ($product->harga_batas > 100)
                                <span class="font-bold text-blue-700 bg-blue-50 px-2 py-0.5 rounded border border-blue-200">
                                    Batas Bawah Rp {{ number_format($product->harga_batas, 0, ',', '.') }}
                                </span>
                            @elseif ($product->harga_batas > 0)
                                <span class="font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200">
                                    Diskon Maksimal {{ $product->harga_batas }}%
                                </span>
                            @else
                                <span class="text-gray-400">Tidak dibatasi (Bebas)</span>
                            @endif
                        </dd>
                    </div>

                    <div>
                        <dt class="font-medium text-gray-500">Kelipatan Gudang Besar</dt>
                        <dd class="font-semibold text-gray-900 mt-0.5">{{ number_format($product->jumlah_kelipatan_gudang_besar) }}</dd>
                    </div>

                    <div>
                        <dt class="font-medium text-gray-500">Karakteristik Produk</dt>
                        <dd class="mt-0.5 flex gap-2">
                            @if ($product->is_manufacture)
                                <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-purple-50 text-purple-700 border border-purple-200">Barang Jadi</span>
                            @endif
                            @if ($product->is_raw_material)
                                <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">Bahan Baku</span>
                            @endif
                            @if (! $product->is_manufacture && ! $product->is_raw_material)
                                <span class="text-gray-500">Barang Dagang Standar</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                @if ($product->description)
                    <div class="pt-3 border-t border-gray-100">
                        <p class="text-xs font-medium text-gray-500 mb-1">Deskripsi / Catatan:</p>
                        <div class="p-3 bg-gray-50 rounded-lg text-xs text-gray-700 leading-relaxed">
                            {{ $product->description }}
                        </div>
                    </div>
                @endif
            </div>

            {{-- Multi-Satuan Konversi --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm space-y-3">
                <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2">Konversi Multi-Satuan</h3>
                @if ($product->unitConversions->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50 text-gray-500 font-semibold uppercase">
                                    <th class="py-2 px-3">Satuan Turunan</th>
                                    <th class="py-2 px-3">Nilai Konversi</th>
                                    <th class="py-2 px-3">Ekuivalen Satuan Dasar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($product->unitConversions as $uc)
                                    <tr>
                                        <td class="py-2 px-3 font-semibold text-gray-900">{{ $uc->uom?->name ?? '-' }} ({{ $uc->uom?->abbreviation ?? '-' }})</td>
                                        <td class="py-2 px-3 font-mono">{{ $uc->nilai_konversi }}</td>
                                        <td class="py-2 px-3 text-gray-600">1 {{ $uc->uom?->name }} = {{ $uc->nilai_konversi }} {{ $product->uom?->name }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-xs text-gray-400 italic py-2">Tidak ada konversi satuan turunan.</p>
                @endif
            </div>

            {{-- Stok Fisik per Gudang --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm space-y-3">
                <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2">Ketersediaan Stok per Gudang</h3>
                @if ($product->inventoryStock->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50 text-gray-500 font-semibold uppercase">
                                    <th class="py-2 px-3">Gudang</th>
                                    <th class="py-2 px-3 text-right">Stok Fisik</th>
                                    <th class="py-2 px-3 text-right">Teralokasi (Reserved)</th>
                                    <th class="py-2 px-3 text-right">Tersedia Siap Jual</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($product->inventoryStock as $st)
                                    <tr>
                                        <td class="py-2 px-3 font-medium text-gray-900">{{ $st->warehouse?->name ?? 'Gudang Utama' }}</td>
                                        <td class="py-2 px-3 text-right font-mono">{{ number_format($st->qty_on_hand, 0, ',', '.') }}</td>
                                        <td class="py-2 px-3 text-right font-mono text-amber-600">{{ number_format($st->qty_reserved, 0, ',', '.') }}</td>
                                        <td class="py-2 px-3 text-right font-mono font-bold text-[#15803d]">{{ number_format($st->qty_available, 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-xs text-gray-400 italic py-2">Belum ada catatan mutasi persediaan di gudang untuk SKU ini.</p>
                @endif
            </div>
        </div>

        {{-- Kolom Kanan: Parameter Akuntansi & Supplier (1 Col) --}}
        <div class="space-y-6">
            {{-- Pemetaan Akun Akuntansi (COA) --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm space-y-3">
                <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Buku Besar & COA
                </h3>

                <div class="space-y-2.5 text-xs">
                    <div>
                        <span class="text-gray-400 block text-[11px]">Akun Persediaan:</span>
                        <span class="font-medium text-gray-900">{{ $product->inventoryCoa?->code ?? '-' }} - {{ $product->inventoryCoa?->name ?? 'Default Sistem' }}</span>
                    </div>

                    <div>
                        <span class="text-gray-400 block text-[11px]">Akun Penjualan:</span>
                        <span class="font-medium text-gray-900">{{ $product->salesCoa?->code ?? '-' }} - {{ $product->salesCoa?->name ?? 'Default Sistem' }}</span>
                    </div>

                    <div>
                        <span class="text-gray-400 block text-[11px]">Akun HPP / COGS:</span>
                        <span class="font-medium text-gray-900">{{ $product->cogsCoa?->code ?? '-' }} - {{ $product->cogsCoa?->name ?? 'Default Sistem' }}</span>
                    </div>

                    <div>
                        <span class="text-gray-400 block text-[11px]">Akun Retur Penjualan:</span>
                        <span class="font-medium text-gray-900">{{ $product->salesReturnCoa?->code ?? '-' }} - {{ $product->salesReturnCoa?->name ?? 'Default Sistem' }}</span>
                    </div>
                </div>
            </div>

            {{-- Rekanan Pemasok (Suppliers) --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm space-y-3">
                <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2">Pemasok / Supplier</h3>
                @if ($product->suppliers->count() > 0)
                    <div class="space-y-2">
                        @foreach ($product->suppliers as $sup)
                            <div class="p-2.5 bg-gray-50 rounded-lg text-xs flex items-center justify-between">
                                <div>
                                    <p class="font-semibold text-gray-900">{{ $sup->perusahaan }}</p>
                                    <p class="text-[11px] text-gray-500 font-mono">{{ $sup->code }}</p>
                                </div>
                                <div class="text-right">
                                    <span class="text-gray-400 text-[10px] block">Harga Beli:</span>
                                    <span class="font-bold text-gray-800">Rp {{ number_format($sup->pivot->supplier_price ?? $product->cost_price, 0, ',', '.') }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-gray-400 italic py-2">Belum ada supplier yang ditautkan ke produk ini.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
