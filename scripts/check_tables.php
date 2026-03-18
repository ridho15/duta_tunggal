<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=duta_tunggal', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get all tables
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
sort($tables);
echo "ALL TABLES (" . count($tables) . "):\n";
foreach ($tables as $t) {
    $cnt = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    if ($cnt > 0) {
        echo "  $t: $cnt rows\n";
    }
}

echo "\nEMPTY tables: ";
$empty = [];
foreach ($tables as $t) {
    $cnt = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    if ($cnt == 0) {
        $empty[] = $t;
    }
}
echo count($empty) . " empty\n";
