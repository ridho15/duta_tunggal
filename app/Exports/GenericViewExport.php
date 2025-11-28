<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;

class GenericViewExport implements FromView, ShouldAutoSize
{
    public function __construct(private View $view) {}

    public function view(): View
    {
        return $this->view;
    }
}
