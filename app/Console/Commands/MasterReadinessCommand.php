<?php

namespace App\Console\Commands;

use App\Services\MasterDataReadiness;
use Illuminate\Console\Command;

/**
 * Checklist kesiapan master data (driver, kendaraan, gudang, rak, akun kas/bank, IDR, PPN).
 * Hanya membaca; aman dijalankan kapan saja. Exit code 1 bila ada master KRITIS yang belum siap.
 */
class MasterReadinessCommand extends Command
{
    protected $signature = 'master:readiness
        {--cabang= : Periksa satu cabang saja (ID cabang)}
        {--json : Keluaran JSON}';

    protected $description = 'Periksa kesiapan master data sebelum UAT / go-live (hanya membaca)';

    public function handle(MasterDataReadiness $readiness): int
    {
        $result = $readiness->check($this->option('cabang') ? (int) $this->option('cabang') : null);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $result['ready'] ? self::SUCCESS : self::FAILURE;
        }

        $this->info('Kesiapan master data' . ($result['cabang'] ? " — cabang {$result['cabang']}" : ' — semua cabang aktif'));

        $rows = [];
        foreach ($result['checks'] as $check) {
            $status = match (true) {
                $check['state'] === 'siap' => 'SIAP',
                $check['state'] === 'sebagian' => 'SEBAGIAN (lihat keterangan)',
                $check['severity'] === MasterDataReadiness::CRITICAL => 'KOSONG (KRITIS)',
                default => 'KOSONG (peringatan)',
            };
            $rows[] = [$check['label'], $check['count'], $status];
        }
        $this->table(['Master', 'Jumlah', 'Status'], $rows);

        foreach ($result['checks'] as $check) {
            if ($check['state'] === 'siap') {
                continue;
            }
            $this->warn("{$check['label']}: {$check['hint']}");
            if ($check['missing_cabang'] !== []) {
                $this->line('   Cabang belum punya data: ' . implode('; ', $check['missing_cabang']));
            }
        }

        if ($result['ready']) {
            $this->info('Master kritis siap.');

            return self::SUCCESS;
        }

        $this->error('Ada master KRITIS yang belum siap. Lengkapi sebelum UAT / go-live.');

        return self::FAILURE;
    }
}
