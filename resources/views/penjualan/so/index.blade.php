@extends('layouts.app')

@section('title', 'Pesanan Penjualan – ERP Duta Tunggal')

@section('content')
<div class="flex flex-col gap-5 max-w-[1180px] mx-auto w-full">
    <!-- Header Section -->
    <div class="flex flex-wrap justify-between items-end gap-3.5 pb-5 border-b border-gray-200">
        <div>
            <h1 class="text-2xl lg:text-[28px] font-extrabold text-gray-900 m-0">Pesanan Penjualan</h1>
            <div class="text-sm text-gray-500 mt-1">
                Semua SO cabang <span class="font-semibold text-gray-700">{{ $cabangNama }}</span>. Klik baris untuk melihat detail dan langkah berikutnya.
            </div>
        </div>
        <a href="{{ route('penjualan.so.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-sm px-4 py-2.5 rounded-xl min-h-[44px] inline-flex items-center gap-2 shadow-sm transition-colors">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            Buat SO
        </a>
    </div>

    <!-- Tab Filter Cepat -->
    <div role="tablist" aria-label="Filter cepat" class="flex flex-wrap gap-2 overflow-x-auto pb-1">
        @php
            $tabs = [
                'perlu_tindakan' => ['label' => 'Perlu tindakan', 'count' => $counts['perlu_tindakan'] ?? 0],
                'draft' => ['label' => 'Draft', 'count' => $counts['draft'] ?? 0],
                'menunggu_approve' => ['label' => 'Menunggu approve', 'count' => $counts['menunggu_approve'] ?? 0],
                'dalam_proses' => ['label' => 'Dalam proses', 'count' => $counts['dalam_proses'] ?? 0],
                'selesai' => ['label' => 'Selesai', 'count' => $counts['selesai'] ?? 0],
                'semua' => ['label' => 'Semua', 'count' => $counts['semua'] ?? 0],
            ];
        @endphp

        @foreach($tabs as $tabKey => $tab)
            @php $isSelected = ($activeTab === $tabKey); @endphp
            <a href="{{ route('penjualan.so.index', array_merge(request()->query(), ['tab' => $tabKey, 'page' => 1])) }}"
               class="text-xs sm:text-[13px] rounded-full px-3.5 py-2 whitespace-nowrap transition-all font-semibold flex items-center gap-1.5 {{ $isSelected ? 'bg-emerald-700 text-white border border-emerald-700 font-bold shadow-sm' : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-50' }}">
                <span>{{ $tab['label'] }}</span>
                @if($tab['count'] > 0)
                    <span class="rounded-full px-1.5 py-0.2 text-[11px] font-bold {{ $isSelected ? 'bg-white text-emerald-800' : 'bg-gray-100 text-gray-600' }}">
                        {{ $tab['count'] }}
                    </span>
                @endif
            </a>
        @endforeach
    </div>

    <!-- Card Wrapper Tabel & Filter Bar -->
    <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
        <!-- Filter Controls -->
        <form method="GET" action="{{ route('penjualan.so.index') }}" class="flex flex-wrap gap-2.5 items-center p-3.5 sm:p-4 border-b border-gray-100 bg-gray-50/50">
            <input type="hidden" name="tab" value="{{ $activeTab }}">

            <label class="flex-1 min-w-[240px] flex items-center gap-2 border border-gray-300 rounded-xl px-3 py-2 bg-white focus-within:border-emerald-600 focus-within:ring-1 focus-within:ring-emerald-600 transition-all">
                <svg class="w-4 h-4 text-gray-400 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input type="search" name="search" value="{{ $search }}" placeholder="Cari nomor SO, customer, atau produk" class="border-none outline-none text-sm w-full bg-transparent text-gray-800 placeholder-gray-400">
            </label>

            <!-- Filter Bulan / Tanggal -->
            <select name="date" onchange="this.form.submit()" class="text-xs sm:text-[13px] font-semibold border border-gray-200 rounded-xl px-3 py-2 bg-white text-gray-700 focus:outline-none focus:border-emerald-600 cursor-pointer">
                <option value="">Semua Periode</option>
                <option value="{{ now()->format('Y-m') }}" {{ request('date') === now()->format('Y-m') ? 'selected' : '' }}>Bulan Ini ({{ now()->translatedFormat('M Y') }})</option>
                <option value="{{ now()->subMonth()->format('Y-m') }}" {{ request('date') === now()->subMonth()->format('Y-m') ? 'selected' : '' }}>Bulan Lalu ({{ now()->subMonth()->translatedFormat('M Y') }})</option>
            </select>

            <!-- Filter Sales -->
            <select name="sales" onchange="this.form.submit()" class="text-xs sm:text-[13px] font-semibold border border-gray-200 rounded-xl px-3 py-2 bg-white text-gray-700 focus:outline-none focus:border-emerald-600 cursor-pointer">
                <option value="semua">Sales: Semua</option>
                @foreach($salesUsers as $sales)
                    <option value="{{ $sales->id }}" {{ request('sales') == $sales->id ? 'selected' : '' }}>{{ $sales->name }}</option>
                @endforeach
            </select>

            <button type="submit" class="hidden">Cari</button>
        </form>

        <!-- Desktop Table View -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full border-collapse text-sm min-w-[760px]">
                <thead>
                    <tr class="text-left text-gray-500 text-xs bg-gray-50/80 border-b border-gray-100">
                        <th class="py-2.5 px-4 font-semibold">No. SO</th>
                        <th class="py-2.5 px-3 font-semibold">Customer</th>
                        <th class="py-2.5 px-3 font-semibold text-right">Total</th>
                        <th class="py-2.5 px-3 font-semibold">Status</th>
                        <th class="py-2.5 px-3 font-semibold">Kirim</th>
                        <th class="py-2.5 px-4 font-semibold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($saleOrders as $so)
                        @php
                            $prog = $progressSummary[$so->id] ?? ['ordered' => 0, 'delivered' => 0, 'available' => 0];
                            $ordered = (float) $prog['ordered'];
                            $delivered = (float) $prog['delivered'];
                            $available = (float) $prog['available'];
                            $pct = $ordered > 0 ? min(100, round(($ordered - $available) / $ordered * 100)) : 0;

                            // Tentukan label status & style
                            $statusStyle = match($so->status) {
                                'request_approve' => 'text-amber-800 bg-amber-50 border-amber-300',
                                'approved', 'confirmed' => 'text-emerald-800 bg-emerald-50 border-emerald-300',
                                'partially_delivered' => 'text-blue-800 bg-blue-50 border-blue-300',
                                'received' => 'text-indigo-800 bg-indigo-50 border-indigo-300',
                                'completed' => 'text-emerald-900 bg-emerald-100 border-emerald-400',
                                'reject' => 'text-red-800 bg-red-50 border-red-300',
                                default => 'text-gray-700 bg-gray-100 border-gray-200',
                            };
                            $statusLabel = match($so->status) {
                                'request_approve' => 'Perlu atasan · approval',
                                'draft' => 'Draft',
                                'approved' => 'Siap buat DO',
                                'partially_delivered' => 'Sebagian DO',
                                'received' => 'Diterima customer',
                                'completed' => 'Selesai',
                                'reject' => 'Ditolak · revisi',
                                default => ucfirst($so->status),
                            };
                        @endphp
                        <tr class="hover:bg-gray-50/70 transition-colors">
                            <td class="py-3 px-4">
                                <a href="{{ route('penjualan.so.show', $so->id) }}" class="font-bold text-gray-900 hover:text-emerald-700 transition-colors">
                                    {{ $so->so_number }}
                                </a>
                                <div class="text-xs text-gray-400 mt-0.5">
                                    {{ $so->order_date ? $so->order_date->format('d M') : '-' }} · {{ $so->createdBy->name ?? 'Sales' }}
                                </div>
                            </td>
                            <td class="py-3 px-3 font-medium text-gray-800">
                                {{ $so->customer?->name ?? '-' }}
                            </td>
                            <td class="py-3 px-3 text-right font-semibold text-gray-900">
                                Rp {{ number_format($so->grandTotal(), 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-3">
                                <span class="inline-block text-[11.5px] font-bold border rounded-md px-2 py-0.5 {{ $statusStyle }}">
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td class="py-3 px-3 text-xs text-gray-600 min-w-[110px]">
                                @if($ordered > 0)
                                    <div>{{ round($ordered - $available) }}/{{ round($ordered) }} pcs</div>
                                    <div class="h-1.5 bg-gray-100 rounded-full mt-1 overflow-hidden w-24">
                                        <div class="h-full bg-emerald-600 rounded-full" style="width: {{ $pct }}%"></div>
                                    </div>
                                @else
                                    <span class="text-gray-400">–</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
                                    @if($so->status === 'request_approve')
                                        <a href="{{ route('penjualan.so.show', $so->id) }}" class="text-xs font-bold px-3 py-1.5 bg-emerald-700 hover:bg-emerald-800 text-white rounded-lg transition-colors">
                                            Approve
                                        </a>
                                    @elseif($so->status === 'draft')
                                        <a href="{{ route('penjualan.so.show', $so->id) }}" class="text-xs font-semibold px-3 py-1.5 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-lg transition-colors">
                                            Lanjutkan
                                        </a>
                                    @elseif(in_array($so->status, ['approved', 'confirmed', 'partially_delivered']) && $available > 0)
                                        <a href="{{ route('penjualan.do.create', ['so_id' => $so->id]) }}" class="text-xs font-bold px-3 py-1.5 bg-emerald-700 hover:bg-emerald-800 text-white rounded-lg transition-colors">
                                            Buat DO
                                        </a>
                                    @elseif($so->status === 'received')
                                        <a href="{{ route('penjualan.invoices.index') }}" class="text-xs font-bold px-3 py-1.5 bg-emerald-700 hover:bg-emerald-800 text-white rounded-lg transition-colors">
                                            Buat Invoice
                                        </a>
                                    @elseif($so->status === 'reject')
                                        <a href="{{ route('penjualan.so.show', $so->id) }}" class="text-xs font-semibold px-3 py-1.5 bg-white border border-red-300 text-red-700 hover:bg-red-50 rounded-lg transition-colors">
                                            Revisi
                                        </a>
                                    @else
                                        <a href="{{ route('penjualan.so.show', $so->id) }}" class="text-xs font-semibold px-3 py-1.5 bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 rounded-lg transition-colors">
                                            Detail
                                        </a>
                                    @endif

                                    <!-- Dropdown Menu / PDF Link -->
                                    <a href="{{ route('pdf-stream', ['type' => 'sale-order', 'id' => $so->id]) }}" target="_blank" title="Cetak PDF SO" class="p-1.5 text-gray-500 hover:text-emerald-700 hover:bg-gray-100 rounded-lg transition-colors">
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-gray-500">
                                <svg class="w-10 h-10 mx-auto text-gray-300 mb-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h5"/></svg>
                                Tidak ada data pesanan penjualan yang sesuai filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Mobile Card List View (B2_SO-List-HP.html) -->
        <div class="block md:hidden divide-y divide-gray-100">
            @forelse($saleOrders as $so)
                @php
                    $prog = $progressSummary[$so->id] ?? ['ordered' => 0, 'delivered' => 0, 'available' => 0];
                    $ordered = (float) $prog['ordered'];
                    $delivered = (float) $prog['delivered'];
                    $available = (float) $prog['available'];
                    $pct = $ordered > 0 ? min(100, round(($ordered - $available) / $ordered * 100)) : 0;

                    $statusStyle = match($so->status) {
                        'request_approve' => 'text-amber-800 bg-amber-50 border-amber-300',
                        'approved', 'confirmed' => 'text-emerald-800 bg-emerald-50 border-emerald-300',
                        'partially_delivered' => 'text-blue-800 bg-blue-50 border-blue-300',
                        'received' => 'text-indigo-800 bg-indigo-50 border-indigo-300',
                        'completed' => 'text-emerald-900 bg-emerald-100 border-emerald-400',
                        'reject' => 'text-red-800 bg-red-50 border-red-300',
                        default => 'text-gray-700 bg-gray-100 border-gray-200',
                    };
                    $statusLabel = match($so->status) {
                        'request_approve' => 'Perlu atasan',
                        'draft' => 'Draft',
                        'approved' => 'Siap DO',
                        'partially_delivered' => 'Sebagian DO',
                        'received' => 'Diterima',
                        'completed' => 'Selesai',
                        'reject' => 'Ditolak',
                        default => ucfirst($so->status),
                    };
                @endphp
                <div class="p-4 hover:bg-gray-50/80 transition-colors">
                    <div class="flex justify-between items-center">
                        <a href="{{ route('penjualan.so.show', $so->id) }}" class="font-bold text-gray-900 text-base">
                            {{ $so->so_number }}
                        </a>
                        <span class="text-[11px] font-bold border rounded-md px-2 py-0.5 {{ $statusStyle }}">
                            {{ $statusLabel }}
                        </span>
                    </div>
                    <div class="text-xs text-gray-600 mt-1">
                        {{ $so->customer?->name ?? '-' }}
                    </div>

                    @if($ordered > 0)
                        <div class="text-[11.5px] text-gray-500 mt-2">
                            Terkirim {{ round($ordered - $available) }} dari {{ round($ordered) }} pcs
                        </div>
                        <div class="h-1.5 bg-gray-100 rounded-full mt-1 overflow-hidden">
                            <div class="h-full bg-emerald-600 rounded-full" style="width: {{ $pct }}%"></div>
                        </div>
                    @endif

                    <div class="flex justify-between items-center mt-3 pt-2 border-t border-gray-100">
                        <span class="text-sm font-extrabold text-gray-900">
                            Rp {{ number_format($so->grandTotal(), 0, ',', '.') }}
                        </span>
                        <a href="{{ route('penjualan.so.show', $so->id) }}" class="text-xs font-bold px-3 py-1.5 bg-emerald-700 hover:bg-emerald-800 text-white rounded-lg transition-colors">
                            Lihat SO ›
                        </a>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-gray-500">
                    Tidak ada data SO ditemukan.
                </div>
            @endforelse
        </div>

        <!-- Pagination Footer -->
        <div class="flex flex-wrap justify-between items-center gap-3 p-3.5 sm:p-4 border-t border-gray-100 text-xs sm:text-sm text-gray-500 bg-gray-50/50">
            <span>{{ $counts[$activeTab] ?? $saleOrders->total() }} SO {{ str_replace('_', ' ', $activeTab) }}</span>
            <div>
                {{ $saleOrders->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
