<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Barcode Produk - Medium</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 10px;
            margin: 15px;
        }

        .barcode-table {
            width: 100%;
            border-collapse: collapse;
        }

        .barcode-table td {
            width: 25%;
            padding: 8px;
            text-align: center;
            vertical-align: top;
            border: 1px dashed #ccc;
        }

        .barcode-label {
            display: inline-block;
        }

        .barcode-name {
            font-weight: bold;
            margin-bottom: 4px;
            font-size: 9px;
        }

        .barcode-image {
            margin: 4px 0;
        }

        .barcode-code {
            font-size: 8px;
            margin-top: 3px;
        }

        .barcode-price {
            font-size: 8px;
            color: #666;
            margin-top: 2px;
        }
    </style>
</head>

<body>

    <h3 style="text-align: center;">Label Barcode Produk - Medium Size</h3>

    <table class="barcode-table">
        @php
            // Support both single product and multiple products
            $products = isset($listProduct) ? $listProduct : collect([$product]);
            $copiesPerProduct = $copies_per_product ?? $copies ?? 1;
            $expandedProducts = collect();
            
            foreach($products as $prod) {
                for($i = 0; $i < $copiesPerProduct; $i++) {
                    $expandedProducts->push($prod);
                }
            }
            
            $labelsPerRow = 4;
        @endphp
        
        @foreach($expandedProducts->chunk($labelsPerRow) as $row)
        <tr>
            @foreach($row as $prod)
            <td>
                <div class="barcode-label">
                    <div class="barcode-image">
                        {!! DNS1D::getBarcodeHTML($prod->sku, 'C128', 1.2, 35) !!}
                    </div>
                    <div class="barcode-code">{{ $prod->sku }}</div>
                    <div class="barcode-name">{{ Str::limit($prod->name, 20) }}</div>
                    <div class="barcode-price">Rp {{ number_format($prod->sell_price, 0, ',', '.') }}</div>
                </div>
            </td>
            @endforeach
            
            {{-- Fill remaining cells if needed --}}
            @for($i = count($row); $i < $labelsPerRow; $i++)
            <td></td>
            @endfor
        </tr>
        @endforeach
        
        {{-- Close incomplete row if needed --}}
        @if($totalLabels % $labelsPerRow != 0)
            @for($j = 0; $j < $labelsPerRow - ($totalLabels % $labelsPerRow); $j++)
                <td></td>
            @endfor
            </tr>
        @endif
    </table>
</body>

</html>