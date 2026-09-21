<?php

namespace App\Models;

use App\Models\InventoryStock;
use App\Models\Scopes\CabangScope;
use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SaleOrder extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;

    /**
     * SO yang boleh dibuatkan Delivery Order (selama masih ada sisa kuantitas).
     */
    public const DELIVERABLE_STATUSES = ['approved', 'confirmed', 'partially_delivered'];

    /**
     * Status SO yang dikelola otomatis dari progres pengiriman (SaleOrderStatusSynchronizer).
     */
    public const DELIVERY_MANAGED_STATUSES = ['approved', 'confirmed', 'partially_delivered', 'completed'];

    /**
     * SO yang invoice-nya boleh muncul di penerimaan customer. Invoice diterbitkan PER DO,
     * jadi SO yang baru terkirim sebagian (partially_delivered) harus ikut.
     */
    public const INVOICEABLE_STATUSES = ['confirmed', 'received', 'completed', 'partially_delivered'];

    /**
     * SO yang masih berjalan (sudah disetujui tetapi belum selesai) — dasar widget "SO Belum Selesai".
     * Draft / menunggu persetujuan belum berjalan; completed / closed / reject / canceled sudah berakhir.
     */
    public const OUTSTANDING_STATUSES = ['approved', 'confirmed', 'partial_confirmed', 'partially_delivered', 'request_close'];

    public const STATUS_LABELS = [
        'draft' => 'Draft',
        'request_approve' => 'Menunggu Persetujuan',
        'approved' => 'Disetujui',
        'confirmed' => 'Dikonfirmasi',
        'partial_confirmed' => 'Dikonfirmasi Sebagian',
        'partially_delivered' => 'Dikirim Sebagian',
        'completed' => 'Selesai',
        'received' => 'Diterima',
        'request_close' => 'Minta Ditutup',
        'closed' => 'Ditutup',
        'reject' => 'Ditolak',
        'canceled' => 'Dibatalkan',
    ];

    public const STATUS_COLORS = [
        'draft' => 'gray',
        'request_approve' => 'primary',
        'approved' => 'success',
        'confirmed' => 'success',
        'partial_confirmed' => 'warning',
        'partially_delivered' => 'warning',
        'completed' => 'success',
        'received' => 'primary',
        'request_close' => 'warning',
        'closed' => 'danger',
        'reject' => 'danger',
        'canceled' => 'danger',
    ];

    public static function statusLabel(?string $status): string
    {
        return self::STATUS_LABELS[$status ?? ''] ?? ($status ? ucfirst(str_replace('_', ' ', $status)) : '-');
    }

    public static function statusColor(?string $status): string
    {
        return self::STATUS_COLORS[$status ?? ''] ?? 'gray';
    }

    /**
     * SO yang masih bisa dikirim: status layak + masih ada item dengan sisa kuantitas yang
     * belum terikat ke DO manapun (terkirim ATAU sedang diproses).
     *
     * @param  array<int,int>  $alwaysIncludeIds  SO yang tetap ditampilkan (mis. sudah terhubung ke DO yang sedang diedit)
     */
    public function scopeDeliverable(Builder $query, array $alwaysIncludeIds = []): Builder
    {
        return $query->where(function (Builder $outer) use ($alwaysIncludeIds) {
            $outer->where(function (Builder $main) {
                $main->whereIn($main->getModel()->getTable() . '.status', self::DELIVERABLE_STATUSES)
                    ->whereHas('saleOrderItem', function (Builder $items) {
                        $items->whereRaw(\App\Services\SaleOrderDeliveryProgress::availableQuantitySql('sale_order_items') . ' > 0');
                    });
            });

            if ($alwaysIncludeIds !== []) {
                $outer->orWhereIn($outer->getModel()->getTable() . '.id', $alwaysIncludeIds);
            }
        });
    }
    protected $table = 'sale_orders';
    protected $casts = [
        'order_date' => 'datetime',
        'delivery_date' => 'datetime',
        'request_approve_at' => 'datetime',
        'request_close_at' => 'datetime',
        'approve_at' => 'datetime',
        'close_at' => 'datetime',
        'completed_at' => 'datetime',
        'reject_at' => 'datetime',
        'warehouse_confirmed_at' => 'datetime',
        'exchange_rate' => 'decimal:8',
        'is_backorder' => 'boolean',
        'backorder_approved_at' => 'datetime',
    ];
    protected $fillable = [
        'customer_id',
        'quotation_id',
        'so_number',
        'order_date',
        'status', // draft, request_approve, request_close, approved, closed, completed, confirmed, received, canceled, 'reject
        'delivery_date',
        'total_amount',
        'request_approve_by',
        'request_approve_at',
        'request_close_by',
        'request_close_at',
        'approve_by',
        'approve_at',
        'close_by',
        'close_at',
        'completed_at',
        'shipped_to',
        'reject_by',
        'reject_at',
        'reason_close',
        'tipe_pengiriman', // Ambil Sendiri, Kirim Langsung
        'tempo_pembayaran',
        'currency_id',
        'exchange_rate',
        'created_by',
        'warehouse_confirmed_at',
        'cabang_id',
        'notes',
        'is_backorder',
        'backorder_reason',
        'backorder_approved_by',
        'backorder_approved_at',
    ];


    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id')->withDefault();
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class, 'quotation_id')->withDefault();
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id')->withDefault();
    }

    public function saleOrderItem()
    {
        return $this->hasMany(SaleOrderItem::class, 'sale_order_id');
    }

    /**
     * Alias for saleOrderItem() to support legacy/consumers expecting ->items
     */
    public function items()
    {
        return $this->saleOrderItem();
    }

    public function requestApproveBy()
    {
        return $this->belongsTo(User::class, 'request_approve_by')->withDefault();
    }

    public function requestCloseBy()
    {
        return $this->belongsTo(User::class, 'request_close_by')->withDefault();
    }

    public function approveBy()
    {
        return $this->belongsTo(User::class, 'approve_by')->withDefault();
    }

    public function closeBy()
    {
        return $this->belongsTo(User::class, 'close_by')->withDefault();
    }

    public function rejectBy()
    {
        return $this->belongsTo(User::class, 'reject_by')->withDefault();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')->withDefault();
    }

    public function deliveryOrder()
    {
        return $this->belongsToMany(DeliveryOrder::class, 'delivery_sales_orders', 'sales_order_id', 'delivery_order_id');
    }

    public function deliverySalesOrder()
    {
        return $this->hasMany(DeliverySalesOrder::class, 'sales_order_id');
    }

    /**
     * Warehouse confirmations linked to this SO via polymorphic relationship.
     * Previously hasOne('sale_order_id') — now morphMany so multiple WCs per SO are supported.
     */
    public function warehouseConfirmations()
    {
        return $this->morphMany(WarehouseConfirmation::class, 'confirmable');
    }

    /** Alias for backward-compatible single-record access. */
    public function warehouseConfirmation()
    {
        return $this->morphOne(WarehouseConfirmation::class, 'confirmable')->latestOfMany();
    }

    public function purchaseOrder()
    {
        return $this->morphMany(PurchaseOrder::class, 'refer_model');
    }

    public function depositLog()
    {
        return $this->morphMany(DepositLog::class, 'reference');
    }

    /**
     * Inverse relation to invoices created from this Sale Order.
     * Filament resources expect a `salesInvoices` relation for eager-loading.
     */
    public function salesInvoices()
    {
        // Use the same polymorphic column names as Invoice::fromModel()
        // Invoice::fromModel() uses ('from_model_type', 'from_model_id') explicitly,
        // so provide those here to avoid Laravel looking for `fromModel_id`.
        return $this->morphMany(\App\Models\Invoice::class, 'fromModel', 'from_model_type', 'from_model_id');
    }

    /**
     * Ada item dengan stok kurang? Memakai StockAvailability (sadar reservasi milik SO ini dan kebijakan cabang, D3).
     */
    public function hasInsufficientStock()
    {
        return app(\App\Services\StockAvailability::class)->check($this)['has_shortage'];
    }

    /**
     * Item dengan stok kurang: [['item', 'available', 'needed', 'shortage'], …]
     */
    public function getInsufficientStockItems()
    {
        return array_map(fn (array $row) => [
            'item' => $row['item'],
            'available' => $row['available'],
            'needed' => $row['needed'],
            'shortage' => $row['shortage'],
        ], app(\App\Services\StockAvailability::class)->check($this)['shortage_items']);
    }

    protected static function booted()
    {
        static::addGlobalScope(new CabangScope());

        static::deleting(function ($saleOrder) {
            if ($saleOrder->isForceDeleting()) {
                $saleOrder->saleOrderItem()->forceDelete();
                $saleOrder->deliverySalesOrder()->forceDelete();
                // Use query builder (with parens) — safe even when no record exists
                $saleOrder->warehouseConfirmations()->forceDelete();
                $saleOrder->purchaseOrder()->forceDelete();
                $saleOrder->depositLog()->forceDelete();
            } else {
                $saleOrder->saleOrderItem()->delete();
                $saleOrder->deliverySalesOrder()->delete();
                $saleOrder->warehouseConfirmations()->delete();
                $saleOrder->purchaseOrder()->delete();
                $saleOrder->depositLog()->delete();
            }
        });

        static::restoring(function ($saleOrder) {
            $saleOrder->saleOrderItem()->withTrashed()->restore();
            $saleOrder->deliverySalesOrder()->withTrashed()->restore();
            $saleOrder->warehouseConfirmations()->withTrashed()->restore();
            $saleOrder->purchaseOrder()->withTrashed()->restore();
            $saleOrder->depositLog()->withTrashed()->restore();
        });
    }

    public function journalEntries()
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id')->withDefault();
    }
}
