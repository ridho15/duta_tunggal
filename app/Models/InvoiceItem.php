<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceItem extends Model
{
    use SoftDeletes, HasFactory,LogsGlobalActivity;
    protected $table = 'invoice_items';
    protected $fillable = [
        'invoice_id',
        'product_id',
        'quantity',
        'price',
        'total'
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id')->withDefault();
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }
}
