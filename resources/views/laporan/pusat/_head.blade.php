{{-- Judul laporan + pertanyaan yang dijawab + tombol cetak. Variabel: $title, $question, $pdfRoute, $excelRoute --}}
<div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-3">
    <div>
        <nav class="flex items-center gap-2 text-xs font-semibold text-gray-600" aria-label="Breadcrumb">
            <a href="{{ route('laporan.pusat.index') }}" class="hover:text-gray-900 hover:underline underline-offset-2">Pusat Laporan</a>
            <span aria-hidden="true">/</span>
            <span class="text-[#15803d] font-bold">{{ $title }}</span>
        </nav>
        <h1 class="mt-1 text-2xl font-bold text-gray-900 tracking-tight">{{ $title }}</h1>
        <p class="mt-1 text-sm text-gray-700 max-w-3xl">{{ $question }}</p>
    </div>
    <div class="flex flex-wrap gap-2">
        @accessRoute($pdfRoute)
            <a href="{{ route($pdfRoute, request()->query()) }}" target="_blank"
               class="min-h-10 inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border border-gray-300 bg-white text-sm font-bold text-gray-800 hover:bg-gray-50 active:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" stroke-width="1.8"/><rect x="6" y="14" width="12" height="8" rx="1" stroke-width="1.8"/></svg>
                Cetak PDF
            </a>
        @endaccessRoute
        @accessRoute($excelRoute)
            <a href="{{ route($excelRoute, request()->query()) }}"
               class="min-h-10 inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-[#15803d] text-sm font-bold text-white hover:bg-[#166534] active:bg-[#14532d] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#15803d]/40">
                Unduh Excel
            </a>
        @endaccessRoute
    </div>
</div>
