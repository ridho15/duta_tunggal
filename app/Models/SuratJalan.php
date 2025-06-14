<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SuratJalan extends Model
{
    use SoftDeletes;
    protected $table = 'surat_jalans';
    protected $fillable = [
        'sj_number',
        'issued_at',
        'signed_by',
        'status',
        'created_by'
    ];

    public function deliveryOrder()
    {
        return $this->belongsToMany(DeliveryOrder::class, 'surat_jalan_delivery_orders', 'surat_jalan_id', 'delivery_order_id');
    }

    public function signedBy()
    {
        return $this->belongsTo(User::class, 'signed_by')->withDefault();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')->withDefault();
    }
}
