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
                    <th>Qty Tersedia</th>
                    <th>Qty Dipesan</th>
                    <th>Qty Minimum</th>
                    <th>Qty On Hand</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data as $stock)
                    @php
                        $onHand = $stock->qty_available - $stock->qty_reserved;
                        $statusClass = $onHand <= 0 ? 'status-habis' : ($onHand <= $stock->qty_min ? 'status-minimum' : 'status-normal');
                    @endphp
                <tr>
                    <td>{{ $stock->warehouse->name ?? '-' }}</td>
                    <td>{{ $stock->product->code ?? '-' }}</td>
                    <td>{{ $stock->product->name ?? '-' }}</td>
                    <td>{{ $stock->rak->name ?? '-' }}</td>
                    <td>{{ $stock->qty_available }}</td>
                    <td>{{ $stock->qty_reserved }}</td>
                    <td>{{ $stock->qty_min }}</td>
                    <td>{{ $onHand }}</td>
                    <td class="{{ $statusClass }}">
                        @if($onHand <= 0)
                            Habis
                        @elseif($onHand <= $stock->qty_min)
                            Minimum
                        @else
                            Normal
                        @endif
                    </td>
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
                    @php
                        $reference = '-';
                        if ($movement->from_model_type && $movement->from_model_id) {
                            $modelName = class_basename($movement->from_model_type);
                            $reference = $modelName . ' #' . $movement->from_model_id;
                        }
                    @endphp
                <tr>
                    <td>{{ $movement->date }}</td>
                    <td>{{ $movement->product->code ?? '-' }}</td>
                    <td>{{ $movement->product->name ?? '-' }}</td>
                    <td>{{ $movement->warehouse->name ?? '-' }}</td>
                    <td>{{ $movement->rak->name ?? '-' }}</td>
                    <td>{{ $movement->type }}</td>
                    <td>{{ $movement->quantity }}</td>
                    <td>Rp {{ number_format($movement->value ?? 0, 0, ',', '.') }}</td>
                    <td>{{ $reference }}</td>
                    <td>{{ $movement->notes ?? '-' }}</td>
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
                    <th>Qty Tersedia</th>
                    <th>Qty Dipesan</th>
                    <th>Qty On Hand</th>
                    <th>Terakhir Movement</th>
                    <th>Hari Aging</th>
                    <th>Kategori Aging</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data as $stock)
                    @php
                        $onHand = $stock->qty_available - $stock->qty_reserved;

                        // Get last movement date
                        $lastMovement = \App\Models\StockMovement::where('product_id', $stock->product_id)
                            ->where('warehouse_id', $stock->warehouse_id)
                            ->orderBy('date', 'desc')
                            ->first();

                        $lastMovementDate = $lastMovement ? $lastMovement->date : null;
                        $agingDays = $lastMovement ? \Carbon\Carbon::parse($lastMovement->date)->diffInDays(now()) : 999;

                        if ($agingDays <= 30) {
                            $agingCategory = 'Aktif';
                            $agingClass = 'aging-aktif';
                        } elseif ($agingDays <= 90) {
                            $agingCategory = 'Slow Moving';
                            $agingClass = 'aging-slow';
                        } elseif ($agingDays <= 180) {
                            $agingCategory = 'Stagnan';
                            $agingClass = 'aging-stagnan';
                        } else {
                            $agingCategory = 'Dead Stock';
                            $agingClass = 'aging-dead';
                        }
                    @endphp
                <tr>
                    <td>{{ $stock->warehouse->name ?? '-' }}</td>
                    <td>{{ $stock->product->code ?? '-' }}</td>
                    <td>{{ $stock->product->name ?? '-' }}</td>
                    <td>{{ $stock->rak->name ?? '-' }}</td>
                    <td>{{ $stock->qty_available }}</td>
                    <td>{{ $stock->qty_reserved }}</td>
                    <td>{{ $onHand }}</td>
                    <td>{{ $lastMovementDate ?: 'Tidak Ada Data' }}</td>
                    <td>{{ $agingDays === 999 ? 'Tidak Ada Data' : $agingDays }}</td>
                    <td class="{{ $agingClass }}">{{ $agingCategory }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>