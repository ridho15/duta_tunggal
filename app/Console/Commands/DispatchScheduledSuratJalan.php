<?php

namespace App\Console\Commands;

use App\Models\DeliveryOrder;
use App\Models\SuratJalan;
use App\Services\SuratJalanService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Keputusan Ko Handy 10 Nov 2026: stok terpotong pada TANGGAL KIRIM Surat Jalan. SJ yang terbit dengan tanggal kirim di masa depan
 * membiarkan DO-nya terkunci (Siap Kirim); perintah ini — dijadwalkan harian — memberangkatkan DO (Dikirim → Diterima, stok fisik
 * dipotong bertanggal tanggal kirim) begitu tanggalnya tiba. Idempoten: DO yang sudah berangkat dilewati.
 */
class DispatchScheduledSuratJalan extends Command
{
    protected $signature = 'sales:dispatch-scheduled-surat-jalan
        {--date= : Anggap hari ini tanggal ini (YYYY-MM-DD). Default: hari ini}
        {--dry-run : Tampilkan saja, jangan ubah apa pun}';

    protected $description = 'Berangkatkan DO dari Surat Jalan yang tanggal kirimnya sudah tiba (stok dipotong pada tanggal kirim)';

    public function handle(SuratJalanService $service): int
    {
        $today = $this->option('date') ? \Carbon\Carbon::parse($this->option('date'))->startOfDay() : now()->startOfDay();
        $dryRun = (bool) $this->option('dry-run');

        $pending = SuratJalan::query()
            ->where('status', SuratJalan::STATUS_ISSUED)
            ->where(fn ($q) => $q->whereDate('delivery_date', '<=', $today)
                ->orWhere(fn ($w) => $w->whereNull('delivery_date')->whereDate('issued_at', '<=', $today)))
            ->whereHas('deliveryOrder', fn ($q) => $q->whereIn('delivery_orders.status', ['approved', 'confirmed', 'partial']))
            ->orderBy('id')
            ->get();

        $this->info(sprintf('%s Surat Jalan jatuh tempo kirim s/d %s: %d', $dryRun ? '[DRY-RUN]' : '', $today->toDateString(), $pending->count()));

        $done = 0;
        $failed = 0;
        foreach ($pending as $suratJalan) {
            $doNumbers = $suratJalan->deliveryOrder()->whereIn('delivery_orders.status', ['approved', 'confirmed', 'partial'])
                ->pluck('do_number')->implode(', ');
            $this->line(" - {$suratJalan->sj_number} (kirim {$suratJalan->effectiveDeliveryDate()->toDateString()}): {$doNumbers}");

            if ($dryRun) {
                continue;
            }

            try {
                DB::transaction(function () use ($service, $suratJalan) {
                    $service->markDeliveryOrdersReceived($suratJalan);
                    // Jadwal kirim SJ ini ikut ditandai terkirim (tanpa memicu observer: DO sudah dipindah di atas).
                    $suratJalan->deliverySchedules()->where('status', 'pending')->get()
                        ->each(fn ($schedule) => $schedule->forceFill(['status' => 'delivered'])->saveQuietly());
                });
                $done++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('sales:dispatch-scheduled-surat-jalan gagal', ['sj' => $suratJalan->sj_number, 'error' => $e->getMessage()]);
                $this->error("   gagal: {$e->getMessage()}");
            }
        }

        if (! $dryRun) {
            $this->info("Diberangkatkan: {$done} SJ".($failed ? " · gagal: {$failed} (lihat log)" : ''));
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
