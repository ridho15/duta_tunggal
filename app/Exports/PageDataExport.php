<?php

namespace App\Exports;

use App\Support\DataTransfer;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/** Export data satu halaman (Owner / Super Admin). Kolom & relasi dari DataTransfer. */
class PageDataExport implements FromQuery, WithHeadings, WithMapping
{
    private array $relations;

    public function __construct(private string $pageKey, private ?string $from = null, private ?string $to = null)
    {
        $this->relations = DataTransfer::relations($pageKey);
    }

    public function query(): Builder
    {
        return DataTransfer::query($this->pageKey, $this->from, $this->to);
    }

    public function headings(): array
    {
        return DataTransfer::headings($this->pageKey);
    }

    public function map($row): array
    {
        return DataTransfer::row($this->pageKey, $row, $this->relations);
    }
}
