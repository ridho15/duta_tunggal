<?php

namespace App\Exports;

use App\Models\IncomeStatementItem;
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
        $items = IncomeStatementItem::all();

        $cabang = null;
        if ($this->cabangId) {
            $cabang = Cabang::find($this->cabangId);
        }

        return [
            'items' => $items,
            'startDate' => Carbon::parse($this->startDate)->format('d M Y'),
            'endDate' => Carbon::parse($this->endDate)->format('d M Y'),
            'cabang' => $cabang,
            'exportDate' => now()->format('d M Y H:i:s'),
        ];
    }
}