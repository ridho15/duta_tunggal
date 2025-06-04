<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SuratJalan extends Model
{
    use SoftDeletes;
    protected $table = 'surat_jalans';
    protected $fillable = [
        'delivery_order_id',
        'sj_number',
        'issue_at',
        'signed_by',
        'status'
    ];

    public function deliveryOrder()
    {
        return $this->belongsTo(DeliveryOrder::class, 'delivery_order_id')->withDefault();
    }

    public function signedBy()
    {
        return $this->belongsTo(User::class, 'signed_by')->withDefault();
    }
}
