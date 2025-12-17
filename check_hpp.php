<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = app(\App\Services\Reports\HppReportService::class);
$report = $service->generate();

echo "=== LAPORAN HPP SAAT INI ===\n";
echo "Periode: " . $report['period']['start'] . " sampai " . $report['period']['end'] . "\n\n";

echo "BAHAN BAKU:\n";
echo "  - Persediaan Awal: Rp " . number_format($report['raw_materials']['opening'], 2, ',', '.') . "\n";
echo "  - Pembelian: Rp " . number_format($report['raw_materials']['purchases'], 2, ',', '.') . "\n";
echo "  - Total Tersedia: Rp " . number_format($report['raw_materials']['available'], 2, ',', '.') . "\n";
echo "  - Persediaan Akhir: Rp " . number_format($report['raw_materials']['closing'], 2, ',', '.') . "\n";
echo "  - Bahan Baku Digunakan: Rp " . number_format($report['raw_materials']['used'], 2, ',', '.') . "\n\n";

echo "BIAYA TENAGA KERJA LANGSUNG: Rp " . number_format($report['direct_labor'], 2, ',', '.') . "\n\n";

echo "BIAYA OVERHEAD PABRIK:\n";
echo "  - Total Overhead: Rp " . number_format($report['overhead']['total'], 2, ',', '.') . "\n";
if (!empty($report['overhead']['items'])) {
    foreach ($report['overhead']['items'] as $item) {
        echo "    - " . $item['label'] . ": Rp " . number_format($item['amount'], 2, ',', '.') . "\n";
    }
}
echo "\n";

echo "TOTAL BIAYA PRODUKSI: Rp " . number_format($report['production_cost'], 2, ',', '.') . "\n\n";

echo "BARANG DALAM PROSES (WIP):\n";
echo "  - Persediaan Awal WIP: Rp " . number_format($report['wip']['opening'], 2, ',', '.') . "\n";
echo "  - Persediaan Akhir WIP: Rp " . number_format($report['wip']['closing'], 2, ',', '.') . "\n\n";

echo "HARGA POKOK PRODUKSI (COGM): Rp " . number_format($report['cogm'], 2, ',', '.') . "\n";
