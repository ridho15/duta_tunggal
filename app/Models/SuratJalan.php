<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SuratJalan extends Model
{
    use SoftDeletes, HasFactory,LogsGlobalActivity;
    protected $table = 'surat_jalans';
    protected $fillable = [
        'sj_number',
        'issued_at',
        'signed_by',
        'status',
        'created_by',
        'document_path'
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

    // Helper method to get customers through delivery orders and sales orders
    public function customers()
    {
        return $this->hasManyThrough(
            Customer::class,
            SaleOrder::class,
            'id', // Foreign key on sale_orders table
            'id', // Foreign key on customers table
            'id', // Local key on surat_jalans table
            'customer_id' // Local key on sale_orders table
        )->join('delivery_sales_orders', 'sale_orders.id', '=', 'delivery_sales_orders.sales_order_id')
         ->join('surat_jalan_delivery_orders', 'delivery_sales_orders.delivery_order_id', '=', 'surat_jalan_delivery_orders.delivery_order_id')
         ->where('surat_jalan_delivery_orders.surat_jalan_id', $this->id ?? 0);
    }
}
