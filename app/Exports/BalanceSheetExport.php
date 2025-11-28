<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Illuminate\Support\Collection;

class BalanceSheetExport implements FromCollection, ShouldAutoSize
{
    public function __construct(private array $data, private $asOf) {}

    public function collection(): Collection
    {
        $rows = collect();

        // Header
        $rows->push(['NERACA']);
        $rows->push(['Per Tanggal: ' . $this->asOf->format('d M Y')]);
        $rows->push(['']);

        // Assets
        $rows->push(['A. ASET']);
        foreach ($this->data['assets'] as $group) {
            $rows->push([$group['parent']]);
            foreach ($group['items'] as $item) {
                $rows->push([
                    $item['coa']->code,
                    $item['coa']->name,
                    $item['balance']
                ]);
            }
            $rows->push(['Subtotal ' . $group['parent'], '', $group['subtotal']]);
        }
        $rows->push(['TOTAL ASET', '', $this->data['asset_total']]);
        $rows->push(['']);

        // Liabilities
        $rows->push(['B. KEWAJIBAN']);
        foreach ($this->data['liabilities'] as $group) {
            $rows->push([$group['parent']]);
            foreach ($group['items'] as $item) {
                $rows->push([
                    $item['coa']->code,
                    $item['coa']->name,
                    $item['balance']
                ]);
            }
            $rows->push(['Subtotal ' . $group['parent'], '', $group['subtotal']]);
        }
        $rows->push(['TOTAL KEWAJIBAN', '', $this->data['liab_total']]);
        $rows->push(['']);

        // Equity
        $rows->push(['C. MODAL']);
        foreach ($this->data['equity'] as $group) {
            $rows->push([$group['parent']]);
            foreach ($group['items'] as $item) {
                $rows->push([
                    $item['coa']->code,
                    $item['coa']->name,
                    $item['balance']
                ]);
            }
            $rows->push(['Subtotal ' . $group['parent'], '', $group['subtotal']]);
        }
        $rows->push(['Laba Ditahan (s/d periode)', '', $this->data['retained_earnings']]);
        if (($this->data['current_earnings'] ?? 0) != 0) {
            $rows->push(['Laba Tahun Berjalan', '', $this->data['current_earnings']]);
        }
        $rows->push(['TOTAL MODAL', '', $this->data['equity_total']]);
        $rows->push(['']);

        // Status
        $rows->push(['STATUS: ' . ($this->data['balanced'] ? 'BALANCED' : 'TIDAK SEIMBANG')]);

        return $rows;
    }
}