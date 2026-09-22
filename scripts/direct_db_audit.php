<?php
// Direct DB query - no model needed
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=duta_tunggal', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== ALL ORDER REQUESTS ===\n";
$stmt = $pdo->query("SELECT id, request_number, status, supplier_id, deleted_at FROM order_requests ORDER BY id");
$ors = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($ors as $row) {
    echo "  OR #{$row['id']}: {$row['request_number']}, status={$row['status']}, supplier_id=" . ($row['supplier_id'] ?? 'NULL') . ", deleted=" . ($row['deleted_at'] ? 'YES' : 'no') . "\n";
}

echo "\n=== ORDER REQUEST ITEMS ===\n";
$stmt = $pdo->query("SELECT ori.*, p.name as product_name, p.sku FROM order_request_items ori LEFT JOIN products p ON p.id = ori.product_id ORDER BY ori.order_request_id, ori.id");
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($items as $row) {
    echo "  item #{$row['id']} (OR #{$row['order_request_id']}): {$row['sku']} {$row['product_name']}\n";
    echo "    supplier_id=" . ($row['supplier_id'] ?? 'NULL') . ", qty={$row['quantity']}, unit_price={$row['unit_price']}, original_price=" . ($row['original_price'] ?? 'NULL') . "\n";
}

echo "\n=== SUPPLIER PRODUCT PRICES (product_supplier) ===\n";
$stmt = $pdo->query("SELECT ps.product_id, p.name prod_name, ps.supplier_id, s.perusahaan sup_name, ps.supplier_price FROM product_supplier ps LEFT JOIN products p ON p.id=ps.product_id LEFT JOIN suppliers s ON s.id=ps.supplier_id ORDER BY ps.product_id");
$pivots = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($pivots as $row) {
    echo "  product_id={$row['product_id']} ({$row['prod_name']}) | supplier_id={$row['supplier_id']} ({$row['sup_name']}) | catalog_price={$row['supplier_price']}\n";
}

echo "\n=== PURCHASE ORDERS ===\n";
$stmt = $pdo->query("SELECT po.id, po.po_number, po.status, po.supplier_id, s.perusahaan FROM purchase_orders po LEFT JOIN suppliers s ON s.id=po.supplier_id ORDER BY po.id");
$pos = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($pos as $row) {
    echo "  PO #{$row['id']}: {$row['po_number']}, supplier={$row['perusahaan']}, status={$row['status']}\n";
}
