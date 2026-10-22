@extends('layouts.app')

@section('title', 'Profil Customer: ' . $customer->name . ' – Duta Tunggal ERP')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    {{-- Header & Action Buttons --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('master.customers.index') }}" class="hover:text-gray-700">Master Customer</a>
                <span class="text-gray-300">/</span>
                <span class="text-gray-500 font-mono">{{ $customer->getDisplayCode() }}</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ $customer->name }}</h1>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ $customer->tipe === 'PKP' ? 'bg-purple-50 text-purple-700 border border-purple-200' : 'bg-gray-100 text-gray-600' }}">
                    {{ $customer->tipe }}
                </span>
                @if ($customer->isSpecial)
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                        ⭐ Pelanggan Spesial
                    </span>
                @endif
            </div>
            <p class="text-xs text-gray-500 mt-1 flex items-center gap-2">
                <span class="font-bold text-gray-700">{{ $customer->perusahaan ?: 'Pribadi' }}</span>
                <span>•</span>
                <span class="font-mono">NIK/NPWP: {{ $customer->nik_npwp ?: '-' }}</span>
                <span>•</span>
                <span>Cabang: {{ $customer->cabang?->nama ?? 'Pusat' }}</span>
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a
                href="{{ route('master.customers.edit', $customer) }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg shadow-sm transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Edit Customer
            </a>
            <a
                href="{{ route('master.customers.index') }}"
                class="inline-flex items-center gap-2 px-3.5 py-2 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition"
            >
                Kembali
            </a>
        </div>
    </div>

    {{-- Highlight Metrics --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Plafon Kredit Limit</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">Rp {{ number_format($customer->kredit_limit, 0, ',', '.') }}</p>
            <p class="text-[11px] text-gray-400 mt-1">Tempo: {{ $customer->tempo_kredit }} Hari</p>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Piutang Berjalan (AR)</p>
            <p class="text-2xl font-bold text-rose-600 mt-1">Rp {{ number_format($totalAR, 0, ',', '.') }}</p>
            <p class="text-[11px] text-gray-400 mt-1">Tagihan belum lunas</p>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Sisa Plafon Kredit</p>
            <p class="text-2xl font-bold text-[#15803d] mt-1">Rp {{ number_format($remainingLimit, 0, ',', '.') }}</p>
            <p class="text-[11px] text-gray-400 mt-1">Kapasitas pemesanan baru</p>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Saldo Titip (Deposit)</p>
            <p class="text-2xl font-bold text-blue-600 mt-1">Rp {{ number_format($customer->deposit?->balance ?? 0, 0, ',', '.') }}</p>
            <p class="text-[11px] text-gray-400 mt-1">Uang muka / saldo deposit</p>
        </div>
    </div>

    {{-- Detail Panels Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Kolom Kiri: Profil & Riwayat Pesanan (2 Col) --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Profil & Kontak --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm space-y-4">
                <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    Informasi Profil & Legalitas
                </h3>

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3 text-xs">
                    <div>
                        <dt class="font-medium text-gray-500">Nama Perusahaan / Instansi</dt>
                        <dd class="font-semibold text-gray-900 mt-0.5">{{ $customer->perusahaan ?: '-' }}</dd>
                    </div>

                    <div>
                        <dt class="font-medium text-gray-500">Alamat Lengkap</dt>
                        <dd class="font-semibold text-gray-900 mt-0.5">{{ $customer->address }}</dd>
                    </div>

                    <div>
                        <dt class="font-medium text-gray-500">Nomor Handphone (WhatsApp)</dt>
                        <dd class="font-mono text-gray-900 font-semibold mt-0.5">{{ $customer->phone }}</dd>
                    </div>

                    <div>
                        <dt class="font-medium text-gray-500">Telepon Kantor</dt>
                        <dd class="font-mono text-gray-700 mt-0.5">{{ $customer->telephone ?: '-' }}</dd>
                    </div>

                    <div>
                        <dt class="font-medium text-gray-500">Alamat Email</dt>
                        <dd class="text-gray-900 mt-0.5">{{ $customer->email }}</dd>
                    </div>

                    <div>
                        <dt class="font-medium text-gray-500">Nomor Fax</dt>
                        <dd class="font-mono text-gray-700 mt-0.5">{{ $customer->fax ?: '-' }}</dd>
                    </div>
                </dl>

                @if ($customer->keterangan)
                    <div class="pt-3 border-t border-gray-100">
                        <p class="text-xs font-medium text-gray-500 mb-1">Catatan Tambahan:</p>
                        <div class="p-3 bg-gray-50 rounded-lg text-xs text-gray-700 leading-relaxed">
                            {{ $customer->keterangan }}
                        </div>
                    </div>
                @endif
            </div>

            {{-- Riwayat Transaksi Sales Order --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm space-y-3">
                <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2">Pesanan Penjualan Terbaru (Sales Order)</h3>
                @if ($recentSales->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50 text-gray-500 font-semibold uppercase">
                                    <th class="py-2 px-3">Nomor SO</th>
                                    <th class="py-2 px-3">Tanggal</th>
                                    <th class="py-2 px-3">Cabang</th>
                                    <th class="py-2 px-3 text-right">Total Transaksi</th>
                                    <th class="py-2 px-3 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($recentSales as $so)
                                    <tr>
                                        <td class="py-2 px-3 font-mono font-bold text-gray-900">
                                            {{ $so->order_number ?? $so->so_number ?? ('SO-' . $so->id) }}
                                        </td>
                                        <td class="py-2 px-3 text-gray-600">{{ $so->created_at->format('d/m/Y') }}</td>
                                        <td class="py-2 px-3 text-gray-700">{{ $so->cabang?->nama ?? '-' }}</td>
                                        <td class="py-2 px-3 text-right font-mono font-semibold text-gray-900">
                                            Rp {{ number_format($so->grand_total ?? $so->total ?? 0, 0, ',', '.') }}
                                        </td>
                                        <td class="py-2 px-3 text-center">
                                            <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-semibold bg-gray-100 text-gray-800 uppercase">
                                                {{ $so->status }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-xs text-gray-400 italic py-2">Belum ada riwayat pesanan penjualan untuk customer ini.</p>
                @endif
            </div>
        </div>

        {{-- Kolom Kanan: Syarat Kredit & Ringkasan --}}
        <div class="space-y-6">
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm space-y-3">
                <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                    Skema Pembayaran
                </h3>

                <div class="space-y-3 text-xs">
                    <div>
                        <span class="text-gray-400 block text-[11px]">Tipe Pembayaran Utama:</span>
                        <span class="font-bold text-gray-900 text-sm">{{ $customer->tipe_pembayaran }}</span>
                    </div>

                    <div>
                        <span class="text-gray-400 block text-[11px]">Maksimal Hari Tempo:</span>
                        <span class="font-semibold text-gray-900">{{ $customer->tempo_kredit }} Hari sejak tanggal faktur</span>
                    </div>

                    <div>
                        <span class="text-gray-400 block text-[11px]">Status Kepatuhan Limit:</span>
                        @if ($remainingLimit <= 0 && $customer->kredit_limit > 0)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200 mt-1">
                                ⚠️ Plafon Kredit Terlampaui
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 mt-1">
                                ✓ Dalam Batas Aman
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
