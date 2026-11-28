@extends('layouts.app')

@section('title', 'Pusat Laporan · Duta Tunggal ERP')

@section('content')
<div class="space-y-6 max-w-5xl mx-auto">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Pusat Laporan</h1>
        <p class="mt-1 text-sm text-gray-700">Semua laporan yang boleh Anda buka, dikelompokkan per bagian. Pilih laporan sesuai pertanyaan yang ingin dijawab.</p>
    </div>

    @forelse ($groups as $group => $reports)
        <section aria-labelledby="grp-{{ \Illuminate\Support\Str::slug($group) }}">
            <h2 id="grp-{{ \Illuminate\Support\Str::slug($group) }}" class="text-xs font-bold text-gray-600 mb-2">{{ $group }}</h2>
            <ul class="bg-white rounded-2xl border border-gray-200 divide-y divide-gray-100 overflow-hidden">
                @foreach ($reports as [$reportRoute, $reportTitle, $reportQuestion])
                    <li>
                        <a href="{{ route($reportRoute) }}" class="group flex items-center gap-4 px-4 py-3.5 hover:bg-[#f0fdf4] focus:outline-none focus-visible:bg-[#f0fdf4] focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#15803d]/40">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-gray-900 group-hover:text-[#14532d]">{{ $reportTitle }}</p>
                                <p class="text-xs text-gray-600 mt-0.5">{{ $reportQuestion }}</p>
                            </div>
                            <span class="shrink-0 text-xs font-bold text-[#15803d]">Buka</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <div class="bg-white rounded-2xl border border-dashed border-gray-300 p-10 text-center text-sm text-gray-700">
            Belum ada laporan yang dicentang untuk peran Anda. Minta Owner / Super Admin mencentang laporan yang dibutuhkan di Pengaturan → Peran &amp; Hak Akses.
        </div>
    @endforelse
</div>
@endsection
