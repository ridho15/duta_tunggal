<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Barcode Produk</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 10px;
            margin: 5px;
        }

        .barcode-wrapper {
            text-align: center;
            padding: 5px;
            border: 1px dashed #aaa;
        }

        .product-name {
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .barcode {
            margin: 5px 0;
            display: inline-block;
            text-align: center;
        }

        .sku {
            font-size: 10px;
            margin-top: 2px;
        }
    </style>
</head>

<body>
    <div class="barcode-wrapper">
        <div class="product-name">{{ $product->name }}</div>

        <div class="barcode">
            {!! DNS1D::getBarcodeHTML($product->sku, 'C128', 1.5, 40) !!}
        </div>

        <div class="sku">{{ $product->sku }}</div>
    </div>
</body>

</html>