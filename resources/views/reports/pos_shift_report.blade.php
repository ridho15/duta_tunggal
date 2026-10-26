<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Shift Kasir (Z-Report) - {{ $start_date }} s/d {{ $end_date }}</title>
    <style>
        @page { size: A4 landscape; margin: 1cm; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9px; color: #333; }
        h1 { font-size: 15px; margin: 0; color: #15803d; }
        .meta { font-size: 9px; color: #555; margin: 2px 0 12px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #eef2f7; font-weight: bold; }
        .num { text-align: right; font-family: monospace; }
        .center { text-align: center; }
        .kpi-table { width: 100%; margin-bottom: 12px; border: none; }
        .kpi-table td { border: 1px solid #e2e8f0; padding: 6px 10px; background: #f8fafc; }
        .kpi-label { font-size: 8px; font-weight: bold; text-transform: uppercase; color: #64748b; }
        .kpi-val { font-size: 11px; font-weight: bold; color: #0f172a; margin-top: 2px; }
    </style>
</head>
<body>
    <h1>LAPORAN SHIFT KASIR & PENUTUPAN LACI (Z-REPORT)</h1>
    <div class="meta">
        Periode: {{ \Carbon\Carbon::parse($start_date)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($end_date)->format('d/m/Y') }}
        &nbsp;|&nbsp; Dicetak: {{ now()->format('d/m/Y H:i') }} oleh {{ auth()->user()->name ?? 'System' }}
    </div>

    <table class="kpi-table">
        <tr>
            <td>
                <div class="kpi-label">Total Omzet Penjualan</div>
                <div class="kpi-val" style="color: #15803d;">Rp {{ number_format($kpi['total_sales'] ?? 0, 0, ',', '.') }}</div>
            </td>
            <td>
                <div class="kpi-label">Penjualan Tunai (Cash)</div>
                <div class="kpi-val">Rp {{ number_format($kpi['total_cash'] ?? 0, 0, ',', '.') }}</div>
            </td>
            <td>
                <div class="kpi-label">Penjualan Non-Tunai</div>
                <div class="kpi-val">Rp {{ number_format($kpi['total_non_cash'] ?? 0, 0, ',', '.') }}</div>
            </td>
            <td>
                <div class="kpi-label">Total Transaksi</div>
                <div class="kpi-val">{{ number_format($kpi['total_transactions'] ?? 0, 0, ',', '.') }} Struk</div>
            </td>
            <td>
                <div class="kpi-label">Shift Selesai (Closed)</div>
                <div class="kpi-val">{{ number_format($kpi['closed_shifts'] ?? 0, 0, ',', '.') }} Shift</div>
            </td>
            <td>
                <div class="kpi-label">Total Selisih Kas</div>
                <div class="kpi-val" style="color: {{ ($kpi['total_difference'] ?? 0) < 0 ? '#b91c1c' : '#15803d' }};">
                    Rp {{ number_format($kpi['total_difference'] ?? 0, 0, ',', '.') }}
                </div>
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>No. Shift</th>
                <th>Kasir</th>
                <th>Gudang</th>
                <th>Waktu Buka</th>
                <th>Waktu Tutup</th>
                <th class="num">Modal Awal</th>
                <th class="num">Tunai</th>
                <th class="num">Non-Tunai</th>
                <th class="num">Total Omzet</th>
                <th class="center">Trx</th>
                <th class="num">Kas Aktual</th>
                <th class="num">Selisih</th>
                <th class="center">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($shifts as $s)
                <tr>
                    <td style="font-family: monospace; font-weight: bold;">{{ $s->shift_number }}</td>
                    <td>{{ $s->user?->name ?? 'Kasir' }}</td>
                    <td>{{ $s->warehouse?->name ?? '-' }}</td>
                    <td>{{ $s->opened_at ? $s->opened_at->format('d/m/Y H:i') : '-' }}</td>
                    <td>{{ $s->closed_at ? $s->closed_at->format('d/m/Y H:i') : 'Aktif' }}</td>
                    <td class="num">Rp {{ number_format((float)$s->starting_cash, 0, ',', '.') }}</td>
                    <td class="num">Rp {{ number_format((float)$s->cash_sales_total, 0, ',', '.') }}</td>
                    <td class="num">Rp {{ number_format((float)$s->non_cash_sales_total, 0, ',', '.') }}</td>
                    <td class="num" style="font-weight: bold;">Rp {{ number_format((float)$s->total_sales, 0, ',', '.') }}</td>
                    <td class="center">{{ $s->total_transactions }}</td>
                    <td class="num">{{ $s->cash_actual !== null ? 'Rp ' . number_format((float)$s->cash_actual, 0, ',', '.') : '-' }}</td>
                    <td class="num" style="font-weight: bold; color: {{ ((float)$s->cash_difference) < 0 ? '#b91c1c' : (((float)$s->cash_difference) > 0 ? '#1d4ed8' : '#15803d') }};">
                        {{ $s->cash_difference !== null ? 'Rp ' . number_format((float)$s->cash_difference, 0, ',', '.') : '-' }}
                    </td>
                    <td class="center">{{ $s->status === 'closed' ? 'Selesai' : 'Aktif' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="13" class="center" style="padding: 16px; color: #888;">
                        Tidak ada data shift kasir pada periode dan filter yang dipilih.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
