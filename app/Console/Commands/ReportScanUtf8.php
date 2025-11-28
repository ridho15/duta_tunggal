<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ReportScanUtf8 extends Command
{
    protected $signature = 'report:scan-utf8';
    protected $description = 'Generate cash flow report and scan for non-UTF8 strings in the report array';

    public function handle()
    {
        $this->info('Generating cash flow report...');
        $service = app(\App\Services\Reports\CashFlowReportService::class);
        $report = $service->generate(null, null, ['branches' => []]);

        $this->info('Scanning report array for non-UTF8 strings...');

        $errors = [];
        $this->scanRecursive($report, '', $errors);

        if (empty($errors)) {
            $this->info('No non-UTF8 strings found in report.');
            return 0;
        }

        $this->warn('Found non-UTF8 entries:');
        foreach ($errors as $err) {
            $this->line("- Path: {$err['path']}, sample (hex): {$err['hex']}");
        }

        return 0;
    }

    private function scanRecursive($value, $path, array & $errors)
    {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $this->scanRecursive($v, $path === '' ? (string)$k : $path . '.' . (string)$k, $errors);
            }
            return;
        }

        if (is_object($value)) {
            // convert object to array if possible
            $arr = (array)$value;
            foreach ($arr as $k => $v) {
                $this->scanRecursive($v, $path === '' ? (string)$k : $path . '.' . (string)$k, $errors);
            }
            return;
        }

        if (is_string($value)) {
            if (!mb_check_encoding($value, 'UTF-8')) {
                $hex = substr(chunk_split(bin2hex(substr($value, 0, 200)), 2, ' '), 0, 400);
                $errors[] = ['path' => $path, 'hex' => $hex];
            }
        }
    }
}
