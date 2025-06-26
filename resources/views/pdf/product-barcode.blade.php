<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Barcode Produk</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 10px;
            margin: 20px;
        }

        .barcode-table {
            width: 100%;
            border-collapse: collapse;
        }

        .barcode-table td {
            width: 33.33%;
            padding: 10px;
            text-align: center;
            vertical-align: top;
            border: 1px dashed #ccc;
        }

        .barcode-label {
            display: inline-block;
        }

        .barcode-name {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .barcode-image {
            margin: 5px 0;
        }

        .barcode-code {
            font-size: 9px;
            margin-top: 3px;
        }
    </style>
</head>

<body>

    <h3 style="text-align: center;">Label Barcode Produk</h3>

    <table class="barcode-table">
        @foreach($listProduct->chunk(3) as $row)
        <tr>
            @foreach($row as $product)
            <td>
                <div class="barcode-label">
                    <div class="barcode-name">{{ $product->name }}</div>
                    <div class="barcode-image">
                        {!! DNS1D::getBarcodeHTML($product->sku, 'C128', 1.5, 40) !!}
                    </div>
                    <div class="barcode-code">{{ $product->sku }}</div>
                </div>
            </td>
            @endforeach

            {{-- Jika kurang dari 3 kolom, isi sel kosong --}}
            @for ($i = 0; $i < 3 - $row->count(); $i++)
                <td></td>
                @endfor
        </tr>
        @endforeach
    </table>

</body>

</html>