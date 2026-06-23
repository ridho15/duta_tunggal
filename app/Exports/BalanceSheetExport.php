<?php

namespace App\Exports;

use App\Support\Reports\BalanceSheetExportPresenter;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Illuminate\Support\Collection;

class BalanceSheetExport implements FromCollection, ShouldAutoSize
{
    public function __construct(private array $data, private $asOf) {}

    public function collection(): Collection
    {
        return app(BalanceSheetExportPresenter::class)->rows($this->data, $this->asOf);
    }
}