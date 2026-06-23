<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

DB::transaction(function () {
    $now = now();

    $baseProduct = DB::table('products')->orderBy('id')->first();
    if (!$baseProduct) {
        throw new RuntimeException('No products found for A2 fixture setup');
    }

    $baseSupplier = DB::table('suppliers')->orderBy('id')->first();
    if (!$baseSupplier) {
        throw new RuntimeException('No suppliers found for A2 fixture setup');
    }

    $productCode = 'A2-PW';
    $productSku = 'FG-OR-A2-PW-001';
    $productName = 'Produk OR A2 Playwright';

    $supplierLowCode = $productCode . '-LOW';
    $supplierHighCode = $productCode . '-HIGH';
    $supplierNullCode = $productCode . '-NUL';

    // Cleanup old fixture product and pivots
    $oldProduct = DB::table('products')->where('sku', $productSku)->first();
    if ($oldProduct) {
        DB::table('product_supplier')->where('product_id', $oldProduct->id)->delete();
        DB::table('products')->where('id', $oldProduct->id)->delete();
    }

    $insertProduct = (array) $baseProduct;
    unset($insertProduct['id']);
    $insertProduct['sku'] = $productSku;
    $insertProduct['name'] = $productName;
    $insertProduct['cost_price'] = 125000;
    $insertProduct['sell_price'] = 175000;
    $insertProduct['pajak'] = 11;
    $insertProduct['created_at'] = $now;
    $insertProduct['updated_at'] = $now;
    $insertProduct['deleted_at'] = null;

    $productId = DB::table('products')->insertGetId($insertProduct);

    $upsertSupplier = function (string $code, string $companyName) use ($baseSupplier, $now) {
        $existing = DB::table('suppliers')->where('code', $code)->first();
        if ($existing) {
            DB::table('suppliers')->where('id', $existing->id)->update([
                'perusahaan' => $companyName,
                'updated_at' => $now,
            ]);

            return $existing->id;
        }

        $row = (array) $baseSupplier;
        unset($row['id']);
        $row['code'] = $code;
        $row['perusahaan'] = $companyName;
        $row['email'] = strtolower(str_replace(' ', '.', $companyName)) . '@example.test';
        $row['created_at'] = $now;
        $row['updated_at'] = $now;
        $row['deleted_at'] = null;

        return DB::table('suppliers')->insertGetId($row);
    };

    $supplierLowId = $upsertSupplier($supplierLowCode, 'Supplier A2 Harga Termurah');
    $supplierHighId = $upsertSupplier($supplierHighCode, 'Supplier A2 Harga Tinggi');
    $supplierNullId = $upsertSupplier($supplierNullCode, 'Supplier A2 Tanpa Katalog');

    DB::table('product_supplier')->where('product_id', $productId)->delete();

    DB::table('product_supplier')->insert([
        [
            'product_id' => $productId,
            'supplier_id' => $supplierLowId,
            'supplier_price' => 100000,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'product_id' => $productId,
            'supplier_id' => $supplierHighId,
            'supplier_price' => 145000,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'product_id' => $productId,
            'supplier_id' => $supplierNullId,
            'supplier_price' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    echo "✅ A2 OR fixture ready\n";
    echo "   Product : {$productName} ({$productSku})\n";
    echo "   Suppliers: {$supplierLowCode}, {$supplierHighCode}, {$supplierNullCode}\n";
    echo "   Prices   : 100000, 145000, null (no catalog price should keep current item price)\n";
});
