@extends('layouts.app', ['title' => 'Detail Retur Penjualan ' . $customerReturn->return_number])

@section('content')
@php
    $st = $customerReturn->status;
    $badgeClasses = match($st) {
        \App\Models\CustomerReturn::STATUS_PENDING       => 'bg-amber-50 text-amber-700 border-amber-200',
        \App\Models\CustomerReturn::STATUS_RECEIVED      => 'bg-blue-50 text-blue-700 border-blue-200',
        \App\Models\CustomerReturn::STATUS_QC_INSPECTION => 'bg-purple-50 text-purple-700 border-purple-200',
        \App\Models\CustomerReturn::STATUS_APPROVED      => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        \App\Models\CustomerReturn::STATUS_COMPLETED     => 'bg-emerald-100 text-emerald-800 border-emerald-300 font-black',
        \App\Models\CustomerReturn::STATUS_REJECTED      => 'bg-rose-50 text-rose-700 border-rose-200',
        default                                          => 'bg-gray-50 text-gray-700 border-gray-200',
    };

    $allQcDone = $customerReturn->customerReturnItems->isNotEmpty()
        && !$customerReturn->customerReturnItems->contains(fn($item) => empty($item->qc_result) || empty($item->decision));
@endphp

<div x-data="{ showRejectModal: false, activeTab: 'items' }" class="space-y-6 max-w-6xl mx-auto">

    {{-- Breadcrumbs & Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-gray-400 mb-1">
                <a href="{{ route('penjualan.returns.index') }}" class="hover:text-gray-600 transition">Retur Penjualan</a>
                <span class="text-gray-300">/</span>
                <span class="text-gray-700 font-semibold">{{ $customerReturn->return_number }}</span>
            </nav>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-black text-gray-900 tracking-tight">{{ $customerReturn->return_number }}</h1>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full border text-xs font-bold {{ $badgeClasses }}">
                    {{ $customerReturn->status_label }}
                </span>
            </div>
            <p class="text-xs text-gray-500 mt-0.5">
                Klaim pengembalian dari {{ $customerReturn->customer->name ?? 'Pelanggan' }} atas faktur {{ $customerReturn->invoice->invoice_number ?? '-' }}.
            </p>
        </div>

        {{-- Action Buttons --}}
        <div class="flex items-center gap-2 flex-wrap">
            {{-- Cetak PDF --}}
            <a
                href="{{ route('penjualan.returns.pdf', $customerReturn->id) }}"
                target="_blank"
                class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-bold text-xs shadow-xs transition"
            >
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" stroke-width="1.8"></path>
                    <rect x="6" y="14" width="12" height="8" rx="1" stroke-width="1.8"></rect>
                </svg>
                <span>Cetak PDF</span>
            </a>

            {{-- 1. Pending -> Mark Received --}}
            @if($st === \App\Models\CustomerReturn::STATUS_PENDING)
                <form method="POST" action="{{ route('penjualan.returns.markReceived', $customerReturn->id) }}" onsubmit="return confirm('Tandai bahwa fisik barang retur telah sampai & diterima di gudang?');">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-xs transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                        </svg>
                        <span>Tandai Diterima Gudang</span>
                    </button>
                </form>
            @endif

            {{-- 2. Received -> Start QC --}}
            @if($st === \App\Models\CustomerReturn::STATUS_RECEIVED)
                <form method="POST" action="{{ route('penjualan.returns.startQc', $customerReturn->id) }}">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs shadow-xs transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                        </svg>
                        <span>Mulai Inspeksi QC</span>
                    </button>
                </form>
            @endif

            {{-- 3. QC Inspection -> Approve --}}
            @if($st === \App\Models\CustomerReturn::STATUS_QC_INSPECTION)
                <form method="POST" action="{{ route('penjualan.returns.approve', $customerReturn->id) }}" onsubmit="return confirm('Setujui pengajuan retur ini? Seluruh keputusan penanganan akan difinalkan.');">
                    @csrf
                    <button
                        type="submit"
                        {{ !$allQcDone ? 'disabled' : '' }}
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#15803d] hover:bg-[#14532d] disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-bold text-xs shadow-xs transition"
                        title="{{ !$allQcDone ? 'Lengkapi hasil QC semua item terlebih dahulu' : 'Setujui Customer Return' }}"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span>Setujui Retur</span>
                    </button>
                </form>
            @endif

            {{-- 4. Approved -> Complete --}}
            @if($st === \App\Models\CustomerReturn::STATUS_APPROVED && !$customerReturn->stock_restored_at)
                <form method="POST" action="{{ route('penjualan.returns.complete', $customerReturn->id) }}" onsubmit="return confirm('Selesaikan retur penjualan? Stok barang (Perbaikan/Penggantian) akan dipulihkan ke gudang dan jurnal akuntansi akan otomatis diposting. Aksi ini tidak dapat dibatalkan.');">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white font-black text-xs shadow-sm transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                        <span>Selesaikan (Restorasi Stok & Jurnal)</span>
                    </button>
                </form>
            @endif

            {{-- Tolak (Jika belum selesai & belum ditolak) --}}
            @if(!in_array($st, [\App\Models\CustomerReturn::STATUS_COMPLETED, \App\Models\CustomerReturn::STATUS_REJECTED]))
                <button
                    type="button"
                    @click="showRejectModal = true"
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-rose-50 hover:bg-rose-100 border border-rose-200 text-rose-700 font-bold text-xs shadow-xs transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    <span>Tolak</span>
                </button>
            @endif
        </div>
    </div>

    {{-- Alert Notifikasi --}}
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-start gap-3 shadow-xs">
            <svg class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div class="flex-1 font-medium">{{ session('success') }}</div>
        </div>
    @endif

    @if(session('error'))
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-start gap-3 shadow-xs">
            <svg class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            <div class="flex-1 font-medium">{{ session('error') }}</div>
        </div>
    @endif

    @if($st === \App\Models\CustomerReturn::STATUS_REJECTED)
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-900 text-xs shadow-xs space-y-1">
            <div class="font-extrabold flex items-center gap-2 text-sm text-rose-700">
                <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                </svg>
                <span>Pengajuan Retur ini telah Ditolak</span>
            </div>
            <p>
                Ditolak pada: <span class="font-bold">{{ $customerReturn->rejected_at ? \Carbon\Carbon::parse($customerReturn->rejected_at)->format('d/m/Y H:i') : '-' }}</span>
                @if($customerReturn->rejectedBy)
                    oleh <span class="font-bold">{{ $customerReturn->rejectedBy->name }}</span>
                @endif
            </p>
        </div>
    @endif

    {{-- Stepper Alur Verifikasi --}}
    <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs">
        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4">Status & Alur Verifikasi Retur</h3>
        @php
            $steps = [
                ['key' => 'pending', 'label' => '1. Diajukan', 'desc' => $customerReturn->created_at?->format('d/m H:i')],
                ['key' => 'received', 'label' => '2. Diterima Gudang', 'desc' => $customerReturn->received_at?->format('d/m H:i') ?? 'Menunggu fisik'],
                ['key' => 'qc_inspection', 'label' => '3. Inspeksi QC', 'desc' => $customerReturn->qc_inspected_at?->format('d/m H:i') ?? 'Pengecekan'],
                ['key' => 'approved', 'label' => '4. Disetujui', 'desc' => $customerReturn->approved_at?->format('d/m H:i') ?? 'Persetujuan'],
                ['key' => 'completed', 'label' => '5. Selesai', 'desc' => $customerReturn->completed_at?->format('d/m H:i') ?? 'Restorasi Stok'],
            ];

            $order = [
                \App\Models\CustomerReturn::STATUS_PENDING       => 1,
                \App\Models\CustomerReturn::STATUS_RECEIVED      => 2,
                \App\Models\CustomerReturn::STATUS_QC_INSPECTION => 3,
                \App\Models\CustomerReturn::STATUS_APPROVED      => 4,
                \App\Models\CustomerReturn::STATUS_COMPLETED     => 5,
            ];

            $currentStepIndex = $order[$st] ?? 0;
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-5 gap-3">
            @foreach($steps as $idx => $step)
                @php
                    $stepNum = $idx + 1;
                    $isPassed = $currentStepIndex > $stepNum;
                    $isCurrent = $currentStepIndex === $stepNum;
                @endphp
                <div class="flex items-center gap-3 p-3 rounded-xl border {{ $isCurrent ? 'bg-emerald-50/70 border-[#86efac]' : ($isPassed ? 'bg-gray-50 border-gray-200' : 'bg-white border-dashed border-gray-200 opacity-60') }}">
                    <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0 {{ $isPassed ? 'bg-[#15803d] text-white' : ($isCurrent ? 'bg-emerald-600 text-white' : 'bg-gray-200 text-gray-500') }}">
                        @if($isPassed)
                            ✓
                        @else
                            {{ $stepNum }}
                        @endif
                    </div>
                    <div class="min-w-0">
                        <span class="block text-xs font-bold {{ $isCurrent ? 'text-[#15803d]' : 'text-gray-900' }} truncate">{{ $step['label'] }}</span>
                        <span class="block text-[10px] text-gray-400 truncate">{{ $step['desc'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Grid 4 Kartu Parameter Dokumen --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        {{-- Kartu Pelanggan --}}
        <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs space-y-3">
            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Pelanggan</span>
            <div>
                <h3 class="text-base font-extrabold text-gray-900">{{ $customerReturn->customer->name ?? '-' }}</h3>
                <p class="text-xs text-gray-500 mt-0.5">{{ $customerReturn->customer->phone ?? 'Tidak ada kontak telepon' }}</p>
                <p class="text-xs text-gray-400 mt-1">{{ $customerReturn->customer->address ?? '-' }}</p>
            </div>
        </div>

        {{-- Kartu Rujukan Faktur --}}
        <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs space-y-3">
            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Faktur Penjualan (Invoice)</span>
            <div>
                @if($customerReturn->invoice && $customerReturn->invoice->id)
                    <a
                        href="{{ route('penjualan.invoices.show', $customerReturn->invoice->id) }}"
                        class="text-base font-extrabold text-blue-700 hover:underline block"
                    >
                        {{ $customerReturn->invoice->invoice_number }}
                    </a>
                    <span class="text-xs text-gray-500 mt-0.5 block">
                        Tgl: {{ $customerReturn->invoice->invoice_date ? \Carbon\Carbon::parse($customerReturn->invoice->invoice_date)->format('d/m/Y') : '-' }}
                    </span>
                    <span class="text-xs font-semibold text-gray-800 mt-1 block">
                        Total Faktur: Rp {{ number_format((float)$customerReturn->invoice->total, 0, ',', '.') }}
                    </span>
                @else
                    <span class="text-sm font-semibold text-gray-400">Tidak terkait invoice</span>
                @endif
            </div>
        </div>

        {{-- Kartu Gudang & Cabang --}}
        <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs space-y-3">
            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Lokasi & Penerima</span>
            <div>
                <span class="text-base font-extrabold text-gray-900 block">
                    {{ $customerReturn->warehouse->name ?? 'Belum Ditentukan' }}
                </span>
                <span class="text-xs text-gray-500 mt-0.5 block">
                    Cabang: <strong class="text-gray-800">{{ $customerReturn->cabang->nama ?? '-' }}</strong>
                </span>
                <span class="text-xs text-gray-400 mt-1 block">
                    Tgl Retur: {{ $customerReturn->return_date ? \Carbon\Carbon::parse($customerReturn->return_date)->format('d/m/Y') : '-' }}
                </span>
            </div>
        </div>
    </div>

    {{-- Alasan Retur Box --}}
    <div class="bg-amber-50/60 border border-amber-200/80 rounded-2xl p-5 shadow-xs space-y-1.5">
        <span class="text-[11px] font-bold text-amber-800 uppercase tracking-wider block">Alasan Pengembalian Barang</span>
        <p class="text-xs font-medium text-amber-950 leading-relaxed">
            {{ $customerReturn->reason }}
        </p>
        @if($customerReturn->notes)
            <div class="pt-2 border-t border-amber-200/60 text-[11px] text-amber-800">
                <strong>Catatan:</strong> {{ $customerReturn->notes }}
            </div>
        @endif
    </div>

    {{-- Tabs Konten: Item Retur / QC Form, Jurnal Akuntansi, Mutasi Stok --}}
    <div class="space-y-4">
        <div class="flex items-center gap-2 border-b border-gray-200 text-xs font-bold pb-2">
            <button
                type="button"
                @click="activeTab = 'items'"
                :class="activeTab === 'items' ? 'text-[#15803d] border-b-2 border-[#15803d] pb-2 -mb-2.5 font-extrabold' : 'text-gray-500 hover:text-gray-800'"
                class="px-3 transition flex items-center gap-1.5"
            >
                <span>Daftar Barang Retur & QC</span>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-gray-100 text-gray-700">
                    {{ count($customerReturn->customerReturnItems) }}
                </span>
            </button>

            @if($st === \App\Models\CustomerReturn::STATUS_COMPLETED && $journals->isNotEmpty())
                <button
                    type="button"
                    @click="activeTab = 'journals'"
                    :class="activeTab === 'journals' ? 'text-[#15803d] border-b-2 border-[#15803d] pb-2 -mb-2.5 font-extrabold' : 'text-gray-500 hover:text-gray-800'"
                    class="px-3 transition flex items-center gap-1.5"
                >
                    <span>Jurnal Akuntansi</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-emerald-100 text-emerald-800 font-bold">
                        Terposting ({{ $journals->count() }})
                    </span>
                </button>
            @endif

            @if($customerReturn->stockMovements->isNotEmpty())
                <button
                    type="button"
                    @click="activeTab = 'stock'"
                    :class="activeTab === 'stock' ? 'text-[#15803d] border-b-2 border-[#15803d] pb-2 -mb-2.5 font-extrabold' : 'text-gray-500 hover:text-gray-800'"
                    class="px-3 transition flex items-center gap-1.5"
                >
                    <span>Pergerakan Stok Gudang</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-blue-100 text-blue-800 font-bold">
                        {{ $customerReturn->stockMovements->count() }}
                    </span>
                </button>
            @endif
        </div>

        {{-- TAB 1: DAFTAR BARANG & FORM QC --}}
        <div x-show="activeTab === 'items'" class="space-y-4">

            {{-- JIKA STATUS ADALAH RECEIVED ATAU QC_INSPECTION: SEDIAKAN FORM INPUT QC LANGSUNG --}}
            @if(in_array($st, [\App\Models\CustomerReturn::STATUS_RECEIVED, \App\Models\CustomerReturn::STATUS_QC_INSPECTION]))
                <form method="POST" action="{{ route('penjualan.returns.saveQc', $customerReturn->id) }}" class="space-y-4">
                    @csrf

                    <div class="bg-white border border-gray-200 rounded-2xl shadow-xs overflow-hidden">
                        <div class="p-4 bg-purple-50/50 border-b border-purple-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <h3 class="text-xs font-bold text-purple-900 uppercase tracking-wider">Lembar Pemeriksaan QC & Keputusan</h3>
                                <p class="text-[11px] text-purple-700 mt-0.5">
                                    Tentukan hasil QC (Pass/Fail) dan keputusan tindak lanjut untuk setiap item barang retur.
                                </p>
                            </div>

                            {{-- Pilihan Gudang Penerima Jika Belum Ada --}}
                            @if(!$customerReturn->warehouse_id)
                                <div class="flex items-center gap-2">
                                    <label class="text-xs font-bold text-gray-700 whitespace-nowrap">Gudang Penerima:</label>
                                    <select
                                        name="warehouse_id"
                                        required
                                        class="py-1 px-2.5 text-xs font-semibold border border-purple-300 rounded-lg bg-white focus:ring-1 focus:ring-purple-500"
                                    >
                                        <option value="">-- Pilih Gudang --</option>
                                        @foreach(\App\Models\Warehouse::where('status', 1)->get() as $wh)
                                            <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 font-bold uppercase tracking-wider">
                                    <tr>
                                        <th class="py-3 px-4">Produk</th>
                                        <th class="py-3 px-4 text-center">Qty Retur</th>
                                        <th class="py-3 px-4">Deskripsi Kerusakan</th>
                                        <th class="py-3 px-4">Hasil QC</th>
                                        <th class="py-3 px-4">Keputusan Tindak Lanjut</th>
                                        <th class="py-3 px-4">Catatan QC</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($customerReturn->customerReturnItems as $idx => $item)
                                        <tr class="hover:bg-gray-50/50 transition">
                                            <input type="hidden" name="items[{{ $idx }}][id]" value="{{ $item->id }}">

                                            {{-- Produk --}}
                                            <td class="py-3 px-4">
                                                <span class="font-bold text-gray-900 block">{{ $item->product->name ?? '-' }}</span>
                                                <span class="text-[10px] text-gray-400 font-mono block mt-0.5">SKU: {{ $item->product->sku ?? '-' }}</span>
                                            </td>

                                            {{-- Qty Retur --}}
                                            <td class="py-3 px-4 text-center font-extrabold text-gray-900">
                                                {{ (float)$item->quantity }} {{ $item->product?->uom?->abbreviation ?? 'pcs' }}
                                            </td>

                                            {{-- Deskripsi Kerusakan --}}
                                            <td class="py-3 px-4 text-gray-600">
                                                {{ $item->problem_description ?: '-' }}
                                            </td>

                                            {{-- Hasil QC --}}
                                            <td class="py-3 px-4">
                                                <select
                                                    name="items[{{ $idx }}][qc_result]"
                                                    required
                                                    class="w-full py-1.5 px-2 text-xs font-bold border rounded-lg focus:ring-1 focus:ring-purple-500 {{ $item->qc_result === 'pass' ? 'text-emerald-700 bg-emerald-50 border-emerald-300' : ($item->qc_result === 'fail' ? 'text-rose-700 bg-rose-50 border-rose-300' : 'border-gray-300 bg-white') }}"
                                                >
                                                    <option value="">-- Pilih --</option>
                                                    <option value="pass" {{ $item->qc_result === 'pass' ? 'selected' : '' }}>Lulus (Pass)</option>
                                                    <option value="fail" {{ $item->qc_result === 'fail' ? 'selected' : '' }}>Gagal / Cacat (Fail)</option>
                                                </select>
                                            </td>

                                            {{-- Keputusan --}}
                                            <td class="py-3 px-4">
                                                <select
                                                    name="items[{{ $idx }}][decision]"
                                                    required
                                                    class="w-full py-1.5 px-2 text-xs font-semibold border border-gray-300 rounded-lg bg-white focus:ring-1 focus:ring-purple-500"
                                                >
                                                    <option value="">-- Pilih Keputusan --</option>
                                                    @foreach($decisionOptions as $decKey => $decLabel)
                                                        <option value="{{ $decKey }}" {{ $item->decision === $decKey ? 'selected' : '' }}>
                                                            {{ $decLabel }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>

                                            {{-- Catatan QC --}}
                                            <td class="py-3 px-4">
                                                <input
                                                    type="text"
                                                    name="items[{{ $idx }}][qc_notes]"
                                                    value="{{ $item->qc_notes }}"
                                                    placeholder="Catatan fisik..."
                                                    class="w-full py-1.5 px-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-purple-500"
                                                >
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="p-4 bg-gray-50/70 border-t border-gray-100 flex items-center justify-between">
                            <span class="text-xs text-gray-500">
                                Pastikan seluruh item telah diinspeksi sebelum menyimpan hasil.
                            </span>
                            <button
                                type="submit"
                                class="bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs px-4 py-2 rounded-xl shadow-xs transition flex items-center gap-1.5"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Simpan Hasil QC & Keputusan</span>
                            </button>
                        </div>
                    </div>
                </form>

            {{-- JIKA STATUS BUKAN QC / SUDAH SELESAI / PENDING: TAMPILKAN TABEL READ-ONLY --}}
            @else
                <div class="bg-white border border-gray-200 rounded-2xl shadow-xs overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 font-bold uppercase tracking-wider">
                                <tr>
                                    <th class="py-3 px-4">No</th>
                                    <th class="py-3 px-4">Produk</th>
                                    <th class="py-3 px-4 text-center">Qty Retur</th>
                                    <th class="py-3 px-4">Alasan / Kerusakan</th>
                                    <th class="py-3 px-4 text-center">Hasil QC</th>
                                    <th class="py-3 px-4 text-center">Keputusan Penanganan</th>
                                    <th class="py-3 px-4">Catatan QC</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($customerReturn->customerReturnItems as $idx => $item)
                                    @php
                                        $qcClass = match($item->qc_result) {
                                            'pass' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                            'fail' => 'bg-rose-50 text-rose-700 border-rose-200',
                                            default => 'bg-gray-50 text-gray-400 border-gray-200',
                                        };

                                        $decClass = match($item->decision) {
                                            \App\Models\CustomerReturnItem::DECISION_REPLACE => 'bg-blue-50 text-blue-700 border-blue-200',
                                            \App\Models\CustomerReturnItem::DECISION_REPAIR  => 'bg-amber-50 text-amber-700 border-amber-200',
                                            \App\Models\CustomerReturnItem::DECISION_CREDIT  => 'bg-purple-50 text-purple-700 border-purple-200',
                                            \App\Models\CustomerReturnItem::DECISION_REJECT  => 'bg-rose-50 text-rose-700 border-rose-200',
                                            default                                          => 'bg-gray-50 text-gray-400 border-gray-200',
                                        };
                                    @endphp
                                    <tr class="hover:bg-gray-50/50 transition">
                                        <td class="py-3 px-4 text-gray-400">{{ $idx + 1 }}</td>
                                        <td class="py-3 px-4">
                                            <span class="font-bold text-gray-900 block">{{ $item->product->name ?? '-' }}</span>
                                            <span class="text-[10px] text-gray-400 font-mono block mt-0.5">SKU: {{ $item->product->sku ?? '-' }}</span>
                                        </td>
                                        <td class="py-3 px-4 text-center font-extrabold text-gray-900">
                                            {{ (float)$item->quantity }} {{ $item->product?->uom?->abbreviation ?? 'pcs' }}
                                        </td>
                                        <td class="py-3 px-4 text-gray-600">
                                            {{ $item->problem_description ?: '-' }}
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $qcClass }}">
                                                {{ $item->qc_result ? strtoupper($item->qc_result) : 'Belum QC' }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $decClass }}">
                                                {{ $item->decision_label }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-gray-600">
                                            {{ $item->qc_notes ?: '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        {{-- TAB 2: JURNAL AKUNTANSI (HANYA MUNCUL JIKA SELESAI & TERJURNAL) --}}
        @if($st === \App\Models\CustomerReturn::STATUS_COMPLETED && $journals->isNotEmpty())
            <div x-show="activeTab === 'journals'" class="space-y-4">
                <div class="bg-white border border-gray-200 rounded-2xl shadow-xs overflow-hidden">
                    <div class="p-4 bg-emerald-50/60 border-b border-emerald-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-xs font-bold text-emerald-900 uppercase tracking-wider">Jurnal Transaksi Pemulihan Stok & Retur Finansial</h3>
                            <p class="text-[11px] text-emerald-700 mt-0.5">
                                Diposting otomatis oleh sistem saat Customer Return dinyatakan Selesai.
                            </p>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-200">
                            Balanced (Seimbang)
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 font-bold uppercase tracking-wider">
                                <tr>
                                    <th class="py-3 px-4">Kode Akun</th>
                                    <th class="py-3 px-4">Nama Akun (COA)</th>
                                    <th class="py-3 px-4">Deskripsi Transaksi</th>
                                    <th class="py-3 px-4 text-right">Debit (Rp)</th>
                                    <th class="py-3 px-4 text-right">Kredit (Rp)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @php
                                    $totDebit = 0;
                                    $totCredit = 0;
                                @endphp
                                @foreach($journals as $j)
                                    @php
                                        $deb = (float)($j->debit ?? 0);
                                        $crd = (float)($j->credit ?? 0);
                                        $totDebit += $deb;
                                        $totCredit += $crd;
                                        $coaCode = $j->coa?->code ?? $j->account?->code ?? '-';
                                        $coaName = $j->coa?->name ?? $j->account?->name ?? '-';
                                    @endphp
                                    <tr class="hover:bg-gray-50/50 transition">
                                        <td class="py-2.5 px-4 font-mono font-bold text-gray-800">
                                            {{ $coaCode }}
                                        </td>
                                        <td class="py-2.5 px-4 font-semibold text-gray-900">
                                            {{ $coaName }}
                                        </td>
                                        <td class="py-2.5 px-4 text-gray-600">
                                            {{ $j->description }}
                                        </td>
                                        <td class="py-2.5 px-4 text-right font-mono font-bold {{ $deb > 0 ? 'text-gray-900' : 'text-gray-300' }}">
                                            {{ $deb > 0 ? number_format($deb, 2, ',', '.') : '-' }}
                                        </td>
                                        <td class="py-2.5 px-4 text-right font-mono font-bold {{ $crd > 0 ? 'text-gray-900' : 'text-gray-300' }}">
                                            {{ $crd > 0 ? number_format($crd, 2, ',', '.') : '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50 border-t border-gray-200 font-black text-xs text-gray-900">
                                <tr>
                                    <td colspan="3" class="py-3 px-4 text-right uppercase tracking-wider">Total Keseimbangan Jurnal:</td>
                                    <td class="py-3 px-4 text-right font-mono text-emerald-700">Rp {{ number_format($totDebit, 2, ',', '.') }}</td>
                                    <td class="py-3 px-4 text-right font-mono text-emerald-700">Rp {{ number_format($totCredit, 2, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        {{-- TAB 3: MUTASI STOK GUDANG --}}
        @if($customerReturn->stockMovements->isNotEmpty())
            <div x-show="activeTab === 'stock'" class="space-y-4">
                <div class="bg-white border border-gray-200 rounded-2xl shadow-xs overflow-hidden">
                    <div class="p-4 bg-blue-50/60 border-b border-blue-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-xs font-bold text-blue-900 uppercase tracking-wider">Riwayat Mutasi Stok Masuk Gudang</h3>
                            <p class="text-[11px] text-blue-700 mt-0.5">
                                Catatan fisik barang retur yang masuk dan dipulihkan ke gudang tujuan.
                            </p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 font-bold uppercase tracking-wider">
                                <tr>
                                    <th class="py-3 px-4">Tanggal</th>
                                    <th class="py-3 px-4">Gudang</th>
                                    <th class="py-3 px-4">Produk</th>
                                    <th class="py-3 px-4 text-center">Kuantitas Masuk</th>
                                    <th class="py-3 px-4 text-right">Nilai HPP (Rp)</th>
                                    <th class="py-3 px-4">Catatan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($customerReturn->stockMovements as $mov)
                                    <tr class="hover:bg-gray-50/50 transition">
                                        <td class="py-2.5 px-4 font-mono text-gray-700">
                                            {{ \Carbon\Carbon::parse($mov->date)->format('d/m/Y') }}
                                        </td>
                                        <td class="py-2.5 px-4 font-semibold text-gray-900">
                                            {{ $mov->warehouse->name ?? '-' }}
                                        </td>
                                        <td class="py-2.5 px-4 font-medium text-gray-800">
                                            {{ $mov->product->name ?? '-' }}
                                        </td>
                                        <td class="py-2.5 px-4 text-center font-extrabold text-emerald-700">
                                            +{{ (float)$mov->quantity }}
                                        </td>
                                        <td class="py-2.5 px-4 text-right font-mono font-bold text-gray-900">
                                            Rp {{ number_format((float)$mov->value, 2, ',', '.') }}
                                        </td>
                                        <td class="py-2.5 px-4 text-gray-500">
                                            {{ $mov->notes }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- MODAL TOLAK PENGANJUAN RETUR --}}
    <div
        x-show="showRejectModal"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-xs flex items-center justify-center p-4"
    >
        <div
            @click.away="showRejectModal = false"
            class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4"
        >
            <div class="flex items-center gap-3 text-rose-600">
                <div class="w-10 h-10 rounded-xl bg-rose-100 flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-gray-900">Tolak Pengajuan Retur</h3>
                    <p class="text-xs text-gray-500">Pengajuan #{{ $customerReturn->return_number }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('penjualan.returns.reject', $customerReturn->id) }}" class="space-y-4">
                @csrf
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-gray-700">
                        Alasan Penolakan <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        name="rejection_reason"
                        rows="3"
                        required
                        minlength="5"
                        placeholder="Tuliskan alasan penolakan secara jelas..."
                        class="w-full text-xs py-2 px-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-rose-500 focus:border-rose-500"
                    ></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button
                        type="button"
                        @click="showRejectModal = false"
                        class="px-4 py-2 rounded-xl border border-gray-300 text-gray-700 font-bold text-xs hover:bg-gray-50 transition"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs shadow-xs transition"
                    >
                        Tolak Pengajuan
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
