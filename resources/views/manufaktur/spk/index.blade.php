@extends('layouts.app')

@section('title', 'Surat Perintah Kerja (SPK) – Duta Tunggal ERP')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">
    {{-- Header & Action Button --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <span>Manufaktur & Produksi</span>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Surat Perintah Kerja (SPK)</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Perintah Kerja Produksi (Manufacturing Order)</h1>
            <p class="text-sm text-gray-500 mt-0.5">Dokumen instruksi kerja pabrik untuk memulai proses konversi bahan baku menjadi barang jadi.</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('manufaktur.spk.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-sm font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Terbitkan SPK Baru
            </a>
        </div>
    </div>

    {{-- Flash Message --}}
    @if (session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2 font-medium">
                <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('success') }}</span>
            </div>
        </div>
    @endif

    @if (session('error'))
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2 font-medium">
                <svg class="w-5 h-5 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('error') }}</span>
            </div>
        </div>
    @endif

    {{-- KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total SPK Terbit</p>
                    <p class="text-2xl font-extrabold text-gray-900 mt-1">{{ number_format($totalOrders) }}</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
            </div>
            <p class="text-[11px] text-gray-400 mt-2">Semua instruksi kerja pabrik</p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Draft (Menunggu Start)</p>
                    <p class="text-2xl font-extrabold text-amber-600 mt-1">{{ number_format($draftOrders) }}</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <p class="text-[11px] text-amber-600 font-medium mt-2">Verifikasi bahan baku</p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Sedang Dikerjakan</p>
                    <p class="text-2xl font-extrabold text-blue-600 mt-1">{{ number_format($inProgressOrders) }}</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </div>
            </div>
            <p class="text-[11px] text-blue-600 font-medium mt-2">Di lantai produksi</p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Selesai Produksi</p>
                    <p class="text-2xl font-extrabold text-emerald-600 mt-1">{{ number_format($completedOrders) }}</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <p class="text-[11px] text-emerald-600 font-medium mt-2">Sudah masuk stok barang jadi</p>
        </div>
    </div>

    {{-- Filter Bar & Table Card --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        {{-- Navigation Tabs --}}
        <div class="border-b border-gray-100 px-4 pt-3 flex flex-wrap items-center justify-between gap-4 bg-gray-50/50">
            <div class="flex items-center gap-2 overflow-x-auto pb-2">
                <a
                    href="{{ route('manufaktur.spk.index', array_merge(request()->query(), ['tab' => 'semua'])) }}"
                    class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition {{ $tab === 'semua' ? 'bg-[#15803d] text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' }}"
                >
                    Semua ({{ $totalOrders }})
                </a>
                <a
                    href="{{ route('manufaktur.spk.index', array_merge(request()->query(), ['tab' => 'draft'])) }}"
                    class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition {{ $tab === 'draft' ? 'bg-[#15803d] text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' }}"
                >
                    Draft ({{ $draftOrders }})
                </a>
                <a
                    href="{{ route('manufaktur.spk.index', array_merge(request()->query(), ['tab' => 'in_progress'])) }}"
                    class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition {{ $tab === 'in_progress' ? 'bg-[#15803d] text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' }}"
                >
                    Sedang Dikerjakan ({{ $inProgressOrders }})
                </a>
                <a
                    href="{{ route('manufaktur.spk.index', array_merge(request()->query(), ['tab' => 'completed'])) }}"
                    class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition {{ $tab === 'completed' ? 'bg-[#15803d] text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' }}"
                >
                    Selesai ({{ $completedOrders }})
                </a>
            </div>

            {{-- Form Search & Filter Cabang --}}
            <form method="GET" action="{{ route('manufaktur.spk.index') }}" class="flex items-center gap-2 pb-2">
                <input type="hidden" name="tab" value="{{ $tab }}">

                @if ($cabangs->count() > 1)
                    <select
                        name="cabang_id"
                        onchange="this.form.submit()"
                        class="text-xs rounded-xl border-gray-300 py-1.5 pl-3 pr-8 focus:ring-[#15803d] focus:border-[#15803d] bg-white"
                    >
                        <option value="">Semua Cabang</option>
                        @foreach ($cabangs as $cb)
                            <option value="{{ $cb->id }}" {{ $cabangId == $cb->id ? 'selected' : '' }}>
                                {{ $cb->nama }}
                            </option>
                        @endforeach
                    </select>
                @endif

                <div class="relative">
                    <input
                        type="text"
                        name="q"
                        value="{{ $search }}"
                        placeholder="Cari nomor MO, produk..."
                        class="w-48 sm:w-64 text-xs rounded-xl border-gray-300 py-1.5 pl-8 pr-3 focus:ring-[#15803d] focus:border-[#15803d]"
                    >
                    <svg class="w-3.5 h-3.5 text-gray-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>

                <button
                    type="submit"
                    class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-xl transition"
                >
                    Filter
                </button>

                @if ($search || $cabangId)
                    <a
                        href="{{ route('manufaktur.spk.index', ['tab' => $tab]) }}"
                        class="px-2.5 py-1.5 text-xs text-rose-600 hover:bg-rose-50 rounded-xl transition font-medium"
                        title="Reset Filter"
                    >
                        Reset
                    </a>
                @endif
            </form>
        </div>

        {{-- Tabel SPK --}}
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-gray-600">
                <thead class="bg-gray-50/75 border-b border-gray-200 text-[11px] font-bold text-gray-500 uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-4">Nomor SPK / MO</th>
                        <th class="py-3 px-4">Produk Jadi Target</th>
                        <th class="py-3 px-4 text-center">Kuantitas Target</th>
                        <th class="py-3 px-4">Resep BOM Acuan</th>
                        <th class="py-3 px-4">Jadwal Produksi</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 font-medium">
                    @forelse ($orders as $order)
                        @php
                            $prod = $order->productionPlan?->product;
                            $bom = $order->productionPlan?->billOfMaterial;
                        @endphp
                        <tr class="hover:bg-gray-50/80 transition">
                            <td class="py-3 px-4">
                                <a href="{{ route('manufaktur.spk.show', $order) }}" class="font-bold text-gray-900 hover:text-[#15803d]">
                                    {{ $order->mo_number }}
                                </a>
                                <div class="text-[11px] text-gray-500 font-normal">
                                    {{ $order->productionPlan?->plan_number ?? 'Rencana Manual' }}
                                </div>
                                @if ($order->cabang)
                                    <span class="inline-block mt-0.5 text-[10px] text-gray-400 font-mono">📍 {{ $order->cabang->nama }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-bold text-gray-900">
                                    {{ $prod?->name ?? 'Produk Jadi' }}
                                </div>
                                <div class="text-[11px] text-gray-400 font-mono">SKU: {{ $prod?->sku ?? '-' }}</div>
                            </td>
                            <td class="py-3 px-4 text-center font-bold text-gray-900">
                                {{ rtrim(rtrim((string) $order->quantity, '0'), '.') }}
                                <span class="text-gray-500 font-normal text-[11px]">{{ $order->productionPlan?->uom?->name ?? 'Pcs' }}</span>
                            </td>
                            <td class="py-3 px-4">
                                @if ($bom)
                                    <a href="{{ route('manufaktur.bom.show', $bom) }}" class="font-bold text-[#15803d] hover:underline">
                                        {{ $bom->code }}
                                    </a>
                                    <div class="text-[11px] text-gray-500">{{ $bom->nama_bom }}</div>
                                @else
                                    <span class="text-gray-400 italic">Tanpa BOM</span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <div class="text-gray-800">
                                    {{ $order->start_date ? $order->start_date->format('d/m/Y') : '-' }}
                                </div>
                                <div class="text-[11px] text-gray-400">
                                    s/d {{ $order->end_date ? $order->end_date->format('d/m/Y') : '-' }}
                                </div>
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if ($order->status === 'draft')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                        Draft
                                    </span>
                                @elseif ($order->status === 'in_progress')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse"></span>
                                        In Progress
                                    </span>
                                @elseif ($order->status === 'completed')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        Selesai
                                    </span>
                                @elseif ($order->status === 'cancelled')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                        Dibatalkan
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-gray-100 text-gray-700">
                                        {{ strtoupper($order->status) }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-center">
                                <div class="inline-flex items-center gap-1.5">
                                    <a
                                        href="{{ route('manufaktur.spk.show', $order) }}"
                                        class="p-1.5 text-gray-500 hover:text-[#15803d] hover:bg-gray-100 rounded-lg transition"
                                        title="Lihat Detail SPK"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>

                                    <a
                                        href="{{ route('manufaktur.spk.pdf', $order) }}"
                                        target="_blank"
                                        class="p-1.5 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition"
                                        title="Cetak SPK PDF"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                        </svg>
                                    </a>

                                    @if ($order->status === 'draft')
                                        <a
                                            href="{{ route('manufaktur.spk.edit', $order) }}"
                                            class="p-1.5 text-gray-500 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition"
                                            title="Ubah SPK"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </a>

                                        <form method="POST" action="{{ route('manufaktur.spk.start', $order) }}" class="inline">
                                            @csrf
                                            <button
                                                type="submit"
                                                onclick="return confirm('Mulai produksi untuk SPK {{ $order->mo_number }}?');"
                                                class="p-1.5 text-emerald-600 hover:text-emerald-800 hover:bg-emerald-50 rounded-lg transition"
                                                title="Mulai Produksi (In Progress)"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-gray-400">
                                <div class="w-12 h-12 mx-auto rounded-full bg-gray-100 flex items-center justify-center text-gray-400 mb-3">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <p class="text-sm font-semibold text-gray-700">Belum ada Surat Perintah Kerja (SPK)</p>
                                <p class="text-xs text-gray-400 mt-0.5">Terbitkan SPK baru berdasarkan resep BOM atau rencana produksi terjadwal.</p>
                                <div class="mt-4">
                                    <a
                                        href="{{ route('manufaktur.spk.create') }}"
                                        class="inline-flex items-center gap-2 px-3.5 py-1.5 text-xs font-semibold bg-[#15803d] text-white rounded-xl shadow-sm hover:bg-[#166534] transition"
                                    >
                                        + Terbitkan SPK Baru
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($orders->hasPages())
            <div class="px-4 py-3 border-t border-gray-100 bg-gray-50/50">
                {{ $orders->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
