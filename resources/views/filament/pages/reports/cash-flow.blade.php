<x-filament::page>
<style>
    .fr-page { font-family:'Inter',ui-sans-serif,system-ui,sans-serif; }
    .fr-report-header { background:linear-gradient(135deg,#7c2d12,#ea580c); color:#fff; border-radius:16px; padding:2rem; margin-bottom:1.5rem; text-align:center; box-shadow:0 8px 24px rgba(234,88,12,.25); }
    .fr-company-name { font-size:1.5rem; font-weight:800; letter-spacing:.02em; }
    .fr-report-type { font-size:1.125rem; font-weight:600; opacity:.9; margin-top:.25rem; }
    .fr-report-period { font-size:.9rem; opacity:.75; margin-top:.25rem; }
    /* Summary cards */
    .fr-card-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(185px,1fr)); gap:1rem; margin-bottom:1.5rem; }
    .fr-card { background:#fff; border-radius:12px; padding:1.1rem 1.5rem; border:1px solid #e2e8f0; box-shadow:0 2px 8px rgba(0,0,0,.06); }
    .fr-card-label { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#64748b; margin-bottom:.3rem; }
    .fr-card-value { font-size:1.35rem; font-weight:800; }
    .fr-card-value.green { color:#059669; }
    .fr-card-value.red { color:#dc2626; }
    .fr-card-value.orange { color:#ea580c; }
    .fr-card-value.blue { color:#2563eb; }
    /* Report body */
    .fr-body { background:#fff; border-radius:16px; border:1px solid #e2e8f0; box-shadow:0 4px 16px rgba(0,0,0,.07); overflow:hidden; margin-bottom:1.5rem; }
    /* Section headers */
    .fr-sec-hdr { display:flex; align-items:center; gap:.75rem; padding:.9rem 1.5rem; font-size:1rem; font-weight:700; color:#fff; letter-spacing:.02em; }
    .fr-sec-hdr.operating { background:linear-gradient(135deg,#065f46,#059669); }
    .fr-sec-hdr.investing { background:linear-gradient(135deg,#1e3a8a,#2563eb); }
    .fr-sec-hdr.financing { background:linear-gradient(135deg,#7c2d12,#ea580c); }
    /* Line items */
    .fr-line { display:flex; justify-content:space-between; align-items:flex-start; padding:.55rem 1.5rem .55rem 2.5rem; border-bottom:1px solid #f1f5f9; font-size:.88rem; }
    .fr-line:hover { background:#f8fafc; }
    .fr-line-label { flex:1; color:#374151; line-height:1.4; }
    .fr-line-sub { font-size:.75rem; color:#94a3b8; margin-top:.1rem; }
    .fr-line-amount { font-weight:600; font-family:monospace; white-space:nowrap; padding-left:.5rem; }
    .fr-line-amount.pos { color:#059669; }
    .fr-line-amount.neg { color:#dc2626; }
    /* Section total */
    .fr-sec-total { display:flex; justify-content:space-between; align-items:center; padding:.75rem 1.5rem; font-weight:800; border-top:2px solid; }
    .fr-sec-total.operating { background:#d1fae5; border-color:#34d399; color:#065f46; }
    .fr-sec-total.investing { background:#dbeafe; border-color:#60a5fa; color:#1e3a8a; }
    .fr-sec-total.financing { background:#ffedd5; border-color:#fdba74; color:#7c2d12; }
    /* Net change & closing */
    .fr-net-change { display:flex; justify-content:space-between; align-items:center; padding:.85rem 1.5rem; background:#fef9c3; border-top:2px solid #fbbf24; font-weight:700; font-size:.95rem; color:#78350f; }
    .fr-closing { display:flex; justify-content:space-between; align-items:center; padding:1rem 1.5rem; background:linear-gradient(135deg,#7c2d12,#c2410c); color:#fff; font-weight:800; font-size:1.05rem; border-radius:0 0 16px 16px; }
    /* Details toggle */
    .fr-detail-toggle { font-size:.75rem; color:#0ea5e9; cursor:pointer; margin-top:.2rem; }
    .fr-detail-content { padding:.5rem 0; color:#64748b; font-size:.78rem; }
    /* Notes */
    .fr-notes { background:#fff7ed; border:1px solid #fed7aa; border-radius:12px; padding:1rem 1.5rem; font-size:.85rem; color:#7c2d12; }
    .fr-notes-title { font-weight:700; margin-bottom:.4rem; }
    .fr-actions { display:flex; gap:.75rem; flex-wrap:wrap; margin-top:1rem; }
    @media print {
        .no-print,.fi-topbar,.fi-sidebar,.fi-page-header,nav { display:none!important; }
        .fr-body { box-shadow:none; }
        details { display:none; }
    }
</style>

    <div class="fr-page">
        <div class="no-print">
            {{ $this->form }}
        </div>

        @if($this->showPreview)

        @php
            $report = $this->getReportData();
        @endphp

        {{-- Section key→css class mapping --}}
        @php
            $sectionClasses = [
                'operating'  => 'operating',
                'investing'  => 'investing',
                'financing'  => 'financing',
            ];
            $sectionIcons = [
                'operating' => '&#9881;',
                'investing' => '&#127970;',
                'financing' => '&#128181;',
            ];
        @endphp

        {{-- Report Header --}}
        <div class="fr-report-header">
            <div class="fr-company-name">{{ config('app.name', 'PT Duta Tunggal') }}</div>
            <div class="fr-report-type">LAPORAN ARUS KAS</div>
            <div class="fr-report-period">
                Untuk Periode {{ \Carbon\Carbon::parse($report['period']['start'])->isoFormat('D MMMM GGGG') }}
                s/d {{ \Carbon\Carbon::parse($report['period']['end'])->isoFormat('D MMMM GGGG') }}
                &nbsp;&bull;&nbsp; Metode: {{ strtoupper($report['method'] ?? 'Direct') }}
            </div>
        </div>

        {{-- Summary Cards --}}
        <div class="fr-card-grid no-print">
            <div class="fr-card">
                <div class="fr-card-label">&#128178; Saldo Kas Awal</div>
                <div class="fr-card-value blue">Rp {{ number_format($report['opening_balance'], 0, ',', '.') }}</div>
            </div>
            @foreach($report['sections'] ?? [] as $sec)
            <div class="fr-card">
                <div class="fr-card-label">{{ $sec['label'] ?? '-' }}</div>
                @php $tot = $sec['total'] ?? 0; @endphp
                <div class="fr-card-value {{ $tot >= 0 ? 'green' : 'red' }}">
                    @if($tot < 0)(Rp {{ number_format(abs($tot), 0, ',', '.') }})
                    @else Rp {{ number_format($tot, 0, ',', '.') }}@endif
                </div>
            </div>
            @endforeach
            <div class="fr-card">
                <div class="fr-card-label">&#128178; Saldo Kas Akhir</div>
                <div class="fr-card-value orange">Rp {{ number_format($report['closing_balance'], 0, ',', '.') }}</div>
            </div>
        </div>

        {{-- Selected branches --}}
        @php $selectedBranches = method_exists($this, 'getSelectedBranchNames') ? $this->getSelectedBranchNames() : []; @endphp
        @if(!empty($selectedBranches))
        <div class="mb-3 px-4 py-2 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-700 no-print">
            &#127968; Cabang: <strong>{{ implode(', ', $selectedBranches) }}</strong>
        </div>
        @endif

        {{-- ===== ARUS KAS BODY ===== --}}
        <div class="fr-body">
            @foreach($report['sections'] ?? [] as $section)
                @php
                    $sKey = $section['key'] ?? 'other';
                    $sCls = $sectionClasses[$sKey] ?? 'operating';
                    $sIcon = $sectionIcons[$sKey] ?? '&#128202;';
                @endphp
                <div class="fr-sec-hdr {{ $sCls }}">
                    {!! $sIcon !!} AKTIVITAS {{ strtoupper($section['label'] ?? '') }}
                </div>
                @foreach($section['items'] ?? [] as $item)
                    @php $amt = $item['amount'] ?? 0; @endphp
                    <div class="fr-line">
                        <div class="fr-line-label">
                            <div>{{ $item['label'] ?? '-' }}</div>
                            @if(!empty($item['metadata']['sources'] ?? []))
                                <div class="fr-line-sub">Sumber: {{ implode(', ', $item['metadata']['sources']) }}</div>
                            @endif
                            @if(!empty($item['metadata']['detail'] ?? []))
                            <details>
                                <summary class="fr-detail-toggle">Lihat Detail</summary>
                                <div class="fr-detail-content">
                                    @foreach($item['metadata']['detail'] as $det)
                                        <div>{{ $det['customer'] ?? $det['customer_name'] ?? '-' }}:
                                            Rp {{ number_format($det['total'] ?? $det['amount'] ?? 0, 0, ',', '.') }}</div>
                                    @endforeach
                                </div>
                            </details>
                            @endif
                            @if(!empty($item['metadata']['breakdown'] ?? []))
                            <details>
                                <summary class="fr-detail-toggle">Rincian COA</summary>
                                <div class="fr-detail-content">
                                    @foreach($item['metadata']['breakdown']['inflow'] ?? [] as $coa)
                                        <div>&#8593; {{ $coa['coa_code'] }} — {{ $coa['coa_name'] }}: Rp {{ number_format($coa['amount'] ?? 0, 0, ',', '.') }}</div>
                                    @endforeach
                                    @foreach($item['metadata']['breakdown']['outflow'] ?? [] as $coa)
                                        <div>&#8595; {{ $coa['coa_code'] }} — {{ $coa['coa_name'] }}: (Rp {{ number_format($coa['amount'] ?? 0, 0, ',', '.') }})</div>
                                    @endforeach
                                </div>
                            </details>
                            @endif
                        </div>
                        <span class="fr-line-amount {{ $amt >= 0 ? 'pos' : 'neg' }}">
                            @if($amt < 0)(Rp {{ number_format(abs($amt), 0, ',', '.') }})
                            @else Rp {{ number_format($amt, 0, ',', '.') }}@endif
                        </span>
                    </div>
                @endforeach
                @php $secTotal = $section['total'] ?? 0; @endphp
                <div class="fr-sec-total {{ $sCls }}">
                    <span>Arus Kas Bersih — {{ $section['label'] ?? '' }}</span>
                    <span style="font-family:monospace;">
                        @if($secTotal < 0)(Rp {{ number_format(abs($secTotal), 0, ',', '.') }})
                        @else Rp {{ number_format($secTotal, 0, ',', '.') }}@endif
                    </span>
                </div>
            @endforeach

            {{-- Net Change --}}
            <div class="fr-net-change">
                <span>&#128200; KENAIKAN (PENURUNAN) BERSIH KAS</span>
                @php $netChange = $report['net_change'] ?? 0; @endphp
                <span style="font-family:monospace;{{ $netChange < 0 ? 'color:#dc2626' : '' }}">
                    @if($netChange < 0)(Rp {{ number_format(abs($netChange), 0, ',', '.') }})
                    @else Rp {{ number_format($netChange, 0, ',', '.') }}@endif
                </span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:.6rem 1.5rem;background:#fef3c7;border-top:1px solid #fde68a;font-size:.88rem;color:#78350f;font-weight:600;">
                <span>Saldo Kas Awal Periode</span>
                <span style="font-family:monospace;">Rp {{ number_format($report['opening_balance'], 0, ',', '.') }}</span>
            </div>
            <div class="fr-closing">
                <span>&#128178; SALDO KAS AKHIR PERIODE</span>
                <span style="font-family:monospace;">Rp {{ number_format($report['closing_balance'], 0, ',', '.') }}</span>
            </div>
        </div>

        {{-- Notes --}}
        <div class="fr-notes no-print">
            <div class="fr-notes-title">&#128161; Tips Membaca:</div>
            <ul style="list-style:disc;padding-left:1.2rem;margin-top:.25rem;line-height:1.8;font-size:.875rem;">
                <li>Arus Kas Operasional: aktivitas utama bisnis (penerimaan pelanggan, pembayaran pemasok, gaji)</li>
                <li>Arus Kas Investasi: pembelian/penjualan aset tetap dan investasi</li>
                <li>Arus Kas Pendanaan: utang &amp; modal (pinjaman, angsuran, dividen)</li>
                <li>Kenaikan Kas = jumlah ketiga aktivitas di atas</li>
            </ul>
        </div>

        <div class="fr-actions no-print">
            <x-filament::button wire:click="export('excel')" color="primary" icon="heroicon-m-arrow-down-tray">Export Excel</x-filament::button>
            <x-filament::button wire:click="export('pdf')" color="danger" icon="heroicon-m-document-text">Export PDF</x-filament::button>
            <x-filament::button wire:click="$refresh" color="gray" icon="heroicon-m-arrow-path">Refresh</x-filament::button>
            <x-filament::button onclick="window.print()" color="gray" icon="heroicon-m-printer">Cetak</x-filament::button>
        </div>

        @else

        <div class="mt-6 p-10 border rounded-xl bg-gray-50 dark:bg-gray-800 text-center text-gray-500">
            <x-heroicon-o-document-chart-bar class="w-16 h-16 mx-auto mb-4 text-gray-400" />
            <p class="text-lg font-semibold">Laporan Arus Kas</p>
            <p class="mt-1 text-sm">Atur filter di atas, kemudian klik <strong>Tampilkan Laporan</strong> untuk melihat data.</p>
        </div>

        @endif
    </div>
</x-filament::page>
