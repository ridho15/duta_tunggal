<?php

namespace App\Exports;

use App\Models\AccountReceivable;
use App\Models\AccountPayable;
use App\Models\AgeingSchedule;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
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

        if ($this->type === 'receivables' || $this->type === 'both') {
            $sheets[] = new ReceivablesAgeingSheet($this->asOfDate, $this->cabangId);
        }

        if ($this->type === 'payables' || $this->type === 'both') {
            $sheets[] = new PayablesAgeingSheet($this->asOfDate, $this->cabangId);
        }

        return $sheets;
    }
}

class ReceivablesAgeingSheet implements FromCollection, WithHeadings, WithTitle
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
            'Customer',
            'Invoice Number',
            'Invoice Date',
            'Due Date',
            'Days Outstanding',
            'Total Amount',
            'Paid Amount',
            'Remaining Amount',
            'Aging Bucket',
            'Status',
            'Branch'
        ];
    }

    public function collection()
    {
        $query = AccountReceivable::with(['customer', 'invoice', 'cabang', 'ageingSchedule'])
            ->where('remaining', '>', 0);

        if ($this->cabangId) {
            $query->where('cabang_id', $this->cabangId);
        }

        return $query->get()->map(function ($receivable) {
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

            return [
                'Customer' => $receivable->customer->name ?? '-',
                'Invoice Number' => $receivable->invoice->no_invoice ?? '-',
                'Invoice Date' => $receivable->invoice->invoice_date ? Carbon::parse($receivable->invoice->invoice_date)->format('d/m/Y') : '-',
                'Due Date' => $receivable->invoice->due_date ? Carbon::parse($receivable->invoice->due_date)->format('d/m/Y') : '-',
                'Days Outstanding' => $daysOutstanding,
                'Total Amount' => 'Rp ' . number_format($receivable->total, 0, ',', '.'),
                'Paid Amount' => 'Rp ' . number_format($receivable->paid, 0, ',', '.'),
                'Remaining Amount' => 'Rp ' . number_format($receivable->remaining, 0, ',', '.'),
                'Aging Bucket' => $bucket,
                'Status' => $receivable->status,
                'Branch' => $receivable->cabang->nama ?? '-'
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
}

class PayablesAgeingSheet implements FromCollection, WithHeadings, WithTitle
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
            'Supplier',
            'Invoice Number',
            'Invoice Date',
            'Due Date',
            'Days Outstanding',
            'Total Amount',
            'Paid Amount',
            'Remaining Amount',
            'Aging Bucket',
            'Status'
        ];
    }

    public function collection()
    {
        $query = AccountPayable::with(['supplier', 'invoice', 'ageingSchedule']);

        if ($this->cabangId) {
            // AccountPayable doesn't have cabang_id, so we'll filter by invoice cabang if needed
            $query->whereHas('invoice', function($q) {
                $q->where('cabang_id', $this->cabangId);
            });
        }

        return $query->where('remaining', '>', 0)->get()->map(function ($payable) {
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

            return [
                'Supplier' => $payable->supplier->name ?? '-',
                'Invoice Number' => $payable->invoice->no_invoice ?? '-',
                'Invoice Date' => $payable->invoice->invoice_date ? Carbon::parse($payable->invoice->invoice_date)->format('d/m/Y') : '-',
                'Due Date' => $payable->invoice->due_date ? Carbon::parse($payable->invoice->due_date)->format('d/m/Y') : '-',
                'Days Outstanding' => $daysOutstanding,
                'Total Amount' => 'Rp ' . number_format($payable->total, 0, ',', '.'),
                'Paid Amount' => 'Rp ' . number_format($payable->paid, 0, ',', '.'),
                'Remaining Amount' => 'Rp ' . number_format($payable->remaining, 0, ',', '.'),
                'Aging Bucket' => $bucket,
                'Status' => $payable->status
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
}