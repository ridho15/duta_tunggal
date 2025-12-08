<!DOCTYPE html>
<html>
<head>
    <title>Laporan Vendor/Customer Summary</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 18px; }
        .header p { margin: 5px 0; color: #666; }
        .summary { margin-bottom: 20px; }
        .summary div { display: inline-block; margin-right: 20px; }
        .total-row { background-color: #e8f4f8; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Laporan Vendor/Customer Summary</h1>
        <p>Periode: {{ $start_date }} - {{ $end_date }}</p>
        <p>Tipe: {{ $type === 'customer' ? 'Customer (Penjualan)' : 'Vendor (Pembelian)' }}</p>
    </div>

    <div class="summary">
        <div><strong>Total Records:</strong> {{ $data->count() }}</div>
        <div><strong>Total Transaksi:</strong> {{ $data->sum('transaction_count') }}</div>
        <div><strong>Total Nilai:</strong> Rp {{ number_format($data->sum('total_amount'), 0, ',', '.') }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="text-center">No</th>
                <th>Kode</th>
                <th>Nama</th>
                <th class="text-center">Jumlah Transaksi</th>
                <th class="text-right">Total Nilai</th>
                <th class="text-right">Rata-rata</th>
                <th>Transaksi Terakhir</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $index => $item)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $item->code ?? '-' }}</td>
                <td>{{ $item->name ?? '-' }}</td>
                <td class="text-center">{{ $item->transaction_count ?? 0 }}</td>
                <td class="text-right">Rp {{ number_format($item->total_amount ?? 0, 0, ',', '.') }}</td>
                <td class="text-right">Rp {{ number_format($item->average_amount ?? 0, 0, ',', '.') }}</td>
                <td>{{ $item->last_transaction_date ? (\is_string($item->last_transaction_date) ? $item->last_transaction_date : $item->last_transaction_date->format('Y-m-d')) : '-' }}</td>
                <td>{{ $item->status_summary === 'active' ? 'Aktif' : 'Tidak Aktif' }}</td>
            </tr>
            @endforeach
            @if($data->count() > 0)
            <tr class="total-row">
                <td colspan="3" class="text-right"><strong>TOTAL</strong></td>
                <td class="text-center"><strong>{{ $data->sum('transaction_count') }}</strong></td>
                <td class="text-right"><strong>Rp {{ number_format($data->sum('total_amount'), 0, ',', '.') }}</strong></td>
                <td class="text-right"><strong>Rp {{ number_format($data->avg('average_amount'), 0, ',', '.') }}</strong></td>
                <td colspan="2"></td>
            </tr>
            @endif
        </tbody>
    </table>
</body>
</html>