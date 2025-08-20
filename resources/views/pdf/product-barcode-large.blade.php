<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Barcode Produk - Large</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 12px;
            margin: 20px;
        }

        .barcode-table {
            width: 100%;
            border-collapse: collapse;
        }

        .barcode-table td {
            width: 50%;
            padding: 15px;
            text-align: center;
            vertical-align: top;
            border: 1px dashed #ccc;
        }

        .barcode-label {
            display: inline-block;
        }

        .barcode-name {
            font-weight: bold;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .barcode-image {
            margin: 8px 0;
        }

        .barcode-code {
            font-size: 11px;
            margin-top: 5px;
        }

        .barcode-price {
            font-size: 12px;
            color: #333;
            margin-top: 5px;
            font-weight: bold;
        }

        .barcode-category {
            font-size: 10px;
            color: #666;
            margin-top: 3px;
        }
    </style>
</head>

<body>

    <h3 style="text-align: center;">Label Barcode Produk - Large Size</h3>

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
            
            $labelsPerRow = 2;
        @endphp
        
        @foreach($expandedProducts->chunk($labelsPerRow) as $row)
        <tr>
            @foreach($row as $prod)
            <td>
                <div class="barcode-label">
                    <div class="barcode-name">{{ $prod->name }}</div>
                    <div class="barcode-image">
                        {!! DNS1D::getBarcodeHTML($prod->sku, 'C128', 2, 60) !!}
                    </div>
                    <div class="barcode-code">{{ $prod->sku }}</div>
                    <div class="barcode-price">Rp {{ number_format($prod->sell_price, 0, ',', '.') }}</div>
                    <div class="barcode-category">{{ $prod->productCategory->name ?? '-' }}</div>
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