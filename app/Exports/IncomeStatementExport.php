<?php

namespace App\Exports;

use App\Models\IncomeStatementItem;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Collection;

class IncomeStatementExport implements FromCollection, ShouldAutoSize, WithHeadings
{
    public function __construct(private string $startDate, private string $endDate, private ?int $cabangId = null) {}

    public function collection(): Collection
    {
        $query = IncomeStatementItem::query();

        $items = $query->get();

        $rows = collect();

        // Header information
        $rows->push(['Laporan Laba Rugi']);
        $rows->push(['Periode: ' . $this->startDate . ' - ' . $this->endDate]);

        if ($this->cabangId) {
            $cabang = \App\Models\Cabang::find($this->cabangId);
            if ($cabang) {
                $rows->push(['Cabang: (' . $cabang->kode . ') ' . $cabang->nama]);
            }
        }

        $rows->push(['']);
        $rows->push(['Tanggal Export: ' . now()->format('d M Y H:i:s')]);
        $rows->push(['']);

        // Add data rows
        foreach ($items as $item) {
            $rows->push([
                $item->account_name,
                $item->debit,
                $item->credit,
                $item->balance,
            ]);
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Akun',
            'Debit',
            'Kredit',
            'Saldo'
        ];
    }
}