<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Barcode Produk</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 10px;
            margin: {{ ($ukuran_kertas ?? 'A4') === 'Sticker' ? '5px' : '20px' }};
        }

        .barcode-table {
            width: 100%;
            border-collapse: collapse;
        }

        .barcode-table td {
            width: {{ 100 / ($barcode_per_baris ?? 3) }}%;
            padding: {{ ($ukuran_barcode ?? 'medium') === 'small' ? '5px' : '10px' }};
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
            font-size: {{ $ukuran_barcode === 'small' ? '8px' : ($ukuran_barcode === 'large' ? '12px' : ($ukuran_barcode === 'extra_large' ? '14px' : '10px')) }};
        }

        .barcode-image {
            margin: 5px 0;
        }

        .barcode-code {
            font-size: {{ $ukuran_barcode === 'small' ? '7px' : ($ukuran_barcode === 'large' ? '10px' : ($ukuran_barcode === 'extra_large' ? '11px' : '9px')) }};
            margin-top: 3px;
        }

        .barcode-price {
            font-size: {{ $ukuran_barcode === 'small' ? '8px' : ($ukuran_barcode === 'large' ? '11px' : ($ukuran_barcode === 'extra_large' ? '12px' : '9px')) }};
            font-weight: bold;
            margin-top: 2px;
        }
    </style>
</head>

<body>

    <h3 style="text-align: center;">Label Barcode Produk</h3>
    
    @php
        // Get barcode parameters
        $ukuran = $ukuran_barcode ?? 'medium';
        $perBaris = (int) ($barcode_per_baris ?? 3);
        
        // Set barcode dimensions based on size
        $barcodeSettings = match($ukuran) {
            'small' => ['width' => 1, 'height' => 25],
            'medium' => ['width' => 1.5, 'height' => 35],
            'large' => ['width' => 2, 'height' => 45],
            'extra_large' => ['width' => 2.5, 'height' => 55],
            default => ['width' => 1.5, 'height' => 35]
        };
    @endphp

    <table class="barcode-table">
        @foreach($listProduct->chunk($perBaris) as $row)
        <tr>
            @foreach($row as $product)
            <td>
                <div class="barcode-label">
                    <div class="barcode-name">
                        {{ $ukuran === 'small' ? Str::limit($product->name, 15) : ($ukuran === 'extra_large' ? $product->name : Str::limit($product->name, 25)) }}
                    </div>
                    <div class="barcode-image">
                        {!! DNS1D::getBarcodeHTML($product->sku, 'C128', $barcodeSettings['width'], $barcodeSettings['height']) !!}
                    </div>
                    <div class="barcode-code">{{ $product->sku }}</div>
                    @if($ukuran !== 'small')
                    <div class="barcode-price">
                        Rp {{ number_format($product->sell_price, 0, ',', '.') }}
                    </div>
                    @endif
                </div>
            </td>
            @endforeach

            {{-- Fill empty cells if needed --}}
            @for ($i = 0; $i < $perBaris - $row->count(); $i++)
                <td></td>
            @endfor
        </tr>
        @endforeach
    </table>

</body>

</html>