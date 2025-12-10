<?php

namespace App\Filament\Resources\Reports\HppResource\Pages;

use App\Exports\GenericViewExport;
use App\Filament\Resources\Reports\HppResource;
use App\Models\Cabang;
use App\Services\Reports\HppReportService;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class ViewHpp extends Page
{
    protected static string $resource = HppResource::class;
    protected static string $view = 'filament.pages.reports.hpp';

    public ?string $startDate = null;
    public ?string $endDate = null;
    public array $branchIds = [];

    public function mount(): void
    {
        $this->startDate = now()->startOfMonth()->toDateString();
        $this->endDate = now()->endOfMonth()->toDateString();
        $this->branchIds = [];
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
                        ->reactive(),
                    DatePicker::make('endDate')
                        ->label('Tanggal Selesai')
                        ->default(now()->endOfMonth())
                        ->reactive(),
                    Select::make('branchIds')
                        ->label('Cabang')
                        ->options(function () {
                            return Cabang::all()->mapWithKeys(function ($cabang) {
                                return [$cabang->id => "({$cabang->kode}) {$cabang->nama}"];
                            });
                        })
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->getSearchResultsUsing(function (string $search) {
                            return Cabang::where('nama', 'like', "%{$search}%")
                                ->orWhere('kode', 'like', "%{$search}%")
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(function ($cabang) {
                                    return [$cabang->id => "({$cabang->kode}) {$cabang->nama}"];
                                });
                        })
                        ->helperText('Kosongkan bila ingin menampilkan semua cabang'),
                ]),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_excel')
                ->label('Export Excel')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    return $this->export('excel');
                }),
            Action::make('export_pdf')
                ->label('Export PDF')
                ->icon('heroicon-m-document-text')
                ->color('danger')
                ->action(function () {
                    return $this->export('pdf');
                }),
        ];
    }

    public function getReportData(): array
    {
        $start = $this->startDate ? Carbon::parse($this->startDate)->toDateString() : null;
        $end = $this->endDate ? Carbon::parse($this->endDate)->toDateString() : null;

        return app(HppReportService::class)->generate($start, $end, [
            'branches' => array_filter($this->branchIds),
        ]);
    }

    public function export(string $format = 'excel')
    {
        $report = $this->getReportData();
        $filename = 'laporan-hpp-' . now()->format('Ymd_His');

        $branchNames = [];
        if (!empty($this->branchIds)) {
            $branchNames = Cabang::whereIn('id', $this->branchIds)
                ->orderBy('nama')
                ->pluck('nama')
                ->toArray();
        }

        $view = view('exports.hpp', [
            'report' => $report,
            'selectedBranches' => $branchNames,
        ]);

        if ($format === 'pdf') {
            try {
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
                        $payloadLog = $debugDir . '/hpp-pdf-payload-' . now()->format('Ymd_His') . '.log';
                        $payload = [
                            'time' => now()->toDateTimeString(),
                            'report_sample' => array_slice($sanitizedReport, 0, 20),
                            'branch_names' => $sanitizedBranches,
                        ];
                        // try to render HTML sample as well (may include problematic bytes)
                        $htmlSample = '';
                        try {
                            $htmlSample = view('exports.hpp', ['report' => $sanitizedReport, 'selectedBranches' => $sanitizedBranches])->render();
                        } catch (\Throwable $_) {
                            $htmlSample = '[render-failed]';
                        }
                        $hex = '';
                        if (is_string($htmlSample) && $htmlSample !== '[render-failed]') {
                            $hex = chunk_split(bin2hex(substr($htmlSample, 0, 400)), 2, ' ');
                        }
                        $logContent = "HPP PAYLOAD DUMP - " . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\nHTML_SAMPLE:\n" . $htmlSample . "\nHEX_SAMPLE:\n" . $hex . "\n";
                        file_put_contents($payloadLog, $logContent);
                    } catch (\Throwable $_) {
                        // ignore logging error
                    }
                }

                // Additional validation after sanitization
                if ($this->hasProblematicChars($sanitizedReport)) {
                    \Illuminate\Support\Facades\Log::error('HPP Export Error - Sanitized data still contains problematic characters');
                    throw new \Exception('Data masih mengandung karakter yang bermasalah setelah sanitization');
                }

                // Let DomPDF render the Blade view directly. Keep sanitization for safety.
                try {
                    $pdfObj = DomPdf::loadView('exports.hpp', [
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
                        $pdfObj2 = DomPdf::loadView('exports.hpp', [
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
                            $html = view('exports.hpp', ['report' => $fullySanitized, 'selectedBranches' => $fullySanitizedBranches])->render();
                            $hex = substr(chunk_split(bin2hex(substr($html, 0, 200)), 2, ' '), 0, 400);
                            $logfile = storage_path('logs/hpp-pdf-render-error-' . now()->format('Ymd_His') . '.log');
                            file_put_contents($logfile, "Exception: " . $e2->getMessage() . "\nHTML sample:\n" . $html . "\nHex:\n" . $hex);
                        } catch (\Throwable $_) {
                            // ignore logging errors
                        }

                        throw $e2;
                    }
                }

            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('HPP PDF Export Error', [
                    'error_message' => $e->getMessage(),
                    'error_trace' => $e->getTraceAsString(),
                    'report_keys' => array_keys($report),
                    'branch_count' => count($branchNames)
                ]);

                // Return user-friendly error
                throw new \Exception('Gagal membuat PDF: ' . $e->getMessage());
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

    private function sanitizeForPdfDeep(mixed $value): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = $this->sanitizeForPdfDeep($v);
            }
            return $out;
        }

        if (is_string($value)) {
            // More aggressive sanitization: strip all non-printable chars and ensure UTF-8
            $s = preg_replace('/[^\x20-\x7E\x0A\x0D\x09]/u', '', $value);
            $res = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
            if ($res === false) {
                $res = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
            }
            return $res;
        }

        return $value;
    }

    public function hasProblematicChars(mixed $data): bool
    {
        if (is_array($data)) {
            foreach ($data as $value) {
                if ($this->hasProblematicChars($value)) {
                    return true;
                }
            }
            return false;
        }

        if (is_string($data)) {
            // Check for invalid UTF-8 sequences or control characters (excluding common whitespace)
            return !mb_check_encoding($data, 'UTF-8') ||
                   preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $data) === 1;
        }

        return false;
    }

    private function getArrayStructure(array $data, $maxDepth = 3, $currentDepth = 0): array
    {
        if ($currentDepth >= $maxDepth) {
            return ['...max depth reached...'];
        }

        $structure = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $structure[$key] = $this->getArrayStructure($value, $maxDepth, $currentDepth + 1);
            } else {
                $structure[$key] = gettype($value) . '(' . (is_string($value) ? strlen($value) : 'N/A') . ')';
            }
        }
        return $structure;
    }

    private function getSampleValues(array $data, $maxSamples = 5): array
    {
        $samples = [];
        $count = 0;

        foreach ($data as $key => $value) {
            if ($count >= $maxSamples) break;

            if (is_array($value)) {
                $samples[$key] = $this->getSampleValues($value, 2);
            } elseif (is_string($value)) {
                $samples[$key] = substr($value, 0, 50) . (strlen($value) > 50 ? '...' : '');
            } elseif (is_numeric($value)) {
                $samples[$key] = $value;
            } else {
                $samples[$key] = gettype($value);
            }

            $count++;
        }

        return $samples;
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
