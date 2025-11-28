<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ReportPdfDryRun extends Command
{
    protected $signature = 'report:pdf-dryrun';
    protected $description = 'Generate cash flow PDF and save to storage for debugging (dry run)';

    public function handle()
    {
        $this->info('Generating cash flow report...');
        $service = app(\App\Services\Reports\CashFlowReportService::class);
        $report = $service->generate(null, null, ['branches' => []]);

        $this->info('Sanitizing report...');
        $sanitized = $this->sanitizeForPdf($report);

        $this->info('Rendering PDF (this may throw) ...');
        try {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.cash-flow', [
                'report' => $sanitized,
                'selectedBranches' => [],
            ])
                ->setOptions(['defaultFont' => 'DejaVu Sans', 'isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true])
                ->setPaper('a4', 'portrait');

            $out = $pdf->output();
            $path = storage_path('app/cashflow-dryrun-' . now()->format('Ymd_His') . '.pdf');
            file_put_contents($path, $out);
            $this->info('PDF written to: ' . $path);
        } catch (\Throwable $e) {
            $this->error('PDF rendering failed: ' . $e->getMessage());
            $logfile = storage_path('logs/pdf-dryrun-error-' . now()->format('Ymd_His') . '.log');
            file_put_contents($logfile, "Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->error('Logged failure to: ' . $logfile);
            return 1;
        }

        return 0;
    }

    private function sanitizeForPdf($value)
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = $this->sanitizeForPdf($v);
            }
            return $out;
        }

        if (is_object($value)) {
            $arr = (array)$value;
            $out = [];
            foreach ($arr as $k => $v) {
                $out[$k] = $this->sanitizeForPdf($v);
            }
            return $out;
        }

        if (is_string($value)) {
            $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value);
            $res = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
            if ($res === false) {
                $res = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
            }
            return $res;
        }

        return $value;
    }
}
