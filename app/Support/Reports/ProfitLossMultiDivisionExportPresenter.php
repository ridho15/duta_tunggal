<?php

namespace App\Support\Reports;

use Carbon\Carbon;

class ProfitLossMultiDivisionExportPresenter
{
    public function rows(array $reportData): array
    {
        $divisions = $reportData['divisions'] ?? [];
        $divIds = array_column($divisions, 'id');
        $rows = [];

        $rows[] = ['PT. DUTA TUNGGAL'];
        $rows[] = ['PROFIT LOSS MULTIPLE BY DIVISION'];
        $rows[] = [
            'As Of : ' . Carbon::parse($reportData['period']['start'] ?? now())->format('d-F-Y')
            . ' to ' . Carbon::parse($reportData['period']['end'] ?? now())->format('d-F-Y'),
        ];
        $rows[] = [];

        $headerRow = ['AccountNo', 'AccountName'];
        foreach ($divisions as $division) {
            $name = strtoupper($division['nama'] ?? $division['kode'] ?? 'DIVISION');
            $headerRow[] = $name . ' Balance';
            $headerRow[] = $name . ' Vtc%';
        }
        $rows[] = $headerRow;

        $totalRevenue = $reportData['total_revenue'] ?? array_fill_keys($divIds, 0.0);

        foreach ($reportData['revenue_rows'] ?? [] as $row) {
            $rows[] = $this->reportRow($row, $divIds, $totalRevenue);
        }

        $rows[] = [];

        foreach ($reportData['cogs_rows'] ?? [] as $row) {
            $rows[] = $this->reportRow($row, $divIds, $totalRevenue);
        }

        if (empty($reportData['cogs_rows'])) {
            $rows[] = $this->makeRow('', 'Total Cost Of Goods Sold', array_fill_keys($divIds, 0.0), $totalRevenue, $divIds);
        }

        $rows[] = $this->makeRow('', 'Gross Profit', $reportData['gross_profit'] ?? [], $totalRevenue, $divIds);
        $rows[] = [];

        foreach ($reportData['opex_sections'] ?? [] as $section) {
            foreach ($section['rows'] ?? [] as $row) {
                $rows[] = $this->reportRow($row, $divIds, $totalRevenue);
            }
        }

        $rows[] = $this->makeRow('', 'Total Operating Expenses', $reportData['total_opex'] ?? [], $totalRevenue, $divIds);
        $rows[] = $this->makeRow('', 'Operating Profit (EBIT)', $reportData['operating_profit'] ?? [], $totalRevenue, $divIds);
        $rows[] = [];

        foreach ($reportData['other_rows'] ?? [] as $row) {
            $rows[] = $this->makeRow($row['code'] ?? '', '  ' . ($row['name'] ?? ''), $row['balances'] ?? [], $totalRevenue, $divIds);
        }

        $rows[] = $this->makeRow('', 'Net Profit', $reportData['net_profit'] ?? [], $totalRevenue, $divIds);

        return $rows;
    }

    private function reportRow(array $row, array $divIds, array $revenue): array
    {
        return match ($row['type'] ?? null) {
            'section_header' => ['', strtoupper($row['name'] ?? '')],
            'account' => $this->makeRow($row['code'] ?? '', '  ' . ($row['name'] ?? ''), $row['balances'] ?? [], $revenue, $divIds),
            'subtotal', 'total_revenue', 'total_cogs' => $this->makeRow('', $row['name'] ?? '', $row['balances'] ?? [], $revenue, $divIds),
            default => $this->makeRow($row['code'] ?? '', $row['name'] ?? '', $row['balances'] ?? [], $revenue, $divIds),
        };
    }

    private function makeRow(string $code, string $label, array $balances, array $revenue, array $divIds): array
    {
        $row = [$code, $label];

        foreach ($divIds as $divisionId) {
            $balance = (float) ($balances[$divisionId] ?? 0.0);
            $divisionRevenue = (float) ($revenue[$divisionId] ?? 0.0);
            $vtc = $divisionRevenue !== 0.0 ? round(($balance / $divisionRevenue) * 100, 2) : 0.0;

            $row[] = number_format($balance, 2, '.', ',');
            $row[] = number_format($vtc, 2, '.', ',');
        }

        return $row;
    }
}