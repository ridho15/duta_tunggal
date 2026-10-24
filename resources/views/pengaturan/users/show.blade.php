@extends('layouts.app')

@section('title', 'Profil Pengguna: ' . $user->name . ' – Duta Tunggal ERP')

@section('content')
<div class="space-y-6 max-w-5xl mx-auto pb-12">
    {{-- Header & Breadcrumb --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('settings.users.index') }}" class="hover:text-gray-700">Pengaturan</a>
                <span class="text-gray-300">/</span>
                <a href="{{ route('settings.users.index') }}" class="hover:text-gray-700">Pengguna & Peran</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Profil Pengguna</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Detail Profil Pengguna</h1>
            <p class="text-sm text-gray-500 mt-0.5">Informasi akun staf, hierarki peran yang diemban, dan transparansi izin akses.</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('settings.users.index') }}"
                class="inline-flex items-center gap-2 px-3.5 py-2 border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-xl transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Daftar Pengguna
            </a>
            <a
                href="{{ route('settings.users.edit', $user) }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-[#15803d] hover:bg-[#166534] text-white text-sm font-semibold rounded-xl transition shadow-xs active:scale-[0.98]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Edit Pengguna
            </a>
        </div>
    </div>


    {{-- Main Profile Card --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-xs">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6 pb-6 border-b border-gray-100">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-2xl bg-emerald-100 text-[#15803d] flex items-center justify-center font-extrabold text-xl tracking-wider shadow-inner flex-shrink-0">
                    {{ $user->initials() ?: strtoupper(substr($user->username, 0, 2)) }}
                </div>
                <div>
                    <div class="flex items-center gap-2.5">
                        <h2 class="text-xl font-bold text-gray-900">{{ $user->name }}</h2>
                        <span class="text-xs font-mono text-gray-500 bg-gray-100 px-2 py-0.5 rounded-md font-semibold">{{ $user->kode_user }}</span>
                    </div>
                    <div class="flex items-center gap-3 text-xs text-gray-500 mt-1">
                        <span>&#64;{{ $user->username }}</span>
                        <span>&bull;</span>
                        <span class="text-gray-700 font-semibold">{{ $user->posisi ?: 'Staf' }}</span>
                        <span>&bull;</span>
                        <span>Bergabung: {{ $user->created_at ? $user->created_at->format('d M Y') : '–' }}</span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold {{ $user->status ? 'bg-emerald-50 text-[#15803d] border border-emerald-200' : 'bg-gray-100 text-gray-600 border border-gray-200' }}">
                    <span class="w-2 h-2 rounded-full {{ $user->status ? 'bg-[#15803d]' : 'bg-gray-400' }}"></span>
                    {{ $user->status ? 'Akun Aktif' : 'Nonaktif' }}
                </span>

                <button
                    type="button"
                    onclick="openResetPasswordModal('{{ $user->id }}', '{{ addslashes($user->name) }}', '{{ $user->username }}')"
                    class="px-3.5 py-1.5 border border-amber-200 bg-amber-50 hover:bg-amber-100 text-amber-800 text-xs font-semibold rounded-xl transition shadow-xs"
                >
                    Reset Password
                </button>
            </div>
        </div>

        {{-- Detail Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-6">
            {{-- Kontak & Identitas --}}
            <div class="space-y-3">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider">Informasi Kontak & Akun</h3>
                <div class="bg-gray-50/70 rounded-xl p-4 border border-gray-100 space-y-2.5 text-xs">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Nama Depan:</span>
                        <span class="font-semibold text-gray-900">{{ $user->first_name ?: '–' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Nama Belakang:</span>
                        <span class="font-semibold text-gray-900">{{ $user->last_name ?: '–' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Email:</span>
                        <span class="font-semibold text-gray-900">{{ $user->email }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Telepon / WA:</span>
                        <span class="font-semibold text-gray-900">{{ $user->telepon ?: '–' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Status Verifikasi Email:</span>
                        <span class="font-semibold {{ $user->email_verified_at ? 'text-emerald-700' : 'text-amber-600' }}">
                            {{ $user->email_verified_at ? 'Terverifikasi' : 'Belum Diverifikasi' }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Wilayah & Penugasan --}}
            <div class="space-y-3">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider">Wilayah Kerja & Penugasan</h3>
                <div class="bg-gray-50/70 rounded-xl p-4 border border-gray-100 space-y-2.5 text-xs">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Akses Kelola:</span>
                        <span class="font-semibold text-gray-900">
                            @php
                                $manage = (array) ($user->manage_type ?? []);
                            @endphp
                            @if (in_array('all', $manage))
                                <span class="text-indigo-600">Semua Cabang / Gudang</span>
                            @else
                                {{ implode(', ', array_map('ucfirst', $manage)) }}
                            @endif
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Cabang Penugasan:</span>
                        <span class="font-semibold text-gray-900">
                            {{ $user->cabang ? "({$user->cabang->kode}) {$user->cabang->nama}" : 'Pusat / Semua' }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Gudang Penugasan:</span>
                        <span class="font-semibold text-gray-900">
                            {{ $user->warehouse ? "({$user->warehouse->kode}) {$user->warehouse->name}" : '–' }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Pembaruan Terakhir:</span>
                        <span class="font-semibold text-gray-900">{{ $user->updated_at ? $user->updated_at->format('d M Y, H:i') : '–' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Peran & Hak Akses (RBAC) Card --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-xs space-y-4">
        <div>
            <h3 class="text-base font-bold text-gray-900">Peran yang Diemban (Roles)</h3>
            <p class="text-xs text-gray-500 mt-0.5">Daftar peran Spatie yang memberikan hak akses menu dan fungsi sistem kepada pengguna ini.</p>
        </div>

        <div class="flex flex-wrap gap-2 pt-1">
            @forelse ($user->roles as $role)
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
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border {{ $roleColor }} text-xs font-bold">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    <span>{{ $role->name }}</span>
                </div>
            @empty
                <div class="text-xs text-gray-400 italic">Pengguna ini belum memiliki peran aktif.</div>
            @endforelse
        </div>
    </div>

    {{-- Matriks Permissions Detail --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-xs space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-gray-900">Transparansi Hak Akses Sistem (Permissions)</h3>
                <p class="text-xs text-gray-500 mt-0.5">Izin operasi spesifik yang diwarisi dari peran maupun izin langsung.</p>
            </div>
            <span class="text-xs font-bold text-gray-500 bg-gray-100 px-2.5 py-1 rounded-lg">
                {{ $allPermissions->count() }} Izin Terdaftar
            </span>
        </div>

        @if ($allPermissions->isNotEmpty())
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2 max-h-96 overflow-y-auto p-1 custom-scrollbar">
                @foreach ($allPermissions as $perm)
                    <div class="flex items-center gap-2 p-2 rounded-lg bg-gray-50 border border-gray-100 text-xs text-gray-700">
                        <svg class="w-3.5 h-3.5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                        </svg>
                        <span class="truncate font-mono text-[11px]">{{ $perm->name }}</span>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-xs text-gray-400 italic py-2">
                Tidak ada izin granular eksplisit yang terdaftar untuk akun ini.
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
