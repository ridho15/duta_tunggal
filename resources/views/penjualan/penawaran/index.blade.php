@extends('layouts.app')

@section('title', 'Penawaran Harga (Quotation) – ERP Duta Tunggal')

@section('content')
<div class="space-y-6">
    {{-- Header & Breadcrumbs --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <nav class="flex items-center gap-2 text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">
                <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Dashboard</a>
                <span class="text-gray-300">/</span>
                <span class="text-gray-500">Penjualan</span>
                <span class="text-gray-300">/</span>
                <span class="text-emerald-700 font-bold">Penawaran Harga</span>
            </nav>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Penawaran Harga (Quotation)</h1>
            <p class="text-xs text-gray-500 mt-1">
                Kelola estimasi dan penawaran harga resmi kepada pelanggan cabang <span class="font-semibold text-gray-700">{{ $cabangNama }}</span>, pengajuan persetujuan, revisi berkala, dan konversi ke Sales Order.
            </p>
        </div>
        <div class="flex items-center gap-2.5">
            <a href="{{ route('penjualan.so.index') }}"
               class="inline-flex items-center gap-1.5 px-3.5 py-2.5 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-xs">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>Pesanan Penjualan (SO)</span>
            </a>
            @can('create', App\Models\Quotation::class)
            <a href="{{ route('penjualan.quotations.create') }}"
               class="inline-flex items-center gap-1.5 px-4 py-2.5 text-xs sm:text-sm font-semibold text-white bg-[#15803d] hover:bg-[#166534] rounded-xl transition shadow-sm active:scale-[0.98]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M12 4v16m8-8H4" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <span>Buat Penawaran Baru</span>
            </a>
            @endcan
        </div>
    </div>


    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3.5">
        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-2xs">
            <div class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Total Penawaran</div>
            <div class="text-2xl font-black text-gray-900 mt-1">{{ number_format($kpi['total']) }}</div>
            <div class="text-[11px] text-gray-400 mt-0.5">Semua riwayat QO</div>
        </div>
        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-2xs">
            <div class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Draft Disiapkan</div>
            <div class="text-2xl font-black text-slate-700 mt-1">{{ number_format($kpi['draft']) }}</div>
            <div class="text-[11px] text-slate-500 mt-0.5">Belum diajukan</div>
        </div>
        <div class="p-4 border border-amber-200 rounded-xl shadow-2xs bg-amber-50/20">
            <div class="text-[11px] font-bold text-amber-700 uppercase tracking-wider">Menunggu Approval</div>
            <div class="text-2xl font-black text-amber-700 mt-1">{{ number_format($kpi['menunggu']) }}</div>
            <div class="text-[11px] text-amber-600 mt-0.5">Perlu review atasan</div>
        </div>
        <div class="p-4 border border-emerald-200 rounded-xl shadow-2xs bg-emerald-50/20">
            <div class="text-[11px] font-bold text-emerald-800 uppercase tracking-wider">Disetujui</div>
            <div class="text-2xl font-black text-emerald-700 mt-1">{{ number_format($kpi['disetujui']) }}</div>
            <div class="text-[11px] text-emerald-600 mt-0.5 font-medium">Rp {{ number_format($kpi['total_nilai_disetujui'], 0, ',', '.') }}</div>
        </div>
        <div class="p-4 border border-red-200 rounded-xl shadow-2xs bg-red-50/20 col-span-2 md:col-span-1">
            <div class="text-[11px] font-bold text-red-700 uppercase tracking-wider">Kedaluwarsa</div>
            <div class="text-2xl font-black text-red-600 mt-1">{{ number_format($kpi['kedaluwarsa']) }}</div>
            <div class="text-[11px] text-red-500 mt-0.5">Perlu diterbitkan revisi</div>
        </div>
    </div>

    {{-- Tab Filter Cepat --}}
    <div role="tablist" class="flex flex-wrap gap-2 overflow-x-auto pb-1">
        @php
            $tabs = [
                'semua' => ['label' => 'Semua', 'count' => $counts['semua']],
                'perlu_tindakan' => ['label' => 'Perlu Tindakan', 'count' => $counts['perlu_tindakan']],
                'draft' => ['label' => 'Draft', 'count' => $counts['draft']],
                'menunggu_approve' => ['label' => 'Menunggu Approve', 'count' => $counts['menunggu_approve']],
                'disetujui' => ['label' => 'Disetujui', 'count' => $counts['disetujui']],
                'kedaluwarsa' => ['label' => 'Kedaluwarsa', 'count' => $counts['kedaluwarsa']],
            ];
        @endphp

        @foreach($tabs as $tabKey => $tab)
            @php $isSelected = ($activeTab === $tabKey); @endphp
            <a href="{{ route('penjualan.quotations.index', array_merge(request()->query(), ['tab' => $tabKey, 'page' => 1])) }}"
               class="text-xs rounded-full px-3.5 py-1.5 whitespace-nowrap transition-all font-semibold flex items-center gap-1.5 {{ $isSelected ? 'bg-emerald-700 text-white border border-emerald-700 font-bold shadow-xs' : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-50' }}">
                <span>{{ $tab['label'] }}</span>
                @if($tab['count'] > 0)
                    <span class="rounded-full px-1.5 py-0.2 text-[10px] font-bold {{ $isSelected ? 'bg-white text-emerald-800' : 'bg-gray-100 text-gray-600' }}">
                        {{ $tab['count'] }}
                    </span>
                @endif
            </a>
        @endforeach
    </div>

    {{-- Table & Filter Controls Container --}}
    <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-sm">
        {{-- Filter Bar --}}
        <form method="GET" action="{{ route('penjualan.quotations.index') }}" class="p-3.5 border-b border-gray-100 bg-gray-50/50 flex flex-wrap gap-2.5 items-center">
            <input type="hidden" name="tab" value="{{ $activeTab }}">

            <div class="flex-1 min-w-[240px]">
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="search" name="search" value="{{ $search }}"
                           placeholder="Cari nomor penawaran, customer, perusahaan..."
                           class="w-full pl-9 pr-3 py-1.5 text-xs bg-white border border-gray-300 rounded-lg text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-emerald-600 focus:border-emerald-600">
                </div>
            </div>

            <div class="min-w-[180px]">
                <x-searchable-select
                    name="customer_id"
                    :options="collect($customers)->map(fn ($c) => ['id' => $c->id, 'label' => $c->name . ($c->perusahaan ? ' (' . $c->perusahaan . ')' : '')])->values()"
                    :value="$customerId"
                    placeholder="Semua Pelanggan"
                    :auto-submit="true"
                    size="xs"
                />
            </div>

            <div class="min-w-[130px]">
                <input type="month" name="date" value="{{ $dateFilter }}" onchange="this.form.submit()"
                       class="w-full py-1.5 px-2.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-emerald-600"
                       title="Filter Bulan">
            </div>

            @if($canAccessAll && $cabangs->isNotEmpty())
            <div class="min-w-[150px]">
                <x-searchable-select name="cabang_id" :options="collect($cabangs)->map(fn ($cb) => ['id' => $cb->id, 'label' => $cb->nama])->values()" :value="$cabangFilter" placeholder="Semua Cabang" :auto-submit="true" size="xs" />
            </div>
            @endif

            @if($search || $customerId || $dateFilter || $cabangFilter)
                <a href="{{ route('penjualan.quotations.index', ['tab' => $activeTab]) }}"
                   class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-gray-500 hover:text-gray-700 bg-white border border-gray-200 rounded-lg">
                    Reset
                </a>
            @endif
        </form>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-gray-50/80 text-gray-500 font-semibold border-b border-gray-100 uppercase tracking-wider text-[11px]">
                        <th class="py-3 px-4">No. Penawaran</th>
                        <th class="py-3 px-3">Pelanggan</th>
                        <th class="py-3 px-3">Tanggal & Berlaku</th>
                        <th class="py-3 px-3 text-right">Nilai Total</th>
                        <th class="py-3 px-3">Cabang / Pembuat</th>
                        <th class="py-3 px-3 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($quotations as $qo)
                        @php
                            $isExpired = $qo->isExpired();
                            $displayStatus = ($qo->status === 'approve' && $isExpired) ? 'Kedaluwarsa' : $qo->statusLabel();
                            $statusConfig = match($qo->status) {
                                'draft' => ['bg' => 'bg-gray-50', 'text' => 'text-gray-700', 'border' => 'border-gray-200', 'dot' => 'bg-gray-400'],
                                'request_approve' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'border' => 'border-amber-200', 'dot' => 'bg-amber-500 animate-pulse'],
                                'approve' => $isExpired
                                    ? ['bg' => 'bg-rose-50', 'text' => 'text-rose-700', 'border' => 'border-rose-200', 'dot' => 'bg-rose-500']
                                    : ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'border' => 'border-emerald-200', 'dot' => 'bg-emerald-500'],
                                'reject' => ['bg' => 'bg-rose-50', 'text' => 'text-rose-700', 'border' => 'border-rose-200', 'dot' => 'bg-rose-500'],
                                'expired' => ['bg' => 'bg-rose-50', 'text' => 'text-rose-700', 'border' => 'border-rose-200', 'dot' => 'bg-rose-500'],
                                default => ['bg' => 'bg-gray-50', 'text' => 'text-gray-700', 'border' => 'border-gray-200', 'dot' => 'bg-gray-400'],
                            };
                        @endphp
                        <tr class="hover:bg-gray-50/80 transition-colors group">
                            {{-- No Penawaran & Revisi --}}
                            <td class="py-3 px-4 font-semibold text-gray-900">
                                <a href="{{ route('penjualan.quotations.show', $qo) }}" class="text-emerald-700 hover:text-emerald-900 hover:underline">
                                    {{ $qo->quotation_number }}
                                </a>
                                @if($qo->revision_no > 0)
                                    <span class="ml-1.5 px-1.5 py-0.2 bg-blue-100 text-blue-700 text-[10px] font-bold rounded-sm border border-blue-200">
                                        Rev {{ $qo->revision_no }}
                                    </span>
                                @endif
                                @if($qo->superseded_at)
                                    <div class="text-[10px] text-gray-400 font-normal italic">Telah digantikan revisi baru</div>
                                @endif
                            </td>

                            {{-- Pelanggan --}}
                            <td class="py-3 px-3">
                                <div class="font-bold text-gray-800">{{ $qo->customer->name ?? '-' }}</div>
                                @if($qo->customer->perusahaan)
                                    <div class="text-[11px] text-gray-500">{{ $qo->customer->perusahaan }}</div>
                                @endif
                            </td>

                            {{-- Tanggal & Valid Until --}}
                            <td class="py-3 px-3 text-gray-600">
                                <div class="font-medium text-gray-800">{{ \Carbon\Carbon::parse($qo->date)->format('d/m/Y') }}</div>
                                @if($qo->valid_until)
                                    <div class="text-[11px] {{ $isExpired ? 'text-red-600 font-bold' : 'text-gray-400' }}">
                                        s/d {{ \Carbon\Carbon::parse($qo->valid_until)->format('d/m/Y') }}
                                        @if($isExpired) <span class="text-[10px] uppercase">(Expired)</span> @endif
                                    </div>
                                @else
                                    <div class="text-[11px] text-gray-400">-</div>
                                @endif
                            </td>

                            {{-- Nilai Total --}}
                            <td class="py-3 px-3 text-right">
                                <div class="font-black text-gray-900">
                                    Rp {{ number_format($qo->total_amount, 0, ',', '.') }}
                                </div>
                                @if($qo->currency && $qo->currency->code !== 'IDR')
                                    <div class="text-[10px] text-gray-500 font-medium">
                                        {{ $qo->currency->code }} {{ number_format($qo->total_amount / max(1, $qo->exchange_rate), 2) }}
                                    </div>
                                @endif
                            </td>

                            {{-- Cabang & Pembuat --}}
                            <td class="py-3 px-3 text-gray-600">
                                <div class="font-semibold text-gray-700">{{ $qo->cabang->nama ?? '-' }}</div>
                                <div class="text-[11px] text-gray-400">Oleh: {{ $qo->createdBy->name ?? 'Sales' }}</div>
                            </td>

                            {{-- Status Badge --}}
                            <td class="py-3 px-3 text-center">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold border {{ $statusConfig['bg'] }} {{ $statusConfig['text'] }} {{ $statusConfig['border'] }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $statusConfig['dot'] }}"></span>
                                    {{ $displayStatus }}
                                </span>
                            </td>

                            {{-- Aksi --}}
                            <td class="py-3 px-4 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <a href="{{ route('penjualan.quotations.show', $qo) }}"
                                       class="px-2 py-1 text-[11px] font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50"
                                       title="Lihat Detail">
                                        Detail
                                    </a>
                                    <a href="{{ route('penjualan.quotations.pdf', $qo) }}" target="_blank"
                                       class="px-2 py-1 text-[11px] font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 rounded hover:bg-emerald-100"
                                       title="Cetak PDF">
                                        PDF
                                    </a>
                                    @if($qo->isEditable())
                                        @can('update', $qo)
                                        <a href="{{ route('penjualan.quotations.edit', $qo) }}"
                                           class="px-2 py-1 text-[11px] font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded hover:bg-blue-100"
                                           title="Edit Draft">
                                            Edit
                                        </a>
                                        @endcan
                                    @endif
                                    @if(is_null($qo->unusableReasonForSaleOrder()))
                                        @can('create', App\Models\SaleOrder::class)
                                        <a href="{{ route('penjualan.quotations.convertToSaleOrder', $qo) }}"
                                           class="px-2 py-1 text-[11px] font-bold text-white bg-emerald-600 rounded hover:bg-emerald-700"
                                           title="Buat Sales Order dari Penawaran Ini">
                                            Buat SO
                                        </a>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-gray-400">
                                <svg class="w-10 h-10 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <div class="text-xs font-semibold text-gray-500">Tidak ada data penawaran harga ditemukan</div>
                                <div class="text-[11px] text-gray-400 mt-1">Gunakan kata kunci lain atau buat penawaran baru.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($quotations->hasPages())
            <div class="px-4 py-3 border-t border-gray-100 bg-gray-50/50">
                {{ $quotations->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
