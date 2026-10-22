@extends('layouts.app')

@section('title', 'Detail Supplier: ' . $supplier->perusahaan . ' – Duta Tunggal ERP')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    {{-- Header & Action Buttons --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('master.suppliers.index') }}" class="hover:text-gray-700">Master Supplier</a>
                <span class="text-gray-300">/</span>
                <span class="text-gray-500 font-mono">{{ $supplier->code }}</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ $supplier->perusahaan }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                    Vendor Aktif
                </span>
            </div>
            <p class="text-xs text-gray-500 mt-1 flex items-center gap-2">
                <span class="font-mono">Kode: {{ $supplier->code }}</span>
                <span>•</span>
                <span>PIC: {{ $supplier->kontak_person ?: '-' }}</span>
                <span>•</span>
                <span>NPWP: {{ $supplier->npwp ?: '-' }}</span>
                <span>•</span>
                <span>Cabang: {{ $supplier->cabang?->nama ?? 'Pusat' }}</span>
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a
                href="{{ route('master.suppliers.edit', $supplier) }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg shadow-sm transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Edit Supplier
            </a>
            <a
                href="{{ route('master.suppliers.index') }}"
                class="inline-flex items-center gap-2 px-3.5 py-2 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition"
            >
                Kembali
            </a>
        </div>
    </div>

    {{-- Highlight Metrics --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Tempo Pembayaran (TOP)</p>
            <p class="text-2xl font-bold text-indigo-600 mt-1">{{ $supplier->tempo_hutang }} <span class="text-xs font-medium text-gray-500">Hari</span></p>
            <p class="text-[11px] text-gray-400 mt-1">Jatuh tempo faktur pembelian</p>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Bank Pembayaran</p>
            <p class="text-lg font-bold text-gray-900 mt-1 truncate">{{ $supplier->nama_bank ?: 'Belum diisi' }}</p>
            <p class="text-[11px] font-mono text-gray-500 mt-1">{{ $supplier->nomor_rekening ?: 'No Rek: -' }}</p>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Purchase Order</p>
            <p class="text-2xl font-bold text-[#15803d] mt-1">{{ number_format($totalPOs) }}</p>
            <p class="text-[11px] text-gray-400 mt-1">Dokumen PO diterbitkan</p>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Katalog Produk</p>
            <p class="text-2xl font-bold text-blue-600 mt-1">{{ number_format($totalSuppliedProducts) }} <span class="text-xs font-medium text-gray-500">SKU</span></p>
            <p class="text-[11px] text-gray-400 mt-1">Produk terdaftar</p>
        </div>
    </div>

    {{-- Detail Panels Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Kolom Kiri: Profil, Alamat & Riwayat PO (2 Col) --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Profil & Kontak --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm space-y-4">
                <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                    Informasi Badan Usaha & Kontak
                </h3>

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3 text-xs">
                    <div>
                        <dt class="font-medium text-gray-500">Nama Perusahaan</dt>
                        <dd class="font-semibold text-gray-900 mt-0.5">{{ $supplier->perusahaan }}</dd>
                    </div>

                    <div>
                        <dt class="font-medium text-gray-500">Kontak Person (Sales/PIC)</dt>
                        <dd class="font-semibold text-gray-900 mt-0.5">{{ $supplier->kontak_person ?: '-' }}</dd>
                    </div>

                    <div>
                        <dt class="font-medium text-gray-500">Nomor NPWP</dt>
                        <dd class="font-mono text-gray-900 font-semibold mt-0.5">{{ $supplier->npwp }}</dd>
                    </div>

                    <div>
                        <dt class="font-medium text-gray-500">Alamat Kantor / Pabrik</dt>
                        <dd class="text-gray-900 mt-0.5">{{ $supplier->address }}</dd>
                    </div>

                    <div>
                        <dt class="font-medium text-gray-500">Nomor Telepon Kantor</dt>
                        <dd class="font-mono text-gray-900 mt-0.5">{{ $supplier->phone }}</dd>
                    </div>

                    <div>
                        <dt class="font-medium text-gray-500">Nomor Handphone (WhatsApp)</dt>
                        <dd class="font-mono text-gray-900 font-semibold mt-0.5">{{ $supplier->handphone }}</dd>
                    </div>

                    <div>
                        <dt class="font-medium text-gray-500">Alamat Email</dt>
                        <dd class="text-gray-900 mt-0.5">{{ $supplier->email }}</dd>
                    </div>

                    <div>
                        <dt class="font-medium text-gray-500">Nomor Fax</dt>
                        <dd class="font-mono text-gray-700 mt-0.5">{{ $supplier->fax ?: '-' }}</dd>
                    </div>
                </dl>

                @if ($supplier->keterangan)
                    <div class="pt-3 border-t border-gray-100">
                        <p class="text-xs font-medium text-gray-500 mb-1">Catatan Tambahan:</p>
                        <div class="p-3 bg-gray-50 rounded-lg text-xs text-gray-700 leading-relaxed">
                            {{ $supplier->keterangan }}
                        </div>
                    </div>
                @endif
            </div>

            {{-- Riwayat Purchase Order Terbaru --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm space-y-3">
                <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2">Pesanan Pembelian Terbaru (Purchase Order)</h3>
                @if ($recentPOs->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50 text-gray-500 font-semibold uppercase">
                                    <th class="py-2 px-3">Nomor PO</th>
                                    <th class="py-2 px-3">Tanggal</th>
                                    <th class="py-2 px-3">Cabang</th>
                                    <th class="py-2 px-3 text-right">Nilai Pesanan</th>
                                    <th class="py-2 px-3 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($recentPOs as $po)
                                    <tr>
                                        <td class="py-2 px-3 font-mono font-bold text-gray-900">
                                            {{ $po->po_number ?? ('PO-' . $po->id) }}
                                        </td>
                                        <td class="py-2 px-3 text-gray-600">{{ $po->created_at->format('d/m/Y') }}</td>
                                        <td class="py-2 px-3 text-gray-700">{{ $po->cabang?->nama ?? '-' }}</td>
                                        <td class="py-2 px-3 text-right font-mono font-semibold text-gray-900">
                                            Rp {{ number_format($po->grand_total ?? $po->total ?? 0, 0, ',', '.') }}
                                        </td>
                                        <td class="py-2 px-3 text-center">
                                            <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-semibold bg-gray-100 text-gray-800 uppercase">
                                                {{ $po->status }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-xs text-gray-400 italic py-2">Belum ada riwayat pesanan pembelian (PO) untuk supplier ini.</p>
                @endif
            </div>
        </div>

        {{-- Kolom Kanan: Rekening Bank Pembayaran (Item C7) & Produk --}}
        <div class="space-y-6">
            {{-- Data Rekening Bank --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm space-y-3">
                <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                    Rekening Bank Pembayaran
                </h3>

                @if ($supplier->nomor_rekening)
                    <div class="p-3.5 bg-blue-50/70 border border-blue-200 rounded-xl space-y-2 text-xs">
                        <div>
                            <span class="text-blue-600 text-[11px] block font-semibold">Nama Bank:</span>
                            <span class="text-base font-bold text-blue-900">{{ $supplier->nama_bank ?: 'Bank Transfer' }}</span>
                        </div>
                        <div>
                            <span class="text-blue-600 text-[11px] block font-semibold">Nomor Rekening:</span>
                            <span class="font-mono text-base font-extrabold text-blue-950">{{ $supplier->nomor_rekening }}</span>
                        </div>
                        <div>
                            <span class="text-blue-600 text-[11px] block font-semibold">Atas Nama Rekening:</span>
                            <span class="font-bold text-gray-900">{{ $supplier->nama_rekening ?: $supplier->perusahaan }}</span>
                        </div>
                    </div>
                    <p class="text-[11px] text-gray-500">Rekening ini otomatis digunakan saat penerbitan voucher pembayaran hutang.</p>
                @else
                    <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-800">
                        ⚠️ <strong>Rekening Belum Terdaftar:</strong> Mohon lengkapi nomor rekening vendor ini pada menu Edit agar staf Keuangan dapat melakukan transfer pelunasan hutang.
                    </div>
                @endif
            </div>

            {{-- Katalog Produk yang Disuplai --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm space-y-3">
                <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2">Produk yang Disuplai</h3>
                @if ($supplier->productSuppliers->count() > 0)
                    <div class="space-y-2 max-h-80 overflow-y-auto">
                        @foreach ($supplier->productSuppliers as $prod)
                            <div class="p-2.5 bg-gray-50 rounded-lg text-xs flex items-center justify-between">
                                <div>
                                    <p class="font-semibold text-gray-900">{{ $prod->name }}</p>
                                    <p class="text-[11px] text-gray-500 font-mono">{{ $prod->sku }}</p>
                                </div>
                                <span class="font-bold text-gray-800">
                                    Rp {{ number_format($prod->pivot->supplier_price ?? $prod->cost_price, 0, ',', '.') }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-gray-400 italic py-2">Belum ada produk yang ditautkan ke supplier ini.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
