<div class="flex flex-col gap-5 sm:gap-6" x-data="{
    showApproveModal: false,
    approveTarget: {
        title: '',
        subtitle: '',
        url: ''
    },
    openApprove(title, subtitle, url) {
        this.approveTarget = { title, subtitle, url };
        this.showApproveModal = true;
    },
    showCustomDateModal: false,
    startDateInput: '{{ $startDate }}',
    endDateInput: '{{ $endDate }}'
}">

    {{-- Header & Toolbar Filter Sesuai Mockup A1 --}}
    <div class="flex flex-wrap justify-between items-end gap-4 pb-5 border-b border-gray-200">
        <div class="flex flex-col gap-2">
            <span class="self-start inline-flex items-center gap-1.5 text-[11px] font-bold tracking-wider text-[#15803d] bg-[#f0fdf4] border border-[#86efac] rounded-md px-2.5 py-1 uppercase shadow-2xs">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="5" width="18" height="16" rx="2"></rect>
                    <path d="M3 10h18M8 3v4M16 3v4"></path>
                </svg>
                {{ strtoupper($todayFormatted) }}
            </span>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight m-0">
                Ringkasan Bisnis
            </h1>
            <div class="text-xs sm:text-sm text-gray-500">
                Angka diperbarui setiap 5 menit. Klik kartu mana saja untuk melihat detailnya.
            </div>
        </div>

        {{-- Filter Buttons: Periode & Cabang --}}
        <div class="flex flex-wrap gap-2 sm:gap-2.5 items-center">
            {{-- Button Group Periode --}}
            <div role="group" aria-label="Periode" class="inline-flex bg-white border border-gray-200 rounded-xl p-1 shadow-2xs">
                <a
                    href="?periode=today{{ $filterCabangId ? '&cabang_id='.$filterCabangId : '' }}"
                    class="text-xs sm:text-sm font-bold rounded-lg px-3 py-1.5 transition no-underline {{ $filterPeriode === 'today' ? 'bg-[#15803d] text-white shadow-xs' : 'text-gray-700 hover:text-gray-900' }}"
                >
                    Hari ini
                </a>
                <a
                    href="?periode=7d{{ $filterCabangId ? '&cabang_id='.$filterCabangId : '' }}"
                    class="text-xs sm:text-sm font-semibold rounded-lg px-3 py-1.5 transition no-underline {{ $filterPeriode === '7d' ? 'bg-[#15803d] text-white shadow-xs' : 'text-gray-700 hover:text-gray-900' }}"
                >
                    7 hari
                </a>
                <a
                    href="?periode=month{{ $filterCabangId ? '&cabang_id='.$filterCabangId : '' }}"
                    class="text-xs sm:text-sm font-semibold rounded-lg px-3 py-1.5 transition no-underline {{ $filterPeriode === 'month' ? 'bg-[#15803d] text-white shadow-xs' : 'text-gray-700 hover:text-gray-900' }}"
                >
                    Bulan ini
                </a>
                <button
                    type="button"
                    @click="showCustomDateModal = true"
                    class="text-xs sm:text-sm font-semibold rounded-lg px-3 py-1.5 transition no-underline {{ $filterPeriode === 'custom' ? 'bg-[#15803d] text-white shadow-xs' : 'text-gray-700 hover:text-gray-900' }}"
                >
                    {{ $filterPeriode === 'custom' ? $periodeLabel : 'Pilih…' }}
                </button>
            </div>

            {{-- Filter Cabang Dropdown --}}
            <div class="relative" x-data="{ openCabang: false }">
                <button
                    type="button"
                    @click="openCabang = !openCabang"
                    class="inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold bg-white border border-gray-200 rounded-xl px-3.5 py-2 text-gray-700 hover:text-gray-900 shadow-2xs transition"
                >
                    <span>{{ $activeCabangName }}</span>
                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M19 9l-7 7-7-7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </button>

                <div
                    x-show="openCabang"
                    @click.outside="openCabang = false"
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="transform opacity-0 scale-95"
                    x-transition:enter-end="transform opacity-100 scale-100"
                    x-cloak
                    class="absolute right-0 mt-1.5 w-56 max-h-72 overflow-y-auto bg-white border border-gray-200 rounded-xl shadow-lg z-30 py-1.5 text-sm"
                >
                    <a
                        href="?periode={{ $filterPeriode }}&cabang_id=all"
                        class="block px-3.5 py-2 text-xs sm:text-sm font-medium hover:bg-gray-50 {{ empty($filterCabangId) ? 'text-[#15803d] font-bold bg-[#f0fdf4]' : 'text-gray-700' }}"
                    >
                        Semua cabang
                    </a>
                    <div class="border-t border-gray-100 my-1"></div>
                    @foreach ($cabangs as $cbg)
                        <a
                            href="?periode={{ $filterPeriode }}&cabang_id={{ $cbg->id }}"
                            class="block px-3.5 py-1.5 text-xs sm:text-sm hover:bg-gray-50 truncate {{ (int)$filterCabangId === (int)$cbg->id ? 'text-[#15803d] font-bold bg-[#f0fdf4]' : 'text-gray-700' }}"
                        >
                            {{ $cbg->nama }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Baris 1: 4 Kartu KPI Eksekutif --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5">
        {{-- KPI 1: Penjualan --}}
        <a
            href="{{ route('penjualan.so.index') }}"
            class="bg-white border border-gray-200 rounded-2xl p-4 sm:p-5 flex flex-col gap-1.5 no-underline text-gray-900 hover:border-gray-300 hover:shadow-xs transition"
        >
            <span class="text-xs sm:text-[13px] text-gray-500 font-semibold">Penjualan {{ $periodeLabel }}</span>
            <span class="text-xl sm:text-[26px] font-extrabold tracking-tight" title="{{ formatCurrency($kpi['penjualan']['nominal']) }}">
                {{ formatShortCurrency($kpi['penjualan']['nominal']) }}
            </span>
            <span class="text-xs font-semibold text-[#15803d] truncate">
                ▲ {{ $kpi['penjualan']['growth_pct'] }}% dari {{ $kpi['penjualan']['prev_label'] }} · {{ $kpi['penjualan']['count_so'] }} SO
            </span>
        </a>

        {{-- KPI 2: Kas & Bank --}}
        <a
            href="{{ route('keuangan.cashbank.index') }}"
            class="bg-white border border-gray-200 rounded-2xl p-4 sm:p-5 flex flex-col gap-1.5 no-underline text-gray-900 hover:border-gray-300 hover:shadow-xs transition"
        >
            <span class="text-xs sm:text-[13px] text-gray-500 font-semibold">Kas &amp; Bank</span>
            <span class="text-xl sm:text-[26px] font-extrabold tracking-tight" title="{{ formatCurrency($kpi['kas_bank']['saldo']) }}">
                {{ formatShortCurrency($kpi['kas_bank']['saldo']) }}
            </span>
            <span class="text-xs text-gray-500 truncate">
                Masuk {{ formatShortCurrency($kpi['kas_bank']['masuk']) }} · Keluar {{ formatShortCurrency($kpi['kas_bank']['keluar']) }}
            </span>
        </a>

        {{-- KPI 3: Piutang Lewat Jatuh Tempo (Kuning Peringatan) --}}
        <a
            href="{{ route('keuangan.cashbank.index') }}"
            class="bg-[#fffbeb] border border-[#fcd34d] rounded-2xl p-4 sm:p-5 flex flex-col gap-1.5 no-underline text-gray-900 hover:border-amber-400 hover:shadow-xs transition"
        >
            <span class="text-xs sm:text-[13px] text-[#92400e] font-semibold">Piutang lewat jatuh tempo</span>
            <span class="text-xl sm:text-[26px] font-extrabold tracking-tight" title="{{ formatCurrency($kpi['piutang_overdue']['nominal']) }}">
                {{ formatShortCurrency($kpi['piutang_overdue']['nominal']) }}
            </span>
            <span class="text-xs font-semibold text-[#92400e] truncate">
                {{ $kpi['piutang_overdue']['count_customer'] }} customer · tagih sekarang ›
            </span>
        </a>

        {{-- KPI 4: Hutang Jatuh Tempo <= 7 Hari --}}
        <a
            href="{{ route('pembelian.po.index') }}"
            class="bg-white border border-gray-200 rounded-2xl p-4 sm:p-5 flex flex-col gap-1.5 no-underline text-gray-900 hover:border-gray-300 hover:shadow-xs transition"
        >
            <span class="text-xs sm:text-[13px] text-gray-500 font-semibold">Hutang jatuh tempo ≤ 7 hari</span>
            <span class="text-xl sm:text-[26px] font-extrabold tracking-tight" title="{{ formatCurrency($kpi['hutang_due_soon']['nominal']) }}">
                {{ formatShortCurrency($kpi['hutang_due_soon']['nominal']) }}
            </span>
            <span class="text-xs text-gray-500 truncate">
                {{ $kpi['hutang_due_soon']['count_supplier'] }} supplier · terdekat {{ $kpi['hutang_due_soon']['nearest_date'] }}
            </span>
        </a>
    </div>

    {{-- Baris 2: Section "Perlu Keputusan Anda" --}}
    <section aria-labelledby="kep" class="bg-white border border-gray-200 rounded-2xl p-4 sm:p-5 flex flex-col gap-3.5 shadow-2xs">
        <div class="flex items-center gap-3 pb-3 border-b border-[#eef0f2]">
            <span class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-[#fffbeb] text-[#b45309] flex-shrink-0 inline-flex items-center justify-center">
                <svg class="w-4 h-4 sm:w-5 sm:h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 11l3 3 8-8"></path>
                    <path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9"></path>
                </svg>
            </span>
            <div class="flex-1 min-w-0">
                <h2 id="kep" class="text-sm sm:text-base font-bold text-gray-900 m-0">Perlu keputusan Anda</h2>
                <div class="text-xs text-gray-500">Hal yang tidak bisa diputuskan kasir atau staf</div>
            </div>
            <span class="text-xs font-semibold text-[#92400e] bg-[#fffbeb] border border-[#fcd34d] rounded-full px-2.5 py-0.5">
                {{ $decisions['total'] }} menunggu
            </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            @foreach ($decisions['items'] as $item)
                <div class="border border-[#eef0f2] rounded-xl p-3.5 flex flex-col justify-between gap-3 bg-gray-50/40">
                    <div class="flex flex-col gap-1">
                        <div class="text-sm font-bold text-gray-900 leading-snug">{{ $item['title'] }}</div>
                        <div class="text-xs text-gray-500 leading-normal">{{ $item['subtitle'] }}</div>
                    </div>
                    <div class="flex items-center gap-2 pt-1">
                        @if ($item['can_approve'])
                            <button
                                type="button"
                                @click="openApprove('{{ addslashes($item['title']) }}', '{{ addslashes($item['subtitle']) }}', '{{ $item['approve_url'] }}')"
                                class="text-xs font-bold text-white bg-[#15803d] hover:bg-[#14532d] rounded-lg px-3.5 py-2 min-h-[38px] transition"
                            >
                                Setujui
                            </button>
                            <a
                                href="{{ $item['view_url'] }}"
                                class="text-xs font-semibold text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-lg px-3.5 py-2 min-h-[38px] inline-flex items-center justify-center no-underline transition"
                            >
                                Lihat
                            </a>
                        @else
                            <a
                                href="{{ $item['view_url'] }}"
                                class="text-xs font-semibold text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-lg px-3.5 py-2 min-h-[38px] inline-flex items-center justify-center no-underline transition"
                            >
                                Periksa
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Baris 3: Section "Alur Pesanan Saat Ini" (5 Tahap) --}}
    <section aria-labelledby="alur" class="bg-white border border-gray-200 rounded-2xl p-4 sm:p-5 flex flex-col gap-3 shadow-2xs">
        <div class="flex flex-wrap justify-between items-baseline gap-2">
            <h2 id="alur" class="text-sm sm:text-base font-bold text-gray-900 m-0">Alur pesanan saat ini</h2>
            <span class="text-xs text-gray-500">Klik tahap untuk melihat dokumennya</span>
        </div>

        <div class="flex md:grid md:grid-cols-5 gap-2.5 overflow-x-auto pb-1 custom-scrollbar">
            @foreach ($order_pipeline as $stage)
                <a
                    href="{{ $stage['url'] }}"
                    style="border-top: 3px solid {{ $stage['border_color'] }}; background: {{ $stage['bg_color'] }};"
                    class="flex-1 min-w-[140px] md:min-w-0 rounded-b-xl p-3 flex flex-col gap-1 no-underline text-gray-900 hover:opacity-90 transition"
                >
                    <div class="text-xl sm:text-[22px] font-extrabold tracking-tight">{{ $stage['count'] }}</div>
                    <div class="text-xs text-gray-600 leading-snug">
                        {{ $stage['label'] }}
                        @if ($stage['delayed_text'])
                            <span class="font-bold text-[#92400e] block sm:inline">{{ $stage['delayed_text'] }}</span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    </section>

    {{-- Baris 4: Dua Kolom Tengah (Penjualan 7 Hari + Top 5 Produk) --}}
    <div class="flex flex-col lg:flex-row gap-5 items-stretch">
        {{-- Kiri (Rasio 3:2): Penjualan 7 Hari Terakhir --}}
        <section aria-labelledby="grf" class="flex-[3] min-w-0 bg-white border border-gray-200 rounded-2xl p-4 sm:p-5 flex flex-col gap-3 shadow-2xs">
            <div class="flex justify-between items-baseline flex-wrap gap-2">
                <h2 id="grf" class="text-sm sm:text-base font-bold text-gray-900 m-0">Penjualan 7 hari terakhir</h2>
                <span class="text-xs text-gray-500">
                    Total {{ formatShortCurrency($sales_7_days['total']) }} · rata-rata {{ formatShortCurrency($sales_7_days['avg_per_day']) }}/hari
                </span>
            </div>

            {{-- Grafik Batang Vertikal CSS Grid --}}
            <div
                role="img"
                aria-label="Grafik batang penjualan 7 hari terakhir"
                class="relative h-[200px] grid grid-cols-7 gap-2 sm:gap-3.5 items-end border-b border-gray-300 pt-5 px-1"
            >
                @foreach ($sales_7_days['days'] as $day)
                    <div class="flex flex-col items-center justify-end h-full">
                        @if ($day['is_highest'] || $day['is_today'])
                            <span class="text-[11px] font-bold text-gray-700 mb-1 leading-none">
                                {{ $day['formatted_amount'] }}
                            </span>
                        @endif
                        <div
                            style="height: {{ $day['height_px'] }}px; background: {{ $day['is_today'] ? '#15803d' : '#86efac' }};"
                            class="w-3/5 sm:w-1/2 rounded-t transition-all duration-300"
                            title="{{ $day['day_label'] }}: {{ formatCurrency($day['amount']) }}"
                        ></div>
                    </div>
                @endforeach
            </div>

            {{-- Label Sumbu X --}}
            <div class="grid grid-cols-7 gap-2 sm:gap-3.5 text-xs text-gray-500 text-center px-1">
                @foreach ($sales_7_days['days'] as $day)
                    <span class="{{ $day['is_today'] ? 'font-bold text-gray-900' : '' }}">
                        {{ $day['day_label'] }}
                    </span>
                @endforeach
            </div>
        </section>

        {{-- Kanan: Produk Terlaris (7 Hari) --}}
        <section aria-labelledby="top" class="flex-[2] min-w-0 bg-white border border-gray-200 rounded-2xl p-4 sm:p-5 flex flex-col gap-3 shadow-2xs">
            <h2 id="top" class="text-sm sm:text-base font-bold text-gray-900 m-0">Produk terlaris (7 hari)</h2>

            <div class="flex flex-col gap-2.5 text-xs sm:text-[13px] my-auto">
                @foreach ($top_products as $prod)
                    <div>
                        <div class="flex justify-between items-center text-gray-800">
                            <span class="truncate pr-2 font-medium">{{ $prod['name'] }}</span>
                            <b class="text-gray-900 flex-shrink-0">{{ formatShortCurrency($prod['sales']) }}</b>
                        </div>
                        <div class="h-1.5 bg-gray-100 rounded-full mt-1.5 overflow-hidden">
                            <div
                                style="width: {{ $prod['percentage'] }}%;"
                                class="h-full bg-[#15803d] rounded-full transition-all duration-500"
                            ></div>
                        </div>
                    </div>
                @endforeach
            </div>

            <a href="{{ route('penjualan.so.index') }}" class="text-xs font-semibold text-[#15803d] hover:text-[#14532d] mt-auto pt-2 no-underline">
                Laporan penjualan lengkap ›
            </a>
        </section>
    </div>

    {{-- Baris 5: Dua Kolom Bawah (Stok Perhatian + Pembelian) --}}
    <div class="flex flex-col lg:flex-row gap-5 items-stretch">
        {{-- Kiri: Stok Perlu Perhatian --}}
        <section aria-labelledby="stk" class="flex-1 min-w-0 bg-white border border-gray-200 rounded-2xl p-4 sm:p-5 flex flex-col gap-3 shadow-2xs">
            <div class="flex justify-between items-baseline gap-2">
                <h2 id="stk" class="text-sm sm:text-base font-bold text-gray-900 m-0">Stok perlu perhatian</h2>
                <span class="text-xs text-gray-500">Nilai stok {{ formatShortCurrency($stock_alerts['total_valuation']) }}</span>
            </div>

            <div class="flex flex-col divide-y divide-gray-100 text-xs sm:text-[13px]">
                @foreach ($stock_alerts['items'] as $alert)
                    <div class="flex items-center gap-2.5 py-2">
                        @if ($alert['type'] === 'out')
                            <span class="text-[11px] font-bold text-[#b91c1c] bg-[#fef2f2] border border-[#fecaca] rounded px-1.5 py-0.5 flex-shrink-0">
                                {{ $alert['label'] }}
                            </span>
                        @else
                            <span class="text-[11px] font-bold text-[#92400e] bg-[#fffbeb] border border-[#fcd34d] rounded px-1.5 py-0.5 flex-shrink-0">
                                {{ $alert['label'] }}
                            </span>
                        @endif
                        <span class="flex-1 truncate font-medium text-gray-800">{{ $alert['name'] }}</span>
                        <span class="text-gray-500 flex-shrink-0">{{ $alert['info'] }}</span>
                    </div>
                @endforeach

                <div class="flex items-center gap-2.5 py-2">
                    <span class="text-[11px] font-bold text-gray-700 bg-gray-100 border border-gray-200 rounded px-1.5 py-0.5 flex-shrink-0">
                        ◷ Lambat
                    </span>
                    <span class="flex-1 truncate font-medium text-gray-800">
                        {{ $stock_alerts['slow_moving_count'] }} produk tidak terjual &gt; 90 hari
                    </span>
                    <a href="{{ route('penjualan.so.index') }}" class="text-xs font-semibold text-[#15803d] hover:underline">
                        Lihat
                    </a>
                </div>
            </div>

            <a
                href="{{ route('pembelian.or.index') }}"
                class="self-start text-xs font-bold text-[#15803d] bg-[#f0fdf4] border border-[#86efac] hover:bg-[#dcfce7] rounded-xl px-3.5 py-2 no-underline mt-auto transition"
            >
                + Buat Permintaan Pembelian dari stok menipis
            </a>
        </section>

        {{-- Kanan: Pembelian --}}
        <section aria-labelledby="pbl" class="flex-1 min-w-0 bg-white border border-gray-200 rounded-2xl p-4 sm:p-5 flex flex-col gap-3 shadow-2xs">
            <h2 id="pbl" class="text-sm sm:text-base font-bold text-gray-900 m-0">Pembelian</h2>

            <div class="grid grid-cols-3 gap-2.5">
                <a
                    href="{{ route('pembelian.or.index') }}"
                    class="border border-dashed border-gray-300 rounded-xl p-3 flex flex-col gap-1 no-underline text-gray-900 hover:bg-gray-50 transition"
                >
                    <div class="text-lg sm:text-xl font-extrabold">{{ $purchase_summary['pr_new'] }}</div>
                    <div class="text-xs text-gray-500">Permintaan baru</div>
                </a>
                <a
                    href="{{ route('pembelian.po.index') }}"
                    class="border border-dashed border-gray-300 rounded-xl p-3 flex flex-col gap-1 no-underline text-gray-900 hover:bg-gray-50 transition"
                >
                    <div class="text-lg sm:text-xl font-extrabold">{{ $purchase_summary['po_waiting'] }}</div>
                    <div class="text-xs text-gray-500">PO menunggu barang</div>
                </a>
                <a
                    href="{{ route('pembelian.po.index') }}"
                    class="border border-[#bbf7d0] bg-[#f0fdf4] rounded-xl p-3 flex flex-col gap-1 no-underline text-gray-900 hover:bg-[#dcfce7] transition"
                >
                    <div class="text-lg sm:text-xl font-extrabold text-[#14532d]">{{ $purchase_summary['arriving_today'] }}</div>
                    <div class="text-xs text-[#15803d] font-medium">Tiba hari ini</div>
                </a>
            </div>

            <div class="flex flex-col divide-y divide-gray-100 text-xs sm:text-[13px] text-gray-700 mt-auto">
                <div class="py-2 truncate">
                    {{ $purchase_summary['delayed_po_text'] }}
                </div>
                <div class="py-2 truncate">
                    Retur pembelian menunggu: {{ $purchase_summary['returns_waiting'] }} dokumen
                </div>
            </div>
        </section>
    </div>

    {{-- Modal Konfirmasi Persetujuan Cepat (Quick Approve Modal) --}}
    <div
        x-show="showApproveModal"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-xs"
    >
        <div
            @click.outside="showApproveModal = false"
            class="bg-white rounded-2xl shadow-xl border border-gray-200 max-w-md w-full p-6 flex flex-col gap-4"
        >
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-[#f0fdf4] border border-[#86efac] text-[#15803d] flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M5 13l4 4L19 7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </span>
                <div>
                    <h3 class="text-base font-bold text-gray-900 m-0">Konfirmasi Persetujuan</h3>
                    <p class="text-xs text-gray-500 m-0">Setujui dokumen ini dengan hak wewenang Super Admin</p>
                </div>
            </div>

            <div class="bg-gray-50 border border-gray-100 rounded-xl p-3.5 flex flex-col gap-1 text-sm">
                <span class="font-bold text-gray-900" x-text="approveTarget.title"></span>
                <span class="text-xs text-gray-500" x-text="approveTarget.subtitle"></span>
            </div>

            <form :action="approveTarget.url" method="POST" class="flex flex-col gap-4">
                @csrf
                <div class="flex flex-col gap-1.5">
                    <label for="override_reason" class="text-xs font-semibold text-gray-700">Catatan / Alasan Persetujuan (Opsional)</label>
                    <input
                        type="text"
                        name="override_reason"
                        id="override_reason"
                        placeholder="Disetujui oleh Super Admin"
                        class="w-full text-xs sm:text-sm border border-gray-300 rounded-xl px-3 py-2 outline-none focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d]"
                    >
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-2">
                    <button
                        type="button"
                        @click="showApproveModal = false"
                        class="text-xs font-semibold text-gray-600 hover:bg-gray-100 rounded-xl px-4 py-2.5 transition"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="text-xs font-bold text-white bg-[#15803d] hover:bg-[#14532d] rounded-xl px-4 py-2.5 shadow-xs transition"
                    >
                        Ya, Setujui Sekarang
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Filter Tanggal Kustom (Custom Date Range) --}}
    <div
        x-show="showCustomDateModal"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-xs"
    >
        <div
            @click.outside="showCustomDateModal = false"
            class="bg-white rounded-2xl shadow-xl border border-gray-200 max-w-sm w-full p-6 flex flex-col gap-4"
        >
            <div>
                <h3 class="text-base font-bold text-gray-900 m-0">Pilih Rentang Tanggal</h3>
                <p class="text-xs text-gray-500 m-0">Tentukan periode laporan data yang ingin ditinjau</p>
            </div>

            <form action="{{ route('dashboard') }}" method="GET" class="flex flex-col gap-3.5">
                <input type="hidden" name="periode" value="custom">
                @if ($filterCabangId)
                    <input type="hidden" name="cabang_id" value="{{ $filterCabangId }}">
                @endif

                <div class="flex flex-col gap-1.5">
                    <label for="start_date" class="text-xs font-semibold text-gray-700">Dari Tanggal</label>
                    <input
                        type="date"
                        name="start_date"
                        id="start_date"
                        x-model="startDateInput"
                        class="w-full text-xs sm:text-sm border border-gray-300 rounded-xl px-3 py-2 outline-none focus:border-[#15803d]"
                        required
                    >
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="end_date" class="text-xs font-semibold text-gray-700">Sampai Tanggal</label>
                    <input
                        type="date"
                        name="end_date"
                        id="end_date"
                        x-model="endDateInput"
                        class="w-full text-xs sm:text-sm border border-gray-300 rounded-xl px-3 py-2 outline-none focus:border-[#15803d]"
                        required
                    >
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-2">
                    <button
                        type="button"
                        @click="showCustomDateModal = false"
                        class="text-xs font-semibold text-gray-600 hover:bg-gray-100 rounded-xl px-4 py-2.5 transition"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="text-xs font-bold text-white bg-[#15803d] hover:bg-[#14532d] rounded-xl px-4 py-2.5 shadow-xs transition"
                    >
                        Terapkan Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
