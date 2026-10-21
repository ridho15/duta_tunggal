<div class="flex flex-col gap-6">

    {{-- Header Banner Sesuai Mockup E1_Main.html --}}
    <div class="flex flex-col gap-2 pb-5 border-b border-gray-200">
        <span class="self-start inline-flex items-center gap-2 text-[11px] font-bold tracking-wider text-[#15803d] bg-[#f0fdf4] border border-[#86efac] rounded-lg px-2.5 py-1 uppercase">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <rect x="3" y="5" width="18" height="16" rx="2" stroke-width="2"></rect>
                <path d="M3 10h18M8 3v4M16 3v4" stroke-width="2" stroke-linecap="round"></path>
            </svg>
            {{ $todayDateFormatted }}
        </span>
        <h1 class="text-2xl md:text-3xl font-extrabold text-gray-900 tracking-tight m-0">
            Halo, {{ $user->name }}
        </h1>
        <p class="text-sm text-gray-500 m-0">
            @if ($tugasAktif > 0)
                Ada <span class="font-bold text-[#15803d]">{{ $tugasAktif }} pekerjaan</span> yang menunggu Anda hari ini. Mulai dari yang paling atas.
            @else
                Semua pekerjaan hari ini sudah selesai atau sedang berjalan lancar.
            @endif
        </p>
    </div>

    {{-- Section: Perlu Anda Kerjakan --}}
    <x-ui.card
        title="Perlu Anda kerjakan"
        subtitle="Klik tombol untuk langsung membuka dokumen atau memproses tugas"
        :badge="$tugasAktif . ' tugas'"
    >
        <x-slot:icon>
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="9" stroke-width="1.8"></circle>
                <path d="M12 7v5l3 2" stroke-width="1.8" stroke-linecap="round"></path>
            </svg>
        </x-slot:icon>

        <div class="divide-y divide-[#eef0f2]">

            {{-- 1. SO Menunggu Approve --}}
            <div class="py-3.5 flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <span class="text-2xl font-extrabold w-10 text-[#15803d]">
                        {{ $soMenungguApprove }}
                    </span>
                    <div>
                        <div class="text-sm font-bold text-gray-900">SO menunggu approve</div>
                        <div class="text-xs text-gray-500 mt-0.5">Pesanan baru yang telah masuk dan siap diperiksa batas harga & limit kreditnya.</div>
                    </div>
                </div>
                <div>
                    <x-ui.button variant="primary" size="sm" :href="route('penjualan.so.index') ?? '#'">
                        Periksa & Approve
                    </x-ui.button>
                </div>
            </div>

            {{-- 2. SO Melewati Limit / Perlu Atasan --}}
            @if ($soPerluAtasan > 0)
                <div class="py-3.5 flex flex-wrap items-center justify-between gap-4 bg-[#fffbeb]/40 -mx-5 px-5 md:-mx-6 md:px-6">
                    <div class="flex items-center gap-4">
                        <span class="text-2xl font-extrabold w-10 text-[#b45309]">
                            {{ $soPerluAtasan }}
                        </span>
                        <div>
                            <div class="text-sm font-bold text-gray-900 flex items-center gap-2">
                                <span>SO melewati limit kredit / diskon khusus</span>
                                <x-ui.badge variant="warning" size="xs">PERLU ATASAN</x-ui.badge>
                            </div>
                            <div class="text-xs text-gray-500 mt-0.5">Memerlukan persetujuan Sales Manager / Owner sebelum dapat dibuatkan DO.</div>
                        </div>
                    </div>
                    <div>
                        <x-ui.button variant="outline" size="sm" :href="route('penjualan.so.index') ?? '#'">
                            Lihat Dokumen
                        </x-ui.button>
                    </div>
                </div>
            @endif

            {{-- 3. DO Gudang Menunggu Diproses --}}
            <div class="py-3.5 flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <span class="text-2xl font-extrabold w-10 text-[#15803d]">
                        {{ $doMenungguGudang }}
                    </span>
                    <div>
                        <div class="text-sm font-bold text-gray-900">Perintah Kirim (DO) menunggu gudang</div>
                        <div class="text-xs text-gray-500 mt-0.5">Barang yang sudah dialokasikan per gudang dan menunggu disiapkan/dikirim oleh staff gudang.</div>
                    </div>
                </div>
                <div>
                    <x-ui.button variant="outline" size="sm" :href="route('penjualan.do.index') ?? '#'">
                        Proses di Gudang
                    </x-ui.button>
                </div>
            </div>

            {{-- 4. Surat Jalan Siap Ditagih --}}
            <div class="py-3.5 flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <span class="text-2xl font-extrabold w-10 text-[#15803d]">
                        {{ $sjSiapDitagih }}
                    </span>
                    <div>
                        <div class="text-sm font-bold text-gray-900">Surat Jalan siap ditagih</div>
                        <div class="text-xs text-gray-500 mt-0.5">Buat invoice penjualan langsung dari Surat Jalan yang sudah terbit tanpa input ulang barang.</div>
                    </div>
                </div>
                <div>
                    <x-ui.button variant="primary" size="sm" :href="route('penjualan.sj.index') ?? '#'">
                        Buat Invoice
                    </x-ui.button>
                </div>
            </div>

        </div>
    </x-ui.card>

    {{-- Section: Ringkasan Selesai Hari Ini --}}
    <x-ui.card
        title="Selesai hari ini"
        subtitle="Ringkasan pemrosesan dokumen operasional cabang Anda"
    >
        <x-slot:icon>
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path d="M5 12l4 4 10-10" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
        </x-slot:icon>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 md:gap-4">
            <x-ui.stat-box
                label="SO Diapprove"
                :value="$soApprovedHariIni"
                subtext="Pesanan penjualan disetujui"
                variant="dashed"
            />
            <x-ui.stat-box
                label="Pembayaran Dicatat"
                :value="$pembayaranHariIni"
                subtext="Penerimaan kas & bank dari customer"
                variant="dashed"
            />
            <x-ui.stat-box
                label="Invoice Diterbitkan"
                :value="$invoiceHariIni"
                subtext="Faktur penagihan penjualan baru"
                variant="dashed"
            />
        </div>
    </x-ui.card>

    {{-- Section: Dokumen Pesanan Penjualan Terkini --}}
    @if ($previewOrders->count() > 0)
        <x-ui.card
            title="Pesanan Penjualan Terbaru"
            subtitle="Dokumen pesanan terbaru yang memerlukan tindak lanjut cepat"
        >
            <x-slot:headerAction>
                <x-ui.button variant="ghost" size="xs" :href="route('penjualan.so.index') ?? '#'">
                    Lihat Semua &rarr;
                </x-ui.button>
            </x-slot:headerAction>

            <div class="overflow-x-auto -mx-5 -mb-5 md:-mx-6 md:-mb-6">
                <table class="w-full border-collapse text-left text-sm">
                    <thead>
                        <tr class="text-xs font-semibold text-gray-500 bg-gray-50/70 border-y border-gray-200">
                            <th class="py-2.5 px-5">No. SO</th>
                            <th class="py-2.5 px-3">Customer</th>
                            <th class="py-2.5 px-3 text-right">Total</th>
                            <th class="py-2.5 px-3">Status</th>
                            <th class="py-2.5 px-5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($previewOrders as $so)
                            <tr class="hover:bg-gray-50/60 transition">
                                <td class="py-3 px-5">
                                    <div class="font-bold text-gray-900">{{ $so->so_number }}</div>
                                    <div class="text-xs text-gray-400">
                                        {{ $so->order_date ? \Carbon\Carbon::parse($so->order_date)->format('d M') : '-' }} · {{ $so->createdBy?->name ?? 'Sales' }}
                                    </div>
                                </td>
                                <td class="py-3 px-3">
                                    <div class="font-semibold text-gray-800">{{ $so->customer?->name ?? '-' }}</div>
                                </td>
                                <td class="py-3 px-3 text-right font-bold text-gray-900">
                                    Rp{{ number_format($so->total_amount ?? 0, 0, ',', '.') }}
                                </td>
                                <td class="py-3 px-3">
                                    @php
                                        $badgeVariant = match ($so->status) {
                                            'request_approve' => 'warning',
                                            'approved' => 'success',
                                            'confirmed', 'partially_delivered' => 'info',
                                            'reject', 'canceled' => 'danger',
                                            default => 'neutral',
                                        };
                                        $badgeLabel = \App\Models\SaleOrder::STATUS_LABELS[$so->status] ?? ucfirst($so->status);
                                    @endphp
                                    <x-ui.badge :variant="$badgeVariant">
                                        {{ $badgeLabel }}
                                    </x-ui.badge>
                                </td>
                                <td class="py-3 px-5 text-right">
                                    <x-ui.button variant="outline" size="xs" :href="route('penjualan.so.index') ?? '#'">
                                        Detail
                                    </x-ui.button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    @endif

</div>
