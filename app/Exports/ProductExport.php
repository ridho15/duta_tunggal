<?php

namespace App\Exports;

use App\Models\Product;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductExport implements FromView, ShouldAutoSize
{
    public $data;
    public function __construct($data)
    {
        $this->data = $data;
    }

    public function view(): View
    {
        $listProduct = Product::with(['cabang', 'productCategory'])->where('cabang_id', $this->data['cabang_id'])
            ->where('id', '>=', $this->data['dari_product_id'])
            ->where('id', '<=', $this->data['sampai_product_id'])
            ->get();
        return view('export.product', [
            'listProduct' => $listProduct
        ]);
    }
    public function collection()
    {
        return Product::where('cabang_id', $this->data['cabang_id'])
            ->where('id', '>=', $this->data['dari_product_id'])
            ->where('id', '<=', $this->data['sampai_product_id'])
            ->get();
    }

    public function headings(): array
    {
        return [];
    }
}
