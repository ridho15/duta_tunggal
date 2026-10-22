@extends('layouts.app')

@section('title', 'Manajemen Pengguna & Peran – Duta Tunggal ERP')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">
    {{-- Header & Tombol Tambah --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <span>Pengaturan</span>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Pengguna & Peran</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Manajemen Pengguna & Hak Akses</h1>
            <p class="text-sm text-gray-500 mt-0.5">Kelola akun staf, hierarki peran (RBAC), penugasan cabang/gudang, dan kredensial login.</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('settings.users.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-[#15803d] hover:bg-[#166534] text-white text-sm font-semibold rounded-xl shadow-xs transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Tambah Pengguna Baru
            </a>
        </div>
    </div>

    {{-- Flash Alerts --}}
    @if (session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2.5 font-medium">
                <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('success') }}</span>
            </div>
        </div>
    @endif

    @if (session('error'))
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2.5 font-medium">
                <svg class="w-5 h-5 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('error') }}</span>
            </div>
        </div>
    @endif

    {{-- KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Pengguna</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($kpi['total_users'] ?? 0) }}</p>
                </div>
                <div class="p-2.5 bg-blue-50 text-blue-600 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Pengguna Aktif</p>
                    <p class="text-2xl font-bold text-[#15803d] mt-1">{{ number_format($kpi['active_users'] ?? 0) }}</p>
                </div>
                <div class="p-2.5 bg-emerald-50 text-emerald-600 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Nonaktif</p>
                    <p class="text-2xl font-bold text-amber-600 mt-1">{{ number_format($kpi['inactive_users'] ?? 0) }}</p>
                </div>
                <div class="p-2.5 bg-amber-50 text-amber-600 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Peran (Roles)</p>
                    <p class="text-2xl font-bold text-indigo-600 mt-1">{{ number_format($kpi['total_roles'] ?? 0) }}</p>
                </div>
                <div class="p-2.5 bg-indigo-50 text-indigo-600 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter & Pencarian --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-xs">
        <form method="GET" action="{{ route('settings.users.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div class="lg:col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Pencarian</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </span>
                    <input
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Nama, username, email, kode user, posisi..."
                        class="w-full pl-9 pr-3 py-2 text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                    >
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Peran (Role)</label>
                <select
                    name="role"
                    class="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                >
                    <option value="all">Semua Peran</option>
                    @foreach ($roles as $r)
                        <option value="{{ $r->name }}" {{ $roleName === $r->name ? 'selected' : '' }}>
                            {{ $r->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Cabang</label>
                <select
                    name="cabang_id"
                    class="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                >
                    <option value="all">Semua Cabang</option>
                    @foreach ($cabangs as $c)
                        <option value="{{ $c->id }}" {{ (string)$cabangId === (string)$c->id ? 'selected' : '' }}>
                            {{ $c->nama }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end gap-2">
                <div class="flex-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Status</label>
                    <select
                        name="status"
                        class="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                    >
                        <option value="all">Semua</option>
                        <option value="1" {{ $status === '1' ? 'selected' : '' }}>Aktif</option>
                        <option value="0" {{ $status === '0' ? 'selected' : '' }}>Nonaktif</option>
                    </select>
                </div>
                <button
                    type="submit"
                    class="px-4 py-2 bg-gray-900 hover:bg-black text-white text-sm font-semibold rounded-xl transition shadow-xs"
                >
                    Filter
                </button>
                @if ($search !== '' || $roleName !== 'all' || $cabangId !== 'all' || $status !== 'all')
                    <a
                        href="{{ route('settings.users.index') }}"
                        class="p-2 border border-gray-200 hover:bg-gray-100 text-gray-500 rounded-xl transition"
                        title="Reset Filter"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Tabel Daftar Pengguna --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-700">
                <thead class="bg-gray-50/80 border-b border-gray-200 text-xs font-semibold uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-5 py-3.5">Pengguna</th>
                        <th class="px-4 py-3.5">Kontak</th>
                        <th class="px-4 py-3.5">Peran (Role)</th>
                        <th class="px-4 py-3.5">Penugasan Cabang / Gudang</th>
                        <th class="px-4 py-3.5 text-center">Status</th>
                        <th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($users as $u)
                        <tr class="hover:bg-gray-50/80 transition">
                            {{-- Pengguna & Info Akun --}}
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-emerald-100 text-[#15803d] flex items-center justify-center font-bold text-sm tracking-wider flex-shrink-0">
                                        {{ $u->initials() ?: strtoupper(substr($u->username, 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-gray-900">{{ $u->name }}</span>
                                            <span class="text-[11px] font-mono text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded">{{ $u->kode_user }}</span>
                                        </div>
                                        <div class="flex items-center gap-2 text-xs text-gray-500 mt-0.5">
                                            <span>&#64;{{ $u->username }}</span>
                                            <span>&bull;</span>
                                            <span class="text-gray-600 font-medium">{{ $u->posisi ?: 'Staf' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            {{-- Kontak --}}
                            <td class="px-4 py-4 text-xs">
                                <div class="text-gray-900 font-medium">{{ $u->email }}</div>
                                <div class="text-gray-500 mt-0.5">{{ $u->telepon ?: '–' }}</div>
                            </td>

                            {{-- Peran / Level Spatie --}}
                            <td class="px-4 py-4">
                                <div class="flex flex-wrap gap-1">
                                    @forelse ($u->roles as $role)
                                        @php
                                            $roleColor = match ($role->name) {
                                                'Super Admin', 'Owner' => 'bg-purple-50 text-purple-700 border-purple-200',
                                                'Admin', 'IT Support' => 'bg-blue-50 text-blue-700 border-blue-200',
                                                'Kasir' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                                'Sales', 'Sales Manager' => 'bg-amber-50 text-amber-700 border-amber-200',
                                                'Finance Manager', 'Accounting', 'Admin Keuangan' => 'bg-cyan-50 text-cyan-700 border-cyan-200',
                                                default => 'bg-gray-100 text-gray-700 border-gray-200',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold border {{ $roleColor }}">
                                            {{ $role->name }}
                                        </span>
                                    @empty
                                        <span class="text-xs text-gray-400 italic">Tanpa Peran</span>
                                    @endforelse
                                </div>
                            </td>

                            {{-- Penugasan Cabang & Gudang --}}
                            <td class="px-4 py-4 text-xs">
                                @php
                                    $manage = $u->manage_type ?? [];
                                    $isAll = in_array('all', (array)$manage);
                                @endphp

                                @if ($isAll)
                                    <span class="inline-flex items-center gap-1 text-[11px] font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 px-2 py-0.5 rounded-md">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064" />
                                        </svg>
                                        Semua Cabang
                                    </span>
                                @elseif ($u->cabang)
                                    <div class="font-medium text-gray-800">{{ $u->cabang->nama }}</div>
                                    @if ($u->warehouse)
                                        <div class="text-gray-500 text-[11px] mt-0.5">{{ $u->warehouse->name }}</div>
                                    @endif
                                @else
                                    <span class="text-gray-400 italic">Belum ditentukan</span>
                                @endif
                            </td>

                            {{-- Status & Toggle --}}
                            <td class="px-4 py-4 text-center">
                                <form method="POST" action="{{ route('settings.users.toggle-active', $u) }}" class="inline-block">
                                    @csrf
                                    <button
                                        type="submit"
                                        title="{{ $u->id === auth()->id() ? 'Tidak dapat mengubah akun sendiri' : 'Klik untuk mengubah status' }}"
                                        {{ $u->id === auth()->id() ? 'disabled' : '' }}
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold transition {{ $u->status ? 'bg-emerald-50 text-[#15803d] border border-emerald-200 hover:bg-emerald-100' : 'bg-gray-100 text-gray-600 border border-gray-200 hover:bg-gray-200' }} {{ $u->id === auth()->id() ? 'opacity-70 cursor-not-allowed' : '' }}"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full {{ $u->status ? 'bg-[#15803d]' : 'bg-gray-400' }}"></span>
                                        {{ $u->status ? 'Aktif' : 'Nonaktif' }}
                                    </button>
                                </form>
                            </td>

                            {{-- Tombol Aksi --}}
                            <td class="px-5 py-4 text-right">
                                <div class="inline-flex items-center gap-1">
                                    {{-- Lihat Detail --}}
                                    <a
                                        href="{{ route('settings.users.show', $u) }}"
                                        class="p-1.5 text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition"
                                        title="Lihat Detail Profil"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>

                                    {{-- Edit User --}}
                                    <a
                                        href="{{ route('settings.users.edit', $u) }}"
                                        class="p-1.5 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded-lg transition"
                                        title="Edit Pengguna"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>

                                    {{-- Reset Password Trigger --}}
                                    <button
                                        type="button"
                                        onclick="openResetPasswordModal('{{ $u->id }}', '{{ addslashes($u->name) }}', '{{ $u->username }}')"
                                        class="p-1.5 text-amber-600 hover:text-amber-800 hover:bg-amber-50 rounded-lg transition"
                                        title="Reset Password Pengguna"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                        </svg>
                                    </button>

                                    {{-- Hapus (Protected) --}}
                                    @if ($u->id !== auth()->id())
                                        <form
                                            method="POST"
                                            action="{{ route('settings.users.destroy', $u) }}"
                                            onsubmit="return confirm('Apakah Anda yakin ingin menghapus akun {{ addslashes($u->name) }}? Tindakan ini tidak dapat dibatalkan.');"
                                            class="inline-block"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="p-1.5 text-rose-500 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition"
                                                title="Hapus Pengguna"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                                <p class="text-base font-semibold text-gray-700">Tidak ada pengguna ditemukan</p>
                                <p class="text-xs text-gray-400 mt-1">Coba sesuaikan kata kunci pencarian atau filter yang dipilih.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="px-5 py-4 border-t border-gray-100">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</div>

{{-- Modal Reset Password --}}
<div id="resetPasswordModal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-gray-900/50 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl border border-gray-200 max-w-md w-full p-6 shadow-xl relative animate-in fade-in zoom-in duration-150">
        <div class="flex items-center justify-between border-b border-gray-100 pb-3 mb-4">
            <h3 class="font-bold text-gray-900 text-base">Reset Password Pengguna</h3>
            <button type="button" onclick="closeResetPasswordModal()" class="text-gray-400 hover:text-gray-600 p-1">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <form id="resetPasswordForm" method="POST" action="">
            @csrf
            <div class="space-y-4">
                <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-800">
                    Mengatur ulang kata sandi untuk akun: <span id="resetModalTargetUser" class="font-bold text-gray-900"></span>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Password Baru <span class="text-rose-500">*</span></label>
                    <input
                        type="password"
                        name="password"
                        required
                        minlength="6"
                        placeholder="Minimal 6 karakter"
                        class="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                    >
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Konfirmasi Password Baru <span class="text-rose-500">*</span></label>
                    <input
                        type="password"
                        name="password_confirmation"
                        required
                        minlength="6"
                        placeholder="Ulangi password baru"
                        class="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                    >
                </div>
            </div>

            <div class="mt-6 flex items-center justify-end gap-2">
                <button
                    type="button"
                    onclick="closeResetPasswordModal()"
                    class="px-4 py-2 border border-gray-200 text-gray-600 hover:bg-gray-50 text-sm font-semibold rounded-xl transition"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    class="px-4 py-2 bg-[#15803d] hover:bg-[#166534] text-white text-sm font-semibold rounded-xl transition shadow-xs"
                >
                    Simpan Password
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openResetPasswordModal(userId, userName, username) {
        const modal = document.getElementById('resetPasswordModal');
        const form = document.getElementById('resetPasswordForm');
        const targetLabel = document.getElementById('resetModalTargetUser');

        targetLabel.innerText = userName + ' (@' + username + ')';
        form.action = '/pengaturan/pengguna/' + userId + '/reset-password';
        modal.classList.remove('hidden');
    }

    function closeResetPasswordModal() {
        const modal = document.getElementById('resetPasswordModal');
        modal.classList.add('hidden');
    }
</script>
@endsection
