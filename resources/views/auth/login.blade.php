@extends('layouts.auth', ['title' => 'Masuk'])

@section('content')
<div class="space-y-6">
    {{-- Header Logo & Brand --}}
    <div class="text-center space-y-2">
        <div class="inline-flex items-center gap-2">
            <span class="font-extrabold text-2xl tracking-tight text-gray-900">DUTA TUNGGAL</span>
            <span class="text-[10px] font-bold text-[#15803d] border border-[#86efac] bg-[#f0fdf4] rounded px-1.5 py-0.5 tracking-wider">ERP</span>
        </div>
        <p class="text-sm text-gray-500">Sistem Operasional & Manajemen Terpadu</p>
    </div>

    {{-- Form Card --}}
    <div class="bg-white border border-gray-200 rounded-2xl p-7 shadow-sm">
        <div class="mb-6">
            <h1 class="text-xl font-bold text-gray-900">Masuk ke Akun Anda</h1>
            <p class="text-sm text-gray-500 mt-1">Masukkan kredensial Anda untuk membuka modul kerja.</p>
        </div>

        {{-- Status Notification --}}
        @if (session('status'))
            <div class="mb-5 p-3.5 rounded-xl bg-blue-50 border border-blue-200 text-blue-800 text-sm flex items-center gap-2">
                <svg class="w-5 h-5 flex-shrink-0 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="9" stroke-width="1.8"></circle>
                    <path d="M12 8v4M12 16h.01" stroke-width="2" stroke-linecap="round"></path>
                </svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        {{-- Error Validation Alert --}}
        @if ($errors->any())
            <div class="mb-5 p-3.5 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm flex items-start gap-2">
                <svg class="w-5 h-5 flex-shrink-0 text-red-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="9" stroke-width="1.8"></circle>
                    <path d="M12 8v4M12 16h.01" stroke-width="2" stroke-linecap="round"></path>
                </svg>
                <div class="flex-1">
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <form action="{{ route('login') }}" method="POST" class="space-y-4">
            @csrf

            {{-- Input Email / Username --}}
            <div class="space-y-1.5">
                <label for="login" class="block text-xs font-bold text-gray-700 tracking-wide uppercase">Email atau Username</label>
                <input
                    type="text"
                    id="login"
                    name="login"
                    value="{{ old('login') }}"
                    required
                    autofocus
                    placeholder="Masukkan email atau username"
                    class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-gray-300 focus:outline-none focus:border-[#15803d] focus:ring-2 focus:ring-[#15803d]/20 transition text-gray-900 bg-white placeholder-gray-400"
                >
            </div>

            {{-- Input Password --}}
            <div class="space-y-1.5">
                <label for="password" class="block text-xs font-bold text-gray-700 tracking-wide uppercase">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    placeholder="••••••••"
                    class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-gray-300 focus:outline-none focus:border-[#15803d] focus:ring-2 focus:ring-[#15803d]/20 transition text-gray-900 bg-white placeholder-gray-400"
                >
            </div>

            {{-- Remember Me --}}
            <div class="flex items-center justify-between pt-1">
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input
                        type="checkbox"
                        name="remember"
                        class="w-4 h-4 rounded border-gray-300 text-[#15803d] focus:ring-[#15803d] focus:ring-offset-0"
                    >
                    <span class="text-sm text-gray-600">Ingat saya</span>
                </label>
            </div>

            {{-- Submit Button --}}
            <div class="pt-2">
                <button
                    type="submit"
                    class="w-full py-3 px-4 rounded-xl bg-[#15803d] hover:bg-[#14532d] text-white font-bold text-sm tracking-wide shadow-sm hover:shadow transition flex items-center justify-center gap-2"
                >
                    <span>Masuk ke Sistem</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M5 12h14M12 5l7 7-7 7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </button>
            </div>
        </form>
    </div>

    {{-- Footer Info --}}
    <div class="text-center text-xs text-gray-400">
        &copy; {{ date('Y') }} PT Duta Tunggal. Seluruh hak cipta dilindungi.
    </div>
</div>
@endsection
