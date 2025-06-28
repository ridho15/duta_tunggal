<?php

namespace App\Livewire;

use App\Models\Product;
use Livewire\Component;

class ModalProductFooter extends Component
{
    public $product_id;
    public function render()
    {
        $product = Product::find($this->product_id);
        return view('livewire.modal-product-footer', [
            'product' => $product
        ]);
    }
}
