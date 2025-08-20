<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Barcode Produk - Small</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 8px;
            margin: 10px;
        }

        .barcode-table {
            width: 100%;
            border-collapse: collapse;
        }

        .barcode-table td {
            width: 20%;
            padding: 5px;
            text-align: center;
            vertical-align: top;
            border: 1px dashed #ccc;
        }

        .barcode-label {
            display: inline-block;
        }

        .barcode-name {
            font-weight: bold;
            margin-bottom: 3px;
            font-size: 7px;
        }

        .barcode-image {
            margin: 3px 0;
        }

        .barcode-code {
            font-size: 6px;
            margin-top: 2px;
        }
    </style>
</head>

<body>

    <h3 style="text-align: center; font-size: 12px;">Label Barcode Produk - Small Size</h3>

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
            
            $labelsPerRow = 5;
        @endphp
        
        @foreach($expandedProducts->chunk($labelsPerRow) as $row)
        <tr>
            @foreach($row as $prod)
            <td>
                <div class="barcode-label">
                    <div class="barcode-image">
                        {!! DNS1D::getBarcodeHTML($prod->sku, 'C128', 1, 25) !!}
                    </div>
                    <div class="barcode-code">{{ $prod->sku }}</div>
                    <div class="barcode-name">{{ Str::limit($prod->name, 15) }}</div>
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