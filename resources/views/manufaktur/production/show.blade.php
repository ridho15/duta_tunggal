@extends('layouts.app')

@section('title', 'Bukti Hasil Produksi ' . $production->production_number . ' – Duta Tunggal ERP')

@section('content')
<div class="space-y-6 max-w-6xl mx-auto">
    {{-- Top Header & Action Buttons --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('manufaktur.production.index') }}" class="hover:text-gray-700">Hasil Produksi</a>
                <span class="text-gray-300">/</span>
                <span class="text-emerald-700 font-bold font-mono">{{ $production->production_number }}</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ $production->production_number }}</h1>
                @if ($production->status === 'finished')
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        Selesai (Finished)
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                        Draft Produksi
                    </span>
                @endif
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a
                href="{{ route('manufaktur.production.pdf', $production) }}"
                target="_blank"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs font-semibold rounded-xl transition"
            >
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Cetak LHP
            </a>

            @if ($production->status === 'draft')
                <a
                    href="{{ route('manufaktur.production.edit', $production) }}"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 border border-amber-200 hover:bg-amber-50 text-amber-800 text-xs font-semibold rounded-xl transition"
                >
                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Ubah
                </a>

                <button
                    type="button"
                    @click="$dispatch('open-modal-finish-production', { action: '{{ route('manufaktur.production.finish', $production) }}' })"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#15803d] hover:bg-[#166534] text-white text-xs font-bold rounded-xl shadow-sm transition active:scale-[0.98]"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Selesaikan Produksi
                </button>
            @endif

            @php
                $qc = $production->qualityControl;
            @endphp
            @if ($qc && $qc->exists)
                @if ($qc->status == 0)
                    <a
                        href="{{ route('manufaktur.qc.process-form', $qc) }}"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-sm transition active:scale-[0.98]"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        Proses Inspeksi QC
                    </a>
                @else
                    <a
                        href="{{ route('manufaktur.qc.show', $qc) }}"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold rounded-xl shadow-sm transition active:scale-[0.98]"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Lihat Sertifikat QC (Lolos: {{ number_format((float)$qc->passed_quantity, 0) }})
                    </a>
                @endif
            @endif
        </div>
    </div>


    {{-- Stepper Lifecycle Visual --}}
    <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 relative">
            {{-- Step 1 --}}
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-xs bg-emerald-100 text-emerald-700 border border-emerald-300">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <div>
                    <p class="text-xs font-bold text-gray-900">1. Pencatatan Output</p>
                    <p class="text-[11px] text-gray-400">Pabrik selesai memproduksi</p>
                </div>
            </div>

            {{-- Step 2 --}}
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-xs {{ $production->status === 'finished' ? 'bg-emerald-100 text-emerald-700 border border-emerald-300' : 'bg-gray-100 text-gray-400' }}">
                    @if ($production->status === 'finished')
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    @else
                        2
                    @endif
                </div>
                <div>
                    <p class="text-xs font-bold {{ $production->status === 'finished' ? 'text-gray-900' : 'text-gray-400' }}">2. Status Finished</p>
                    <p class="text-[11px] text-gray-400">Siap inspeksi mutu (QC)</p>
                </div>
            </div>

            {{-- Step 3 --}}
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-xs {{ $qc && $qc->status == 1 ? 'bg-emerald-100 text-emerald-700 border border-emerald-300' : ($qc ? 'bg-blue-100 text-blue-700 border border-blue-300 animate-pulse' : 'bg-gray-100 text-gray-400') }}">
                    @if ($qc && $qc->status == 1)
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    @elseif($qc)
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10" stroke-width="2"></circle>
                        </svg>
                    @else
                        3
                    @endif
                </div>
                <div>
                    <p class="text-xs font-bold {{ $qc && $qc->status == 1 ? 'text-emerald-700' : ($qc ? 'text-blue-700' : 'text-gray-400') }}">
                        3. Pemeriksaan Mutu (QC)
                    </p>
                    <p class="text-[11px] text-gray-400">
                        {{ $qc && $qc->status == 1 ? 'Lolos & Masuk Gudang' : ($qc ? 'Menunggu Pemeriksaan' : 'Belum Ada Lembar QC') }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    @php
        $spk = $production->manufacturingOrder;
        $plan = $spk?->productionPlan;
        $product = $plan?->product;
    @endphp

    {{-- Main 2 Column Details --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left 2 Columns: Product Output & SPK Information --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Finished Good Info Card --}}
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">
                        Hasil Produk Jadi
                    </h3>
                    <span class="text-xs font-mono font-bold text-gray-500">
                        SKU: {{ $product?->sku ?? '-' }}
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="p-4 bg-gray-50/80 rounded-xl border border-gray-100 space-y-1">
                        <span class="text-xs text-gray-400 block font-medium">Nama Produk Jadi</span>
                        <h4 class="text-base font-bold text-gray-900">{{ $product?->name ?? 'Produk Tidak Ditemukan' }}</h4>
                        <p class="text-xs text-gray-500">Kategori: {{ $product?->category?->name ?? 'General' }}</p>
                    </div>

                    <div class="p-4 bg-emerald-50/60 rounded-xl border border-emerald-100 space-y-1">
                        <span class="text-xs text-emerald-700 block font-medium uppercase tracking-wider">Kuantitas Output Produksi</span>
                        <div class="text-2xl font-bold font-mono text-emerald-900">
                            {{ number_format((float)($production->quantity_produced ?? $plan?->quantity ?? 0), 2, ',', '.') }}
                            <span class="text-xs font-sans font-medium text-emerald-700">{{ $product?->uom?->name ?? 'Pcs' }}</span>
                        </div>
                        <p class="text-[11px] text-emerald-600/80">Target SPK: {{ number_format((float)($plan?->quantity ?? 0), 2, ',', '.') }} {{ $product?->uom?->name ?? 'Pcs' }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs pt-2">
                    <div>
                        <span class="text-gray-400 block mb-0.5">Tanggal Produksi:</span>
                        <span class="font-bold text-gray-900">{{ $production->production_date ? $production->production_date->format('d F Y') : '-' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-400 block mb-0.5">Cabang / Lokasi:</span>
                        <span class="font-bold text-gray-900">{{ $spk?->cabang?->nama ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-400 block mb-0.5">Gudang Alokasi:</span>
                        <span class="font-bold text-gray-900">{{ $plan?->warehouse?->name ?? 'Gudang Utama' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-400 block mb-0.5">Dibuat Pada:</span>
                        <span class="font-bold text-gray-900">{{ $production->created_at ? $production->created_at->format('d/m/Y H:i') : '-' }}</span>
                    </div>
                </div>
            </div>

            {{-- SPK & BOM Traceability Card --}}
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">
                        Rujukan Surat Perintah Kerja (SPK) & Resep (BOM)
                    </h3>
                    @if ($spk)
                        <a href="{{ route('manufaktur.spk.show', $spk) }}" class="text-xs text-[#15803d] hover:underline font-bold flex items-center gap-1">
                            Lihat Dokumen SPK
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    @endif
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div>
                        <span class="text-gray-400 block mb-0.5">Nomor SPK:</span>
                        <span class="font-mono font-bold text-gray-900 text-sm">{{ $spk?->mo_number ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-400 block mb-0.5">Formula BOM:</span>
                        <span class="font-bold text-gray-900 text-sm">{{ $plan?->billOfMaterial?->nama_bom ?? '-' }} ({{ $plan?->billOfMaterial?->code ?? '-' }})</span>
                    </div>
                    <div>
                        <span class="text-gray-400 block mb-0.5">Rentang Waktu SPK:</span>
                        <span class="text-gray-700">
                            {{ $spk?->start_date ? \Carbon\Carbon::parse($spk->start_date)->format('d/m/Y') : '-' }} s/d {{ $spk?->end_date ? \Carbon\Carbon::parse($spk->end_date)->format('d/m/Y') : '-' }}
                        </span>
                    </div>
                    <div>
                        <span class="text-gray-400 block mb-0.5">Status SPK:</span>
                        <span class="font-bold uppercase text-emerald-700">{{ $spk?->status ?? '-' }}</span>
                    </div>
                </div>

                {{-- Material BOM breakdown summary --}}
                @if ($plan?->billOfMaterial && $plan->billOfMaterial->items->isNotEmpty())
                    <div class="pt-3 border-t border-gray-100">
                        <p class="text-xs font-semibold text-gray-600 mb-2">Komponen Bahan Baku Menurut Resep:</p>
                        <div class="divide-y divide-gray-100 border border-gray-100 rounded-xl overflow-hidden text-xs">
                            @foreach ($plan->billOfMaterial->items as $item)
                                <div class="px-3 py-2 flex items-center justify-between hover:bg-gray-50/60">
                                    <div class="flex items-center gap-2">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                                        <span class="font-medium text-gray-900">{{ $item->product?->name }}</span>
                                        <span class="text-gray-400 font-mono text-[11px]">({{ $item->product?->sku }})</span>
                                    </div>
                                    <div class="font-mono font-semibold text-gray-700">
                                        {{ number_format((float)$item->quantity, 2) }} {{ $item->uom?->name ?? 'Pcs' }} / unit
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Journal Entries Card (if attached to Production) --}}
            @if ($production->journalEntries && $production->journalEntries->isNotEmpty())
                <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-4">
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">
                        Jurnal Akuntansi Produksi
                    </h3>
                    <div class="overflow-x-auto text-xs">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50 text-gray-500 font-semibold border-b border-gray-100">
                                    <th class="py-2 px-3">Kode Akun</th>
                                    <th class="py-2 px-3">Nama Akun</th>
                                    <th class="py-2 px-3 text-right">Debit (Rp)</th>
                                    <th class="py-2 px-3 text-right">Kredit (Rp)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-gray-700 font-mono">
                                @foreach ($production->journalEntries as $entry)
                                    <tr>
                                        <td class="py-2 px-3 font-bold text-gray-900">{{ $entry->coa?->code }}</td>
                                        <td class="py-2 px-3 font-sans text-gray-800">{{ $entry->coa?->name }}</td>
                                        <td class="py-2 px-3 text-right text-emerald-700">
                                            {{ (float)$entry->debit > 0 ? number_format((float)$entry->debit, 2, ',', '.') : '-' }}
                                        </td>
                                        <td class="py-2 px-3 text-right text-blue-700">
                                            {{ (float)$entry->credit > 0 ? number_format((float)$entry->credit, 2, ',', '.') : '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        {{-- Right Column: Quality Control Status & Actions --}}
        <div class="space-y-6">
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider">
                        Hasil Pemeriksaan Mutu (QC)
                    </h3>
                    @if ($qc)
                        <span class="text-[10px] font-mono px-2 py-0.5 rounded font-bold bg-gray-100 text-gray-600">
                            {{ $qc->qc_number }}
                        </span>
                    @endif
                </div>

                @if ($qc && $qc->exists)
                    <div class="space-y-3 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-400">Status QC:</span>
                            @if ($qc->status == 1)
                                <span class="px-2 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-md font-bold text-[11px]">
                                    Sudah Diproses (Selesai)
                                </span>
                            @else
                                <span class="px-2 py-0.5 bg-blue-50 text-blue-700 border border-blue-200 rounded-md font-bold text-[11px] animate-pulse">
                                    Belum Diproses (Menunggu)
                                </span>
                            @endif
                        </div>

                        <div class="p-3 bg-gray-50 rounded-xl space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600 font-medium">Lolos Kualitas:</span>
                                <span class="font-mono font-bold text-emerald-700 text-sm">
                                    {{ number_format((float)$qc->passed_quantity, 2, ',', '.') }} {{ $product?->uom?->name ?? 'Pcs' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600 font-medium">Ditolak / Rusak:</span>
                                <span class="font-mono font-bold text-rose-700 text-sm">
                                    {{ number_format((float)$qc->rejected_quantity, 2, ',', '.') }} {{ $product?->uom?->name ?? 'Pcs' }}
                                </span>
                            </div>
                            @if ($qc->reason_reject)
                                <div class="pt-2 border-t border-gray-200/70 text-[11px] text-rose-600">
                                    <strong>Alasan Reject:</strong> {{ $qc->reason_reject }}
                                </div>
                            @endif
                        </div>

                        <div class="space-y-1.5 text-gray-600 text-[11px]">
                            <div class="flex justify-between">
                                <span class="text-gray-400">Pemeriksa QC:</span>
                                <span class="font-semibold text-gray-900">{{ $qc->inspectedBy?->name ?? '-' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Gudang Tujuan:</span>
                                <span class="font-semibold text-gray-900">{{ $qc->warehouse?->name ?? '-' }}</span>
                            </div>
                            @if ($qc->rak)
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Rak Penyimpanan:</span>
                                    <span class="font-semibold text-gray-900">{{ $qc->rak->name }} ({{ $qc->rak->code }})</span>
                                </div>
                            @endif
                        </div>

                        <div class="pt-3 border-t border-gray-100 flex flex-col gap-2">
                            @if ($qc->status == 0)
                                <a
                                    href="{{ route('manufaktur.qc.process-form', $qc) }}"
                                    class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-center text-xs shadow-xs transition"
                                >
                                    Proses Hasil QC Sekarang
                                </a>
                            @else
                                <a
                                    href="{{ route('manufaktur.qc.show', $qc) }}"
                                    class="w-full py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold rounded-xl text-center text-xs transition"
                                >
                                    Lihat Detail & Sertifikat QC
                                </a>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="py-6 text-center text-gray-400 text-xs space-y-2">
                        <div class="w-10 h-10 mx-auto bg-gray-50 rounded-full flex items-center justify-center text-gray-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <p class="font-medium text-gray-600">Dokumen QC Belum Diterbitkan</p>
                        <p class="text-[11px] text-gray-400">Quality Control akan diterbitkan secara otomatis setelah status produksi ini diubah menjadi <strong>Finished (Selesai)</strong>.</p>
                    </div>
                @endif
            </div>

            {{-- Quick Help Box --}}
            <div class="bg-emerald-50/40 p-5 rounded-2xl border border-emerald-100 text-xs text-emerald-900/80 space-y-2">
                <h4 class="font-bold text-emerald-950 flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Catatan Bisnis Manufaktur
                </h4>
                <p class="text-[11px] leading-relaxed">
                    Setelah hasil produksi selesai dicatat, produk belum langsung menambah stok persediaan yang siap dijual. Produk harus melalui verifikasi <strong>Quality Control (QC)</strong> untuk memisahkan barang lolos standar dengan barang cacat (scrap/reject).
                </p>
            </div>
        </div>
    {{-- Modal Konfirmasi Selesai Produksi --}}
    <x-confirmation-modal
        name="finish-production"
        title="Selesaikan Produksi & Terbitkan QC"
        type="success"
        confirm-text="Ya, Selesaikan Produksi"
        cancel-text="Batal"
        method="POST"
    >
        <p class="text-sm text-gray-600">
            Apakah Anda yakin ingin menyelesaikan proses produksi <span class="font-bold text-gray-900 font-mono">{{ $production->production_number }}</span>?
        </p>
        <p class="text-xs text-emerald-600 bg-emerald-50 border border-emerald-100 rounded-lg p-2.5 mt-2">
            Status produksi akan menjadi <strong>Finished</strong> dan dokumen Quality Control (QC) akan diterbitkan secara otomatis.
        </p>
    </x-confirmation-modal>
</div>
@endsection
