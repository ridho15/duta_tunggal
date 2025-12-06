<?php

namespace App\Models;

use App\Models\Scopes\CabangScope;
use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;

    // Status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_SENT = 'sent';
    const STATUS_PAID = 'paid';
    const STATUS_PARTIALLY_PAID = 'partially_paid';
    const STATUS_OVERDUE = 'overdue';

    // Status labels
    const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_SENT => 'Terkirim',
        self::STATUS_PAID => 'Lunas',
        self::STATUS_PARTIALLY_PAID => 'Dibayar Sebagian',
        self::STATUS_OVERDUE => 'Terlambat',
    ];

    protected $table = 'invoices';
    protected $fillable = [
        'invoice_number',
        'from_model_type',
        'from_model_id',
        'invoice_date',
        'subtotal',
        'tax',
        'other_fee',
        'total',
        'due_date',
        'status', // draft, sent, paid, partially_paid, overdue
        'ppn_rate',
        'dpp', //Dasar penggunaan pajak,
        'customer_name',
        'customer_phone',
        'supplier_name',
        'supplier_phone',
        'delivery_orders',
        'purchase_receipts',
        'accounts_payable_coa_id',
        'ppn_masukan_coa_id',
        'inventory_coa_id',
        'expense_coa_id',
        'revenue_coa_id',
        'ar_coa_id',
        'ppn_keluaran_coa_id',
        'biaya_pengiriman_coa_id',
        'cabang_id'
    ];

    protected $casts = [
        'delivery_orders' => 'array',
        'purchase_receipts' => 'array',
        'other_fee' => 'array',
    ];

    public function getOtherFeeTotalAttribute(): int
    {
        $fees = $this->other_fee;
        if (!$fees) return 0;
        $sum = 0;
        foreach ((array) $fees as $fee) {
            if (is_array($fee)) {
                $sum += (int) ($fee['amount'] ?? 0);
            } else {
                $sum += (int) $fee;
            }
        }
        return $sum;
    }

    public function invoiceItem()
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id');
    }

    public function fromModel()
    {
        return $this->morphTo(__FUNCTION__, 'from_model_type', 'from_model_id')->withDefault();
    }

    public function accountPayable()
    {
        return $this->hasOne(AccountPayable::class, 'invoice_id')->withDefault();
    }

    public function accountReceivable()
    {
        return $this->hasOne(AccountReceivable::class, 'invoice_id')->withDefault();
    }

    // Accessor to get customer data from related model
    public function getCustomerAttribute()
    {
        if ($this->fromModel) {
            if (method_exists($this->fromModel, 'customer')) {
                return $this->fromModel->customer()->first();
            } elseif (method_exists($this->fromModel, 'supplier')) {
                return $this->fromModel->supplier()->first();
            } elseif ($this->fromModel instanceof \App\Models\DeliveryOrder) {
                // For DeliveryOrder, get customer from related sales orders
                return $this->fromModel->salesOrders()->first()?->customer;
            }
        }
        return null;
    }

    public function getCustomerNameDisplayAttribute()
    {
        // First check if customer_name is filled manually
        if (!empty($this->customer_name)) {
            return $this->customer_name;
        }
        
        // Otherwise get from relationship
        $customer = $this->customer;
        return $customer ? $customer->name : '';
    }

    public function getCustomerPhoneDisplayAttribute()
    {
        // First check if customer_phone is filled manually
        if (!empty($this->customer_phone)) {
            return $this->customer_phone;
        }
        
        // Otherwise get from relationship
        $customer = $this->customer;
        return $customer ? $customer->phone : '';
    }

    public function getStatusLabelAttribute()
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function getDeliveryOrdersDisplayAttribute()
    {
        if (is_array($this->delivery_orders) && !empty($this->delivery_orders)) {
            $doNumbers = \App\Models\DeliveryOrder::whereIn('id', $this->delivery_orders)
                ->pluck('do_number')
                ->join(', ');
            return $doNumbers ?: 'Has ' . count($this->delivery_orders) . ' delivery order(s)';
        }
        return 'None';
    }

    public static function getStatusOptions()
    {
        return self::STATUS_LABELS;
    }

    protected static function booted()
    {
        static::addGlobalScope(new CabangScope());

        static::deleting(function ($invoice) {
            if ($invoice->isForceDeleting()) {
                $invoice->invoiceItem()->forceDelete();
            } else {
                $invoice->invoiceItem()->delete();
            }
        });

        static::restoring(function ($invoice) {
            $invoice->invoiceItem()->withTrashed()->restore();
        });
    }

    // Relationships
    public function accountsPayableCoa()
    {
        return $this->belongsTo(\App\Models\ChartOfAccount::class, 'accounts_payable_coa_id');
    }

    public function ppnMasukanCoa()
    {
        return $this->belongsTo(\App\Models\ChartOfAccount::class, 'ppn_masukan_coa_id');
    }

    public function inventoryCoa()
    {
        return $this->belongsTo(\App\Models\ChartOfAccount::class, 'inventory_coa_id');
    }

    public function expenseCoa()
    {
        return $this->belongsTo(\App\Models\ChartOfAccount::class, 'expense_coa_id');
    }

    public function revenueCoa()
    {
        return $this->belongsTo(\App\Models\ChartOfAccount::class, 'revenue_coa_id');
    }

    public function arCoa()
    {
        return $this->belongsTo(\App\Models\ChartOfAccount::class, 'ar_coa_id');
    }

    public function ppnKeluaranCoa()
    {
        return $this->belongsTo(\App\Models\ChartOfAccount::class, 'ppn_keluaran_coa_id');
    }

    public function biayaPengirimanCoa()
    {
        return $this->belongsTo(\App\Models\ChartOfAccount::class, 'biaya_pengiriman_coa_id');
    }

    public function cabang()
    {
        return $this->belongsTo(\App\Models\Cabang::class, 'cabang_id')->withDefault();
    }
}
