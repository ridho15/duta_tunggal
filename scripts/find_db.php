<?php
// Try MAMP/XAMPP socket paths and different ports
$attempts = [
    ['host' => '127.0.0.1', 'port' => 3306, 'db' => 'duta_tunggal', 'user' => 'root', 'pass' => ''],
    ['host' => '127.0.0.1', 'port' => 3307, 'db' => 'duta_tunggal', 'user' => 'root', 'pass' => ''],
    ['host' => '127.0.0.1', 'port' => 8889, 'db' => 'duta_tunggal', 'user' => 'root', 'pass' => ''],
    ['host' => 'localhost', 'port' => 3306, 'db' => 'duta_tunggal', 'user' => 'root', 'pass' => 'root'],
    ['host' => '127.0.0.1', 'port' => 3306, 'db' => 'duta_tunggal', 'user' => 'root', 'pass' => 'root'],
];

// Try MAMP socket
$sockets = [
    '/Applications/MAMP/tmp/mysql/mysql.sock',
    '/tmp/mysql.sock',
    '/var/run/mysqld/mysql.sock',
    '/opt/homebrew/var/mysql/mysql.sock',
];

foreach ($sockets as $sock) {
    if (file_exists($sock)) {
        $attempts[] = ['unix_socket' => $sock, 'db' => 'duta_tunggal', 'user' => 'root', 'pass' => ''];
        $attempts[] = ['unix_socket' => $sock, 'db' => 'duta_tunggal', 'user' => 'root', 'pass' => 'root'];
    }
}

foreach ($attempts as $a) {
    try {
        if (isset($a['unix_socket'])) {
            $dsn = "mysql:unix_socket={$a['unix_socket']};dbname={$a['db']}";
        } else {
            $dsn = "mysql:host={$a['host']};port={$a['port']};dbname={$a['db']}";
        }
        $pdo = new PDO($dsn, $a['user'], $a['pass']);
        $cnt = $pdo->query("SELECT COUNT(*) FROM order_requests")->fetchColumn();
        $desc = isset($a['unix_socket']) ? $a['unix_socket'] : "{$a['host']}:{$a['port']}";
        echo "FOUND: $desc user={$a['user']} | order_requests: $cnt rows\n";
        if ($cnt > 0) {
            $rows = $pdo->query("SELECT id, request_number, status, supplier_id FROM order_requests ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                echo "  OR #{$r['id']}: {$r['request_number']} | {$r['status']} | sup=" . ($r['supplier_id'] ?? 'NULL') . "\n";
            }
        }
    } catch (\Throwable $e) {
        // silent
    }
}
echo "Done\n";
