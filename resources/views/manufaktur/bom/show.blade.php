@extends('layouts.app')

@section('title', 'Detail Resep BOM: ' . $billOfMaterial->code . ' – Duta Tunggal ERP')

@section('content')
<div class="space-y-6 max-w-6xl mx-auto pb-12">
    {{-- Header & Action Buttons --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('manufaktur.bom.index') }}" class="hover:text-gray-700">Resep BOM</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">{{ $billOfMaterial->code }}</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ $billOfMaterial->nama_bom }}</h1>
                @if ($billOfMaterial->is_active)
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        Aktif
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-500 border border-gray-200">
                        Nonaktif
                    </span>
                @endif
            </div>
            <p class="text-sm text-gray-500 mt-0.5 font-mono">Kode: {{ $billOfMaterial->code }} • Dibuat: {{ $billOfMaterial->created_at->format('d/m/Y H:i') }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a
                href="{{ route('manufaktur.bom.index') }}"
                class="px-3.5 py-2 border border-gray-300 text-gray-700 hover:bg-gray-50 text-xs font-semibold rounded-xl transition"
            >
                Kembali
            </a>

            <a
                href="{{ route('manufaktur.bom.pdf', $billOfMaterial) }}"
                target="_blank"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 border border-gray-300 text-gray-700 hover:bg-gray-50 text-xs font-semibold rounded-xl transition"
            >
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Cetak PDF
            </a>

            <a
                href="{{ route('manufaktur.bom.edit', $billOfMaterial) }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Ubah Resep
            </a>

            <a
                href="{{ route('manufaktur.spk.create', ['bom_id' => $billOfMaterial->id]) }}"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#15803d] hover:bg-[#166534] text-white text-xs font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                + Terbitkan SPK
            </a>

            @if (!$isInUse)
                <form method="POST" action="{{ route('manufaktur.bom.destroy', $billOfMaterial) }}" onsubmit="return confirm('Apakah Anda yakin ingin menghapus Resep BOM ini?');" class="inline">
                    @csrf
                    @method('DELETE')
                    <button
                        type="submit"
                        class="p-2 text-rose-600 hover:bg-rose-50 rounded-xl transition"
                        title="Hapus Resep"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </form>
            @endif
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

    {{-- Grid Konten Utama --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Kolom Kiri: Rincian Produk Jadi & Daftar Komponen Bahan Baku --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Kartu Info Produk Jadi --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm space-y-4">
                <h2 class="text-xs font-extrabold uppercase tracking-wider text-gray-500 border-b border-gray-100 pb-2">
                    Spesifikasi Produk Jadi (Output)
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div>
                        <span class="text-gray-400 block text-[11px]">Nama Produk Jadi:</span>
                        <span class="font-bold text-gray-900 text-sm block mt-0.5">
                            {{ $billOfMaterial->product?->name ?? 'Produk Tidak Ditemukan' }}
                        </span>
                        <span class="text-gray-500 font-mono text-[11px]">SKU: {{ $billOfMaterial->product?->sku ?? '-' }}</span>
                    </div>

                    <div>
                        <span class="text-gray-400 block text-[11px]">Hasil Target Produksi:</span>
                        <span class="font-extrabold text-gray-900 text-sm block mt-0.5">
                            {{ rtrim(rtrim((string) $billOfMaterial->quantity, '0'), '.') }} {{ $billOfMaterial->uom?->name ?? 'Pcs' }}
                        </span>
                        <span class="text-gray-400 text-[11px]">Formula dasar per batch</span>
                    </div>

                    <div>
                        <span class="text-gray-400 block text-[11px]">Cabang / Pabrik:</span>
                        <span class="font-semibold text-gray-800 block mt-0.5">
                            {{ $billOfMaterial->cabang?->nama ?? 'Semua Cabang' }}
                        </span>
                    </div>

                    <div>
                        <span class="text-gray-400 block text-[11px]">Status Ketersediaan:</span>
                        <span class="inline-flex items-center gap-1.5 font-bold mt-0.5 {{ $billOfMaterial->is_active ? 'text-emerald-700' : 'text-gray-500' }}">
                            {{ $billOfMaterial->is_active ? 'Siap digunakan pada SPK' : 'Diarsipkan (Nonaktif)' }}
                        </span>
                    </div>

                    @if ($billOfMaterial->note)
                        <div class="sm:col-span-2 p-3 bg-gray-50 rounded-xl border border-gray-100">
                            <span class="text-gray-400 block text-[10.5px] uppercase font-bold tracking-wider mb-0.5">Instruksi Khusus / Catatan:</span>
                            <p class="text-gray-700 text-xs">{{ $billOfMaterial->note }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Kartu Tabel Bahan Baku --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <div>
                        <h2 class="text-xs font-extrabold uppercase tracking-wider text-gray-500">
                            Komponen Bahan Baku
                        </h2>
                        <p class="text-xs text-gray-400 mt-0.5">Total {{ $billOfMaterial->items->count() }} jenis material dibutuhkan</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 border-b border-gray-200 text-[11px] font-bold text-gray-500 uppercase tracking-wider">
                            <tr>
                                <th class="py-2.5 px-3">Bahan Baku</th>
                                <th class="py-2.5 px-3 text-center">Kebutuhan Qty</th>
                                <th class="py-2.5 px-3 text-right">Biaya Satuan</th>
                                <th class="py-2.5 px-3 text-right">Subtotal Biaya</th>
                                <th class="py-2.5 px-3">Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 font-medium">
                            @foreach ($billOfMaterial->items as $item)
                                @php
                                    $rowSubtotal = (float) ($item->subtotal ?? ($item->quantity * $item->unit_price));
                                @endphp
                                <tr class="hover:bg-gray-50/70 transition">
                                    <td class="py-2.5 px-3">
                                        <div class="font-bold text-gray-900">{{ $item->product?->name ?? 'Material Dihapus' }}</div>
                                        <div class="text-[11px] text-gray-400 font-mono">SKU: {{ $item->product?->sku ?? '-' }}</div>
                                    </td>
                                    <td class="py-2.5 px-3 text-center font-bold text-gray-900">
                                        {{ rtrim(rtrim((string) $item->quantity, '0'), '.') }}
                                        <span class="text-gray-500 font-normal text-[11px]">{{ $item->uom?->name ?? 'pcs' }}</span>
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono text-gray-700">
                                        Rp {{ number_format((float) $item->unit_price, 0, ',', '.') }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono font-bold text-gray-900">
                                        Rp {{ number_format($rowSubtotal, 0, ',', '.') }}
                                    </td>
                                    <td class="py-2.5 px-3 text-gray-500 text-[11px]">
                                        {{ $item->note ?: '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-gray-200 bg-gray-50/50 font-bold">
                            <tr>
                                <td colspan="3" class="py-2.5 px-3 text-right text-gray-700 uppercase tracking-wider text-[11px]">
                                    Total Biaya Material:
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono text-[#15803d]">
                                    Rp {{ number_format($totalMaterialCost, 0, ',', '.') }}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        {{-- Kolom Kanan: Ringkasan Biaya Produksi (HPP) & Riwayat SPK --}}
        <div class="space-y-6">
            {{-- Kartu Ringkasan Struktur Biaya --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm space-y-4">
                <h3 class="text-xs font-extrabold uppercase tracking-wider text-gray-500 border-b border-gray-100 pb-2">
                    Struktur Biaya Produksi
                </h3>

                <div class="space-y-2.5 text-xs divide-y divide-gray-100">
                    <div class="flex justify-between py-1">
                        <span class="text-gray-600">Total Biaya Bahan Baku:</span>
                        <span class="font-mono font-bold text-gray-900">Rp {{ number_format($totalMaterialCost, 0, ',', '.') }}</span>
                    </div>

                    <div class="flex justify-between py-1">
                        <span class="text-gray-600">Biaya Tenaga Kerja (TKL):</span>
                        <span class="font-mono font-bold text-gray-900">Rp {{ number_format((float) $billOfMaterial->labor_cost, 0, ',', '.') }}</span>
                    </div>

                    <div class="flex justify-between py-1">
                        <span class="text-gray-600">Biaya Overhead Pabrik (FOH):</span>
                        <span class="font-mono font-bold text-gray-900">Rp {{ number_format((float) $billOfMaterial->overhead_cost, 0, ',', '.') }}</span>
                    </div>

                    <div class="flex justify-between pt-3 pb-1 border-t-2 border-gray-200 text-sm font-extrabold text-[#15803d]">
                        <span>Grand Total Biaya BOM:</span>
                        <span class="font-mono text-base">Rp {{ number_format((float) $billOfMaterial->total_cost, 0, ',', '.') }}</span>
                    </div>

                    <div class="flex justify-between pt-2 text-xs font-bold text-emerald-800 bg-emerald-50/70 p-3 rounded-xl border border-emerald-100">
                        <span>Estimasi HPP per Unit:</span>
                        <span class="font-mono text-sm font-extrabold">Rp {{ number_format($costPerUnit, 0, ',', '.') }} / {{ $billOfMaterial->uom?->name ?? 'unit' }}</span>
                    </div>
                </div>

                <div class="pt-2">
                    <a
                        href="{{ route('manufaktur.spk.create', ['bom_id' => $billOfMaterial->id]) }}"
                        class="w-full inline-flex items-center justify-center gap-2 py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-xs font-bold rounded-xl shadow-sm transition active:scale-[0.98]"
                    >
                        Buat Surat Perintah Kerja (SPK)
                    </a>
                </div>
            </div>

            {{-- Kartu Riwayat Penggunaan di Rencana Produksi / SPK --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm space-y-3">
                <h3 class="text-xs font-extrabold uppercase tracking-wider text-gray-500 border-b border-gray-100 pb-2">
                    Penggunaan di Rencana / SPK
                </h3>

                @if ($billOfMaterial->productionPlans->count() > 0)
                    <div class="space-y-2">
                        @foreach ($billOfMaterial->productionPlans->take(5) as $plan)
                            <div class="p-2.5 bg-gray-50 rounded-xl border border-gray-100 flex items-center justify-between text-xs">
                                <div>
                                    <div class="font-bold text-gray-900">{{ $plan->plan_number }}</div>
                                    <div class="text-[11px] text-gray-500">Qty: {{ rtrim(rtrim((string) $plan->quantity, '0'), '.') }} • Status: {{ strtoupper($plan->status) }}</div>
                                </div>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $plan->status === 'completed' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ $plan->status }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-4 text-center text-gray-400 text-xs">
                        Belum pernah digunakan pada SPK atau rencana produksi manapun.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
