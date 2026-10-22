@extends('layouts.app')

@section('title', 'Dokumen Quality Control ' . $qc->qc_number . ' – Duta Tunggal ERP')

@section('content')
<div class="space-y-6 max-w-6xl mx-auto">
    {{-- Top Header & Action Buttons --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('manufaktur.qc.index') }}" class="hover:text-gray-700">QC Pabrik</a>
                <span class="text-gray-300">/</span>
                <span class="text-emerald-700 font-bold font-mono">{{ $qc->qc_number }}</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ $qc->qc_number }}</h1>
                @if ($qc->status == 1)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        Sudah Diproses (Selesai)
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse"></span>
                        Belum Diproses (Menunggu)
                    </span>
                @endif
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a
                href="{{ route('manufaktur.qc.pdf', $qc) }}"
                target="_blank"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs font-semibold rounded-xl transition"
            >
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Cetak Sertifikat Mutu (PDF)
            </a>

            @if ($qc->status == 0)
                <a
                    href="{{ route('manufaktur.qc.process-form', $qc) }}"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#15803d] hover:bg-[#166534] text-white text-xs font-bold rounded-xl shadow-sm transition active:scale-[0.98]"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Proses Inspeksi Mutu
                </a>
            @endif
        </div>
    </div>

    {{-- Flash Notifications --}}
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

    {{-- Stepper Lifecycle Visual --}}
    <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 relative">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-xs bg-emerald-100 text-emerald-700 border border-emerald-300">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <div>
                    <p class="text-xs font-bold text-gray-900">1. Penerbitan QC</p>
                    <p class="text-[11px] text-gray-400">Otomatis dari hasil produksi</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-xs {{ $qc->status == 1 ? 'bg-emerald-100 text-emerald-700 border border-emerald-300' : 'bg-blue-100 text-blue-700 border border-blue-300 animate-pulse' }}">
                    @if ($qc->status == 1)
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    @else
                        2
                    @endif
                </div>
                <div>
                    <p class="text-xs font-bold {{ $qc->status == 1 ? 'text-gray-900' : 'text-blue-700' }}">2. Pemeriksaan Mutu Fisik</p>
                    <p class="text-[11px] text-gray-400">Pemisahan unit lolos vs reject</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-xs {{ $qc->status == 1 ? 'bg-emerald-100 text-emerald-700 border border-emerald-300' : 'bg-gray-100 text-gray-400' }}">
                    @if ($qc->status == 1)
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    @else
                        3
                    @endif
                </div>
                <div>
                    <p class="text-xs font-bold {{ $qc->status == 1 ? 'text-emerald-700' : 'text-gray-400' }}">
                        3. Masuk Gudang & Jurnal FG
                    </p>
                    <p class="text-[11px] text-gray-400">
                        {{ $qc->status == 1 ? 'Stok bertambah & BDP ditutup' : 'Belum diproses' }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    @php
        $prod = $qc->fromModel;
        $spk = $prod?->manufacturingOrder;
        $plan = $spk?->productionPlan;
        $product = $qc->product ?? $plan?->product;
    @endphp

    {{-- Main Content Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left 2 Columns: Inspection Result & Accounting / Stock --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Inspection Result Card --}}
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">
                        Hasil Pemeriksaan Mutu Fisik Produk Jadi
                    </h3>
                    <span class="text-xs font-mono font-bold text-gray-500">
                        SKU: {{ $product?->sku ?? '-' }}
                    </span>
                </div>

                <div class="p-4 bg-gray-50/80 rounded-xl border border-gray-100 space-y-1">
                    <span class="text-xs text-gray-400 block font-medium">Nama Produk Jadi</span>
                    <h4 class="text-base font-bold text-gray-900">{{ $product?->name ?? 'Produk Tidak Ditemukan' }}</h4>
                    <p class="text-xs text-gray-500">Kategori: {{ $product?->category?->name ?? 'Umum' }} • Satuan: {{ $product?->uom?->name ?? 'Pcs' }}</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {{-- Passed Quantity Card --}}
                    <div class="p-4 bg-emerald-50/70 rounded-xl border border-emerald-200/70 space-y-1">
                        <span class="text-xs font-bold text-emerald-800 uppercase tracking-wider block">Unit Lolos Mutu (Passed)</span>
                        <div class="text-2xl font-bold font-mono text-emerald-900">
                            {{ number_format((float)$qc->passed_quantity, 2, ',', '.') }}
                            <span class="text-xs font-sans font-medium text-emerald-700">{{ $product?->uom?->name ?? 'Pcs' }}</span>
                        </div>
                        <p class="text-[11px] text-emerald-700/80">
                            {{ $qc->status == 1 ? '✓ Telah masuk ke stok persediaan gudang siap jual.' : 'Unit yang lolos akan menambah stok fisik gudang saat QC diproses.' }}
                        </p>
                    </div>

                    {{-- Rejected Quantity Card --}}
                    <div class="p-4 bg-rose-50/70 rounded-xl border border-rose-200/70 space-y-1">
                        <span class="text-xs font-bold text-rose-800 uppercase tracking-wider block">Unit Ditolak (Rejected)</span>
                        <div class="text-2xl font-bold font-mono text-rose-900">
                            {{ number_format((float)$qc->rejected_quantity, 2, ',', '.') }}
                            <span class="text-xs font-sans font-medium text-rose-700">{{ $product?->uom?->name ?? 'Pcs' }}</span>
                        </div>
                        <p class="text-[11px] text-rose-700/80">
                            {{ (float)$qc->rejected_quantity > 0 ? 'Unit cacat/rusak dan tidak masuk ke stok persediaan siap jual.' : 'Tidak ada unit cacat pada inspeksi ini.' }}
                        </p>
                    </div>
                </div>

                @if ($qc->reason_reject)
                    <div class="p-3.5 bg-rose-50/60 rounded-xl border border-rose-200 text-xs text-rose-800 space-y-1">
                        <span class="font-bold block">Alasan Penolakan / Catatan Kerusakan:</span>
                        <p>{{ $qc->reason_reject }}</p>
                    </div>
                @endif

                @if ($qc->notes)
                    <div class="p-3 bg-gray-50 rounded-xl text-xs text-gray-700 space-y-1">
                        <span class="font-bold text-gray-900 block">Catatan Pemeriksa (Notes):</span>
                        <p>{{ $qc->notes }}</p>
                    </div>
                @endif
            </div>

            {{-- Finished Goods Journal Entries Card --}}
            @if ($qc->journalEntries && $qc->journalEntries->isNotEmpty())
                <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-4">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">
                            Jurnal Akuntansi Penyelesaian Barang Jadi (Finished Goods)
                        </h3>
                        <span class="text-xs text-emerald-700 font-bold bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200">
                            Otomatis Terbentuk
                        </span>
                    </div>

                    <p class="text-xs text-gray-500">
                        Pengalihan biaya dari Pos Sementara Produksi / Barang Dalam Proses (BDP) ke akun Persediaan Barang Jadi di neraca.
                    </p>

                    <div class="overflow-x-auto text-xs">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50 text-gray-500 font-semibold border-b border-gray-100">
                                    <th class="py-2.5 px-3">Kode Akun</th>
                                    <th class="py-2.5 px-3">Nama Akun</th>
                                    <th class="py-2.5 px-3 text-right">Debit (Rp)</th>
                                    <th class="py-2.5 px-3 text-right">Kredit (Rp)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-gray-700 font-mono">
                                @foreach ($qc->journalEntries as $entry)
                                    <tr>
                                        <td class="py-2 px-3 font-bold text-gray-900">{{ $entry->coa?->code }}</td>
                                        <td class="py-2 px-3 font-sans text-gray-800">{{ $entry->coa?->name }}</td>
                                        <td class="py-2 px-3 text-right text-emerald-700 font-bold">
                                            {{ (float)$entry->debit > 0 ? number_format((float)$entry->debit, 2, ',', '.') : '-' }}
                                        </td>
                                        <td class="py-2 px-3 text-right text-blue-700 font-bold">
                                            {{ (float)$entry->credit > 0 ? number_format((float)$entry->credit, 2, ',', '.') : '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Stock Movement Card --}}
            @if ($qc->stockMovement && $qc->stockMovement->exists)
                <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-3">
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">
                        Mutasi Persediaan Gudang (Stock Movement)
                    </h3>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                        <div>
                            <span class="text-gray-400 block mb-0.5">Tipe Mutasi:</span>
                            <span class="font-bold text-emerald-700 uppercase font-mono">manufacture_in</span>
                        </div>
                        <div>
                            <span class="text-gray-400 block mb-0.5">Kuantitas Masuk:</span>
                            <span class="font-bold text-gray-900 font-mono text-sm">+{{ number_format((float)$qc->stockMovement->quantity, 2, ',', '.') }} {{ $product?->uom?->name ?? 'Pcs' }}</span>
                        </div>
                        <div>
                            <span class="text-gray-400 block mb-0.5">Nilai Persediaan:</span>
                            <span class="font-bold text-gray-900 font-mono text-sm">Rp {{ number_format((float)$qc->stockMovement->value, 2, ',', '.') }}</span>
                        </div>
                        <div>
                            <span class="text-gray-400 block mb-0.5">Tanggal Mutasi:</span>
                            <span class="font-bold text-gray-900">{{ $qc->stockMovement->date ? \Carbon\Carbon::parse($qc->stockMovement->date)->format('d/m/Y') : '-' }}</span>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Right Column: Document Details & Tracing Links --}}
        <div class="space-y-6">
            {{-- Document Summary Box --}}
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-4">
                <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">
                    Metadata Dokumen QC
                </h3>

                <div class="space-y-3 text-xs">
                    <div>
                        <span class="text-gray-400 block mb-0.5">Petugas Pemeriksa QC:</span>
                        <span class="font-bold text-gray-900 text-sm">{{ $qc->inspectedBy?->name ?? '-' }}</span>
                        <span class="text-[11px] text-gray-400 block">{{ $qc->inspectedBy?->email ?? '' }}</span>
                    </div>

                    <div>
                        <span class="text-gray-400 block mb-0.5">Gudang Penyimpanan:</span>
                        <span class="font-bold text-gray-900">{{ $qc->warehouse?->name ?? 'Gudang Utama' }}</span>
                    </div>

                    @if ($qc->rak)
                        <div>
                            <span class="text-gray-400 block mb-0.5">Rak Penyimpanan:</span>
                            <span class="font-bold text-gray-900">{{ $qc->rak->name }} ({{ $qc->rak->code }})</span>
                        </div>
                    @endif

                    <div>
                        <span class="text-gray-400 block mb-0.5">Tanggal Pemeriksaan:</span>
                        <span class="font-bold text-gray-900">{{ $qc->date_send_stock ? \Carbon\Carbon::parse($qc->date_send_stock)->format('d F Y H:i') : ($qc->created_at ? $qc->created_at->format('d F Y H:i') : '-') }}</span>
                    </div>

                    <div>
                        <span class="text-gray-400 block mb-0.5">Cabang / Lokasi Pabrik:</span>
                        <span class="font-bold text-gray-900">{{ $qc->cabang?->nama ?? 'Pusat' }}</span>
                    </div>
                </div>
            </div>

            {{-- Reference Traceability Box --}}
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-3">
                <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">
                    Keterkaitan Dokumen
                </h3>

                @if ($prod)
                    <div class="p-3 bg-gray-50 rounded-xl space-y-1">
                        <span class="text-[10.5px] text-gray-400 block uppercase font-bold">Bukti Hasil Produksi:</span>
                        <a href="{{ route('manufaktur.production.show', $prod) }}" class="font-mono font-bold text-[#15803d] hover:underline text-xs block">
                            {{ $prod->production_number }}
                        </a>
                        <span class="text-[11px] text-gray-500">Status: {{ strtoupper($prod->status) }}</span>
                    </div>
                @endif

                @if ($spk)
                    <div class="p-3 bg-gray-50 rounded-xl space-y-1">
                        <span class="text-[10.5px] text-gray-400 block uppercase font-bold">Surat Perintah Kerja (SPK):</span>
                        <a href="{{ route('manufaktur.spk.show', $spk) }}" class="font-mono font-bold text-[#15803d] hover:underline text-xs block">
                            {{ $spk->mo_number }}
                        </a>
                        <span class="text-[11px] text-gray-500">Status: {{ strtoupper($spk->status) }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
