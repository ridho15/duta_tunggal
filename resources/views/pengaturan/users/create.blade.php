@extends('layouts.app')

@section('title', 'Tambah Pengguna Baru – Duta Tunggal ERP')

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
                <span class="text-[#15803d] font-bold">Tambah Baru</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Pendaftaran Pengguna Baru</h1>
            <p class="text-sm text-gray-500 mt-0.5">Buat akun staf baru, tentukan hak akses peran (RBAC), dan penugasan cabang operasional.</p>
        </div>
        <div>
            <a
                href="{{ route('settings.users.index') }}"
                class="inline-flex items-center gap-2 px-4 py-2 border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-xl transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Kembali ke Daftar
            </a>
        </div>
    </div>

    {{-- Error Banner --}}
    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm shadow-xs">
            <div class="flex items-center gap-2 font-bold mb-1">
                <svg class="w-5 h-5 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Mohon periksa kesalahan input berikut:</span>
            </div>
            <ul class="list-disc list-inside text-xs space-y-1 ml-7 text-rose-700">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('settings.users.store') }}" class="space-y-6">
        @csrf

        {{-- Section 1: Informasi Kredensial & Profil --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-xs space-y-4">
            <div class="border-b border-gray-100 pb-3">
                <h2 class="text-base font-bold text-gray-900">1. Kredensial & Profil Pengguna</h2>
                <p class="text-xs text-gray-500 mt-0.5">Identitas dasar staf untuk login dan komunikasi sistem.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Nama Depan <span class="text-rose-500">*</span></label>
                    <input
                        type="text"
                        name="first_name"
                        value="{{ old('first_name') }}"
                        required
                        placeholder="Contoh: Budi"
                        class="w-full px-3.5 py-2 text-sm rounded-xl border {{ $errors->has('first_name') ? 'border-rose-300 ring-1 ring-rose-300' : 'border-gray-200' }} focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                    >
                    @error('first_name')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Nama Belakang</label>
                    <input
                        type="text"
                        name="last_name"
                        value="{{ old('last_name') }}"
                        placeholder="Contoh: Pratama"
                        class="w-full px-3.5 py-2 text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                    >
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Username <span class="text-rose-500">*</span></label>
                    <input
                        type="text"
                        name="username"
                        value="{{ old('username') }}"
                        required
                        placeholder="Contoh: budipratama"
                        class="w-full px-3.5 py-2 text-sm rounded-xl border {{ $errors->has('username') ? 'border-rose-300 ring-1 ring-rose-300' : 'border-gray-200' }} focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                    >
                    @error('username')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Alamat Email <span class="text-rose-500">*</span></label>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        placeholder="Contoh: budi@dutatunggal.co.id"
                        class="w-full px-3.5 py-2 text-sm rounded-xl border {{ $errors->has('email') ? 'border-rose-300 ring-1 ring-rose-300' : 'border-gray-200' }} focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                    >
                    @error('email')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Nomor Telepon / WhatsApp</label>
                    <input
                        type="text"
                        name="telepon"
                        value="{{ old('telepon') }}"
                        placeholder="Contoh: 081234567890"
                        class="w-full px-3.5 py-2 text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                    >
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Posisi / Jabatan <span class="text-rose-500">*</span></label>
                    <input
                        type="text"
                        name="posisi"
                        value="{{ old('posisi') }}"
                        required
                        placeholder="Contoh: Kasir Ritel, Kepala Gudang, Salesman"
                        class="w-full px-3.5 py-2 text-sm rounded-xl border {{ $errors->has('posisi') ? 'border-rose-300 ring-1 ring-rose-300' : 'border-gray-200' }} focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                    >
                    @error('posisi')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Password Login <span class="text-rose-500">*</span></label>
                    <input
                        type="password"
                        name="password"
                        required
                        minlength="6"
                        placeholder="Minimal 6 karakter"
                        class="w-full px-3.5 py-2 text-sm rounded-xl border {{ $errors->has('password') ? 'border-rose-300 ring-1 ring-rose-300' : 'border-gray-200' }} focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                    >
                    @error('password')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Konfirmasi Password <span class="text-rose-500">*</span></label>
                    <input
                        type="password"
                        name="password_confirmation"
                        required
                        minlength="6"
                        placeholder="Ulangi password login"
                        class="w-full px-3.5 py-2 text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                    >
                </div>
            </div>
        </div>

        {{-- Section 2: Peran & Otorisasi (RBAC) --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-xs space-y-4">
            <div class="border-b border-gray-100 pb-3 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-gray-900">2. Hak Akses & Peran (Role)</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Tentukan satu atau lebih peran untuk mengatur menu dan otorisasi fitur pengguna.</p>
                </div>
                <div class="flex items-center gap-2">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="status" value="1" class="sr-only peer" {{ old('status', '1') == '1' ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#15803d]"></div>
                        <span class="ml-2 text-xs font-semibold text-gray-700">Akun Aktif</span>
                    </label>
                </div>
            </div>

            @error('roles')
                <p class="text-xs text-rose-600">{{ $message }}</p>
            @enderror

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @php
                    $oldRoles = (array) old('roles', []);
                @endphp
                @foreach ($roles as $r)
                    <label class="flex items-start gap-3 p-3 rounded-xl border border-gray-200 hover:bg-gray-50/80 cursor-pointer transition has-[:checked]:border-[#15803d] has-[:checked]:bg-emerald-50/50">
                        <input
                            type="checkbox"
                            name="roles[]"
                            value="{{ $r->name }}"
                            class="mt-0.5 rounded text-[#15803d] focus:ring-[#15803d]"
                            {{ in_array($r->name, $oldRoles) ? 'checked' : '' }}
                        >
                        <div class="text-xs">
                            <span class="font-bold text-gray-900 block">{{ $r->name }}</span>
                            <span class="text-gray-500 text-[11px]">{{ $r->description ?: 'Hak akses sistem peran ' . $r->name }}</span>
                        </div>
                    </label>
                @endforeach
            </div>
        </div>

        {{-- Section 3: Cakupan Kelola & Penugasan Cabang/Gudang --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-xs space-y-4">
            <div class="border-b border-gray-100 pb-3">
                <h2 class="text-base font-bold text-gray-900">3. Wilayah Kerja & Penugasan</h2>
                <p class="text-xs text-gray-500 mt-0.5">Batasi jangkauan data transaksi staf berdasarkan cabang atau gudang tertentu.</p>
            </div>

            @php
                $oldManage = (array) old('manage_type', ['all']);
            @endphp

            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-2">Akses Kelola <span class="text-rose-500">*</span></label>
                <div class="flex flex-wrap gap-4">
                    <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-medium text-gray-700">
                        <input type="checkbox" name="manage_type[]" value="all" class="rounded text-[#15803d] focus:ring-[#15803d]" {{ in_array('all', $oldManage) ? 'checked' : '' }}>
                        <span>Semua Cabang / Gudang (Holding/Pusat)</span>
                    </label>
                    <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-medium text-gray-700">
                        <input type="checkbox" name="manage_type[]" value="cabang" class="rounded text-[#15803d] focus:ring-[#15803d]" {{ in_array('cabang', $oldManage) ? 'checked' : '' }}>
                        <span>Cabang Tertentu</span>
                    </label>
                    <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-medium text-gray-700">
                        <input type="checkbox" name="manage_type[]" value="warehouse" class="rounded text-[#15803d] focus:ring-[#15803d]" {{ in_array('warehouse', $oldManage) ? 'checked' : '' }}>
                        <span>Gudang Tertentu</span>
                    </label>
                </div>
                @error('manage_type')
                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Cabang Penugasan</label>
                    <select
                        name="cabang_id"
                        class="w-full px-3.5 py-2 text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                    >
                        <option value="">Pilih Cabang (Opsional jika Semua Cabang)</option>
                        @foreach ($cabangs as $c)
                            <option value="{{ $c->id }}" {{ old('cabang_id') == $c->id ? 'selected' : '' }}>
                                ({{ $c->kode }}) {{ $c->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Gudang Penugasan</label>
                    <select
                        name="warehouse_id"
                        class="w-full px-3.5 py-2 text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#15803d]/20 focus:border-[#15803d] transition"
                    >
                        <option value="">Pilih Gudang (Opsional)</option>
                        @foreach ($warehouses as $w)
                            <option value="{{ $w->id }}" {{ old('warehouse_id') == $w->id ? 'selected' : '' }}>
                                ({{ $w->kode }}) {{ $w->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-end gap-3 pt-2">
            <a
                href="{{ route('settings.users.index') }}"
                class="px-5 py-2.5 border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-xl transition"
            >
                Batal
            </a>
            <button
                type="submit"
                class="px-6 py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-sm font-semibold rounded-xl transition shadow-xs active:scale-[0.98]"
            >
                Simpan Pengguna Baru
            </button>
        </div>
    </form>
</div>
@endsection
