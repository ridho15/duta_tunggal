<?php

namespace App\Exports;

use App\Models\AccountReceivable;
use App\Models\AccountPayable;
use App\Models\AgeingSchedule;
use App\Models\Cabang;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use Carbon\Carbon;

class AgeingReportExport implements WithMultipleSheets
{
    protected $asOfDate;
    protected $cabangId;
    protected $type; // 'receivables', 'payables', or 'both'

    public function __construct($asOfDate = null, $cabangId = null, $type = 'both')
    {
        $this->asOfDate = $asOfDate ? Carbon::parse($asOfDate) : now();
        $this->cabangId = $cabangId;
        $this->type = $type;
    }

    public function sheets(): array
    {
        $sheets = [];

        // Always add summary sheet first
        $sheets[] = new SummarySheet($this->asOfDate, $this->cabangId, $this->type);

        if ($this->type === 'receivables' || $this->type === 'both') {
            $sheets[] = new ReceivablesAgeingSheet($this->asOfDate, $this->cabangId);
        }

        if ($this->type === 'payables' || $this->type === 'both') {
            $sheets[] = new PayablesAgeingSheet($this->asOfDate, $this->cabangId);
        }

        return $sheets;
    }
}

class SummarySheet implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths, WithEvents
{
    protected $asOfDate;
    protected $cabangId;
    protected $type;

    public function __construct($asOfDate, $cabangId, $type)
    {
        $this->asOfDate = $asOfDate;
        $this->cabangId = $cabangId;
        $this->type = $type;
    }

    public function title(): string
    {
        return 'Summary';
    }

    public function headings(): array
    {
        return [
            'Report Information',
            'Value',
            '',
            'Ageing Summary',
            'Current',
            '31-60 Days',
            '61-90 Days',
            '>90 Days',
            'Total'
        ];
    }

    public function collection()
    {
        $cabangName = $this->cabangId ? Cabang::find($this->cabangId)->nama ?? 'All Branches' : 'All Branches';

        $data = [
            ['Report Date', $this->asOfDate->format('d/m/Y'), '', 'Account Receivables', '', '', '', '', ''],
            ['Branch', $cabangName, '', 'Amount', '', '', '', '', ''],
            ['Generated On', now()->format('d/m/Y H:i:s'), '', '', '', '', '', '', ''],
            ['', '', '', '', '', '', '', '', ''],
        ];

        // Calculate receivables summary
        if ($this->type === 'receivables' || $this->type === 'both') {
            $receivablesData = $this->calculateAgeingSummary('receivables');
            $data[] = ['Account Receivables Summary', '', '', 'Count', $receivablesData['current']['count'], $receivablesData['31-60']['count'], $receivablesData['61-90']['count'], $receivablesData['>90']['count'], $receivablesData['total']['count']];
            $data[] = ['', '', '', 'Amount', 'Rp ' . number_format($receivablesData['current']['amount'], 0, ',', '.'), 'Rp ' . number_format($receivablesData['31-60']['amount'], 0, ',', '.'), 'Rp ' . number_format($receivablesData['61-90']['amount'], 0, ',', '.'), 'Rp ' . number_format($receivablesData['>90']['amount'], 0, ',', '.'), 'Rp ' . number_format($receivablesData['total']['amount'], 0, ',', '.')];
        }

        $data[] = ['', '', '', '', '', '', '', '', ''];

        // Calculate payables summary
        if ($this->type === 'payables' || $this->type === 'both') {
            $payablesData = $this->calculateAgeingSummary('payables');
            $data[] = ['Account Payables Summary', '', '', 'Count', $payablesData['current']['count'], $payablesData['31-60']['count'], $payablesData['61-90']['count'], $payablesData['>90']['count'], $payablesData['total']['count']];
            $data[] = ['', '', '', 'Amount', 'Rp ' . number_format($payablesData['current']['amount'], 0, ',', '.'), 'Rp ' . number_format($payablesData['31-60']['amount'], 0, ',', '.'), 'Rp ' . number_format($payablesData['61-90']['amount'], 0, ',', '.'), 'Rp ' . number_format($payablesData['>90']['amount'], 0, ',', '.'), 'Rp ' . number_format($payablesData['total']['amount'], 0, ',', '.')];
        }

        // Add cash flow projection
        $data[] = ['', '', '', '', '', '', '', '', ''];
        $data[] = ['Cash Flow Projection (Next 30 Days)', '', '', '', '', '', '', '', ''];

        $cashFlow30 = $this->calculateCashFlowProjection(30);
        $cashFlow60 = $this->calculateCashFlowProjection(60);
        $cashFlow90 = $this->calculateCashFlowProjection(90);

        $data[] = ['Expected Collections (30 days)', 'Rp ' . number_format($cashFlow30['receivables'], 0, ',', '.'), '', 'Expected Payments (30 days)', 'Rp ' . number_format($cashFlow30['payables'], 0, ',', '.'), '', 'Net Cash Flow', 'Rp ' . number_format($cashFlow30['receivables'] - $cashFlow30['payables'], 0, ',', '.'), ''];
        $data[] = ['Expected Collections (60 days)', 'Rp ' . number_format($cashFlow60['receivables'], 0, ',', '.'), '', 'Expected Payments (60 days)', 'Rp ' . number_format($cashFlow60['payables'], 0, ',', '.'), '', 'Net Cash Flow', 'Rp ' . number_format($cashFlow60['receivables'] - $cashFlow60['payables'], 0, ',', '.'), ''];
        $data[] = ['Expected Collections (90 days)', 'Rp ' . number_format($cashFlow90['receivables'], 0, ',', '.'), '', 'Expected Payments (90 days)', 'Rp ' . number_format($cashFlow90['payables'], 0, ',', '.'), '', 'Net Cash Flow', 'Rp ' . number_format($cashFlow90['receivables'] - $cashFlow90['payables'], 0, ',', '.'), ''];

        return collect($data);
    }

    private function calculateAgeingSummary($type)
    {
        $query = null;

        if ($type === 'receivables') {
            $query = AccountReceivable::with(['ageingSchedule', 'invoice'])->where('remaining', '>', 0);
            if ($this->cabangId) {
                $query->where('cabang_id', $this->cabangId);
            }
        } elseif ($type === 'payables') {
            $query = AccountPayable::with(['ageingSchedule', 'invoice'])->where('remaining', '>', 0);
            if ($this->cabangId) {
                $query->whereHas('invoice', function($q) {
                    $q->where('cabang_id', $this->cabangId);
                });
            }
        }

        if (!$query) return [
            'current' => ['count' => 0, 'amount' => 0],
            '31-60' => ['count' => 0, 'amount' => 0],
            '61-90' => ['count' => 0, 'amount' => 0],
            '>90' => ['count' => 0, 'amount' => 0],
            'total' => ['count' => 0, 'amount' => 0]
        ];

        $records = $query->get();
        $summary = [
            'current' => ['count' => 0, 'amount' => 0],
            '31-60' => ['count' => 0, 'amount' => 0],
            '61-90' => ['count' => 0, 'amount' => 0],
            '>90' => ['count' => 0, 'amount' => 0],
            'total' => ['count' => 0, 'amount' => 0]
        ];

        foreach ($records as $record) {
            $bucket = $this->calculateBucket($record);
            $summary[$bucket]['count']++;
            $summary[$bucket]['amount'] += $record->remaining;
            $summary['total']['count']++;
            $summary['total']['amount'] += $record->remaining;
        }

        return $summary;
    }

    private function calculateBucket($record)
    {
        $ageingSchedule = $record->ageingSchedule;
        $daysOutstanding = 0;

        if ($ageingSchedule && $ageingSchedule->days_outstanding) {
            $daysOutstanding = $ageingSchedule->days_outstanding;
        } elseif ($record->invoice && $record->invoice->invoice_date) {
            $invoiceDate = Carbon::parse($record->invoice->invoice_date);
            $daysOutstanding = $invoiceDate->diffInDays($this->asOfDate, false);
        }

        if ($daysOutstanding <= 30) return 'current';
        if ($daysOutstanding <= 60) return '31-60';
        if ($daysOutstanding <= 90) return '61-90';
        return '>90';
    }

    private function calculateCashFlowProjection($days)
    {
        $futureDate = $this->asOfDate->copy()->addDays($days);

        // Receivables expected to be collected
        $receivablesQuery = AccountReceivable::where('remaining', '>', 0)
            ->whereHas('invoice', function($q) use ($futureDate) {
                $q->where('due_date', '<=', $futureDate->format('Y-m-d'));
            });
        if ($this->cabangId) {
            $receivablesQuery->where('cabang_id', $this->cabangId);
        }
        $receivablesAmount = $receivablesQuery->sum('remaining');

        // Payables expected to be paid
        $payablesQuery = AccountPayable::where('remaining', '>', 0)
            ->whereHas('invoice', function($q) use ($futureDate) {
                $q->where('due_date', '<=', $futureDate->format('Y-m-d'));
            });
        if ($this->cabangId) {
            $payablesQuery->whereHas('invoice', function($q) {
                $q->where('cabang_id', $this->cabangId);
            });
        }
        $payablesAmount = $payablesQuery->sum('remaining');

        return [
            'receivables' => $receivablesAmount,
            'payables' => $payablesAmount
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2E75B6']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            2 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2E75B6']],
            ],
            5 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            ],
            6 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            ],
            8 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C00000']],
            ],
            9 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C00000']],
            ],
            11 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '548235']],
            ],
            'A1:I1' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]],
            'D5:I9' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 25,
            'B' => 20,
            'C' => 5,
            'D' => 20,
            'E' => 15,
            'F' => 15,
            'G' => 15,
            'H' => 15,
            'I' => 20,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;

                // Add borders to the summary tables
                $sheet->getStyle('A1:B3')->applyFromArray([
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                    ],
                ]);

                $sheet->getStyle('D5:I9')->applyFromArray([
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                    ],
                ]);

                $sheet->getStyle('A11:I13')->applyFromArray([
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                    ],
                ]);
            },
        ];
    }
}

class ReceivablesAgeingSheet implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths, WithEvents
{
    protected $asOfDate;
    protected $cabangId;

    public function __construct($asOfDate, $cabangId)
    {
        $this->asOfDate = $asOfDate;
        $this->cabangId = $cabangId;
    }

    public function title(): string
    {
        return 'Account Receivables Aging';
    }

    public function headings(): array
    {
        return [
            'No.',
            'Customer Name',
            'Contact Person',
            'Phone',
            'Email',
            'Invoice Number',
            'Invoice Date',
            'Due Date',
            'Payment Terms',
            'Days Outstanding',
            'Invoice Amount',
            'Paid Amount',
            'Remaining Amount',
            'Aging Bucket',
            'Status',
            'Branch',
            'Sales Person',
            'Notes'
        ];
    }

    public function collection()
    {
        $query = AccountReceivable::with([
            'customer',
            'invoice.fromModel', // Use polymorphic relationship instead of purchaseOrder
            'cabang',
            'ageingSchedule'
        ])->where('remaining', '>', 0);

        if ($this->cabangId) {
            $query->where('cabang_id', $this->cabangId);
        }

        $records = $query->get();
        $counter = 1;

        return $records->map(function ($receivable) use (&$counter) {
            $ageingSchedule = $receivable->ageingSchedule;
            $daysOutstanding = 0;
            $bucket = 'Current';

            if ($ageingSchedule) {
                $daysOutstanding = $ageingSchedule->days_outstanding ?? 0;
                $bucket = $ageingSchedule->bucket ?? 'Current';
            } else {
                // Calculate days outstanding if ageing schedule doesn't exist
                if ($receivable->invoice && $receivable->invoice->invoice_date) {
                    $invoiceDate = Carbon::parse($receivable->invoice->invoice_date);
                    $daysOutstanding = $invoiceDate->diffInDays($this->asOfDate, false);
                    $bucket = $this->calculateBucket($daysOutstanding);
                }
            }

            // Get sales person from related sales order through polymorphic relationship
            $salesPerson = '-';
            if ($receivable->invoice && $receivable->invoice->fromModel && $receivable->invoice->fromModel_type === 'App\\Models\\SalesOrder') {
                $salesPerson = $receivable->invoice->fromModel->sales_person ?? '-';
            }

            return [
                'No.' => $counter++,
                'Customer Name' => $receivable->customer->name ?? '-',
                'Contact Person' => $receivable->customer->contact_person ?? '-',
                'Phone' => $receivable->customer->phone ?? '-',
                'Email' => $receivable->customer->email ?? '-',
                'Invoice Number' => $receivable->invoice->no_invoice ?? '-',
                'Invoice Date' => $receivable->invoice->invoice_date ? Carbon::parse($receivable->invoice->invoice_date)->format('d/m/Y') : '-',
                'Due Date' => $receivable->invoice->due_date ? Carbon::parse($receivable->invoice->due_date)->format('d/m/Y') : '-',
                'Payment Terms' => $receivable->invoice->payment_terms ?? '-',
                'Days Outstanding' => $daysOutstanding,
                'Invoice Amount' => $receivable->total ? 'Rp ' . number_format($receivable->total, 0, ',', '.') : 'Rp 0',
                'Paid Amount' => $receivable->paid ? 'Rp ' . number_format($receivable->paid, 0, ',', '.') : 'Rp 0',
                'Remaining Amount' => $receivable->remaining ? 'Rp ' . number_format($receivable->remaining, 0, ',', '.') : 'Rp 0',
                'Aging Bucket' => $bucket,
                'Status' => $receivable->status ?? 'Active',
                'Branch' => $receivable->cabang->nama ?? '-',
                'Sales Person' => $salesPerson,
                'Notes' => $receivable->notes ?? '-'
            ];
        });
    }

    private function calculateBucket($days)
    {
        if ($days <= 30) return 'Current';
        if ($days <= 60) return '31–60';
        if ($days <= 90) return '61–90';
        return '>90';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2E75B6']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            'A1:R1' => [
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'font' => ['bold' => true],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,   // No.
            'B' => 25,  // Customer Name
            'C' => 20,  // Contact Person
            'D' => 15,  // Phone
            'E' => 25,  // Email
            'F' => 20,  // Invoice Number
            'G' => 12,  // Invoice Date
            'H' => 12,  // Due Date
            'I' => 15,  // Payment Terms
            'J' => 15,  // Days Outstanding
            'K' => 18,  // Invoice Amount
            'L' => 18,  // Paid Amount
            'M' => 18,  // Remaining Amount
            'N' => 12,  // Aging Bucket
            'O' => 10,  // Status
            'P' => 15,  // Branch
            'Q' => 15,  // Sales Person
            'R' => 30,  // Notes
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;

                // Add borders to all data
                $lastRow = $sheet->getHighestRow();
                $lastColumn = $sheet->getHighestColumn();

                $sheet->getStyle('A1:' . $lastColumn . $lastRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                    ],
                ]);

                // Center align specific columns
                $sheet->getStyle('A:A')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('G:H')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('J:J')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('N:O')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Right align amount columns
                $sheet->getStyle('K:M')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Auto filter
                $sheet->setAutoFilter('A1:' . $lastColumn . '1');
            },
        ];
    }
}

class PayablesAgeingSheet implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths, WithEvents
{
    protected $asOfDate;
    protected $cabangId;

    public function __construct($asOfDate, $cabangId)
    {
        $this->asOfDate = $asOfDate;
        $this->cabangId = $cabangId;
    }

    public function title(): string
    {
        return 'Account Payables Aging';
    }

    public function headings(): array
    {
        return [
            'No.',
            'Supplier Name',
            'Contact Person',
            'Phone',
            'Email',
            'Invoice Number',
            'Invoice Date',
            'Due Date',
            'Payment Terms',
            'Days Outstanding',
            'Invoice Amount',
            'Paid Amount',
            'Remaining Amount',
            'Aging Bucket',
            'Status',
            'Purchase Type',
            'Procurement Person',
            'Notes'
        ];
    }

    public function collection()
    {
        $query = AccountPayable::with([
            'supplier',
            'invoice.fromModel', // Use polymorphic relationship instead of purchaseOrder
            'ageingSchedule'
        ]);

        if ($this->cabangId) {
            $query->whereHas('invoice', function($q) {
                $q->where('cabang_id', $this->cabangId);
            });
        }

        $records = $query->where('remaining', '>', 0)->get();
        $counter = 1;

        return $records->map(function ($payable) use (&$counter) {
            $ageingSchedule = $payable->ageingSchedule;
            $daysOutstanding = 0;
            $bucket = 'Current';

            if ($ageingSchedule) {
                $daysOutstanding = $ageingSchedule->days_outstanding ?? 0;
                $bucket = $ageingSchedule->bucket ?? 'Current';
            } else {
                // Calculate days outstanding if ageing schedule doesn't exist
                if ($payable->invoice && $payable->invoice->invoice_date) {
                    $invoiceDate = Carbon::parse($payable->invoice->invoice_date);
                    $daysOutstanding = $invoiceDate->diffInDays($this->asOfDate, false);
                    $bucket = $this->calculateBucket($daysOutstanding);
                }
            }

            // Get procurement person from related purchase order through polymorphic relationship
            $procurementPerson = '-';
            $purchaseType = '-';
            if ($payable->invoice && $payable->invoice->fromModel && $payable->invoice->fromModel_type === 'App\\Models\\PurchaseOrder') {
                $procurementPerson = $payable->invoice->fromModel->procurement_person ?? '-';
                $purchaseType = $payable->invoice->fromModel->type ?? '-';
            }

            return [
                'No.' => $counter++,
                'Supplier Name' => $payable->supplier->name ?? '-',
                'Contact Person' => $payable->supplier->contact_person ?? '-',
                'Phone' => $payable->supplier->phone ?? '-',
                'Email' => $payable->supplier->email ?? '-',
                'Invoice Number' => $payable->invoice->no_invoice ?? '-',
                'Invoice Date' => $payable->invoice->invoice_date ? Carbon::parse($payable->invoice->invoice_date)->format('d/m/Y') : '-',
                'Due Date' => $payable->invoice->due_date ? Carbon::parse($payable->invoice->due_date)->format('d/m/Y') : '-',
                'Payment Terms' => $payable->invoice->payment_terms ?? '-',
                'Days Outstanding' => $daysOutstanding,
                'Invoice Amount' => $payable->total ? 'Rp ' . number_format($payable->total, 0, ',', '.') : 'Rp 0',
                'Paid Amount' => $payable->paid ? 'Rp ' . number_format($payable->paid, 0, ',', '.') : 'Rp 0',
                'Remaining Amount' => $payable->remaining ? 'Rp ' . number_format($payable->remaining, 0, ',', '.') : 'Rp 0',
                'Aging Bucket' => $bucket,
                'Status' => $payable->status ?? 'Active',
                'Purchase Type' => $purchaseType,
                'Procurement Person' => $procurementPerson,
                'Notes' => $payable->notes ?? '-'
            ];
        });
    }

    private function calculateBucket($days)
    {
        if ($days <= 30) return 'Current';
        if ($days <= 60) return '31–60';
        if ($days <= 90) return '61–90';
        return '>90';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C00000']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            'A1:Q1' => [
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'font' => ['bold' => true],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,   // No.
            'B' => 25,  // Supplier Name
            'C' => 20,  // Contact Person
            'D' => 15,  // Phone
            'E' => 25,  // Email
            'F' => 20,  // Invoice Number
            'G' => 12,  // Invoice Date
            'H' => 12,  // Due Date
            'I' => 15,  // Payment Terms
            'J' => 15,  // Days Outstanding
            'K' => 18,  // Invoice Amount
            'L' => 18,  // Paid Amount
            'M' => 18,  // Remaining Amount
            'N' => 12,  // Aging Bucket
            'O' => 10,  // Status
            'P' => 15,  // Purchase Type
            'Q' => 15,  // Procurement Person
            'R' => 30,  // Notes
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;

                // Add borders to all data
                $lastRow = $sheet->getHighestRow();
                $lastColumn = $sheet->getHighestColumn();

                $sheet->getStyle('A1:' . $lastColumn . $lastRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                    ],
                ]);

                // Center align specific columns
                $sheet->getStyle('A:A')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('G:H')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('J:J')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('N:O')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Right align amount columns
                $sheet->getStyle('K:M')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Auto filter
                $sheet->setAutoFilter('A1:' . $lastColumn . '1');
            },
        ];
    }
}