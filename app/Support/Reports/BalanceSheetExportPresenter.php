<?php

namespace App\Support\Reports;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class BalanceSheetExportPresenter
{
    public function rows(array $data, Carbon|string $asOf): Collection
    {
        $asOfDate = $asOf instanceof Carbon ? $asOf : Carbon::parse($asOf);
        $rows = collect();

        $rows->push(['NERACA']);
        $rows->push(['Per Tanggal: ' . $asOfDate->format('d M Y')]);
        $rows->push(['']);

        $this->appendSection($rows, 'A. ASET', $data['assets'] ?? [], 'TOTAL ASET', $data['asset_total'] ?? 0);
        $rows->push(['']);
        $this->appendSection($rows, 'B. KEWAJIBAN', $data['liabilities'] ?? [], 'TOTAL KEWAJIBAN', $data['liab_total'] ?? 0);
        $rows->push(['']);
        $this->appendSection($rows, 'C. MODAL', $data['equity'] ?? [], 'TOTAL MODAL', $data['equity_total'] ?? 0, [
            ['Laba Ditahan (s/d periode)', '', $data['retained_earnings'] ?? 0],
            ...(($data['current_earnings'] ?? 0) != 0 ? [['Laba Tahun Berjalan', '', $data['current_earnings']]] : []),
        ]);
        $rows->push(['']);
        $rows->push(['STATUS: ' . (($data['balanced'] ?? false) ? 'BALANCED' : 'TIDAK SEIMBANG')]);

        return $rows;
    }

    private function appendSection(Collection $rows, string $title, array $groups, string $totalLabel, float|int $total, array $extraRows = []): void
    {
        $rows->push([$title]);

        foreach ($groups as $group) {
            $rows->push([$group['parent']]);

            foreach ($group['items'] as $item) {
                $rows->push([
                    $item['coa']->code,
                    $item['coa']->name,
                    $item['balance'],
                ]);
            }

            $rows->push(['Subtotal ' . $group['parent'], '', $group['subtotal']]);
        }

        foreach ($extraRows as $row) {
            $rows->push($row);
        }

        $rows->push([$totalLabel, '', $total]);
    }
}