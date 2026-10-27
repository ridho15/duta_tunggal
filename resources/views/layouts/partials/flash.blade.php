{{-- Flash Notifications Global Terpusat (Bebas Duplikasi) --}}
@if (session('success'))
    <div
        x-data="{ show: true }"
        x-show="show"
        x-init="setTimeout(() => show = false, 6000)"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
        class="mb-4 p-4 rounded-xl bg-[#f0fdf4] border border-[#86efac] text-[#14532d] text-sm flex items-start gap-3 shadow-sm relative"
        role="alert"
    >
        <div class="w-6 h-6 rounded-full bg-[#dcfce7] flex items-center justify-center flex-shrink-0 mt-0.5">
            <svg class="w-4 h-4 text-[#15803d]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path d="M5 13l4 4L19 7" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
        </div>
        <div class="flex-1 font-semibold pr-6">
            {{ session('success') }}
        </div>
        <button
            type="button"
            @click="show = false"
            class="absolute top-3.5 right-3 text-[#15803d]/60 hover:text-[#15803d] p-1 rounded-lg hover:bg-[#dcfce7]/60 transition"
            aria-label="Tutup notifikasi"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>
@endif

@if (session('error'))
    <div
        x-data="{ show: true }"
        x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
        class="mb-4 p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm flex items-start gap-3 shadow-sm relative"
        role="alert"
    >
        <div class="w-6 h-6 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0 mt-0.5">
            <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="9" stroke-width="2"></circle>
                <path d="M12 8v4M12 16h.01" stroke-width="2.5" stroke-linecap="round"></path>
            </svg>
        </div>
        <div class="flex-1 font-semibold pr-6">
            {{ session('error') }}
        </div>
        <button
            type="button"
            @click="show = false"
            class="absolute top-3.5 right-3 text-red-600/60 hover:text-red-800 p-1 rounded-lg hover:bg-red-100/60 transition"
            aria-label="Tutup notifikasi"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>
@endif

@if (session('warning'))
    <div
        x-data="{ show: true }"
        x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
        class="mb-4 p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-sm flex items-start gap-3 shadow-sm relative"
        role="alert"
    >
        <div class="w-6 h-6 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0 mt-0.5">
            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
        </div>
        <div class="flex-1 font-semibold pr-6">
            {{ session('warning') }}
        </div>
        <button
            type="button"
            @click="show = false"
            class="absolute top-3.5 right-3 text-amber-700/60 hover:text-amber-900 p-1 rounded-lg hover:bg-amber-100/60 transition"
            aria-label="Tutup notifikasi"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>
@endif

@if (session('info'))
    <div
        x-data="{ show: true }"
        x-show="show"
        x-init="setTimeout(() => show = false, 6000)"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
        class="mb-4 p-4 rounded-xl bg-blue-50 border border-blue-200 text-blue-900 text-sm flex items-start gap-3 shadow-sm relative"
        role="alert"
    >
        <div class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0 mt-0.5">
            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="9" stroke-width="2"></circle>
                <path d="M12 16v-4m0-4h.01" stroke-width="2.5" stroke-linecap="round"></path>
            </svg>
        </div>
        <div class="flex-1 font-semibold pr-6">
            {{ session('info') }}
        </div>
        <button
            type="button"
            @click="show = false"
            class="absolute top-3.5 right-3 text-blue-700/60 hover:text-blue-900 p-1 rounded-lg hover:bg-blue-100/60 transition"
            aria-label="Tutup notifikasi"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>
@endif

@if ($errors->any())
    <div
        x-data="{ show: true }"
        x-show="show"
        class="mb-4 p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm flex items-start gap-3 shadow-sm relative"
        role="alert"
    >
        <div class="w-6 h-6 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0 mt-0.5">
            <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="9" stroke-width="2"></circle>
                <path d="M12 8v4M12 16h.01" stroke-width="2.5" stroke-linecap="round"></path>
            </svg>
        </div>
        <div class="flex-1 font-semibold pr-6">
            <div class="font-bold mb-1">Perhatian / Validasi Ditolak:</div>
            <ul class="list-disc pl-4 text-xs font-medium space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        <button
            type="button"
            @click="show = false"
            class="absolute top-3.5 right-3 text-red-600/60 hover:text-red-800 p-1 rounded-lg hover:bg-red-100/60 transition"
            aria-label="Tutup notifikasi"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>
@endif
