<!DOCTYPE html>
<html>
<head>
    <title>Laporan Inventori</title>
    <style>
        body { font-family: Arial, sans-serif; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 12px; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .header { text-align: center; margin-bottom: 20px; }
        .filters { margin-bottom: 20px; }
        .status-normal { background-color: #d4edda; }
        .status-minimum { background-color: #fff3cd; }
        .status-habis { background-color: #f8d7da; }
        .aging-aktif { background-color: #d4edda; }
        .aging-slow { background-color: #fff3cd; }
        .aging-stagnan { background-color: #f8d7da; }
        .aging-dead { background-color: #e2e3e5; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Laporan Inventori</h1>
        @if($type === 'movement')
            <h2>History Movement Stok</h2>
            <p>Periode: {{ $start_date }} - {{ $end_date }}</p>
        @elseif($type === 'aging')
            <h2>Aging Stock Analysis</h2>
        @else
            <h2>Stok Barang per Gudang</h2>
        @endif
    </div>

    <div class="filters">
        @if($warehouse)
            <p><strong>Gudang:</strong> {{ $warehouse->name }}</p>
        @endif
        @if($product)
            <p><strong>Produk:</strong> {{ $product->name }}</p>
        @endif
    </div>

    @if($type === 'stock')
        <table>
            <thead>
                <tr>
                    <th>Gudang</th>
                    <th>Kode Produk</th>
                    <th>Nama Produk</th>
                    <th>Rak</th>
                    <th>Qty Fisik</th>
                    <th>Qty Reserved</th>
                    <th>Qty Minimum</th>
                    <th>Qty Tersedia Bebas</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data as $stock)
                    @php($statusClass = ($stock['Status'] ?? '') === 'Habis' ? 'status-habis' : (($stock['Status'] ?? '') === 'Minimum' ? 'status-minimum' : 'status-normal'))
                <tr>
                    <td>{{ $stock['Gudang'] ?? '-' }}</td>
                    <td>{{ $stock['Kode Produk'] ?? '-' }}</td>
                    <td>{{ $stock['Nama Produk'] ?? '-' }}</td>
                    <td>{{ $stock['Rak'] ?? '-' }}</td>
                    <td>{{ $stock['Qty Fisik'] ?? 0 }}</td>
                    <td>{{ $stock['Qty Reserved'] ?? 0 }}</td>
                    <td>{{ $stock['Qty Minimum'] ?? 0 }}</td>
                    <td>{{ $stock['Qty Tersedia Bebas'] ?? 0 }}</td>
                    <td class="{{ $statusClass }}">{{ $stock['Status'] ?? 'Normal' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @elseif($type === 'movement')
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Kode Produk</th>
                    <th>Nama Produk</th>
                    <th>Gudang</th>
                    <th>Rak</th>
                    <th>Tipe Movement</th>
                    <th>Quantity</th>
                    <th>Nilai</th>
                    <th>Referensi</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data as $movement)
                <tr>
                    <td>{{ $movement['Tanggal'] ?? '-' }}</td>
                    <td>{{ $movement['Kode Produk'] ?? '-' }}</td>
                    <td>{{ $movement['Nama Produk'] ?? '-' }}</td>
                    <td>{{ $movement['Gudang'] ?? '-' }}</td>
                    <td>{{ $movement['Rak'] ?? '-' }}</td>
                    <td>{{ $movement['Tipe Movement'] ?? '-' }}</td>
                    <td>{{ $movement['Quantity'] ?? 0 }}</td>
                    <td>Rp {{ number_format($movement['Nilai'] ?? 0, 0, ',', '.') }}</td>
                    <td>{{ $movement['Referensi'] ?? '-' }}</td>
                    <td>{{ $movement['Catatan'] ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @elseif($type === 'aging')
        <table>
            <thead>
                <tr>
                    <th>Gudang</th>
                    <th>Kode Produk</th>
                    <th>Nama Produk</th>
                    <th>Rak</th>
                    <th>Qty Fisik</th>
                    <th>Qty Reserved</th>
                    <th>Qty Tersedia Bebas</th>
                    <th>Terakhir Movement</th>
                    <th>Hari Aging</th>
                    <th>Kategori Aging</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data as $stock)
                    @php($agingClass = ($stock['Kategori Aging'] ?? '') === 'Aktif' ? 'aging-aktif' : (($stock['Kategori Aging'] ?? '') === 'Slow Moving' ? 'aging-slow' : (($stock['Kategori Aging'] ?? '') === 'Stagnan' ? 'aging-stagnan' : 'aging-dead')))
                <tr>
                    <td>{{ $stock['Gudang'] ?? '-' }}</td>
                    <td>{{ $stock['Kode Produk'] ?? '-' }}</td>
                    <td>{{ $stock['Nama Produk'] ?? '-' }}</td>
                    <td>{{ $stock['Rak'] ?? '-' }}</td>
                    <td>{{ $stock['Qty Fisik'] ?? 0 }}</td>
                    <td>{{ $stock['Qty Reserved'] ?? 0 }}</td>
                    <td>{{ $stock['Qty Tersedia Bebas'] ?? 0 }}</td>
                    <td>{{ $stock['Terakhir Movement'] ?? 'Tidak Ada Data' }}</td>
                    <td>{{ $stock['Hari Aging'] ?? 'Tidak Ada Data' }}</td>
                    <td class="{{ $agingClass }}">{{ $stock['Kategori Aging'] ?? 'Tidak Ada Movement' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>