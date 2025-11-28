<?php

namespace App\Filament\Resources\Reports\CashFlowResource\Pages;

use App\Exports\GenericViewExport;
use App\Filament\Resources\Reports\CashFlowResource;
use App\Models\Cabang;
use App\Services\Reports\CashFlowReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class ViewCashFlow extends Page
{
    protected static string $resource = CashFlowResource::class;
    protected static string $view = 'filament.pages.reports.cash-flow';

    public ?string $startDate = null;
    public ?string $endDate = null;
    public array $branchIds = [];
    public ?string $method = 'direct'; // 'direct' or 'indirect'

    public function mount(): void
    {
        $this->startDate = $this->startDate ?? now()->startOfMonth()->toDateString();
        $this->endDate = $this->endDate ?? now()->endOfMonth()->toDateString();
        $this->method = $this->method ?? 'direct';
        $this->branchIds = $this->branchIds ?? [];

        $this->form->fill([
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'method' => $this->method,
            'branchIds' => $this->branchIds,
        ]);
    }

    public function updated($property): void
    {
        // This will be called when any reactive property is updated
        // We can use this to refresh the data
        $this->resetValidation();
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Filter')
                ->columns(2)
                ->schema([
                    DatePicker::make('startDate')
                        ->label('Tanggal Mulai')
                        ->default(now()->startOfMonth())
                        ->statePath('startDate'),
                    DatePicker::make('endDate')
                        ->label('Tanggal Selesai')
                        ->default(now()->endOfMonth())
                        ->statePath('endDate'),
                    Select::make('method')
                        ->label('Metode Arus Kas')
                        ->options([
                            'direct' => 'Metode Langsung (Direct Method)',
                            'indirect' => 'Metode Tidak Langsung (Indirect Method)',
                        ])
                        ->default('direct')
                        ->statePath('method'),
                    Select::make('branchIds')
                        ->label('Cabang')
                        ->options(fn () => Cabang::orderBy('nama')->pluck('nama', 'id'))
                        ->multiple()
                        ->searchable()
                        ->helperText('Kosongkan bila ingin menampilkan semua cabang')
                        ->statePath('branchIds'),
                ]),
        ];
    }

    public function getReportData(): array
    {
        $start = $this->startDate ? Carbon::parse($this->startDate)->toDateString() : null;
        $end = $this->endDate ? Carbon::parse($this->endDate)->toDateString() : null;

        try {
            return app(CashFlowReportService::class)->generate($start, $end, [
                'branches' => array_filter($this->branchIds),
                'method' => $this->method ?? 'direct',
            ]);
        } catch (\Throwable $e) {
            // Defensive: if the report service or DB is misconfigured (missing columns, etc.)
            // we should not let the entire Filament page crash. Log the exception and
            // return an empty, well-formed report structure so the UI can render safely.
            if (app()->environment('local') || app()->environment('testing')) {
                logger()->error('CashFlow report generation failed: ' . $e->getMessage(), [
                    'exception' => $e,
                ]);
            }

            return [
                'period' => ['start' => $start ?? now()->startOfMonth()->toDateString(), 'end' => $end ?? now()->endOfMonth()->toDateString()],
                'opening_balance' => 0,
                'net_change' => 0,
                'closing_balance' => 0,
                'sections' => [],
            ];
        }
    }

    public function export(string $format = 'excel')
    {
        $report = $this->getReportData();
        $filename = 'laporan-arus-kas-' . now()->format('Ymd_His');

        $branchNames = [];
        if (!empty($this->branchIds)) {
            $branchNames = Cabang::whereIn('id', $this->branchIds)
                ->orderBy('nama')
                ->pluck('nama')
                ->toArray();
        }

        $view = view('exports.cash-flow', [
            'report' => $report,
            'selectedBranches' => $branchNames,
        ]);

        if ($format === 'pdf') {
            // sanitize all strings in the report and branch names to avoid malformed UTF-8 bytes
            $sanitizedReport = $this->sanitizeForPdf($report);
            $sanitizedBranches = array_map([$this, 'sanitizeForPdf'], $branchNames);

            // defensive local-only logging: persist payload so we can inspect bytes when Filament path fails
            if (app()->isLocal()) {
                try {
                    $debugDir = storage_path('debug');
                    if (! is_dir($debugDir)) {
                        @mkdir($debugDir, 0755, true);
                    }
                    $payloadLog = $debugDir . '/pdf-payload-' . now()->format('Ymd_His') . '.log';
                    $payload = [
                        'time' => now()->toDateTimeString(),
                        'report_sample' => array_slice($sanitizedReport, 0, 20),
                        'branch_names' => $sanitizedBranches,
                    ];
                    // try to render HTML sample as well (may include problematic bytes)
                    $htmlSample = '';
                    try {
                        $htmlSample = view('exports.cash-flow', ['report' => $sanitizedReport, 'selectedBranches' => $sanitizedBranches])->render();
                    } catch (\Throwable $_) {
                        $htmlSample = '[render-failed]';
                    }
                    $hex = '';
                    if (is_string($htmlSample) && $htmlSample !== '[render-failed]') {
                        $hex = chunk_split(bin2hex(substr($htmlSample, 0, 400)), 2, ' ');
                    }
                    $logContent = "PAYLOAD DUMP - " . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\nHTML_SAMPLE:\n" . $htmlSample . "\nHEX_SAMPLE:\n" . $hex . "\n";
                    file_put_contents($payloadLog, $logContent);
                } catch (\Throwable $_) {
                    // ignore logging error
                }
            }

            // Let DomPDF render the Blade view directly. Keep sanitization for safety.
            try {
                $pdfObj = Pdf::loadView('exports.cash-flow', [
                    'report' => $sanitizedReport,
                    'selectedBranches' => $sanitizedBranches,
                ])
                    ->setOptions(['defaultFont' => 'DejaVu Sans', 'isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true])
                    ->setPaper('a4', 'portrait');

                // Generate PDF binary
                $pdfBinary = $pdfObj->output();

                // Ensure export directory exists
                $exportsDir = storage_path('app/exports');
                if (! is_dir($exportsDir)) {
                    @mkdir($exportsDir, 0755, true);
                }

                $tmpFilename = $filename . '.pdf';
                $tmpPath = $exportsDir . '/' . $tmpFilename;
                file_put_contents($tmpPath, $pdfBinary);

                // If caller expects JSON (Livewire/Filament XHR), return a safe download URL instead
                if (request()->wantsJson() || request()->ajax()) {
                    // use a simple route that serves files from storage/app/exports
                    $url = route('exports.download', ['filename' => $tmpFilename]);
                    return response()->json(['download_url' => $url]);
                }

                return response()->download($tmpPath, $tmpFilename)->deleteFileAfterSend(true);
            } catch (\Throwable $e) {
                // Retry with more aggressive sanitization
                $fullySanitized = $this->sanitizeForPdfDeep($report);
                $fullySanitizedBranches = array_map([$this, 'sanitizeForPdf'], $branchNames);
                try {
                    $pdfObj2 = Pdf::loadView('exports.cash-flow', [
                        'report' => $fullySanitized,
                        'selectedBranches' => $fullySanitizedBranches,
                    ])
                        ->setOptions(['defaultFont' => 'DejaVu Sans', 'isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true])
                        ->setPaper('a4', 'portrait');

                    $pdfBinary2 = $pdfObj2->output();
                    $exportsDir = storage_path('app/exports');
                    if (! is_dir($exportsDir)) {
                        @mkdir($exportsDir, 0755, true);
                    }
                    $tmpFilename = $filename . '.pdf';
                    $tmpPath = $exportsDir . '/' . $tmpFilename;
                    file_put_contents($tmpPath, $pdfBinary2);

                    if (request()->wantsJson() || request()->ajax()) {
                        $url = route('exports.download', ['filename' => $tmpFilename]);
                        return response()->json(['download_url' => $url]);
                    }

                    return response()->download($tmpPath, $tmpFilename)->deleteFileAfterSend(true);
                } catch (\Throwable $e2) {
                    // Log rendered HTML and a hex snippet for debugging
                    try {
                        $html = view('exports.cash-flow', ['report' => $fullySanitized, 'selectedBranches' => $fullySanitizedBranches])->render();
                        $hex = substr(chunk_split(bin2hex(substr($html, 0, 200)), 2, ' '), 0, 400);
                        $logfile = storage_path('logs/pdf-render-error-' . now()->format('Ymd_His') . '.log');
                        file_put_contents($logfile, "Exception: " . $e2->getMessage() . "\nHTML sample:\n" . $html . "\nHex:\n" . $hex);
                    } catch (\Throwable $_) {
                        // ignore logging errors
                    }

                    throw $e2;
                }
            }
        }

        return Excel::download(new GenericViewExport($view), $filename . '.xlsx');
    }

    public function getSelectedBranchNames(): array
    {
        if (empty($this->branchIds)) {
            return [];
        }

        return Cabang::whereIn('id', $this->branchIds)
            ->orderBy('nama')
            ->pluck('nama')
            ->toArray();
    }

    /**
     * Recursively sanitize data for PDF rendering.
     * Converts strings to valid UTF-8, strips control chars.
     *
     * @param mixed $value
     * @return mixed
     */
    private function sanitizeForPdf(mixed $value): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = $this->sanitizeForPdf($v);
            }
            return $out;
        }

        if (is_string($value)) {
            // remove ascii control chars except common whitespace
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

// helpers
if (! function_exists('strip_invalid_utf8')) {
    function strip_invalid_utf8(string $s): string
    {
        // remove ascii control chars except common whitespace
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s);
        // convert using iconv (fallback) then mb
        $res = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
        if ($res === false) {
            $res = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
        }
        return $res;
    }
}

