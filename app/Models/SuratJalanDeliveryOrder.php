<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SuratJalanDeliveryOrder extends Model
{
    use SoftDeletes, HasFactory,LogsGlobalActivity;
    protected $table = 'surat_jalan_delivery_orders';
    protected $fillable = [
        'surat_jalan_id',
        'delivery_order_id'
    ];

    public function suratJalan()
    {
        return $this->belongsTo(SuratJalan::class, 'surat_jalan_id')->withDefault();
    }

    public function deliveryOrder()
    {
        return $this->belongsTo(DeliveryOrder::class, 'delivery_order_id')->withDefault();
    }
}
