@extends('layouts.app')

@section('title', $materialIssue->issue_number . ' – Detail Pengeluaran / Retur Bahan Baku')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto pb-16">
    {{-- Top Bar & Action Toolbar --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('manufaktur.material-issue.index') }}" class="hover:text-[#15803d]">Pengeluaran Bahan</a>
                <span class="text-gray-300">/</span>
                <span class="text-gray-700 font-mono">{{ $materialIssue->issue_number }}</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight font-mono">{{ $materialIssue->issue_number }}</h1>
                @if ($materialIssue->type === 'issue')
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                        Pengeluaran Bahan Baku
                    </span>
                @else
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                        Retur Sisa Bahan
                    </span>
                @endif
            </div>
            <p class="text-xs text-gray-500 mt-1">Dibuat pada {{ $materialIssue->created_at?->format('d M Y, H:i') }} oleh <span class="font-semibold text-gray-700">{{ $materialIssue->createdBy?->name ?? 'Sistem' }}</span></p>
        </div>

        {{-- Action Buttons --}}
        <div class="flex flex-wrap items-center gap-2">
            <a
                href="{{ route('manufaktur.material-issue.pdf', $materialIssue) }}"
                target="_blank"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white hover:bg-gray-50 text-gray-700 border border-gray-200 text-xs font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Cetak PDF
            </a>

            {{-- Draft Actions --}}
            @if ($materialIssue->status === 'draft')
                <a
                    href="{{ route('manufaktur.material-issue.edit', $materialIssue) }}"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white hover:bg-gray-50 text-amber-700 border border-gray-200 text-xs font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Edit Draft
                </a>

                <form action="{{ route('manufaktur.material-issue.submit-approval', $materialIssue) }}" method="POST" class="inline">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                        </svg>
                        Ajukan ke Gudang
                    </button>
                </form>
            @endif

            {{-- Pending Approval Actions --}}
            @if (in_array($materialIssue->status, ['draft', 'pending_approval']))
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#15803d] hover:bg-[#14532d] text-white text-xs font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
                    @click="$dispatch('open-modal-confirm-warehouse')"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Konfirmasi & Setujui Gudang
                </button>

                <button
                    type="button"
                    onclick="document.getElementById('rejectModal').classList.remove('hidden')"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white hover:bg-rose-50 text-rose-700 border border-gray-200 text-xs font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Tolak
                </button>
            @endif

            {{-- Approved Actions --}}
            @if ($materialIssue->status === 'approved')
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#15803d] hover:bg-[#14532d] text-white text-xs font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
                    @click="$dispatch('open-modal-complete-issue')"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Selesaikan Serah Terima
                </button>
            @endif

            {{-- Delete action for Draft --}}
            @if ($materialIssue->status === 'draft')
                <button
                    type="button"
                    class="p-2 text-gray-400 hover:text-rose-600 hover:bg-rose-50 border border-transparent rounded-xl transition"
                    title="Hapus Draft"
                    @click="$dispatch('open-modal-delete-issue')"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </button>
            @endif
        </div>
    </div>


    {{-- Stepper Status Lifecycle --}}
    @php
        $statusLevels = [
            'draft' => 1,
            'pending_approval' => 2,
            'approved' => 3,
            'completed' => 4,
            'rejected' => 0,
        ];
        $currentLevel = $statusLevels[$materialIssue->status] ?? 1;
    @endphp
    <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm">
        <div class="flex items-center justify-between max-w-3xl mx-auto relative">
            <div class="absolute left-6 right-6 top-1/2 -translate-y-1/2 h-1 bg-gray-200 -z-0"></div>
            @if ($materialIssue->status !== 'rejected')
                <div
                    class="absolute left-6 top-1/2 -translate-y-1/2 h-1 bg-[#15803d] transition-all duration-500 -z-0"
                    style="width: {{ $currentLevel === 1 ? '0%' : ($currentLevel === 2 ? '33%' : ($currentLevel === 3 ? '66%' : '100%')) }};"
                ></div>
            @endif

            {{-- Step 1: Draft --}}
            <div class="relative z-10 flex flex-col items-center bg-white px-2">
                <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-xs {{ $currentLevel >= 1 ? 'bg-[#15803d] text-white ring-4 ring-emerald-50' : 'bg-gray-100 text-gray-400' }}">
                    1
                </div>
                <span class="text-xs font-bold text-gray-800 mt-2">Draft Dibuat</span>
                <span class="text-[10.5px] text-gray-400">Pencatatan Item</span>
            </div>

            {{-- Step 2: Menunggu Gudang --}}
            <div class="relative z-10 flex flex-col items-center bg-white px-2">
                <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-xs {{ $currentLevel >= 2 ? 'bg-[#15803d] text-white ring-4 ring-emerald-50' : 'bg-gray-100 text-gray-400' }}">
                    2
                </div>
                <span class="text-xs font-bold text-gray-800 mt-2">Menunggu Gudang</span>
                <span class="text-[10.5px] text-gray-400">Verifikasi Fisik</span>
            </div>

            {{-- Step 3: Disetujui (Reservasi Stok) --}}
            <div class="relative z-10 flex flex-col items-center bg-white px-2">
                <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-xs {{ $currentLevel >= 3 ? 'bg-[#15803d] text-white ring-4 ring-emerald-50' : 'bg-gray-100 text-gray-400' }}">
                    3
                </div>
                <span class="text-xs font-bold text-gray-800 mt-2">Disetujui</span>
                <span class="text-[10.5px] text-gray-400">Stok Direservasi</span>
            </div>

            {{-- Step 4: Selesai (Mutasi Stok & Jurnal BDP) --}}
            <div class="relative z-10 flex flex-col items-center bg-white px-2">
                <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-xs {{ $currentLevel >= 4 ? 'bg-[#15803d] text-white ring-4 ring-emerald-50' : 'bg-gray-100 text-gray-400' }}">
                    4
                </div>
                <span class="text-xs font-bold text-gray-800 mt-2">Selesai</span>
                <span class="text-[10.5px] text-gray-400">Mutasi Stok & BDP</span>
            </div>
        </div>

        @if ($materialIssue->status === 'rejected')
            <div class="mt-4 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center gap-2">
                <svg class="w-4 h-4 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span><strong>Dokumen ini telah ditolak oleh Gudang:</strong> {{ $materialIssue->notes }}</span>
            </div>
        @endif
    </div>

    {{-- Detail Summary Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        {{-- Card 1: Informasi Produksi --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm space-y-3">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider">Perintah Kerja (SPK)</h3>
            <div>
                @if ($materialIssue->manufacturingOrder && $materialIssue->manufacturingOrder->exists)
                    <div class="flex items-center gap-2">
                        <a
                            href="{{ route('manufaktur.spk.show', $materialIssue->manufacturingOrder) }}"
                            class="text-sm font-extrabold text-[#15803d] hover:underline"
                        >
                            {{ $materialIssue->manufacturingOrder->mo_number }}
                        </a>
                        <span class="text-[11px] px-2 py-0.5 rounded-full font-semibold bg-gray-100 text-gray-700">
                            {{ ucfirst($materialIssue->manufacturingOrder->status) }}
                        </span>
                    </div>
                    <p class="text-xs font-semibold text-gray-800 mt-1">
                        {{ $materialIssue->manufacturingOrder->productionPlan?->product?->name ?? 'Produk Jadi' }}
                    </p>
                @elseif ($materialIssue->productionPlan && $materialIssue->productionPlan->exists)
                    <span class="text-sm font-bold text-gray-800">{{ $materialIssue->productionPlan->plan_number }}</span>
                    <p class="text-xs text-gray-600">{{ $materialIssue->productionPlan->product?->name ?? '-' }}</p>
                @else
                    <span class="text-xs text-gray-400 italic">Pengeluaran Mandiri (Tanpa SPK)</span>
                @endif
            </div>
        </div>

        {{-- Card 2: Gudang & Tanggal --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm space-y-3">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider">Lokasi Gudang & Tanggal</h3>
            <div>
                <p class="text-sm font-bold text-gray-900">{{ $materialIssue->warehouse?->name ?? 'Gudang Utama' }}</p>
                <p class="text-xs text-gray-500">Kode: {{ $materialIssue->warehouse?->kode ?? '-' }} • Cabang: {{ $materialIssue->warehouse?->cabang?->nama ?? '-' }}</p>
                <div class="mt-2 text-xs text-gray-600 flex items-center gap-1.5 font-medium">
                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span>Tanggal: <strong>{{ $materialIssue->issue_date?->format('d F Y') }}</strong></span>
                </div>
            </div>
        </div>

        {{-- Card 3: Nilai BDP Bahan --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm space-y-3">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider">Nilai Total Bahan Baku</h3>
            <div>
                <p class="text-2xl font-extrabold text-[#15803d]">Rp {{ number_format($materialIssue->total_cost, 0, ',', '.') }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $materialIssue->items->count() }} jenis material dikeluarkan</p>
                @if ($materialIssue->status === 'completed')
                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 mt-1">
                        ✓ Terjurnal ke Pos BDP 1400.04
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-600 mt-1">
                        ⏳ Menunggu penyelesaian untuk penjurnalan
                    </span>
                @endif
            </div>
        </div>
    </div>

    {{-- Alert Ketersediaan Stok Realtime --}}
    @if ($materialIssue->status !== 'completed')
        @if ($allStockSufficient)
            <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span><strong>Stok Tersedia:</strong> Seluruh item bahan baku memiliki stok bebas pakai yang mencukupi di gudang terkait.</span>
            </div>
        @else
            <div class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center gap-2">
                <svg class="w-4 h-4 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span><strong>Peringatan Stok Kurang:</strong> Beberapa bahan baku memiliki stok bebas di bawah kuantitas yang diminta. Segera lakukan pengadaan atau mutasi antar gudang.</span>
            </div>
        @endif
    @endif

    {{-- Tabel Rincian Bahan Baku --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-sm sm:text-base font-bold text-gray-900">Rincian Material & Kebutuhan Fisik</h2>
            <span class="text-xs font-semibold text-gray-500">{{ $materialIssue->items->count() }} Item Bahan</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs sm:text-sm">
                <thead>
                    <tr class="bg-gray-50/75 border-b border-gray-200 text-gray-600 font-semibold uppercase tracking-wider text-[10.5px]">
                        <th class="py-3 px-4 sm:px-6">No.</th>
                        <th class="py-3 px-4">Bahan Baku (SKU & Nama)</th>
                        <th class="py-3 px-4">Satuan</th>
                        <th class="py-3 px-4 text-right">Kuantitas</th>
                        <th class="py-3 px-4 text-right">Harga Pokok</th>
                        <th class="py-3 px-4 text-right">Subtotal</th>
                        <th class="py-3 px-4 sm:px-6 text-center">Status Stok Bebas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    @forelse ($materialIssue->items as $idx => $item)
                        @php
                            $sc = $stockCheck[$item->id] ?? null;
                            $isSuff = $sc['is_sufficient'] ?? true;
                        @endphp
                        <tr class="hover:bg-gray-50/75 transition">
                            <td class="py-3 px-4 sm:px-6 text-gray-400 font-mono text-xs">{{ $idx + 1 }}</td>
                            <td class="py-3 px-4">
                                <span class="font-bold text-gray-900">{{ $item->product?->name ?? 'Produk' }}</span>
                                <div class="text-[11px] text-gray-400 font-mono">{{ $item->product?->sku ?? '-' }}</div>
                            </td>
                            <td class="py-3 px-4 font-medium text-gray-600">
                                {{ $item->uom?->name ?? $item->product?->uom?->name ?? 'Pcs' }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-gray-900">
                                {{ number_format($item->quantity, 2, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-gray-600">
                                Rp {{ number_format($item->cost_per_unit, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-gray-900">
                                Rp {{ number_format($item->total_cost, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 sm:px-6 text-center">
                                @if ($sc)
                                    @if ($isSuff)
                                        <span class="inline-flex items-center gap-1 font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full text-xs">
                                            ✓ Bebas: {{ number_format($sc['free'], 2, ',', '.') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 font-semibold text-rose-700 bg-rose-50 px-2.5 py-1 rounded-full text-xs">
                                            ⚠ Kurang (Bebas: {{ number_format($sc['free'], 2, ',', '.') }})
                                        </span>
                                    @endif
                                @else
                                    <span class="text-gray-400 text-xs">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-gray-400">Tidak ada rincian bahan.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50 border-t-2 border-gray-200 font-bold text-gray-900 text-xs sm:text-sm">
                        <td colspan="3" class="py-3 px-4 sm:px-6 text-right uppercase tracking-wider text-[11px] text-gray-500">
                            Total Kuantitas:
                        </td>
                        <td class="py-3 px-4 text-right font-mono">
                            {{ number_format($materialIssue->items->sum('quantity'), 2, ',', '.') }}
                        </td>
                        <td class="py-3 px-4 text-right uppercase tracking-wider text-[11px] text-gray-500">
                            Total Biaya:
                        </td>
                        <td class="py-3 px-4 text-right font-mono text-[#15803d] text-base">
                            Rp {{ number_format($materialIssue->total_cost, 0, ',', '.') }}
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Integrasi Jurnal Akuntansi BDP --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm space-y-4">
        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
            <div class="flex items-center gap-2">
                <span class="w-6 h-6 rounded-lg bg-indigo-50 text-indigo-700 flex items-center justify-center text-xs font-extrabold">J</span>
                <div>
                    <h3 class="text-sm font-bold text-gray-900">Jurnal Akuntansi BDP (Barang Dalam Proses)</h3>
                    <p class="text-[11px] text-gray-500">Pencatatan akuntansi otomatis ke buku besar keuangan.</p>
                </div>
            </div>
            @if ($materialIssue->journalEntries->isNotEmpty())
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                    ✓ Jurnal Terposting ({{ $materialIssue->journalEntries->count() }} Baris)
                </span>
            @else
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-600">
                    Belum Terposting (Otomatis saat status Selesai)
                </span>
            @endif
        </div>

        @if ($materialIssue->journalEntries->isNotEmpty())
            <div class="overflow-x-auto border border-gray-100 rounded-xl">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-gray-50 text-gray-600 font-semibold uppercase tracking-wider text-[10.5px]">
                            <th class="py-2.5 px-3">Kode COA</th>
                            <th class="py-2.5 px-3">Nama Akun</th>
                            <th class="py-2.5 px-3">Keterangan</th>
                            <th class="py-2.5 px-3 text-right">Debit (Rp)</th>
                            <th class="py-2.5 px-3 text-right">Kredit (Rp)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 font-mono">
                        @foreach ($materialIssue->journalEntries as $entry)
                            <tr class="hover:bg-gray-50/75">
                                <td class="py-2 px-3 font-bold text-indigo-700">{{ $entry->chartOfAccount?->code ?? '-' }}</td>
                                <td class="py-2 px-3 text-gray-800 font-sans font-medium">{{ $entry->chartOfAccount?->name ?? '-' }}</td>
                                <td class="py-2 px-3 text-gray-500 font-sans">{{ $entry->description }}</td>
                                <td class="py-2 px-3 text-right font-bold text-gray-900">
                                    {{ $entry->debit > 0 ? number_format($entry->debit, 0, ',', '.') : '-' }}
                                </td>
                                <td class="py-2 px-3 text-right font-bold text-gray-900">
                                    {{ $entry->credit > 0 ? number_format($entry->credit, 0, ',', '.') : '-' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="bg-gray-50/50 p-4 rounded-xl border border-dashed border-gray-200 text-xs text-gray-500">
                <p class="font-semibold text-gray-700">Mekanisme Jurnal yang akan terbentuk saat serah terima selesai:</p>
                @if ($materialIssue->type === 'issue')
                    <ul class="list-disc pl-5 mt-1 space-y-0.5 font-mono text-[11.5px]">
                        <li><strong class="text-emerald-700">DEBIT:</strong> 1400.04 Pos Sementara Produksi / BDP sebesar Rp {{ number_format($materialIssue->total_cost, 0, ',', '.') }}</li>
                        <li><strong class="text-rose-700">KREDIT:</strong> Akun Persediaan Bahan Baku terkait sebesar Rp {{ number_format($materialIssue->total_cost, 0, ',', '.') }}</li>
                    </ul>
                @else
                    <ul class="list-disc pl-5 mt-1 space-y-0.5 font-mono text-[11.5px]">
                        <li><strong class="text-emerald-700">DEBIT:</strong> Akun Persediaan Bahan Baku terkait sebesar Rp {{ number_format($materialIssue->total_cost, 0, ',', '.') }}</li>
                        <li><strong class="text-rose-700">KREDIT:</strong> 1400.04 Pos Sementara Produksi / BDP sebesar Rp {{ number_format($materialIssue->total_cost, 0, ',', '.') }}</li>
                    </ul>
                @endif
            </div>
        @endif
    </div>

    {{-- Audit Log Konfirmasi Gudang --}}
    @if ($materialIssue->warehouseConfirmations->isNotEmpty())
        <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm space-y-3">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider">Riwayat Konfirmasi Gudang (Warehouse Confirmations)</h3>
            <div class="space-y-2">
                @foreach ($materialIssue->warehouseConfirmations as $wc)
                    <div class="p-3 rounded-xl border border-gray-100 bg-gray-50/50 flex items-center justify-between text-xs">
                        <div>
                            <span class="font-bold text-gray-800 font-mono">WC #{{ $wc->id }}</span>
                            <span class="text-gray-400 mx-1">•</span>
                            <span class="text-gray-600">{{ $wc->note ?: 'Konfirmasi Pengambilan Bahan' }}</span>
                            @if ($wc->confirmed_at)
                                <p class="text-[11px] text-gray-400 mt-0.5">Dikonfirmasi oleh {{ $wc->user?->name ?? 'Petugas Gudang' }} pada {{ $wc->confirmed_at?->format('d/m/Y H:i') }}</p>
                            @endif
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $wc->status === 'confirmed' ? 'bg-emerald-100 text-emerald-800' : ($wc->status === 'rejected' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800') }}">
                            {{ ucfirst($wc->status) }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

{{-- Modal Penolakan (Reject Modal) --}}
<div id="rejectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 hidden">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 shadow-xl">
        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
            <h3 class="text-base font-bold text-gray-900">Tolak Pengeluaran Bahan</h3>
            <button
                type="button"
                onclick="document.getElementById('rejectModal').classList.add('hidden')"
                class="text-gray-400 hover:text-gray-600"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <form action="{{ route('manufaktur.material-issue.reject', $materialIssue) }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label for="rejection_reason" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                    Alasan Penolakan <span class="text-rose-500">*</span>
                </label>
                <textarea
                    name="rejection_reason"
                    id="rejection_reason"
                    rows="3"
                    required
                    placeholder="Contoh: Fisik bahan baku di gudang basah atau tidak memenuhi standar mutu produksi."
                    class="w-full py-2 px-3 text-xs sm:text-sm border border-gray-200 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500"
                ></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <button
                    type="button"
                    onclick="document.getElementById('rejectModal').classList.add('hidden')"
                    class="px-4 py-2 text-xs font-semibold text-gray-600 hover:text-gray-900"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-xl shadow-sm transition"
                >
                    Konfirmasi Penolakan
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Konfirmasi Setujui Gudang --}}
<x-confirmation-modal
    name="confirm-warehouse"
    title="Konfirmasi & Setujui Pengeluaran Gudang"
    variant="success"
    confirm-text="Ya, Setujui Dokumen"
    cancel-text="Batal"
    action="{{ route('manufaktur.material-issue.confirm-warehouse', $materialIssue) }}"
    method="POST"
>
    <p class="text-sm text-gray-600">
        Konfirmasi serah terima fisik barang dan setujui dokumen pengeluaran bahan <strong>{{ $materialIssue->issue_number }}</strong>?
    </p>
    <p class="text-xs text-[#15803d] mt-2 font-medium">
        Dokumen akan ditandai disetujui oleh gudang dan siap dieksekusi pemotongan stok fisik.
    </p>
</x-confirmation-modal>

{{-- Modal Konfirmasi Selesaikan Pengeluaran Bahan --}}
<x-confirmation-modal
    name="complete-issue"
    title="Selesaikan Serah Terima Bahan Baku"
    variant="success"
    confirm-text="Ya, Selesaikan & Potong Stok"
    cancel-text="Batal"
    action="{{ route('manufaktur.material-issue.complete', $materialIssue) }}"
    method="POST"
>
    <p class="text-sm text-gray-600">
        Selesaikan dokumen pengeluaran bahan <strong>{{ $materialIssue->issue_number }}</strong>?
    </p>
    <p class="text-xs text-[#15803d] mt-2 font-medium">
        Stok fisik bahan baku di gudang akan langsung dipotong dan jurnal akuntansi Barang Dalam Proses (BDP) otomatis dibukukan.
    </p>
</x-confirmation-modal>

{{-- Modal Konfirmasi Hapus Draft --}}
<x-confirmation-modal
    name="delete-issue"
    title="Hapus Draft Pengeluaran Bahan"
    variant="danger"
    confirm-text="Ya, Hapus Draft"
    cancel-text="Batal"
    action="{{ route('manufaktur.material-issue.destroy', $materialIssue) }}"
    method="DELETE"
>
    <p class="text-sm text-gray-600">
        Apakah Anda yakin ingin menghapus draft pengeluaran bahan <strong>{{ $materialIssue->issue_number }}</strong>? Tindakan ini tidak dapat dibatalkan.
    </p>
</x-confirmation-modal>
@endsection
