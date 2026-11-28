<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Export Excel sederhana untuk laporan Pusat Laporan: judul kolom + baris yang sudah jadi. */
class ArrayReportExport implements FromArray, WithHeadings, ShouldAutoSize, WithTitle
{
    public function __construct(private array $headings, private array $rows, private string $title = 'Laporan') {}

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return mb_substr($this->title, 0, 31);
    }
}
