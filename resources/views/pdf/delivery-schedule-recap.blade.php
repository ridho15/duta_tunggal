<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Jadwal Pengiriman</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333; margin: 20px; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 16px; }
        .header h2 { margin: 4px 0; font-size: 16px; text-transform: uppercase; }
        .header .title { font-size: 14px; font-weight: bold; margin-top: 8px; letter-spacing: 1px; }
        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .meta-table td { border: none; padding: 3px 6px; }
        .meta-table td:first-child { width: 180px; font-weight: bold; }
        .schedule-section { margin-bottom: 20px; border: 1px solid #aaa; border-radius: 4px; padding: 10px; }
        .schedule-header { font-weight: bold; font-size: 12px; background-color: #f0f0f0; padding: 6px 8px; margin: -10px -10px 10px -10px; border-bottom: 1px solid #aaa; }
        table.items-table { width: 100%; border-collapse: collapse; font-size: 10px; }
        table.items-table th { background-color: #1E40AF; color: #fff; padding: 5px 6px; text-align: left; }
        table.items-table td { border: 1px solid #ccc; padding: 4px 6px; vertical-align: top; }
        table.items-table tr:nth-child(even) td { background-color: #f0f4ff; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; }
        .badge-pending           { background-color: #fef9c3; color: #854d0e; }
        .badge-on_the_way        { background-color: #dbeafe; color: #1e40af; }
        .badge-delivered         { background-color: #d1fae5; color: #065f46; }
        .badge-partial_delivered { background-color: #e0e7ff; color: #3730a3; }
        .badge-failed            { background-color: #fee2e2; color: #991b1b; }
        .badge-cancelled         { background-color: #f3f4f6; color: #374151; }
        .no-data { text-align: center; padding: 20px; color: #888; font-style: italic; }
    </style>
</head>
<body>
    <div class="header">
        <h2>PT. DUTA TUNGGAL</h2>
        <div class="title">REKAP JADWAL PENGIRIMAN</div>
    </div>

    <table class="meta-table">
        <tr>
            <td>Driver</td>
            <td>: <strong>{{ implode(', ', $driverNames) }}</strong></td>
        </tr>
        <tr>
            <td>Periode</td>
            <td>: <strong>
                @if($dateFrom && $dateTo)
                    {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}
                @elseif($dateFrom)
                    Mulai {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }}
                @elseif($dateTo)
                    Sampai {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}
                @else
                    Semua Tanggal
                @endif
            </strong></td>
        </tr>
        <tr>
            <td>Total Jadwal</td>
            <td>: <strong>{{ $schedules->count() }}</strong></td>
        </tr>
        <tr>
            <td>Dicetak</td>
            <td>: {{ now()->format('d/m/Y H:i') }}</td>
        </tr>
    </table>

    @if($schedules->isEmpty())
        <div class="no-data">Tidak ada jadwal pengiriman untuk filter yang dipilih.</div>
    @else
        @php
            $statusLabels = [
                'pending'           => 'Menunggu Keberangkatan',
                'on_the_way'        => 'Sedang Berjalan',
                'delivered'         => 'Selesai / Terkirim',
                'partial_delivered' => 'Sebagian Terkirim',
                'failed'            => 'Gagal',
                'cancelled'         => 'Dibatalkan',
            ];
            $no = 1;
        @endphp

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width:30px">No</th>
                    <th style="width:90px">No. Jadwal</th>
                    <th style="width:110px">Tgl Keberangkatan</th>
                    <th style="width:90px">Driver</th>
                    <th style="width:70px">Kendaraan</th>
                    <th>Surat Jalan</th>
                    <th style="width:110px">Status</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($schedules as $schedule)
                    @php
                        $sjNumbers = $schedule->suratJalans->pluck('sj_number')->implode(', ') ?: '-';
                        $statusClass = 'badge-' . $schedule->status;
                        $statusLabel = $statusLabels[$schedule->status] ?? $schedule->status;
                    @endphp
                    <tr>
                        <td>{{ $no++ }}</td>
                        <td>{{ $schedule->schedule_number }}</td>
                        <td>{{ $schedule->scheduled_date ? \Carbon\Carbon::parse($schedule->scheduled_date)->format('d/m/Y H:i') : '-' }}</td>
                        <td>{{ $schedule->driver->name ?? '-' }}</td>
                        <td>{{ $schedule->vehicle->plate ?? '-' }}</td>
                        <td>{{ $sjNumbers }}</td>
                        <td><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                        <td>{{ $schedule->notes ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
