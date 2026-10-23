@extends('layouts.app')

@section('title', 'Detail Mutasi Aset Tetap – Duta Tunggal ERP')

@section('content')
<div class="space-y-6 max-w-5xl mx-auto">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <span>Keuangan & Akuntansi</span>
                <span class="text-gray-300">/</span>
                <a href="{{ route('keuangan.asset.index') }}" class="hover:underline">Aset Tetap</a>
                <span class="text-gray-300">/</span>
                <a href="{{ route('keuangan.asset.transfer.index') }}" class="hover:underline">Mutasi Antar Cabang</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Detail #{{ $transfer->id }}</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Mutasi #{{ $transfer->id }}</h1>
                @if ($transfer->status === 'pending')
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-200">
                        Menunggu Persetujuan
                    </span>
                @elseif ($transfer->status === 'approved')
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-800 border border-blue-200">
                        Disetujui (Dalam Transit)
                    </span>
                @elseif ($transfer->status === 'completed')
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                        Selesai (Completed)
                    </span>
                @else
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800 border border-rose-200">
                        Dibatalkan (Cancelled)
                    </span>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a
                href="{{ route('keuangan.asset.transfer.pdf', $transfer) }}"
                target="_blank"
                class="inline-flex items-center gap-2 px-4 py-2 bg-purple-50 hover:bg-purple-100 text-purple-700 text-sm font-semibold rounded-xl border border-purple-200 transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Cetak Surat Mutasi (PDF)
            </a>
            <a
                href="{{ route('keuangan.asset.transfer.index') }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold rounded-xl transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Kembali
            </a>
        </div>
    </div>

    {{-- Alert Flash --}}
    @if (session('success'))
        <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-2xl flex items-center gap-3 text-emerald-800 text-sm">
            <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="font-medium">{{ session('success') }}</span>
        </div>
    @endif

    @if (session('error'))
        <div class="p-4 bg-rose-50 border border-rose-200 rounded-2xl flex items-center gap-3 text-rose-800 text-sm">
            <svg class="w-5 h-5 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="font-medium">{{ session('error') }}</span>
        </div>
    @endif

    {{-- Visual Timeline Alur Mutasi --}}
    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4">Progres Mutasi Fisik Aset</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            {{-- Step 1: Pengajuan --}}
            <div class="p-4 rounded-xl border {{ in_array($transfer->status, ['pending', 'approved', 'completed']) ? 'bg-emerald-50/50 border-emerald-200' : 'bg-gray-50 border-gray-200' }}">
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold bg-[#15803d] text-white">1</span>
                    <span class="font-bold text-xs text-gray-900">Pengajuan Mutasi</span>
                </div>
                <p class="text-[11px] text-gray-500 mt-2">
                    Oleh: {{ $transfer->requestedBy->name ?? 'System' }}<br>
                    Tgl: {{ $transfer->transfer_date ? $transfer->transfer_date->format('d M Y') : '-' }}
                </p>
            </div>

            {{-- Step 2: Persetujuan --}}
            <div class="p-4 rounded-xl border {{ in_array($transfer->status, ['approved', 'completed']) ? 'bg-emerald-50/50 border-emerald-200' : ($transfer->status === 'pending' ? 'bg-amber-50/50 border-amber-200' : 'bg-gray-50 border-gray-200') }}">
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold {{ in_array($transfer->status, ['approved', 'completed']) ? 'bg-[#15803d] text-white' : 'bg-gray-300 text-gray-700' }}">2</span>
                    <span class="font-bold text-xs text-gray-900">Persetujuan (Approval)</span>
                </div>
                <p class="text-[11px] text-gray-500 mt-2">
                    @if ($transfer->approved_at)
                        Oleh: {{ $transfer->approvedBy->name ?? 'Admin' }}<br>
                        Tgl: {{ $transfer->approved_at->format('d M Y H:i') }}
                    @else
                        <span class="text-amber-700 font-medium">Menunggu persetujuan pimpinan</span>
                    @endif
                </p>
            </div>

            {{-- Step 3: Penyelesaian --}}
            <div class="p-4 rounded-xl border {{ $transfer->status === 'completed' ? 'bg-emerald-50/50 border-emerald-200' : 'bg-gray-50 border-gray-200' }}">
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold {{ $transfer->status === 'completed' ? 'bg-[#15803d] text-white' : 'bg-gray-300 text-gray-700' }}">3</span>
                    <span class="font-bold text-xs text-gray-900">Serah Terima Selesai</span>
                </div>
                <p class="text-[11px] text-gray-500 mt-2">
                    @if ($transfer->completed_at)
                        Diterima oleh: {{ $transfer->completedBy->name ?? 'Penerima' }}<br>
                        Tgl: {{ $transfer->completed_at->format('d M Y H:i') }}
                    @else
                        <span class="text-gray-400">Menunggu serah terima di cabang tujuan</span>
                    @endif
                </p>
            </div>
        </div>

        {{-- Action Buttons Transition --}}
        @if (in_array($transfer->status, ['pending', 'approved']))
            <div class="mt-6 pt-4 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3">
                <div class="text-xs text-gray-500">
                    Aksi yang tersedia untuk status <strong>{{ ucfirst($transfer->status) }}</strong>:
                </div>
                <div class="flex items-center gap-2">
                    @if ($transfer->status === 'pending')
                        <form action="{{ route('keuangan.asset.transfer.approve', $transfer) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menyetujui mutasi aset ini?');">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#15803d] hover:bg-emerald-800 text-white font-semibold text-xs rounded-xl shadow-sm transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Setujui Mutasi (Approve)
                            </button>
                        </form>
                    @elseif ($transfer->status === 'approved')
                        <form action="{{ route('keuangan.asset.transfer.complete', $transfer) }}" method="POST" onsubmit="return confirm('Apakah fisik aset telah diterima di cabang tujuan? Lokasi aset akan resmi dipindahkan.');">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs rounded-xl shadow-sm transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Konfirmasi Selesai Serah Terima (Complete)
                            </button>
                        </form>
                    @endif

                    {{-- Tombol Batal --}}
                    <button
                        type="button"
                        onclick="document.getElementById('cancelModal').classList.remove('hidden')"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 font-semibold text-xs rounded-xl transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Batalkan Mutasi
                    </button>
                </div>
            </div>
        @endif
    </div>

    {{-- Detail Rincian Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Card Kiri: Identitas Aset & Perjalanan Mutasi --}}
        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-4">
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">
                Informasi Aset & Cabang
            </h3>

            <div>
                <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Aset Tetap Terkait</span>
                <a href="{{ route('keuangan.asset.show', $transfer->asset_id) }}" class="text-base font-bold text-[#15803d] hover:underline block mt-0.5">
                    {{ $transfer->asset->name ?? '-' }}
                </a>
                <span class="text-xs font-mono text-gray-500 block">{{ $transfer->asset->code ?? '-' }}</span>
            </div>

            <div class="grid grid-cols-2 gap-4 pt-2 border-t border-gray-100">
                <div class="p-3 bg-gray-50 rounded-xl border border-gray-100">
                    <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block">Cabang Asal</span>
                    <span class="text-xs font-bold text-gray-800 block mt-1">{{ $transfer->fromCabang->nama ?? '-' }}</span>
                    <span class="text-[11px] font-mono text-gray-400">{{ $transfer->fromCabang->kode ?? '-' }}</span>
                </div>
                <div class="p-3 bg-emerald-50/60 rounded-xl border border-emerald-100">
                    <span class="text-[10px] font-bold text-emerald-800 uppercase tracking-wider block">Cabang Tujuan</span>
                    <span class="text-xs font-bold text-emerald-900 block mt-1">{{ $transfer->toCabang->nama ?? '-' }}</span>
                    <span class="text-[11px] font-mono text-emerald-600">{{ $transfer->toCabang->kode ?? '-' }}</span>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 pt-2 border-t border-gray-100">
                <div>
                    <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Nilai Perolehan</span>
                    <span class="text-xs font-mono font-bold text-gray-800 block mt-0.5">
                        {{ \App\Helpers\MoneyHelper::rupiah($transfer->asset->purchase_cost ?? 0) }}
                    </span>
                </div>
                <div>
                    <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Nilai Buku Saat Ini</span>
                    <span class="text-xs font-mono font-bold text-emerald-700 block mt-0.5">
                        {{ \App\Helpers\MoneyHelper::rupiah($transfer->asset->book_value ?? 0) }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Card Kanan: Alasan & Berkas Lampiran --}}
        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-4">
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">
                Alasan & Dokumen Pendukung
            </h3>

            <div>
                <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block mb-1">Alasan Mutasi</span>
                <p class="text-xs text-gray-700 bg-gray-50 p-3 rounded-xl border border-gray-100 whitespace-pre-line leading-relaxed">
                    {{ $transfer->reason ?: 'Tidak ada catatan alasan.' }}
                </p>
            </div>

            <div>
                <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block mb-1">Dokumen Lampiran</span>
                @if ($transfer->transfer_document)
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl border border-gray-100">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-purple-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span class="text-xs font-medium text-gray-700 truncate max-w-xs">Berkas Lampiran Mutasi</span>
                        </div>
                        <a
                            href="{{ asset('storage/' . $transfer->transfer_document) }}"
                            target="_blank"
                            class="px-3 py-1 bg-white hover:bg-gray-100 text-purple-700 text-xs font-semibold rounded-lg border border-gray-200 transition"
                        >
                            Buka Berkas
                        </a>
                    </div>
                @else
                    <span class="text-xs text-gray-400 italic">Tidak ada dokumen yang dilampirkan.</span>
                @endif
            </div>

            <div class="pt-2 border-t border-gray-100 space-y-1.5 text-xs text-gray-500">
                <div class="flex justify-between">
                    <span>Didaftarkan Pada:</span>
                    <span class="font-medium text-gray-800">{{ $transfer->created_at->format('d/m/Y H:i') }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Terakhir Diperbarui:</span>
                    <span class="font-medium text-gray-800">{{ $transfer->updated_at->format('d/m/Y H:i') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal Pembatalan Mutasi --}}
<div id="cancelModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm hidden p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
            <h3 class="text-sm font-bold text-gray-900">Batalkan Pengajuan Mutasi</h3>
            <button type="button" onclick="document.getElementById('cancelModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <form action="{{ route('keuangan.asset.transfer.cancel', $transfer) }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                    Alasan Pembatalan <span class="text-rose-500">*</span>
                </label>
                <textarea
                    name="cancel_reason"
                    rows="3"
                    required
                    placeholder="Tuliskan alasan mengapa mutasi ini dibatalkan..."
                    class="w-full text-xs rounded-xl border-gray-200 focus:border-rose-500 focus:ring focus:ring-rose-200"
                ></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <button
                    type="button"
                    onclick="document.getElementById('cancelModal').classList.add('hidden')"
                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-xl transition"
                >
                    Tutup
                </button>
                <button
                    type="submit"
                    class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-xl shadow-sm transition"
                >
                    Ya, Batalkan Mutasi
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
