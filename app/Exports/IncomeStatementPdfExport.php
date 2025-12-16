<?php

namespace App\Exports;

use App\Services\IncomeStatementService;
use App\Models\Cabang;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class IncomeStatementPdfExport
{
    protected string $startDate;
    protected string $endDate;
    protected ?int $cabangId;

    public function __construct(string $startDate, string $endDate, ?int $cabangId = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->cabangId = $cabangId;
    }

    public function generatePdf()
    {
        $data = $this->prepareData();

        $pdf = Pdf::loadView('exports.income-statement-pdf', $data)
            ->setPaper('a4', 'portrait')
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
        // Generate grouped data using the same service as the page
        $incomeStatementService = app(IncomeStatementService::class);
        $incomeData = $incomeStatementService->getGroupedByParent([
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'cabang_id' => $this->cabangId,
        ]);

        $cabang = null;
        if ($this->cabangId) {
            $cabang = Cabang::find($this->cabangId);
        }

        // Flatten the data structure for the PDF view
        $flatData = collect();

        $this->addGroupedSectionToFlatData($flatData, $incomeData, 'sales_revenue', 'Pendapatan Usaha');
        $this->addGroupedSectionToFlatData($flatData, $incomeData, 'cogs', 'Harga Pokok Penjualan');
        // Gross profit after COGS
        $flatData->push(['code' => '', 'description' => 'LABA KOTOR (Gross Profit)', 'amount' => $incomeData['gross_profit'] ?? 0, 'is_total' => true]);

        $this->addGroupedSectionToFlatData($flatData, $incomeData, 'operating_expenses', 'Biaya Operasional');
        // Operating profit after operating expenses
        $flatData->push(['code' => '', 'description' => 'LABA OPERASIONAL (Operating Profit)', 'amount' => $incomeData['operating_profit'] ?? 0, 'is_total' => true]);

        $this->addGroupedSectionToFlatData($flatData, $incomeData, 'other_income', 'Pendapatan Lain');
        $this->addGroupedSectionToFlatData($flatData, $incomeData, 'other_expense', 'Biaya Lain');
        // Profit before tax
        $flatData->push(['code' => '', 'description' => 'LABA SEBELUM PAJAK (Profit Before Tax)', 'amount' => $incomeData['profit_before_tax'] ?? 0, 'is_total' => true]);

        $this->addGroupedSectionToFlatData($flatData, $incomeData, 'tax_expense', 'Pajak');
        // Net profit after tax
        $flatData->push(['code' => '', 'description' => 'LABA BERSIH (Net Profit)', 'amount' => $incomeData['net_profit'] ?? 0, 'is_total' => true]);

        return [
            'items' => $flatData,
            'incomeData' => $incomeData, // Pass the full structured data for PDF template
            'startDate' => Carbon::parse($this->startDate)->format('d M Y'),
            'endDate' => Carbon::parse($this->endDate)->format('d M Y'),
            'cabang' => $cabang,
            'exportDate' => now()->format('d M Y H:i:s'),
        ];
    }

    private function addSectionToFlatData($flatData, array $incomeData, string $sectionKey, string $sectionName): void
    {
        // Fallback legacy method (kept for compatibility)
        if (!isset($incomeData[$sectionKey]['accounts'])) {
            return;
        }

        // Add section header
        $flatData->push([
            'code' => '',
            'description' => $sectionName,
            'amount' => '',
            'is_header' => true,
        ]);

        foreach ($incomeData[$sectionKey]['accounts'] as $account) {
            $flatData->push([
                'account_name' => $account['account_name'] ?? $account['name'] ?? ($account['code'] ?? ''),
                'debit' => $account['debit'] ?? $account['total_debit'] ?? 0,
                'credit' => $account['credit'] ?? $account['total_credit'] ?? 0,
                'balance' => $account['balance'] ?? 0,
                'is_header' => false,
            ]);
        }

        // Add total for the section
        $flatData->push([
            'account_name' => 'Total ' . $sectionName,
            'debit' => '',
            'credit' => '',
            'balance' => $incomeData[$sectionKey]['total'] ?? 0,
            'is_total' => true,
        ]);

        // Add empty row
        $flatData->push([
            'account_name' => '',
            'debit' => '',
            'credit' => '',
            'balance' => '',
            'is_spacer' => true,
        ]);
    }

    private function addGroupedSectionToFlatData($flatData, array $incomeData, string $sectionKey, string $sectionName): void
    {
        if (!isset($incomeData[$sectionKey]['grouped'])) {
            $this->addSectionToFlatData($flatData, $incomeData, $sectionKey, $sectionName);
            return;
        }

        // Add section header
        $flatData->push([
            'account_name' => $sectionName,
            'debit' => '',
            'credit' => '',
            'balance' => '',
            'is_header' => true,
        ]);

        $groups = $incomeData[$sectionKey]['grouped'];
        foreach ($groups as $group) {
            $parent = $group['account'] ?? null;
            $children = $group['children'] ?? collect();

            if ($parent) {
                $flatData->push([
                    'code' => $parent['code'] ?? '',
                    'description' => $parent['name'] ?? '',
                    'amount' => $parent['balance'] ?? 0,
                    'row_type' => 'parent',
                    'is_header' => false,
                ]);
            }

            foreach ($children as $child) {
                $flatData->push([
                    'code' => $child['code'] ?? '',
                    'description' => $child['name'] ?? '',
                    'amount' => $child['balance'] ?? 0,
                    'row_type' => 'child',
                    'is_header' => false,
                ]);
            }

            // No subtotal per parent - balance already includes children
        }

        // Section total
        $flatData->push([
            'code' => '',
            'description' => 'Total ' . $sectionName,
            'amount' => $incomeData[$sectionKey]['total'] ?? 0,
            'is_total' => true,
        ]);

        // Spacer
        $flatData->push([
            'code' => '',
            'description' => '',
            'amount' => '',
            'is_spacer' => true,
        ]);
    }
}