<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Daftar Produk</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            /* ⬅️ penting agar kolom tidak melebar liar */
            word-wrap: break-word;
        }

        th,
        td {
            border: 1px solid #888;
            padding: 6px 8px;
            text-align: left;
            vertical-align: top;
        }

        tr {
            page-break-inside: avoid;
        }

        thead {
            background-color: #f2f2f2;
        }

        th {
            font-weight: bold;
            background-color: #f0f0f0;
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <h2>Daftar Produk</h2>
    <table>
        <thead>
            <tr>
                <th>SKU</th>
                <th>Nama Product</th>
                <th>Cabang</th>
                <th>Kategori</th>
                <th>Harga Jual</th>
                <th>Harga Pokok</th>
                <th>Biaya</th>
                <th>Batas (%)</th>
                <th>Item Value</th>
                <th>Tipe Pajak</th>
                <th>Pajak (%)</th>
                <th>Kelipatan GB</th>
                <th>Jual Banyak</th>
                <th>Merek</th>
                <th>Satuan</th>
                <th>Konversi</th>
                <th>Deskripsi</th>
            </tr>
        </thead>
        <tbody>
            @foreach($listProduct as $product)
            <tr>
                <td>{{ $product->sku }}</td>
                <td>{{ $product->name }}</td>
                <td>({{ $product->cabang->kode }}) {{ $product->cabang->nama }}</td>
                <td>{{ $product->productCategory->name }}</td>
                <td>Rp {{ number_format($product->sell_price, 0, ',', '.') }}</td>
                <td>Rp {{ number_format($product->cost_price, 0, ',', '.') }}</td>
                <td>Rp {{ number_format($product->biaya, 0, ',', '.') }}</td>
                <td>{{ $product->harga_batas }}%</td>
                <td>Rp {{ number_format($product->item_value, 0, ',', '.') }}</td>
                <td>{{ $product->tipe_pajak }}</td>
                <td>{{ $product->pajak }}%</td>
                <td>{{ $product->jumlah_kelipatan_gudang_besar }}</td>
                <td>{{ $product->jumlah_jual_kategori_banyak }}</td>
                <td>{{ $product->kode_merek }}</td>
                <td>{{ $product->uom->name }}</td>
                <td>
                    @foreach ($product->unitConversions as $unitConversion)
                    {{ $unitConversion->nilai_konversi }} {{ $unitConversion->uom->name }}<br>
                    @endforeach
                </td>
                <td style="max-width: 120px; overflow: hidden;">{{ Str::limit($product->description, 200) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>