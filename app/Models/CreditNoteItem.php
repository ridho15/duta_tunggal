<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditNoteItem extends Model
{
    protected $fillable = ['credit_note_id', 'invoice_item_id', 'product_id', 'description', 'quantity', 'unit_price', 'subtotal', 'tax_amount', 'total'];

    protected $casts = ['quantity' => 'decimal:2', 'unit_price' => 'decimal:4', 'subtotal' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total' => 'decimal:2'];

    public function creditNote()
    {
        return $this->belongsTo(CreditNote::class);
    }

    public function invoiceItem()
    {
        return $this->belongsTo(InvoiceItem::class)->withDefault();
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withDefault();
    }
}
