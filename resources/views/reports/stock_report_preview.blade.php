<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Stok – {{ $startDate->format('d/m/Y') }} s/d {{ $endDate->format('d/m/Y') }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 11px;
            line-height: 1.5;
            color: #1a1a1a;
            background: #f8fafc;
        }

        /* ---- Print-specific ---- */
        @media print {
            body { background: #fff; font-size: 10px; }
            .no-print { display: none !important; }
            .page-break { page-break-before: always; }
            table { font-size: 9px; }
            @page { size: A4 landscape; margin: 1cm; }
        }

        /* ---- Page wrapper ---- */
        .page-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px;
            background: #fff;
        }

        /* ---- Toolbar ---- */
        .toolbar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            padding: 12px 16px;
            background: #f1f5f9;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all .15s;
        }
        .btn-print  { background: #0f766e; color: #fff; }
        .btn-close  { background: #64748b; color: #fff; }
        .btn:hover  { opacity: .88; }

        /* ---- Report Header ---- */
        .report-header {
            border-bottom: 3px solid #0f766e;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        .company-name {
            font-size: 20px;
            font-weight: 800;
            color: #0f766e;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .report-title {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
            margin-top: 2px;
        }
        .report-meta {
            margin-top: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
        }
        .meta-item {
            font-size: 11px;
            color: #475569;
        }
        .meta-item strong { color: #1e293b; }

        /* ---- Filter chips ---- */
        .filter-chips {
            margin-top: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .chip {
            padding: 3px 10px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 500;
            border: 1px solid;
        }
        .chip-period    { background: #ecfdf5; border-color: #6ee7b7; color: #065f46; }
        .chip-product   { background: #eff6ff; border-color: #93c5fd; color: #1e40af; }
        .chip-warehouse { background: #faf5ff; border-color: #c4b5fd; color: #5b21b6; }

        /* ---- Summary cards ---- */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        .summary-card {
            padding: 14px 16px;
            border-radius: 8px;
            border: 1px solid;
        }
        .card-blue   { background: #eff6ff; border-color: #bfdbfe; }
        .card-green  { background: #f0fdf4; border-color: #bbf7d0; }
        .card-orange { background: #fff7ed; border-color: #fed7aa; }
        .card-purple { background: #faf5ff; border-color: #e9d5ff; }
        .card-label  { font-size: 10px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.4px; }
        .card-value  { font-size: 18px; font-weight: 800; margin-top: 4px; }
        .card-blue   .card-value { color: #1d4ed8; }
        .card-green  .card-value { color: #15803d; }
        .card-orange .card-value { color: #c2410c; }
        .card-purple .card-value { color: #7c3aed; }
        .card-sub    { font-size: 10px; color: #94a3b8; margin-top: 2px; }

        /* ---- Data Table ---- */
        .table-wrapper {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead th {
            background: #0f766e;
            color: #fff;
            font-weight: 700;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding: 10px 10px;
            text-align: left;
            border-right: 1px solid #0d6b63;
            white-space: nowrap;
        }
        thead th:last-child { border-right: none; }
        thead th.right { text-align: right; }

        tbody tr { border-bottom: 1px solid #f1f5f9; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        tbody tr:hover { background: #ecfdf5; }

        tbody td {
            padding: 8px 10px;
            font-size: 11px;
            color: #1e293b;
            border-right: 1px solid #f1f5f9;
        }
        tbody td:last-child { border-right: none; }
        tbody td.right { text-align: right; }
        tbody td.center { text-align: center; }

        .product-name { font-weight: 600; color: #1e293b; }
        .product-code { font-size: 10px; color: #94a3b8; }
        .warehouse-name { font-weight: 500; }
        .warehouse-code { font-size: 10px; color: #94a3b8; }

        /* Status badge */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 9999px;
            font-size: 10px;
            font-weight: 600;
        }
        .badge-normal  { background: #dcfce7; color: #166534; }
        .badge-min     { background: #fef3c7; color: #92400e; }
        .badge-habis   { background: #fee2e2; color: #991b1b; }

        /* ---- Footer ---- */
        tfoot td {
            padding: 10px;
            font-weight: 800;
            font-size: 11px;
            background: #f0fdf4;
            border-top: 2px solid #0f766e;
        }
        tfoot td.right { text-align: right; }

        .report-footer {
            margin-top: 20px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
            font-size: 10px;
            color: #94a3b8;
            display: flex;
            justify-content: space-between;
        }

        /* ---- Empty state ---- */
        .empty-state {
            text-align: center;
            padding: 48px 24px;
            color: #94a3b8;
        }
        .empty-icon { font-size: 40px; margin-bottom: 8px; }
    </style>
</head>
<body>
<div class="page-wrapper">

    {{-- Toolbar (hidden on print) --}}
    <div class="toolbar no-print">
        <button class="btn btn-print" onclick="window.print()">
            🖨 Cetak Laporan
        </button>
        <button class="btn btn-close" onclick="window.close()">
            ✕ Tutup
        </button>
    </div>

    {{-- Report Header --}}
    <div class="report-header">
        <div class="company-name">{{ config('app.name', 'DUTA TUNGGAL ERP') }}</div>
        <div class="report-title">LAPORAN STOK PERSEDIAAN</div>

        <div class="report-meta">
            <div class="meta-item">
                <strong>Periode:</strong>
                {{ $startDate->format('d/m/Y') }} – {{ $endDate->format('d/m/Y') }}
            </div>
            <div class="meta-item">
                <strong>Tanggal Cetak:</strong>
                {{ now()->format('d/m/Y H:i') }}
            </div>
        </div>

        <div class="filter-chips">
            <span class="chip chip-period">
                📅 Periode: {{ $startDate->format('d M Y') }} – {{ $endDate->format('d M Y') }}
            </span>
            @if($selectedProducts->isNotEmpty())
                @foreach($selectedProducts as $pname)
                    <span class="chip chip-product">📦 {{ $pname }}</span>
                @endforeach
            @else
                <span class="chip chip-product">📦 Semua Produk</span>
            @endif
            @if($selectedWarehouses->isNotEmpty())
                @foreach($selectedWarehouses as $wname)
                    <span class="chip chip-warehouse">🏭 {{ $wname }}</span>
                @endforeach
            @else
                <span class="chip chip-warehouse">🏭 Semua Gudang</span>
            @endif
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="summary-grid">
        <div class="summary-card card-blue">
            <div class="card-label">Total Item</div>
            <div class="card-value">{{ number_format($totals['items'], 0, ',', '.') }}</div>
            <div class="card-sub">baris stok</div>
        </div>
        <div class="summary-card card-green">
            <div class="card-label">Total On Hand</div>
            <div class="card-value">{{ number_format($totals['qty_on_hand'], 2, ',', '.') }}</div>
            <div class="card-sub">tersedia – terpesan</div>
        </div>
        <div class="summary-card card-orange">
            <div class="card-label">Total Nilai Stok</div>
            <div class="card-value" style="font-size:14px;">Rp {{ number_format($totals['total_value'], 0, ',', '.') }}</div>
            <div class="card-sub">qty on hand × HPP</div>
        </div>
        <div class="summary-card card-purple">
            <div class="card-label">Mutasi Periode</div>
            <div class="card-value" style="font-size:14px;">{{ number_format($totals['total_in'] - $totals['total_out'], 2, ',', '.') }}</div>
            <div class="card-sub">masuk {{ number_format($totals['total_in'],2,',','.') }} | keluar {{ number_format($totals['total_out'],2,',','.') }}</div>
        </div>
    </div>

    {{-- Data Table --}}
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th style="width:30px">#</th>
                    <th>Kode Produk</th>
                    <th>Nama Produk</th>
                    <th>Gudang</th>
                    <th>Rak</th>
                    <th class="right">Qty Tersedia</th>
                    <th class="right">Qty Dipesan</th>
                    <th class="right">Qty On Hand</th>
                    <th class="right">Qty Min</th>
                    <th class="right">Masuk (Periode)</th>
                    <th class="right">Keluar (Periode)</th>
                    <th class="right">HPP (Rp)</th>
                    <th class="right">Nilai Stok (Rp)</th>
                    <th class="center">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $i => $row)
                    <tr>
                        <td class="center" style="color:#94a3b8">{{ $i + 1 }}</td>
                        <td>
                            <div class="product-code">{{ $row['product_code'] }}</div>
                        </td>
                        <td>
                            <div class="product-name">{{ $row['product_name'] }}</div>
                        </td>
                        <td>
                            <div class="warehouse-name">{{ $row['warehouse_name'] }}</div>
                            @if($row['warehouse_code'] && $row['warehouse_code'] !== '-')
                                <div class="warehouse-code">{{ $row['warehouse_code'] }}</div>
                            @endif
                        </td>
                        <td>{{ $row['rak_name'] }}</td>
                        <td class="right">{{ number_format($row['qty_available'], 2, ',', '.') }}</td>
                        <td class="right" style="color:#f97316">{{ number_format($row['qty_reserved'], 2, ',', '.') }}</td>
                        <td class="right" style="font-weight:700">{{ number_format($row['qty_on_hand'], 2, ',', '.') }}</td>
                        <td class="right" style="color:#94a3b8">{{ number_format($row['qty_min'], 2, ',', '.') }}</td>
                        <td class="right" style="color:#16a34a">
                            {{ $row['total_in'] > 0 ? number_format($row['total_in'], 2, ',', '.') : '–' }}
                        </td>
                        <td class="right" style="color:#dc2626">
                            {{ $row['total_out'] > 0 ? number_format($row['total_out'], 2, ',', '.') : '–' }}
                        </td>
                        <td class="right">{{ number_format($row['cost_price'], 0, ',', '.') }}</td>
                        <td class="right" style="font-weight:700">{{ number_format($row['total_value'], 0, ',', '.') }}</td>
                        <td class="center">
                            @if($row['status'] === 'Habis')
                                <span class="badge badge-habis">Habis</span>
                            @elseif($row['status'] === 'Min')
                                <span class="badge badge-min">Min</span>
                            @else
                                <span class="badge badge-normal">Normal</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="14">
                            <div class="empty-state">
                                <div class="empty-icon">📭</div>
                                <div>Tidak ada data stok untuk filter yang dipilih.</div>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if($rows->count() > 0)
            <tfoot>
                <tr>
                    <td colspan="5" style="font-weight:700; color:#0f766e">TOTAL</td>
                    <td class="right">{{ number_format($totals['qty_available'], 2, ',', '.') }}</td>
                    <td class="right">{{ number_format($totals['qty_reserved'], 2, ',', '.') }}</td>
                    <td class="right">{{ number_format($totals['qty_on_hand'], 2, ',', '.') }}</td>
                    <td></td>
                    <td class="right" style="color:#16a34a">{{ number_format($totals['total_in'], 2, ',', '.') }}</td>
                    <td class="right" style="color:#dc2626">{{ number_format($totals['total_out'], 2, ',', '.') }}</td>
                    <td></td>
                    <td class="right">{{ number_format($totals['total_value'], 0, ',', '.') }}</td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>

    {{-- Report Footer --}}
    <div class="report-footer">
        <span>{{ config('app.name') }} &bull; Laporan Stok Persediaan</span>
        <span>Dicetak: {{ now()->format('d/m/Y H:i:s') }}</span>
    </div>
</div>
</body>
</html>
