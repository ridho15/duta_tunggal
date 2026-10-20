<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reset database mode latihan ke data contoh (Tahap 3).
 * Hanya berjalan bila APP_TRAINING_MODE=true DAN nama database mengandung "latihan".
 */
class ResetTrainingDatabase extends Command
{
    protected $signature = 'latihan:reset {--force : Lewati konfirmasi}';

    protected $description = 'Kosongkan & isi ulang database MODE LATIHAN dengan data contoh (tidak bisa dipakai di produksi)';

    public function handle(): int
    {
        if (! config('training.enabled')) {
            $this->error('Mode latihan tidak aktif (APP_TRAINING_MODE=false). Perintah dibatalkan.');

            return self::FAILURE;
        }

        $database = (string) DB::connection()->getDatabaseName();
        $marker = (string) config('training.database_marker', 'latihan');

        if ($database === '' || ! str_contains(strtolower($database), strtolower($marker))) {
            $this->error("Nama database \"{$database}\" tidak mengandung \"{$marker}\". Demi keamanan, reset dibatalkan.");

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Hapus SEMUA data di database {$database} dan isi ulang data contoh?")) {
            return self::FAILURE;
        }

        $this->info("Mereset database latihan {$database}…");
        $this->call('migrate:fresh', ['--force' => true, '--seed' => true]);
        $this->info('Selesai. Database latihan berisi data contoh.');

        return self::SUCCESS;
    }
}
