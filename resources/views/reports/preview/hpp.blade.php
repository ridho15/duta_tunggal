<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan HPP – {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; background: #f8fafc; color: #1e293b; margin: 0; padding: 0; }
        .print-toolbar { display: flex; align-items: center; gap: .75rem; padding: .75rem 1.5rem; background: #1e3a8a; color: #fff; position: sticky; top: 0; z-index: 100; }
        .print-toolbar h1 { flex: 1; font-size: 1rem; font-weight: 700; margin: 0; }
        .pt-btn { display: inline-flex; align-items: center; gap: .4rem; padding: .45rem 1rem; border-radius: 8px; border: none; font-size: .85rem; font-weight: 600; cursor: pointer; }
        .pt-btn-print { background: #2563eb; color: #fff; }
        .pt-btn-close  { background: #475569; color: #fff; }
        .report-wrap { max-width: 900px; margin: 0 auto; padding: 1.5rem; }
        .rh { background: linear-gradient(135deg,#1e3a8a,#2563eb); color:#fff; border-radius:16px; padding:2rem; margin-bottom:1.5rem; text-align:center; box-shadow:0 8px 24px rgba(37,99,235,.25); }
        .rh-name { font-size:1.5rem; font-weight:800; }
        .rh-type { font-size:1.125rem; font-weight:600; opacity:.9; margin-top:.25rem; }
        .rh-period { font-size:.9rem; opacity:.75; margin-top:.25rem; }
        .card-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(185px,1fr)); gap:1rem; margin-bottom:1.5rem; }
        .card { background:#fff; border-radius:12px; padding:1.1rem 1.5rem; border:1px solid #e2e8f0; box-shadow:0 2px 8px rgba(0,0,0,.06); }
        .card-label { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#64748b; margin-bottom:.3rem; }
        .card-value { font-size:1.3rem; font-weight:800; color:#2563eb; }
        .table-wrap { overflow-x:auto; border-radius:12px; box-shadow:0 4px 16px rgba(0,0,0,.07); margin-bottom:1.5rem; }
        table { width:100%; border-collapse:collapse; background:#fff; }
        thead th { background:#1e3a8a; color:#fff; padding:.6rem 1rem; font-size:.8rem; font-weight:700; text-align:left; }
        thead th:last-child { text-align:right; }
        tbody tr { border-bottom:1px solid #f1f5f9; }
        tbody tr:hover { background:#f8fafc; }
        tbody td { padding:.55rem 1rem; font-size:.875rem; }
        tbody td:last-child { text-align:right; font-family:monospace; font-weight:600; }
        .row-section td { background:#dbeafe; font-weight:800; color:#1e3a8a; }
        .row-total td { background:#f1f5f9; font-weight:800; border-top:2px solid #cbd5e1; }
        .row-cogm td { background:linear-gradient(135deg,#1e3a8a,#1d4ed8); color:#fff; font-weight:900; font-size:.95rem; }
        .row-cogm td:last-child { color:#fff; }
        .row-sub td { padding-left:2rem; }
        tfoot td { background:linear-gradient(135deg,#1e3a8a,#1d4ed8); color:#fff; font-weight:900; padding:.75rem 1rem; font-size:.95rem; }
        tfoot td:last-child { text-align:right; font-family:monospace; }
        .overhead-item { display:flex; justify-content:space-between; font-size:.78rem; color:#64748b; padding:.15rem 0; }
        .data-quality-warning { background:#fffbeb; border:1px solid #f59e0b; border-radius:12px; padding:1rem 1.1rem; margin-bottom:1.25rem; color:#92400e; }
        .data-quality-warning h3 { margin:0 0 .35rem; font-size:.9rem; font-weight:800; }
        .data-quality-warning ul { margin:.5rem 0 0; padding-left:1.1rem; }
        .data-quality-warning li { margin:.2rem 0; }
        @media print {
            body { background:#fff; }
            .print-toolbar,.no-print { display:none !important; }
            .table-wrap { box-shadow:none; }
        }
    </style>
</head>
<body>
    <div class="print-toolbar no-print">
        <h1>&#128202; Laporan HPP (Harga Pokok Produksi)</h1>
        <button class="pt-btn pt-btn-print" onclick="window.print()">&#128424; Cetak / PDF</button>
        <button class="pt-btn pt-btn-close" onclick="window.close()">&#10005; Tutup</button>
    </div>

    <div class="report-wrap">
        @php
            $raw      = $report['raw_materials'];
            $overhead = $report['overhead'];
            $wip      = $report['wip'];
            $dataQuality = $report['data_quality'] ?? [];
            $warnings = $dataQuality['warnings'] ?? [];
        @endphp

        <div class="rh">
            <div class="rh-name">{{ config('app.name', 'PT Duta Tunggal') }}</div>
            <div class="rh-type">LAPORAN HARGA POKOK PRODUKSI</div>
            <div class="rh-period">
                Periode {{ \Carbon\Carbon::parse($report['period']['start'])->isoFormat('D MMMM GGGG') }}
                s/d {{ \Carbon\Carbon::parse($report['period']['end'])->isoFormat('D MMMM GGGG') }}
            </div>
        </div>

        <div class="card-row no-print">
            <div class="card">
                <div class="card-label">&#128230; Bahan Baku Digunakan</div>
                <div class="card-value">Rp {{ number_format($raw['used'], 2, ',', '.') }}</div>
            </div>
            <div class="card">
                <div class="card-label">&#128202; Total Biaya Produksi</div>
                <div class="card-value">Rp {{ number_format($report['production_cost'], 2, ',', '.') }}</div>
            </div>
            <div class="card">
                <div class="card-label">&#127381; Harga Pokok Produksi</div>
                <div class="card-value">Rp {{ number_format($report['cogm'], 2, ',', '.') }}</div>
            </div>
        </div>

        @if(!empty($selectedBranches))
        <div style="margin-bottom:1rem;padding:.6rem 1rem;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;font-size:.85rem;color:#1d4ed8;" class="no-print">
            &#127968; Cabang: <strong>{{ implode(', ', $selectedBranches) }}</strong>
        </div>
        @endif

        @if(!empty($warnings))
        <div class="data-quality-warning no-print">
            <h3>Peringatan kualitas data HPP</h3>
            <div>Laporan ini menggunakan fallback untuk sebagian nilai. Periksa sumber posting jurnal atau stok berikut:</div>
            <ul>
                @foreach($warnings as $warning)
                    <li>{{ is_array($warning) ? ($warning['message'] ?? json_encode($warning)) : $warning }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Deskripsi</th>
                        <th style="text-align:right;">Jumlah (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- Bahan Baku --}}
                    <tr class="row-section"><td colspan="2">I. BAHAN BAKU</td></tr>
                    <tr><td>Persediaan Awal Bahan Baku</td><td>{{ number_format($raw['opening'], 2, ',', '.') }}</td></tr>
                    <tr><td>+ Pembelian Bahan Baku</td><td>{{ number_format($raw['purchases'], 2, ',', '.') }}</td></tr>
                    <tr class="row-total"><td>= Total Bahan Baku Tersedia</td><td>{{ number_format($raw['available'], 2, ',', '.') }}</td></tr>
                    <tr><td>- Persediaan Akhir Bahan Baku</td><td>({{ number_format($raw['closing'], 2, ',', '.') }})</td></tr>
                    <tr class="row-total"><td><strong>= Bahan Baku yang Digunakan</strong></td><td><strong>{{ number_format($raw['used'], 2, ',', '.') }}</strong></td></tr>
                    {{-- Tenaga Kerja --}}
                    <tr class="row-section"><td colspan="2">II. BIAYA TENAGA KERJA LANGSUNG</td></tr>
                    <tr><td>+ Biaya Tenaga Kerja Langsung</td><td>{{ number_format($report['direct_labor'], 2, ',', '.') }}</td></tr>
                    {{-- Overhead --}}
                    <tr class="row-section"><td colspan="2">III. BIAYA OVERHEAD PABRIK</td></tr>
                    <tr>
                        <td>
                            + Biaya Overhead Pabrik
                            <div style="margin-top:.35rem;">
                                @foreach($overhead['items'] as $item)
                                    <div class="overhead-item">
                                        <span>{{ $item['label'] }}</span>
                                        <span>Rp {{ number_format($item['amount'], 2, ',', '.') }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </td>
                        <td>{{ number_format($overhead['total'], 2, ',', '.') }}</td>
                    </tr>
                    {{-- Production Cost --}}
                    <tr class="row-total"><td><strong>= Total Biaya Produksi</strong></td><td><strong>{{ number_format($report['production_cost'], 2, ',', '.') }}</strong></td></tr>
                    {{-- WIP --}}
                    <tr class="row-section"><td colspan="2">IV. BARANG DALAM PROSES (WIP)</td></tr>
                    <tr><td>+ Persediaan Awal WIP</td><td>{{ number_format($wip['opening'], 2, ',', '.') }}</td></tr>
                    <tr><td>- Persediaan Akhir WIP</td><td>({{ number_format($wip['closing'], 2, ',', '.') }})</td></tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td>&#127381; HARGA POKOK PRODUKSI (Cost of Goods Manufactured)</td>
                        <td>{{ number_format($report['cogm'], 2, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</body>
</html>
