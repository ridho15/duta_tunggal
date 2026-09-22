<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=duta_tunggal', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
echo "CONNECTED to duta_tunggal\n\n";

echo "=== ORDER REQUESTS ===\n";
foreach ($pdo->query("SELECT id, request_number, status, supplier_id, deleted_at FROM order_requests ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  #{$r['id']} {$r['request_number']} | {$r['status']} | supplier_id=" . ($r['supplier_id'] ?? 'NULL') . " | deleted=" . ($r['deleted_at'] ? 'YES' : 'no') . "\n";
}

echo "\n=== ORDER REQUEST ITEMS ===\n";
foreach ($pdo->query("SELECT ori.id, ori.order_request_id, ori.product_id, p.name pname, p.sku, ori.supplier_id, ori.quantity, ori.fulfilled_quantity, ori.unit_price, ori.original_price, ori.deleted_at FROM order_request_items ori LEFT JOIN products p ON p.id=ori.product_id ORDER BY ori.order_request_id, ori.id")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  item#{$r['id']} (OR#{$r['order_request_id']}): [{$r['sku']}] {$r['pname']} | supplier_id=" . ($r['supplier_id'] ?? 'NULL') . " | qty={$r['quantity']} fulfilled={$r['fulfilled_quantity']} | unit_price={$r['unit_price']} original_price=" . ($r['original_price'] ?? 'NULL') . " | deleted=" . ($r['deleted_at'] ? 'YES' : 'no') . "\n";
}

echo "\n=== PRODUCT_SUPPLIER CATALOG PRICES ===\n";
foreach ($pdo->query("SELECT ps.product_id, p.name pname, ps.supplier_id, s.perusahaan sname, ps.supplier_price FROM product_supplier ps LEFT JOIN products p ON p.id=ps.product_id LEFT JOIN suppliers s ON s.id=ps.supplier_id ORDER BY ps.product_id LIMIT 30")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  product#{$r['product_id']} {$r['pname']} + supplier#{$r['supplier_id']} {$r['sname']} = catalog_price {$r['supplier_price']}\n";
}

echo "\n=== PURCHASE ORDERS ===\n";
foreach ($pdo->query("SELECT po.id, po.po_number, po.status, po.supplier_id, s.perusahaan FROM purchase_orders po LEFT JOIN suppliers s ON s.id=po.supplier_id ORDER BY po.id")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  PO#{$r['id']} {$r['po_number']} | supplier: {$r['perusahaan']} | {$r['status']}\n";
}

echo "\n=== PURCHASE INVOICES ===\n";
foreach ($pdo->query("SELECT pi.id, pi.invoice_number, pi.status, pi.supplier_id, s.perusahaan FROM purchase_invoices pi LEFT JOIN suppliers s ON s.id=pi.supplier_id ORDER BY pi.id LIMIT 10")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  PI#{$r['id']} {$r['invoice_number']} | supplier: {$r['perusahaan']} | {$r['status']}\n";
}
