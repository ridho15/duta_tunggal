<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuotationItem extends Model
{
    use SoftDeletes, HasFactory,LogsGlobalActivity;
    protected $table = 'quotation_items';
    protected $fillable = [
        'quotation_id',
        'product_id',
        'notes',
        'quantity',
        'unit_price',
        'total_price',
        'discount',
        'tax',
    ];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class, 'quotation_id')->withDefault();
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }
}
