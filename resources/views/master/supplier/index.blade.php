@extends('layouts.app')

@section('title', 'Master Supplier – Duta Tunggal ERP')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">
    {{-- Header & Action Button --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <span>Master Data</span>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Supplier</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Data Pemasok (Supplier)</h1>
            <p class="text-sm text-gray-500 mt-0.5">Kelola data vendor pengadaan, rekening bank pembayaran hutang, dan tempo hutang dagang.</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('master.suppliers.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-[#15803d] hover:bg-[#166534] text-white text-sm font-semibold rounded-lg shadow-sm transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Tambah Supplier Baru
            </a>
        </div>
    </div>

    {{-- Flash Alerts --}}
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
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Supplier</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($kpi['total_suppliers'] ?? 0) }}</p>
                </div>
                <div class="p-2.5 bg-blue-50 text-blue-600 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Rata-rata Tempo (TOP)</p>
                    <p class="text-2xl font-bold text-indigo-600 mt-1">{{ number_format($kpi['avg_top'] ?? 0) }} <span class="text-xs font-medium text-gray-500">Hari</span></p>
                </div>
                <div class="p-2.5 bg-indigo-50 text-indigo-600 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Rekening Terdaftar</p>
                    <p class="text-2xl font-bold text-[#15803d] mt-1">{{ number_format($kpi['valid_bank_suppliers'] ?? 0) }}</p>
                </div>
                <div class="p-2.5 bg-emerald-50 text-emerald-600 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Cakupan Cabang</p>
                    <p class="text-2xl font-bold text-gray-700 mt-1">{{ number_format($kpi['total_branches'] ?? 0) }}</p>
                </div>
                <div class="p-2.5 bg-gray-50 text-gray-600 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter & Search Bar --}}
    <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
        <form method="GET" action="{{ route('master.suppliers.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div class="lg:col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Cari Supplier</label>
                <div class="relative">
                    <input
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Ketik kode, nama perusahaan, kontak, no rekening..."
                        class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                    >
                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Bank Pembayaran</label>
                <select name="bank" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]">
                    <option value="all" {{ $bank === 'all' ? 'selected' : '' }}>Semua Bank</option>
                    @foreach ($availableBanks as $b)
                        <option value="{{ $b }}" {{ $bank === $b ? 'selected' : '' }}>
                            {{ $b }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Cabang</label>
                <select name="cabang_id" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]">
                    <option value="">Semua Cabang</option>
                    @foreach ($cabangs as $cb)
                        <option value="{{ $cb->id }}" {{ (string)$cabangId === (string)$cb->id ? 'selected' : '' }}>
                            {{ $cb->nama }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button
                    type="submit"
                    class="flex-1 py-2 px-4 bg-gray-900 hover:bg-gray-800 text-white text-sm font-semibold rounded-lg shadow-sm transition"
                >
                    Filter
                </button>
                <a
                    href="{{ route('master.suppliers.index') }}"
                    class="py-2 px-3 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold rounded-lg transition"
                    title="Reset Filter"
                >
                    Reset
                </a>
            </div>
        </form>
    </div>

    {{-- Data Table --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-50/80 border-b border-gray-200 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                        <th class="py-3 px-4">Kode & Perusahaan</th>
                        <th class="py-3 px-4">Kontak Person</th>
                        <th class="py-3 px-4">Kontak (Telp / Email)</th>
                        <th class="py-3 px-4">Rekening Bank Pembayaran</th>
                        <th class="py-3 px-4 text-center">Tempo Hutang</th>
                        <th class="py-3 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($suppliers as $s)
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-3 px-4">
                                <a href="{{ route('master.suppliers.show', $s) }}" class="font-bold text-gray-900 hover:text-[#15803d] transition">
                                    {{ $s->perusahaan }}
                                </a>
                                <div class="text-xs font-mono text-gray-500 mt-0.5">
                                    <span>{{ $s->code }}</span>
                                    @if ($s->npwp)
                                        <span class="text-gray-400">• NPWP: {{ $s->npwp }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="text-gray-900 font-medium">{{ $s->kontak_person ?: '-' }}</div>
                                <div class="text-xs text-gray-500">{{ $s->cabang?->nama ?? 'Pusat' }}</div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="text-gray-900 text-xs font-mono">{{ $s->phone }}</div>
                                <div class="text-[11px] text-gray-500">{{ $s->email }}</div>
                            </td>
                            <td class="py-3 px-4">
                                @if ($s->nomor_rekening)
                                    <div class="flex items-center gap-1.5 font-medium text-gray-900 text-xs">
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                            {{ $s->nama_bank ?: 'Bank' }}
                                        </span>
                                        <span class="font-mono">{{ $s->nomor_rekening }}</span>
                                    </div>
                                    <div class="text-[11px] text-gray-500 mt-0.5">a.n. {{ $s->nama_rekening ?: $s->perusahaan }}</div>
                                @else
                                    <span class="text-xs text-gray-400 italic">Belum didaftarkan</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-800">
                                    {{ $s->tempo_hutang }} Hari
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <div class="inline-flex items-center gap-1">
                                    <a
                                        href="{{ route('master.suppliers.show', $s) }}"
                                        class="p-1.5 rounded-lg text-gray-500 hover:text-gray-900 hover:bg-gray-100 transition"
                                        title="Lihat Detail"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>
                                    <a
                                        href="{{ route('master.suppliers.edit', $s) }}"
                                        class="p-1.5 rounded-lg text-blue-600 hover:text-blue-800 hover:bg-blue-50 transition"
                                        title="Edit Supplier"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                    <form
                                        method="POST"
                                        action="{{ route('master.suppliers.destroy', $s) }}"
                                        class="inline"
                                        onsubmit="return confirm('Apakah Anda yakin ingin menghapus supplier {{ $s->perusahaan }}?');"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            class="p-1.5 rounded-lg text-rose-500 hover:text-rose-700 hover:bg-rose-50 transition"
                                            title="Hapus Supplier"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-gray-500">
                                <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                                <p class="text-base font-semibold text-gray-800">Tidak ada supplier ditemukan</p>
                                <p class="text-xs text-gray-500 mt-1">Coba sesuaikan kata kunci pencarian atau daftarkan supplier baru.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($suppliers->hasPages())
            <div class="p-4 border-t border-gray-200">
                {{ $suppliers->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
