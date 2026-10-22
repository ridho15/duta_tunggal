@extends('layouts.app')

@section('title', 'Master Customer – Duta Tunggal ERP')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">
    {{-- Header & Action Button --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <span>Master Data</span>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Customer</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Data Pelanggan (Customer)</h1>
            <p class="text-sm text-gray-500 mt-0.5">Kelola data mitra pembeli, legalitas PKP/PRI, plafon kredit limit, dan tempo pembayaran.</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('master.customers.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-[#15803d] hover:bg-[#166534] text-white text-sm font-semibold rounded-lg shadow-sm transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Tambah Customer Baru
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
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Customer</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($kpi['total_customers'] ?? 0) }}</p>
                </div>
                <div class="p-2.5 bg-blue-50 text-blue-600 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Customer PKP</p>
                    <p class="text-2xl font-bold text-[#15803d] mt-1">{{ number_format($kpi['pkp_customers'] ?? 0) }}</p>
                </div>
                <div class="p-2.5 bg-emerald-50 text-emerald-600 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Non-PKP / Pribadi</p>
                    <p class="text-2xl font-bold text-gray-600 mt-1">{{ number_format($kpi['pri_customers'] ?? 0) }}</p>
                </div>
                <div class="p-2.5 bg-gray-50 text-gray-600 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Plafon Kredit Aktif</p>
                    <p class="text-xl font-bold text-amber-600 mt-1">Rp {{ number_format(($kpi['total_credit_limit'] ?? 0) / 1000000, 1, ',', '.') }} Jt</p>
                </div>
                <div class="p-2.5 bg-amber-50 text-amber-600 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter & Search Bar --}}
    <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
        <form method="GET" action="{{ route('master.customers.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div class="lg:col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Cari Customer</label>
                <div class="relative">
                    <input
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Ketik kode, nama, perusahaan, telepon, NIK/NPWP..."
                        class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                    >
                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Tipe Legalitas</label>
                <select name="tipe" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]">
                    <option value="all" {{ $tipe === 'all' ? 'selected' : '' }}>Semua Tipe</option>
                    <option value="PKP" {{ $tipe === 'PKP' ? 'selected' : '' }}>PKP (Perusahaan)</option>
                    <option value="PRI" {{ $tipe === 'PRI' ? 'selected' : '' }}>PRI (Pribadi)</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Tipe Pembayaran</label>
                <select name="tipe_pembayaran" class="w-full py-2 px-3 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]">
                    <option value="all" {{ $tipePembayaran === 'all' ? 'selected' : '' }}>Semua Tipe Bayar</option>
                    <option value="Bebas" {{ $tipePembayaran === 'Bebas' ? 'selected' : '' }}>Bebas</option>
                    <option value="COD (Bayar Lunas)" {{ $tipePembayaran === 'COD (Bayar Lunas)' ? 'selected' : '' }}>COD (Bayar Lunas)</option>
                    <option value="Kredit" {{ $tipePembayaran === 'Kredit' ? 'selected' : '' }}>Kredit</option>
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
                    href="{{ route('master.customers.index') }}"
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
                        <th class="py-3 px-4">Kode & Customer</th>
                        <th class="py-3 px-4">Perusahaan / NIK</th>
                        <th class="py-3 px-4 text-center">Legalitas</th>
                        <th class="py-3 px-4">Kontak (Telp / HP)</th>
                        <th class="py-3 px-4">Syarat Pembayaran</th>
                        <th class="py-3 px-4 text-right">Limit Kredit</th>
                        <th class="py-3 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($customers as $c)
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-3 px-4">
                                <a href="{{ route('master.customers.show', $c) }}" class="font-bold text-gray-900 hover:text-[#15803d] transition">
                                    {{ $c->name }}
                                </a>
                                <div class="text-xs font-mono text-gray-500 mt-0.5 flex items-center gap-1.5">
                                    <span>{{ $c->getDisplayCode() }}</span>
                                    @if ($c->isSpecial)
                                        <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">⭐ Spesial</span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="text-gray-900 font-medium">{{ $c->perusahaan ?: '-' }}</div>
                                <div class="text-xs text-gray-500 font-mono">{{ $c->nik_npwp ?: 'Tanpa NIK/NPWP' }}</div>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ $c->tipe === 'PKP' ? 'bg-purple-50 text-purple-700 border border-purple-200' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $c->tipe }}
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <div class="text-gray-900 text-xs font-mono">{{ $c->phone ?: '-' }}</div>
                                <div class="text-[11px] text-gray-500 font-mono">{{ $c->telephone ?: '' }}</div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $c->tipe_pembayaran === 'Kredit' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-gray-100 text-gray-700' }}">
                                    {{ $c->tipe_pembayaran }}
                                </span>
                                @if ($c->tipe_pembayaran === 'Kredit' && $c->tempo_kredit > 0)
                                    <div class="text-[11px] text-gray-500 mt-0.5">{{ $c->tempo_kredit }} Hari</div>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right">
                                @if ($c->kredit_limit > 0)
                                    <span class="font-bold text-gray-900">
                                        Rp {{ number_format($c->kredit_limit, 0, ',', '.') }}
                                    </span>
                                @else
                                    <span class="text-xs text-gray-400">Tidak ada limit</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-center">
                                <div class="inline-flex items-center gap-1">
                                    <a
                                        href="{{ route('master.customers.show', $c) }}"
                                        class="p-1.5 rounded-lg text-gray-500 hover:text-gray-900 hover:bg-gray-100 transition"
                                        title="Lihat Profil"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>
                                    <a
                                        href="{{ route('master.customers.edit', $c) }}"
                                        class="p-1.5 rounded-lg text-blue-600 hover:text-blue-800 hover:bg-blue-50 transition"
                                        title="Edit Customer"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                    <form
                                        method="POST"
                                        action="{{ route('master.customers.destroy', $c) }}"
                                        class="inline"
                                        onsubmit="return confirm('Apakah Anda yakin ingin menghapus customer {{ $c->name }}?');"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            class="p-1.5 rounded-lg text-rose-500 hover:text-rose-700 hover:bg-rose-50 transition"
                                            title="Hapus Customer"
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
                            <td colspan="7" class="py-12 text-center text-gray-500">
                                <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                <p class="text-base font-semibold text-gray-800">Tidak ada customer ditemukan</p>
                                <p class="text-xs text-gray-500 mt-1">Coba sesuaikan kata kunci pencarian atau daftarkan customer baru.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($customers->hasPages())
            <div class="p-4 border-t border-gray-200">
                {{ $customers->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
