@extends('layouts.app')

@section('title', 'Tutup Buku – Duta Tunggal ERP')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Keuangan</div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Tutup Buku</h1>
            <p class="text-sm text-gray-500 mt-0.5">Periode yang ditutup mengunci semua jurnal bertanggal di dalam rentangnya, untuk cabang yang dipilih.</p>
        </div>
        @can('create', \App\Models\AccountingPeriod::class)
            <a href="{{ route('keuangan.period.create') }}" class="px-4 py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-xs sm:text-sm font-semibold rounded-xl shadow-sm transition whitespace-nowrap">Buat Periode</a>
        @endcan
    </div>

    <form method="GET" class="flex items-end gap-3">
        <div>
            <label for="status" class="block text-[11px] font-bold text-gray-600 uppercase tracking-wider mb-1">Status</label>
            <select id="status" name="status" onchange="this.form.submit()" class="px-3 py-2 text-xs sm:text-sm border border-gray-200 rounded-xl focus:ring-1 focus:ring-[#15803d] outline-none">
                <option value="">Semua</option>
                @foreach ($statuses as $value => $text)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $text }}</option>
                @endforeach
            </select>
        </div>
    </form>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        @if ($periods->isEmpty())
            <div class="p-10 text-center">
                <p class="text-sm font-semibold text-gray-700">{{ $status ? 'Tidak ada periode dengan status ini.' : 'Belum ada periode akuntansi.' }}</p>
                @unless ($status)
                    <p class="text-xs text-gray-500 mt-1">Selama belum ada periode yang ditutup, jurnal dengan tanggal kapan pun masih bisa dibuat atau diubah.</p>
                @endunless
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm">
                    <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-[11px]">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-bold">Cabang</th>
                            <th scope="col" class="px-4 py-3 font-bold">Mulai</th>
                            <th scope="col" class="px-4 py-3 font-bold">Selesai</th>
                            <th scope="col" class="px-4 py-3 font-bold">Status</th>
                            <th scope="col" class="px-4 py-3 font-bold">Ditutup oleh</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($periods as $period)
                            @php $label = $period->period_start->format('d/m/Y').' s/d '.$period->period_end->format('d/m/Y'); @endphp
                            <tr class="hover:bg-gray-50/60">
                                <td class="px-4 py-3 text-gray-800">{{ $period->cabang_id ? $period->cabang->nama : 'Tanpa cabang (pusat)' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-gray-700">{{ $period->period_start->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-gray-700">{{ $period->period_end->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 whitespace-nowrap font-semibold {{ $period->status === 'closed' ? 'text-rose-600' : 'text-[#15803d]' }}">{{ $period->status === 'closed' ? 'Ditutup (terkunci)' : 'Terbuka' }}</td>
                                <td class="px-4 py-3 text-gray-700">@if ($period->closed_at){{ $period->closedBy->name ?? '-' }}, {{ $period->closed_at->format('d/m/Y H:i') }}@else - @endif</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-3">
                                        @can('close', $period)
                                            <a href="{{ route('keuangan.period.close.form', $period) }}" class="text-xs font-semibold text-rose-600 hover:underline">Tutup buku<span class="sr-only"> {{ $label }}</span></a>
                                        @endcan
                                        @can('reopen', $period)
                                            <form method="POST" action="{{ route('keuangan.period.reopen', $period) }}" class="inline" onsubmit="return confirm('Buka kembali periode {{ $label }}? Jurnal di rentang ini akan bisa dibuat dan diubah lagi. Jurnal penutup yang sudah dibuat TIDAK dibatalkan otomatis. Lakukan hanya untuk koreksi resmi.')">
                                                @csrf
                                                <button type="submit" class="text-xs font-semibold text-amber-700 hover:underline">Buka kembali<span class="sr-only"> {{ $label }}</span></button>
                                            </form>
                                        @endcan
                                        @can('delete', $period)
                                            <form method="POST" action="{{ route('keuangan.period.destroy', $period) }}" class="inline" onsubmit="return confirm('Hapus periode {{ $label }}?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-xs font-semibold text-gray-500 hover:text-rose-600 hover:underline">Hapus<span class="sr-only"> {{ $label }}</span></button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-gray-100">{{ $periods->links() }}</div>
        @endif
    </div>
</div>
@endsection
