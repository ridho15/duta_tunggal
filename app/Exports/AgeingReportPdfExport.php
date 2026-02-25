<?php

namespace App\Exports;

use App\Models\AccountReceivable;
use App\Models\AccountPayable;
use App\Models\Cabang;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class AgeingReportPdfExport
{
    protected $asOfDate;
    protected $cabangId;
    protected $type;

    public function __construct($asOfDate = null, $cabangId = null, $type = 'both')
    {
        $this->asOfDate = $asOfDate ? Carbon::parse($asOfDate) : now();
        $this->cabangId = $cabangId;
        $this->type = $type;
    }

    public function generatePdf()
    {
        $data = $this->prepareData();

        $pdf = Pdf::loadView('exports.ageing-report-pdf', $data)
            ->setPaper('a4', 'landscape')
            ->setOptions([
                'defaultFont' => 'sans-serif',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'isJavascriptEnabled' => false,
                'dpi' => 96,
                'debugPng' => false,
                'debugKeepTemp' => false,
                'debugCss' => false,
                'debugLayout' => false,
                'debugLayoutLines' => false,
                'debugLayoutBlocks' => false,
                'debugLayoutInline' => false,
                'debugLayoutPaddingBox' => false,
            ]);

        return $pdf;
    }

    private function prepareData()
    {
        $cabangName = $this->cabangId ? Cabang::find($this->cabangId)->nama ?? 'All Branches' : 'All Branches';

        $data = [
            'reportTitle' => 'Ageing Report - ' . ucfirst($this->type),
            'asOfDate' => $this->asOfDate->format('d F Y'),
            'cabangName' => $cabangName,
            'generatedAt' => now()->format('d/m/Y H:i:s'),
            'companyName' => config('app.name', 'Duta Tunggal ERP'),
            'type' => $this->type,
            'receivables' => [],
            'payables' => [],
            'summary' => $this->calculateSummary(),
            'cashFlowProjection' => $this->calculateCashFlowProjection(),
        ];

        // Get receivables data
        if ($this->type === 'receivables' || $this->type === 'both') {
            $data['receivables'] = $this->getReceivablesData();
        }

        // Get payables data
        if ($this->type === 'payables' || $this->type === 'both') {
            $data['payables'] = $this->getPayablesData();
        }

        return $data;
    }

    private function getReceivablesData()
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

        return $query->get()->map(function ($receivable, $index) {
            $ageingSchedule = $receivable->ageingSchedule;
            $daysOutstanding = 0;
            $bucket = 'Current';

            if ($ageingSchedule) {
                $daysOutstanding = $ageingSchedule->days_outstanding ?? 0;
                $bucket = $ageingSchedule->bucket ?? 'Current';
            } else {
                if ($receivable->invoice && $receivable->invoice->invoice_date) {
                    $invoiceDate = Carbon::parse($receivable->invoice->invoice_date);
                    $daysOutstanding = $invoiceDate->diffInDays($this->asOfDate, false);
                    $bucket = $this->calculateBucket($daysOutstanding);
                }
            }

            // Get sales person from related sales order through polymorphic relationship
            $salesPerson = '-';
            if ($receivable->invoice && $receivable->invoice->fromModel && $receivable->invoice->from_model_type === 'App\\Models\\SaleOrder') {
                $salesPerson = $receivable->invoice->fromModel->sales_person ?? '-';
            }

            return [
                'no' => $index + 1,
                'customer_name' => $receivable->customer->name ?? '-',
                'contact_person' => $receivable->customer->contact_person ?? '-',
                'phone' => $receivable->customer->phone ?? '-',
                'email' => $receivable->customer->email ?? '-',
                'invoice_number' => $receivable->invoice->no_invoice ?? '-',
                'invoice_date' => $receivable->invoice->invoice_date ? Carbon::parse($receivable->invoice->invoice_date)->format('d/m/Y') : '-',
                'due_date' => $receivable->invoice->due_date ? Carbon::parse($receivable->invoice->due_date)->format('d/m/Y') : '-',
                'payment_terms' => $receivable->invoice->payment_terms ?? '-',
                'days_outstanding' => $daysOutstanding,
                'total_amount' => $receivable->total ?? 0,
                'paid_amount' => $receivable->paid ?? 0,
                'remaining_amount' => $receivable->remaining ?? 0,
                'aging_bucket' => $bucket,
                'status' => $receivable->status ?? 'Active',
                'branch' => $receivable->cabang->nama ?? '-',
                'sales_person' => $salesPerson,
                'notes' => $receivable->notes ?? '-'
            ];
        })->toArray();
    }

    private function getPayablesData()
    {
        $query = AccountPayable::with([
            'supplier',
            'invoice.fromModel', // Use polymorphic relationship instead of purchaseOrder
            'ageingSchedule'
        ])->where('remaining', '>', 0);

        if ($this->cabangId) {
            $query->whereHas('invoice', function($q) {
                $q->where('cabang_id', $this->cabangId);
            });
        }

        return $query->get()->map(function ($payable, $index) {
            $ageingSchedule = $payable->ageingSchedule;
            $daysOutstanding = 0;
            $bucket = 'Current';

            if ($ageingSchedule) {
                $daysOutstanding = $ageingSchedule->days_outstanding ?? 0;
                $bucket = $ageingSchedule->bucket ?? 'Current';
            } else {
                if ($payable->invoice && $payable->invoice->invoice_date) {
                    $invoiceDate = Carbon::parse($payable->invoice->invoice_date);
                    $daysOutstanding = $invoiceDate->diffInDays($this->asOfDate, false);
                    $bucket = $this->calculateBucket($daysOutstanding);
                }
            }

            // Get procurement person from related purchase order through polymorphic relationship
            $procurementPerson = '-';
            $purchaseType = '-';
            if ($payable->invoice && $payable->invoice->fromModel && $payable->invoice->from_model_type === 'App\\Models\\PurchaseOrder') {
                $procurementPerson = $payable->invoice->fromModel->procurement_person ?? '-';
                $purchaseType = $payable->invoice->fromModel->type ?? '-';
            }

            return [
                'no' => $index + 1,
                'supplier_name' => $payable->supplier->perusahaan ?? '-',
                'contact_person' => $payable->supplier->contact_person ?? '-',
                'phone' => $payable->supplier->phone ?? '-',
                'email' => $payable->supplier->email ?? '-',
                'invoice_number' => $payable->invoice->no_invoice ?? '-',
                'invoice_date' => $payable->invoice->invoice_date ? Carbon::parse($payable->invoice->invoice_date)->format('d/m/Y') : '-',
                'due_date' => $payable->invoice->due_date ? Carbon::parse($payable->invoice->due_date)->format('d/m/Y') : '-',
                'payment_terms' => $payable->invoice->payment_terms ?? '-',
                'days_outstanding' => $daysOutstanding,
                'total_amount' => $payable->total ?? 0,
                'paid_amount' => $payable->paid ?? 0,
                'remaining_amount' => $payable->remaining ?? 0,
                'aging_bucket' => $bucket,
                'status' => $payable->status ?? 'Active',
                'purchase_type' => $purchaseType,
                'procurement_person' => $procurementPerson,
                'notes' => $payable->notes ?? '-'
            ];
        })->toArray();
    }

    private function calculateSummary()
    {
        $summary = [
            'receivables' => [
                'Current' => ['count' => 0, 'amount' => 0],
                '31–60' => ['count' => 0, 'amount' => 0],
                '61–90' => ['count' => 0, 'amount' => 0],
                '>90' => ['count' => 0, 'amount' => 0],
                'total' => ['count' => 0, 'amount' => 0]
            ],
            'payables' => [
                'Current' => ['count' => 0, 'amount' => 0],
                '31–60' => ['count' => 0, 'amount' => 0],
                '61–90' => ['count' => 0, 'amount' => 0],
                '>90' => ['count' => 0, 'amount' => 0],
                'total' => ['count' => 0, 'amount' => 0]
            ]
        ];

        // Calculate receivables summary
        if ($this->type === 'receivables' || $this->type === 'both') {
            $receivablesQuery = AccountReceivable::with(['ageingSchedule', 'invoice'])->where('remaining', '>', 0);
            if ($this->cabangId) {
                $receivablesQuery->where('cabang_id', $this->cabangId);
            }

            foreach ($receivablesQuery->get() as $record) {
                $bucket = $this->calculateBucketForRecord($record);
                $summary['receivables'][$bucket]['count']++;
                $summary['receivables'][$bucket]['amount'] += $record->remaining;
                $summary['receivables']['total']['count']++;
                $summary['receivables']['total']['amount'] += $record->remaining;
            }
        }

        // Calculate payables summary
        if ($this->type === 'payables' || $this->type === 'both') {
            $payablesQuery = AccountPayable::with(['ageingSchedule', 'invoice'])->where('remaining', '>', 0);
            if ($this->cabangId) {
                $payablesQuery->whereHas('invoice', function($q) {
                    $q->where('cabang_id', $this->cabangId);
                });
            }

            foreach ($payablesQuery->get() as $record) {
                $bucket = $this->calculateBucketForRecord($record);
                $summary['payables'][$bucket]['count']++;
                $summary['payables'][$bucket]['amount'] += $record->remaining;
                $summary['payables']['total']['count']++;
                $summary['payables']['total']['amount'] += $record->remaining;
            }
        }

        return $summary;
    }

    private function calculateCashFlowProjection()
    {
        $projections = [];

        for ($days = 30; $days <= 90; $days += 30) {
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

            $projections[$days] = [
                'receivables' => $receivablesAmount,
                'payables' => $payablesAmount,
                'net_cash_flow' => $receivablesAmount - $payablesAmount
            ];
        }

        return $projections;
    }

    private function calculateBucket($days)
    {
        if ($days <= 30) return 'Current';
        if ($days <= 60) return '31–60';
        if ($days <= 90) return '61–90';
        return '>90';
    }

    private function calculateBucketForRecord($record)
    {
        $ageingSchedule = $record->ageingSchedule;
        $daysOutstanding = 0;

        if ($ageingSchedule && $ageingSchedule->days_outstanding) {
            $daysOutstanding = $ageingSchedule->days_outstanding;
        } elseif ($record->invoice && $record->invoice->invoice_date) {
            $invoiceDate = Carbon::parse($record->invoice->invoice_date);
            $daysOutstanding = $invoiceDate->diffInDays($this->asOfDate, false);
        }

        return $this->calculateBucket($daysOutstanding);
    }
}