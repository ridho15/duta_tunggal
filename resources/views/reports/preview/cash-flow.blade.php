<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Arus Kas – {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; background: #f8fafc; color: #1e293b; margin: 0; padding: 0; }
        .print-toolbar { display: flex; align-items: center; gap: .75rem; padding: .75rem 1.5rem; background: #7c2d12; color: #fff; position: sticky; top: 0; z-index: 100; }
        .print-toolbar h1 { flex: 1; font-size: 1rem; font-weight: 700; margin: 0; }
        .pt-btn { display: inline-flex; align-items: center; gap: .4rem; padding: .45rem 1rem; border-radius: 8px; border: none; font-size: .85rem; font-weight: 600; cursor: pointer; }
        .pt-btn-print { background: #ea580c; color: #fff; }
        .pt-btn-close  { background: #475569; color: #fff; }
        .report-wrap { max-width: 960px; margin: 0 auto; padding: 1.5rem; }
        .fr-report-header { background:linear-gradient(135deg,#7c2d12,#ea580c); color:#fff; border-radius:16px; padding:2rem; margin-bottom:1.5rem; text-align:center; box-shadow:0 8px 24px rgba(234,88,12,.25); }
        .fr-company-name { font-size:1.5rem; font-weight:800; letter-spacing:.02em; }
        .fr-report-type { font-size:1.125rem; font-weight:600; opacity:.9; margin-top:.25rem; }
        .fr-report-period { font-size:.9rem; opacity:.75; margin-top:.25rem; }
        .fr-card-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(185px,1fr)); gap:1rem; margin-bottom:1.5rem; }
        .fr-card { background:#fff; border-radius:12px; padding:1.1rem 1.5rem; border:1px solid #e2e8f0; box-shadow:0 2px 8px rgba(0,0,0,.06); }
        .fr-card-label { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#64748b; margin-bottom:.3rem; }
        .fr-card-value { font-size:1.35rem; font-weight:800; }
        .fr-card-value.green  { color:#059669; }
        .fr-card-value.red    { color:#dc2626; }
        .fr-card-value.orange { color:#ea580c; }
        .fr-card-value.blue   { color:#2563eb; }
        .fr-body { background:#fff; border-radius:16px; border:1px solid #e2e8f0; box-shadow:0 4px 16px rgba(0,0,0,.07); overflow:hidden; margin-bottom:1.5rem; }
        .fr-sec-hdr { display:flex; align-items:center; gap:.75rem; padding:.9rem 1.5rem; font-size:1rem; font-weight:700; color:#fff; letter-spacing:.02em; }
        .fr-sec-hdr.operating { background:linear-gradient(135deg,#065f46,#059669); }
        .fr-sec-hdr.investing { background:linear-gradient(135deg,#1e3a8a,#2563eb); }
        .fr-sec-hdr.financing { background:linear-gradient(135deg,#7c2d12,#ea580c); }
        .fr-line { display:flex; justify-content:space-between; align-items:flex-start; padding:.55rem 1.5rem .55rem 2.5rem; border-bottom:1px solid #f1f5f9; font-size:.88rem; }
        .fr-line:hover { background:#f8fafc; }
        .fr-line-label { flex:1; color:#374151; line-height:1.4; }
        .fr-line-sub { font-size:.75rem; color:#94a3b8; margin-top:.1rem; }
        .fr-line-amount { font-weight:600; font-family:monospace; white-space:nowrap; padding-left:.5rem; }
        .fr-line-amount.pos { color:#059669; }
        .fr-line-amount.neg { color:#dc2626; }
        .fr-sec-total { display:flex; justify-content:space-between; align-items:center; padding:.75rem 1.5rem; font-weight:800; border-top:2px solid; }
        .fr-sec-total.operating { background:#d1fae5; border-color:#34d399; color:#065f46; }
        .fr-sec-total.investing { background:#dbeafe; border-color:#60a5fa; color:#1e3a8a; }
        .fr-sec-total.financing { background:#ffedd5; border-color:#fdba74; color:#7c2d12; }
        .fr-net-change { display:flex; justify-content:space-between; align-items:center; padding:.85rem 1.5rem; background:#fef9c3; border-top:2px solid #fbbf24; font-weight:700; font-size:.95rem; color:#78350f; }
        .fr-closing { display:flex; justify-content:space-between; align-items:center; padding:1rem 1.5rem; background:linear-gradient(135deg,#7c2d12,#c2410c); color:#fff; font-weight:800; font-size:1.05rem; border-radius:0 0 16px 16px; }
        .fr-notes { background:#fff7ed; border:1px solid #fed7aa; border-radius:12px; padding:1rem 1.5rem; font-size:.85rem; color:#7c2d12; }
        .fr-notes-title { font-weight:700; margin-bottom:.4rem; }
        details { font-size:.78rem; color:#64748b; }
        details summary { cursor:pointer; color:#0ea5e9; font-size:.75rem; margin-top:.2rem; }
        @media print {
            body { background:#fff; }
            .print-toolbar,.no-print { display:none !important; }
            .fr-body { box-shadow:none; }
            details { display:none; }
        }
    </style>
</head>
<body>
    <div class="print-toolbar no-print">
        <h1>&#9881; Laporan Arus Kas</h1>
        <button class="pt-btn pt-btn-print" onclick="window.print()">&#128424; Cetak / PDF</button>
        <button class="pt-btn pt-btn-close" onclick="window.close()">&#10005; Tutup</button>
    </div>

    <div class="report-wrap">
        @php
            $sectionClasses = ['operating'=>'operating','investing'=>'investing','financing'=>'financing'];
            $sectionIcons   = ['operating'=>'&#9881;','investing'=>'&#127970;','financing'=>'&#128181;'];
        @endphp

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

        @if(!empty($selectedBranches))
        <div style="margin-bottom:1rem;padding:.6rem 1rem;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;font-size:.85rem;color:#1d4ed8;" class="no-print">
            &#127968; Cabang: <strong>{{ implode(', ', $selectedBranches) }}</strong>
        </div>
        @endif

        <div class="fr-body">
            @foreach($report['sections'] ?? [] as $section)
                @php
                    $sKey  = $section['key'] ?? 'other';
                    $sCls  = $sectionClasses[$sKey] ?? 'operating';
                    $sIcon = $sectionIcons[$sKey] ?? '&#128202;';
                @endphp
                <div class="fr-sec-hdr {{ $sCls }}">{!! $sIcon !!} AKTIVITAS {{ strtoupper($section['label'] ?? '') }}</div>
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
                                <summary>Lihat Detail</summary>
                                @foreach($item['metadata']['detail'] as $det)
                                    <div>{{ $det['customer'] ?? $det['customer_name'] ?? '-' }}: Rp {{ number_format($det['total'] ?? $det['amount'] ?? 0, 0, ',', '.') }}</div>
                                @endforeach
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

            @php $netChange = $report['net_change'] ?? 0; @endphp
            <div class="fr-net-change">
                <span>&#128200; KENAIKAN (PENURUNAN) BERSIH KAS</span>
                <span style="font-family:monospace;{{ $netChange < 0 ? 'color:#dc2626' : '' }}">
                    @if($netChange < 0)(Rp {{ number_format(abs($netChange), 0, ',', '.') }})
                    @else Rp {{ number_format($netChange, 0, ',', '.') }}@endif
                </span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:.6rem 1.5rem;background:#fef3c7;font-size:.88rem;color:#78350f;font-weight:600;">
                <span>Saldo Kas Awal Periode</span>
                <span style="font-family:monospace;">Rp {{ number_format($report['opening_balance'], 0, ',', '.') }}</span>
            </div>
            <div class="fr-closing">
                <span>&#128178; SALDO KAS AKHIR PERIODE</span>
                <span style="font-family:monospace;">Rp {{ number_format($report['closing_balance'], 0, ',', '.') }}</span>
            </div>
        </div>

        <div class="fr-notes no-print">
            <div class="fr-notes-title">&#128161; Tips Membaca:</div>
            <ul style="list-style:disc;padding-left:1.2rem;margin-top:.25rem;line-height:1.8;font-size:.875rem;">
                <li>Arus Kas Operasional: aktivitas utama bisnis (penerimaan pelanggan, pembayaran pemasok, gaji)</li>
                <li>Arus Kas Investasi: pembelian/penjualan aset tetap dan investasi</li>
                <li>Arus Kas Pendanaan: utang &amp; modal (pinjaman, angsuran, dividen)</li>
                <li>Kenaikan Kas = jumlah ketiga aktivitas di atas</li>
            </ul>
        </div>
    </div>
</body>
</html>
