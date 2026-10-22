@extends('layouts.app')

@section('title', 'Detail SPK: ' . $manufacturingOrder->mo_number . ' – Duta Tunggal ERP')

@section('content')
<div class="space-y-6 max-w-6xl mx-auto pb-12">
    {{-- Header & Tombol Aksi --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('manufaktur.spk.index') }}" class="hover:text-gray-700">Perintah Kerja (SPK)</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">{{ $manufacturingOrder->mo_number }}</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ $manufacturingOrder->mo_number }}</h1>
                @if ($manufacturingOrder->status === 'draft')
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                        Draft (Menunggu Start)
                    </span>
                @elseif ($manufacturingOrder->status === 'in_progress')
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse"></span>
                        Sedang Dikerjakan (In Progress)
                    </span>
                @elseif ($manufacturingOrder->status === 'completed')
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        Selesai Produksi
                    </span>
                @elseif ($manufacturingOrder->status === 'cancelled')
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200">
                        Dibatalkan
                    </span>
                @endif
            </div>
            <p class="text-sm text-gray-500 mt-0.5 font-mono">Diterbitkan: {{ $manufacturingOrder->created_at->format('d/m/Y H:i') }} • Cabang: {{ $manufacturingOrder->cabang?->nama ?? '-' }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a
                href="{{ route('manufaktur.spk.index') }}"
                class="px-3.5 py-2 border border-gray-300 text-gray-700 hover:bg-gray-50 text-xs font-semibold rounded-xl transition"
            >
                Kembali
            </a>

            <a
                href="{{ route('manufaktur.spk.pdf', $manufacturingOrder) }}"
                target="_blank"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 border border-gray-300 text-gray-700 hover:bg-gray-50 text-xs font-semibold rounded-xl transition"
            >
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Cetak Lembar SPK (PDF)
            </a>

            @if ($manufacturingOrder->status === 'draft')
                <a
                    href="{{ route('manufaktur.spk.edit', $manufacturingOrder) }}"
                    class="px-3.5 py-2 bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold rounded-xl shadow-sm transition"
                >
                    Ubah SPK
                </a>

                <form method="POST" action="{{ route('manufaktur.spk.start', $manufacturingOrder) }}" class="inline">
                    @csrf
                    <button
                        type="submit"
                        onclick="return confirm('Mulai proses produksi sekarang?');"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#15803d] hover:bg-[#166534] text-white text-xs font-bold rounded-xl shadow-sm transition active:scale-[0.98]"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Mulai Produksi
                    </button>
                </form>

                <form method="POST" action="{{ route('manufaktur.spk.cancel', $manufacturingOrder) }}" class="inline">
                    @csrf
                    <button
                        type="submit"
                        onclick="return confirm('Batalkan SPK ini?');"
                        class="px-3 py-2 text-rose-600 hover:bg-rose-50 text-xs font-semibold rounded-xl transition"
                    >
                        Batalkan SPK
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- Flash Message --}}
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

    {{-- Stepper Alur Kerja Manufaktur --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
            {{-- Tahap 1: SPK Diterbitkan --}}
            <div class="space-y-1.5">
                <div class="h-1.5 rounded-full {{ in_array($manufacturingOrder->status, ['draft', 'in_progress', 'completed']) ? 'bg-emerald-600' : 'bg-gray-200' }}"></div>
                <div class="text-xs font-bold text-gray-900">1. Terbitkan SPK</div>
                <div class="text-[10.5px] text-gray-400">Instruksi target output</div>
            </div>

            {{-- Tahap 2: Sedang Dikerjakan --}}
            <div class="space-y-1.5">
                <div class="h-1.5 rounded-full {{ in_array($manufacturingOrder->status, ['in_progress', 'completed']) ? 'bg-emerald-600' : 'bg-gray-200' }}"></div>
                <div class="text-xs font-bold {{ $manufacturingOrder->status === 'in_progress' ? 'text-blue-600' : 'text-gray-900' }}">
                    2. In Progress
                </div>
                <div class="text-[10.5px] text-gray-400">Lantai produksi aktif</div>
            </div>

            {{-- Tahap 3: Pengeluaran Bahan (Material Issue) --}}
            <div class="space-y-1.5">
                <div class="h-1.5 rounded-full {{ $manufacturingOrder->status === 'completed' ? 'bg-emerald-600' : 'bg-gray-200' }}"></div>
                <div class="text-xs font-bold text-gray-900">3. Material Issue</div>
                <div class="text-[10.5px] text-gray-400">Jurnal BDP diposting</div>
            </div>

            {{-- Tahap 4: Hasil Produksi & Selesai --}}
            <div class="space-y-1.5">
                <div class="h-1.5 rounded-full {{ $manufacturingOrder->status === 'completed' ? 'bg-emerald-600' : 'bg-gray-200' }}"></div>
                <div class="text-xs font-bold text-gray-900">4. Selesai & Masuk Stok</div>
                <div class="text-[10.5px] text-gray-400">Pengakuan barang jadi</div>
            </div>
        </div>
    </div>

    {{-- Grid Rincian SPK & Kesiapan Material --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Kolom Kiri: Spesifikasi & Komponen Material --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Kartu 1: Identitas Produksi --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm space-y-4">
                <h2 class="text-xs font-extrabold uppercase tracking-wider text-gray-500 border-b border-gray-100 pb-2">
                    Parameter Instruksi Kerja
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div>
                        <span class="text-gray-400 block text-[11px]">Produk Jadi Target:</span>
                        <span class="font-bold text-gray-900 text-sm block mt-0.5">
                            {{ $plan?->product?->name ?? 'Produk Dihapus' }}
                        </span>
                        <span class="text-gray-500 font-mono text-[11px]">SKU: {{ $plan?->product?->sku ?? '-' }}</span>
                    </div>

                    <div>
                        <span class="text-gray-400 block text-[11px]">Kuantitas Perintah Kerja:</span>
                        <span class="font-extrabold text-[#15803d] text-base block mt-0.5">
                            {{ rtrim(rtrim((string)$manufacturingOrder->quantity, '0'), '.') }} {{ $plan?->uom?->name ?? 'Pcs' }}
                        </span>
                        <span class="text-gray-400 text-[11px]">Target selesai dirakit</span>
                    </div>

                    <div>
                        <span class="text-gray-400 block text-[11px]">Resep Formula Acuan:</span>
                        @if ($bom)
                            <a href="{{ route('manufaktur.bom.show', $bom) }}" class="font-bold text-[#15803d] hover:underline block mt-0.5">
                                {{ $bom->code }} — {{ $bom->nama_bom }}
                            </a>
                        @else
                            <span class="text-gray-500 block mt-0.5">-</span>
                        @endif
                    </div>

                    <div>
                        <span class="text-gray-400 block text-[11px]">Gudang Pengambilan Bahan:</span>
                        <span class="font-semibold text-gray-900 block mt-0.5">
                            {{ $warehouse?->name ?? 'Gudang Utama' }}
                        </span>
                    </div>

                    <div>
                        <span class="text-gray-400 block text-[11px]">Jadwal Mulai Kerja:</span>
                        <span class="font-semibold text-gray-800 block mt-0.5">
                            {{ $manufacturingOrder->start_date ? $manufacturingOrder->start_date->format('d/m/Y H:i') : '-' }}
                        </span>
                    </div>

                    <div>
                        <span class="text-gray-400 block text-[11px]">Estimasi Selesai:</span>
                        <span class="font-semibold text-gray-800 block mt-0.5">
                            {{ $manufacturingOrder->end_date ? $manufacturingOrder->end_date->format('d/m/Y H:i') : '-' }}
                        </span>
                    </div>

                    @if ($plan?->notes)
                        <div class="sm:col-span-2 p-3 bg-gray-50 rounded-xl border border-gray-100">
                            <span class="text-gray-400 block text-[10.5px] uppercase font-bold tracking-wider mb-0.5">Catatan Instruksi:</span>
                            <p class="text-gray-700 text-xs">{{ $plan->notes }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Kartu 2: Tabel Kesiapan Bahan Baku --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <div>
                        <h2 class="text-xs font-extrabold uppercase tracking-wider text-gray-500">
                            Kebutuhan Bahan Baku & Cek Kesiapan Gudang
                        </h2>
                        <p class="text-xs text-gray-400 mt-0.5">Pemeriksaan stok bebas gudang saat ini</p>
                    </div>

                    @if ($allAvailable)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            Bahan Baku Siap Lengkap
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                            Bahan Baku Belum Cukup
                        </span>
                    @endif
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 border-b border-gray-200 text-[11px] font-bold text-gray-500 uppercase tracking-wider">
                            <tr>
                                <th class="py-2.5 px-3">Bahan Baku</th>
                                <th class="py-2.5 px-3 text-center">Dibutuhkan</th>
                                <th class="py-2.5 px-3 text-center">Stok Bebas Gudang</th>
                                <th class="py-2.5 px-3 text-center">Kesiapan</th>
                                <th class="py-2.5 px-3">Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 font-medium">
                            @forelse ($materialChecks as $check)
                                <tr class="hover:bg-gray-50/70 transition">
                                    <td class="py-2.5 px-3">
                                        <div class="font-bold text-gray-900">{{ $check['product']?->name ?? 'Material Dihapus' }}</div>
                                        <div class="text-[11px] text-gray-400 font-mono">SKU: {{ $check['product']?->sku ?? '-' }}</div>
                                    </td>
                                    <td class="py-2.5 px-3 text-center font-bold text-gray-900">
                                        {{ rtrim(rtrim((string)$check['required'], '0'), '.') }}
                                        <span class="text-gray-500 font-normal text-[11px]">{{ $check['uom_name'] }}</span>
                                    </td>
                                    <td class="py-2.5 px-3 text-center font-mono {{ $check['is_sufficient'] ? 'text-emerald-700' : 'text-rose-600 font-bold' }}">
                                        {{ rtrim(rtrim((string)$check['available'], '0'), '.') }}
                                        <span class="text-gray-400 text-[11px]">{{ $check['uom_name'] }}</span>
                                    </td>
                                    <td class="py-2.5 px-3 text-center">
                                        @if ($check['is_sufficient'])
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                Cukup
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                                Kurang
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 text-gray-500 text-[11px]">
                                        {{ $check['notes'] ?: '-' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-6 text-center text-gray-400">
                                        Tidak ada rincian material untuk SPK ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Kolom Kanan: Panduan Tindakan & Kotak Langkah Berikutnya --}}
        <div class="space-y-6">
            {{-- Kotak Langkah Berikutnya --}}
            <div class="bg-gradient-to-br from-[#f0fdf4] to-white rounded-2xl border border-[#bbf7d0] p-5 shadow-sm space-y-4">
                <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-wider text-[#14532d]">
                    <svg class="w-4 h-4 text-[#15803d]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                    Langkah Berikutnya
                </div>

                @if ($manufacturingOrder->status === 'draft')
                    <p class="text-xs text-gray-600 leading-relaxed">
                        SPK saat ini berstatus <strong>Draft</strong>. Silakan cetak lembar kerja untuk mandor pabrik, pastikan stok bahan baku di gudang sudah siap, lalu klik tombol di bawah untuk memulai pengerjaan.
                    </p>
                    <div class="pt-2">
                        <form method="POST" action="{{ route('manufaktur.spk.start', $manufacturingOrder) }}">
                            @csrf
                            <button
                                type="submit"
                                onclick="return confirm('Mulai produksi untuk SPK {{ $manufacturingOrder->mo_number }}?');"
                                class="w-full py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-xs font-bold rounded-xl shadow-sm transition active:scale-[0.98] text-center"
                            >
                                Mulai Produksi (In Progress)
                            </button>
                        </form>
                    </div>
                @elseif ($manufacturingOrder->status === 'in_progress')
                    <p class="text-xs text-gray-600 leading-relaxed">
                        Produksi sedang berjalan. Lakukan <strong>Pengeluaran Bahan Baku (Material Issue)</strong> dari gudang ke pos sementara produksi untuk membukukan mutasi stok dan jurnal BDP.
                    </p>
                    <div class="pt-2 space-y-2">
                        <a
                            href="{{ route('manufaktur.spk.pdf', $manufacturingOrder) }}"
                            target="_blank"
                            class="w-full inline-flex items-center justify-center gap-1.5 py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-xs font-bold rounded-xl shadow-sm transition active:scale-[0.98] text-center"
                        >
                            Cetak Surat Perintah Kerja
                        </a>
                    </div>
                @elseif ($manufacturingOrder->status === 'completed')
                    <div class="p-3 bg-emerald-50 rounded-xl border border-emerald-100 text-xs text-emerald-800 font-semibold">
                        ✅ Proses produksi SPK ini telah selesai sepenuhnya dan barang jadi telah diakui ke stok gudang.
                    </div>
                @endif
            </div>

            {{-- Kartu Riwayat Produksi Aktual --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm space-y-3">
                <h3 class="text-xs font-extrabold uppercase tracking-wider text-gray-500 border-b border-gray-100 pb-2">
                    Catatan Hasil Jadi Aktual
                </h3>

                @if ($manufacturingOrder->productions->count() > 0)
                    <div class="space-y-2">
                        @foreach ($manufacturingOrder->productions as $prod)
                            <div class="p-2.5 bg-gray-50 rounded-xl border border-gray-100 text-xs space-y-1">
                                <div class="font-bold text-gray-900">{{ $prod->production_number ?? ('Produksi #' . $prod->id) }}</div>
                                <div class="text-[11px] text-gray-500">Output: {{ rtrim(rtrim((string)$prod->quantity, '0'), '.') }} Pcs</div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-4 text-center text-gray-400 text-xs">
                        Belum ada pencatatan hasil produksi aktual. (Akan diproses di Fase 3).
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
