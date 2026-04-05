<?php

namespace App\Exports;

use App\Models\Cabang;
use App\Services\Reports\AgeingReportService;
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
        $service = app(AgeingReportService::class);
        $cabangName = $this->cabangId ? Cabang::find($this->cabangId)->nama ?? 'All Branches' : 'All Branches';
        $filters = [
            'as_of_date' => $this->asOfDate,
            'cabang_id' => $this->cabangId,
            'report_type' => $this->type,
        ];
        $report = $service->generate($filters);

        $data = [
            'reportTitle' => 'Ageing Report - ' . ucfirst($this->type),
            'asOfDate' => $this->asOfDate->format('d F Y'),
            'cabangName' => $cabangName,
            'generatedAt' => now()->format('d/m/Y H:i:s'),
            'companyName' => config('app.name', 'Duta Tunggal ERP'),
            'type' => $this->type,
            'receivables' => [],
            'payables' => [],
            'summary' => [
                'receivables' => $service->summarizeBuckets($report['arRecords'], true),
                'payables' => $service->summarizeBuckets($report['apRecords'], true),
            ],
            'cashFlowProjection' => [
                30 => $service->projectCashFlow($filters, 30),
                60 => $service->projectCashFlow($filters, 60),
                90 => $service->projectCashFlow($filters, 90),
            ],
        ];

        // Get receivables data
        if ($this->type === 'receivables' || $this->type === 'both') {
            $data['receivables'] = $this->getReceivablesData($report['arRecords']);
        }

        // Get payables data
        if ($this->type === 'payables' || $this->type === 'both') {
            $data['payables'] = $this->getPayablesData($report['apRecords']);
        }

        return $data;
    }

    private function getReceivablesData($records)
    {
        return $records->values()->map(function ($receivable, $index) {
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
                'days_outstanding' => $receivable->days_outstanding_computed,
                'total_amount' => $receivable->total ?? 0,
                'paid_amount' => $receivable->paid ?? 0,
                'remaining_amount' => $receivable->remaining ?? 0,
                'aging_bucket' => $receivable->aging_bucket_computed,
                'status' => $receivable->status ?? 'Active',
                'branch' => $receivable->cabang->nama ?? '-',
                'sales_person' => $salesPerson,
                'notes' => $receivable->notes ?? '-'
            ];
        })->toArray();
    }

    private function getPayablesData($records)
    {
        return $records->values()->map(function ($payable, $index) {
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
                'days_outstanding' => $payable->days_outstanding_computed,
                'total_amount' => $payable->total ?? 0,
                'paid_amount' => $payable->paid ?? 0,
                'remaining_amount' => $payable->remaining ?? 0,
                'aging_bucket' => $payable->aging_bucket_computed,
                'status' => $payable->status ?? 'Active',
                'purchase_type' => $purchaseType,
                'procurement_person' => $procurementPerson,
                'notes' => $payable->notes ?? '-'
            ];
        })->toArray();
    }
}