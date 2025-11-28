<?php

namespace App\Console\Commands;

use App\Services\AssetDepreciationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateMonthlyDepreciation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'asset:depreciate {--date= : Tanggal penyusutan (format: YYYY-MM-DD)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate penyusutan bulanan untuk semua aset aktif';

    /**
     * Execute the console command.
     */
    public function handle(AssetDepreciationService $service)
    {
        $this->info('Memulai proses penyusutan aset...');
        
        // Get date from option or use current date
        $dateString = $this->option('date');
        $date = $dateString ? Carbon::parse($dateString) : now();
        
        $this->info('Tanggal penyusutan: ' . $date->format('d F Y'));
        
        // Confirm before proceeding
        if (!$this->confirm('Apakah Anda yakin ingin melanjutkan?')) {
            $this->info('Proses dibatalkan.');
            return Command::SUCCESS;
        }
        
        try {
            $results = $service->generateAllMonthlyDepreciation($date);
            
            $this->info("\nProses selesai!");
            $this->info("Berhasil: {$results['success']} aset");
            $this->info("Gagal: {$results['failed']} aset");
            
            if (!empty($results['errors'])) {
                $this->error("\nError yang terjadi:");
                foreach ($results['errors'] as $error) {
                    $this->error("- {$error['asset']}: {$error['error']}");
                }
            }
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
