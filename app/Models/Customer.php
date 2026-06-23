<?php

namespace App\Models;

use App\Models\Scopes\CabangScope;
use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Customer extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'customers';
    protected $fillable = [
        'name',
        'code',
        'address',
        'telephone',
        'phone',
        'email',
        'perusahaan',
        'tipe', // PKP, PRI,
        'fax',
        'isSpecial', // ya / tidak
        'tempo_kredit', // harian
        'kredit_limit', // Rp
        'tipe_pembayaran', //'Bebas','COD (Bayar Lunas)','Kredit'
        'nik_npwp', // NIK / NPWP, -> string karena bisa mengandung karakter selain angka
        'keterangan',
        'cabang_id',
    ];

    protected static function booted()
    {
        // Removed CabangScope - customers are global entities that can buy from multiple branches
        // static::addGlobalScope(new CabangScope);

        // Ensure cabang_id is set when creating via Filament forms that may hide the field
        static::creating(function ($model) {
            if (empty($model->cabang_id)) {
                $model->cabang_id = Auth::user()?->cabang_id
                    ?? \App\Models\Cabang::first()?->id
                    ?? \App\Models\Cabang::create([
                        'kode' => 'CBG-' . uniqid(),
                        'nama' => 'Cabang Default',
                        'alamat' => 'Auto-created',
                        'telepon' => '0000000000',
                        'status' => true,
                    ])->id;
            }
        });
    }

    public function sales()
    {
        return $this->hasMany(SaleOrder::class, 'customer_id');
    }

    public function scopeFilter($query, $search)
    {
        if ($search) {
            return $query->where(function ($query) use ($search) {
                $query->where('name', 'LIKE', '%' . $search . '%')
                    ->orWhere('address', 'LIKE', '%' . $search . '%')
                    ->orWhere('phone', 'LIKE', '%' . $search . '%')
                    ->orWhere('email', 'LIKE', '%' . $search . '%');
            });
        }
        return $query;
    }

    public function deposit()
    {
        return $this->morphOne(Deposit::class, 'from_model')->withDefault();
    }

    public function depositLog()
    {
        return $this->morphMany(DepositLog::class, 'reference');
    }

    public function accountReceivables()
    {
        return $this->hasMany(AccountReceivable::class, 'customer_id');
    }

    public function invoices()
    {
        return $this->hasManyThrough(
            Invoice::class,
            SaleOrder::class,
            'customer_id', // Foreign key on sale_orders table
            'from_model_id', // Foreign key on invoices table
            'id', // Local key on customers table
            'id' // Local key on sale_orders table
        )->where('invoices.from_model_type', 'App\Models\SaleOrder');
    }

    /**
     * All sales invoices for this customer, regardless of source model type.
     * Includes invoices sourced from SaleOrder AND from DeliveryOrder.
     * Use this for accurate outstanding AR balance calculations.
     */
    public function allInvoices()
    {
        $customerId = $this->id;
        return Invoice::query()
            ->where(function ($q) use ($customerId) {
                // Invoices sourced directly from a SaleOrder
                $q->where('from_model_type', 'App\Models\SaleOrder')
                  ->whereIn('from_model_id', function ($sub) use ($customerId) {
                      $sub->select('id')->from('sale_orders')
                          ->where('customer_id', $customerId)
                          ->whereNull('deleted_at');
                  });
            })
            ->orWhere(function ($q) use ($customerId) {
                // Invoices sourced from a DeliveryOrder linked to this customer's SaleOrders
                $q->where('from_model_type', 'App\Models\DeliveryOrder')
                  ->whereIn('from_model_id', function ($sub) use ($customerId) {
                      $sub->select('delivery_orders.id')
                          ->from('delivery_orders')
                          ->join('delivery_sales_orders', 'delivery_orders.id', '=', 'delivery_sales_orders.delivery_order_id')
                          ->join('sale_orders', 'sale_orders.id', '=', 'delivery_sales_orders.sale_order_id')
                          ->where('sale_orders.customer_id', $customerId)
                          ->whereNull('sale_orders.deleted_at')
                          ->whereNull('delivery_orders.deleted_at');
                  });
            });
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id')->withDefault();
    }
}
