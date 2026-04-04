<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan HPP / COGM – {{ $startDate->format('d/m/Y') }} s/d {{ $endDate->format('d/m/Y') }}</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f3f4f6;
            color: #111827;
        }

        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            @page { size: A4 portrait; margin: 14mm; }
        }

        /* ── Toolbar ── */
        .toolbar {
            display: flex;
            gap: 10px;
            align-items: center;
            justify-content: space-between;
            padding: 14px 20px;
            margin-bottom: 18px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            box-shadow: 0 4px 20px rgba(15,23,42,.06);
        }
        .toolbar .title-text {
            font-size: 15px;
            font-weight: 700;
            color: #1e293b;
        }
        .toolbar .actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn {
            border: 0;
            border-radius: 10px;
            padding: 9px 18px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            color: #fff;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-print { background: linear-gradient(135deg, #7c3aed, #6d28d9); }
        .btn-close  { background: linear-gradient(135deg, #475569, #334155); }

        /* ── Page wrapper ── */
        .page {
            max-width: 900px;
            margin: 0 auto;
            padding: 0 20px 40px;
        }

        /* ── Report header ── */
        .rh {
            background: linear-gradient(135deg, #4c1d95 0%, #7c3aed 60%, #a855f7 100%);
            color: #fff;
            border-radius: 20px;
            padding: 26px 28px;
            margin-bottom: 20px;
            box-shadow: 0 12px 36px rgba(109,40,217,.22);
        }
        .rh-brand { font-size: 12px; letter-spacing: .18em; text-transform: uppercase; opacity: .82; margin-bottom: 4px; }
        .rh-title { font-size: 26px; font-weight: 900; text-transform: uppercase; letter-spacing: .04em; margin: 0; }
        .rh-sub   { font-size: 14px; opacity: .85; margin-top: 6px; }

        /* ── Meta cards ── */
        .meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        .meta-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 14px 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,.05);
        }
        .meta-label {
            font-size: 11px;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: .07em;
        }
        .meta-value {
            margin-top: 5px;
            font-size: 15px;
            font-weight: 800;
            color: #111827;
        }

        /* ── Summary cards ── */
        .card-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        .sum-card {
            background: #fff;
            border-radius: 14px;
            padding: 14px 18px;
            box-shadow: 0 2px 10px rgba(0,0,0,.06);
            border-left: 4px solid #7c3aed;
        }
        .sum-card.blue  { border-left-color: #2563eb; }
        .sum-card.green { border-left-color: #059669; }
        .sum-card.red   { border-left-color: #dc2626; }
        .sum-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #6b7280; margin-bottom: 4px; }
        .sum-value { font-size: 16px; font-weight: 900; color: #1e293b; }

        /* ── COGM Statement table ── */
        .table-wrap {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 16px rgba(0,0,0,.07);
            overflow: hidden;
            margin-bottom: 20px;
        }
        .table-head-band {
            background: linear-gradient(135deg, #4c1d95, #7c3aed);
            color: #fff;
            padding: 14px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .table-head-band h3 { margin: 0; font-size: 15px; font-weight: 800; }
        .table-head-band span { font-size: 13px; opacity: .85; }

        table.cogm-stmt { width: 100%; border-collapse: collapse; font-size: .86rem; }
        table.cogm-stmt td { padding: 10px 18px; border-bottom: 1px solid #f1f5f9; }
        table.cogm-stmt tr:last-child td { border-bottom: none; }
        table.cogm-stmt td:last-child { text-align: right; font-family: monospace; white-space: nowrap; }

        .row-section td { background: #f8fafc; font-weight: 700; color: #374151; font-size: .8rem; text-transform: uppercase; letter-spacing: .05em; }
        .row-indent td:first-child { padding-left: 36px; color: #4b5563; }
        .row-sub-total td { background: #ede9fe; font-weight: 700; color: #5b21b6; }
        .row-total td { background: #ddd6fe; font-weight: 800; color: #4c1d95; border-top: 2px solid #c4b5fd; }
        .row-grand td {
            background: linear-gradient(135deg, #4c1d95, #7c3aed);
            color: #fff;
            font-weight: 900;
            font-size: 1rem;
            padding: 14px 18px;
        }

        /* ── MO detail table ── */
        table.mo-table { width: 100%; border-collapse: collapse; font-size: .83rem; }
        table.mo-table thead tr { background: #4c1d95; color: #fff; }
        table.mo-table thead th { padding: 9px 14px; text-align: left; font-weight: 700; white-space: nowrap; }
        table.mo-table thead th:nth-child(3) { text-align: right; }
        table.mo-table tbody tr { border-bottom: 1px solid #f1f5f9; }
        table.mo-table tbody tr:hover { background: #faf5ff; }
        table.mo-table tbody td { padding: 8px 14px; color: #374151; }
        table.mo-table tbody td:nth-child(3) { text-align: right; font-family: monospace; }

        .badge {
            display: inline-flex; align-items: center;
            border-radius: 9999px; padding: 2px 10px;
            font-size: .75rem; font-weight: 700; white-space: nowrap;
        }
        .badge-completed  { background: #d1fae5; color: #065f46; }
        .badge-in_progress { background: #fef3c7; color: #92400e; }
        .badge-default    { background: #f3f4f6; color: #374151; }

        /* ── Footer ── */
        .report-footer {
            text-align: center;
            margin-top: 24px;
            font-size: .72rem;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
            padding-top: 10px;
            font-style: italic;
        }
    </style>
</head>
<body>
    {{-- Toolbar --}}
    <div class="toolbar no-print">
        <span class="title-text">&#128202; Laporan Harga Pokok Produksi (COGM)</span>
        <div class="actions">
            <button class="btn btn-print" onclick="window.print()">&#128424; Cetak / PDF</button>
            <button class="btn btn-close"  onclick="window.close()">&#10005; Tutup</button>
        </div>
    </div>

    <div class="page">

        {{-- Report header --}}
        <div class="rh">
            <div class="rh-brand">{{ config('app.name', 'PT Duta Tunggal') }}</div>
            <h1 class="rh-title">Laporan Harga Pokok Produksi</h1>
            <div class="rh-sub">
                Periode: {{ $startDate->isoFormat('D MMMM YYYY') }} s/d {{ $endDate->isoFormat('D MMMM YYYY') }}
                @if($selectedCabang)
                    &nbsp;&bull;&nbsp; Cabang: <strong>{{ $selectedCabang->nama }}</strong>
                @endif
                @if($selectedProduct)
                    &nbsp;&bull;&nbsp; Produk: <strong>{{ $selectedProduct->name }}</strong>
                @endif
            </div>
        </div>

        {{-- Meta cards --}}
        <div class="meta no-print">
            <div class="meta-card">
                <div class="meta-label">Periode Mulai</div>
                <div class="meta-value">{{ $startDate->format('d/m/Y') }}</div>
            </div>
            <div class="meta-card">
                <div class="meta-label">Periode Akhir</div>
                <div class="meta-value">{{ $endDate->format('d/m/Y') }}</div>
            </div>
            <div class="meta-card">
                <div class="meta-label">Cabang</div>
                <div class="meta-value">{{ $selectedCabang ? $selectedCabang->nama : 'Semua Cabang' }}</div>
            </div>
            <div class="meta-card">
                <div class="meta-label">Jumlah MO</div>
                <div class="meta-value">{{ $report['mo_count'] }}</div>
            </div>
        </div>

        {{-- Summary cards --}}
        <div class="card-row no-print">
            <div class="sum-card blue">
                <div class="sum-label">&#128230; Bahan Baku Terpakai</div>
                <div class="sum-value">Rp {{ number_format($report['raw_material_used'], 0, ',', '.') }}</div>
            </div>
            <div class="sum-card">
                <div class="sum-label">&#128104;&#8205;&#127981; Tenaga Kerja</div>
                <div class="sum-value">Rp {{ number_format($report['labor_cost'], 0, ',', '.') }}</div>
            </div>
            <div class="sum-card">
                <div class="sum-label">&#9881; Overhead Pabrik</div>
                <div class="sum-value">Rp {{ number_format($report['overhead'], 0, ',', '.') }}</div>
            </div>
            <div class="sum-card green">
                <div class="sum-label">&#127381; COGM</div>
                <div class="sum-value">Rp {{ number_format($report['cogm'], 0, ',', '.') }}</div>
            </div>
        </div>

        {{-- ─── COGM Statement ─── --}}
        <div class="table-wrap">
            <div class="table-head-band">
                <h3>Laporan Harga Pokok Produksi (Cost of Goods Manufactured)</h3>
                <span>{{ $startDate->isoFormat('D MMM YYYY') }} – {{ $endDate->isoFormat('D MMM YYYY') }}</span>
            </div>
            <table class="cogm-stmt">
                <tbody>
                    {{-- Opening WIP --}}
                    <tr class="row-section"><td colspan="2">I. Saldo Awal WIP (Barang Dalam Proses)</td></tr>
                    <tr>
                        <td>Persediaan Awal Barang Dalam Proses</td>
                        <td>Rp {{ number_format($report['opening_wip'], 0, ',', '.') }}</td>
                    </tr>

                    {{-- Production Costs --}}
                    <tr class="row-section"><td colspan="2">II. Biaya Produksi Periode Ini</td></tr>
                    <tr class="row-indent">
                        <td>Bahan Baku Terpakai (Raw Material Used)</td>
                        <td>Rp {{ number_format($report['raw_material_used'], 0, ',', '.') }}</td>
                    </tr>
                    <tr class="row-indent">
                        <td>Biaya Tenaga Kerja Langsung (Direct Labor)</td>
                        <td>Rp {{ number_format($report['labor_cost'], 0, ',', '.') }}</td>
                    </tr>
                    <tr class="row-indent">
                        <td>Biaya Overhead Pabrik (Manufacturing Overhead)</td>
                        <td>Rp {{ number_format($report['overhead'], 0, ',', '.') }}</td>
                    </tr>
                    <tr class="row-sub-total">
                        <td>Total Biaya Produksi Ditambahkan</td>
                        <td>Rp {{ number_format($report['total_cost_added'], 0, ',', '.') }}</td>
                    </tr>

                    {{-- Total WIP available --}}
                    <tr class="row-section"><td colspan="2">III. Total WIP Tersedia</td></tr>
                    <tr>
                        <td>Saldo Awal WIP + Total Biaya Produksi</td>
                        <td>Rp {{ number_format($report['total_wip'], 0, ',', '.') }}</td>
                    </tr>

                    {{-- Closing WIP --}}
                    <tr class="row-section"><td colspan="2">IV. Saldo Akhir WIP</td></tr>
                    <tr class="row-indent">
                        <td>Dikurangi: Persediaan Akhir Barang Dalam Proses</td>
                        <td>(Rp {{ number_format($report['closing_wip'], 0, ',', '.') }})</td>
                    </tr>

                    {{-- COGM Grand Total --}}
                    <tr class="row-grand">
                        <td>&#128200; Harga Pokok Produksi (COGM)</td>
                        <td>Rp {{ number_format($report['cogm'], 0, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- ─── Manufacturing Orders Detail ─── --}}
        @if($report['mo_count'] > 0)
        <div class="table-wrap">
            <div class="table-head-band">
                <h3>Detail Manufacturing Orders ({{ $report['mo_count'] }} MO)</h3>
                <span>Periode {{ $startDate->isoFormat('D MMM YYYY') }} – {{ $endDate->isoFormat('D MMM YYYY') }}</span>
            </div>
            <table class="mo-table">
                <thead>
                    <tr>
                        <th>No. MO</th>
                        <th>Produk</th>
                        <th>Qty</th>
                        <th>Status</th>
                        <th>Tanggal Dibuat</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['orders'] as $mo)
                    @php
                        $productName = optional(optional($mo->productionPlan)->product)->name ?? '-';
                        $status = $mo->status ?? 'draft';
                        $badgeClass = match($status) {
                            'completed'   => 'badge-completed',
                            'in_progress' => 'badge-in_progress',
                            default       => 'badge-default',
                        };
                    @endphp
                    <tr>
                        <td>{{ $mo->mo_number ?? '#' . $mo->id }}</td>
                        <td>{{ $productName }}</td>
                        <td>{{ number_format($mo->quantity ?? 0) }}</td>
                        <td>
                            <span class="badge {{ $badgeClass }}">
                                {{ ucfirst(str_replace('_', ' ', $status)) }}
                            </span>
                        </td>
                        <td>{{ \Carbon\Carbon::parse($mo->created_at)->format('d/m/Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <div class="report-footer">
            Dicetak pada {{ now()->isoFormat('D MMMM YYYY, HH:mm') }} &nbsp;|&nbsp; {{ config('app.name') }}
            &nbsp;|&nbsp; Laporan Harga Pokok Produksi (Cost of Goods Manufactured)
        </div>

    </div>
</body>
</html>
