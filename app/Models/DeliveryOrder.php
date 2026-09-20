<?php

namespace App\Models;

use App\Models\Scopes\CabangScope;
use App\Traits\LogsGlobalActivity;
use App\Traits\CascadesJournalEntries;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryOrder extends Model
{
    use SoftDeletes, HasFactory,LogsGlobalActivity, CascadesJournalEntries;

    /**
     * Status DO yang kuantitasnya dihitung SUDAH TERKIRIM ke SO (barang sudah keluar gudang).
     */
    public const DELIVERED_STATUSES = ['sent', 'received', 'completed'];

    /**
     * Status DO yang kuantitasnya DILEPAS kembali ke SO (DO ditutup sebelum dikirim).
     * Semua status lain (draft s/d approved, partial, reject, delivery_failed, dst.)
     * masih "terikat" ke SO karena DO dapat diperbaiki / dijadwalkan ulang.
     */
    public const RELEASED_STATUSES = ['closed'];

    public const STATUS_LABELS = [
        'draft' => 'Draf',
        'request_stock' => 'Menunggu Konfirmasi Stok',
        'request_approve' => 'Menunggu Persetujuan',
        'approved' => 'Disetujui',
        'confirmed' => 'Dikonfirmasi',
        'partial' => 'Sebagian',
        'sent' => 'Sedang Dikirim',
        'received' => 'Diterima',
        'completed' => 'Selesai',
        'supplier' => 'Dari Supplier',
        'request_close' => 'Minta Ditutup',
        'closed' => 'Ditutup',
        'reject' => 'Ditolak',
        'delivery_failed' => 'Pengiriman Gagal',
    ];

    public const STATUS_COLORS = [
        'draft' => 'gray',
        'request_stock' => 'warning',
        'request_approve' => 'gray',
        'approved' => 'info',
        'confirmed' => 'info',
        'partial' => 'warning',
        'sent' => 'primary',
        'received' => 'info',
        'completed' => 'success',
        'supplier' => 'warning',
        'request_close' => 'warning',
        'closed' => 'danger',
        'reject' => 'danger',
        'delivery_failed' => 'danger',
    ];

    public static function statusLabel(?string $status): string
    {
        return self::STATUS_LABELS[$status ?? ''] ?? ($status ? ucfirst(str_replace('_', ' ', $status)) : '-');
    }

    public static function statusColor(?string $status): string
    {
        return self::STATUS_COLORS[$status ?? ''] ?? 'gray';
    }

    protected $table = 'delivery_orders';
    protected $fillable = [
        'do_number',
        'delivery_date',
        'driver_id',
        'vehicle_id',
        'warehouse_id',
        'status', // 'draft', 'sent', 'received', 'supplier', 'completed', 'request_approve', 'approved', 'request_close', 'closed', 'reject'
        'notes',
        'additional_cost',
        'additional_cost_description',
        'created_by',
        'cabang_id'
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id')->withDefault();
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id')->withDefault();
    }

    public function deliveryOrderItem()
    {
        return $this->hasMany(DeliveryOrderItem::class, 'delivery_order_id');
    }

    public function salesOrders()
    {
        return $this->belongsToMany(SaleOrder::class, 'delivery_sales_orders', 'delivery_order_id', 'sales_order_id');
    }

    public function suratJalan()
    {
        return $this->belongsToMany(SuratJalan::class, 'surat_jalan_delivery_orders', 'delivery_order_id', 'surat_jalan_id')->withTimestamps();
    }

    public function deliverySchedules()
    {
        return $this->belongsToMany(DeliverySchedule::class, 'delivery_schedule_delivery_orders', 'delivery_order_id', 'delivery_schedule_id')->withTimestamps();
    }

    public function deliverySalesOrder()
    {
        return $this->hasMany(DeliverySalesOrder::class, 'delivery_order_id');
    }

    public function log()
    {
        return $this->hasMany(DeliveryOrderLog::class, 'delivery_order_id');
    }

    public function returnProduct(){
        return $this->morphOne(ReturnProduct::class, 'from_model')->withDefault();
    }

    public function stockMovement()
    {
        return $this->morphOne(StockMovement::class, 'from_model')->withDefault();
    }

    public function approvalLogs()
    {
        return $this->hasMany(DeliveryOrderApprovalLog::class, 'delivery_order_id');
    }

    /**
     * Nilai DO = total invoice yang akan terbit dari DO ini (barang + PPN + biaya tambahan), via satu sumber:
     * DeliveryOrderValuation (LineAmounts). Sebelumnya `harga − diskon + pajak` mencampur persen dengan rupiah.
     */
    public function getTotalAttribute()
    {
        return $this->valueBreakdown()['total'];
    }

    /** Rincian nilai DO: baris, DPP, PPN, total barang, biaya tambahan, total. */
    public function valueBreakdown(): array
    {
        return app(\App\Services\DeliveryOrderValuation::class)->forDeliveryOrder($this);
    }

    protected static function booted()
    {
        static::addGlobalScope(new CabangScope);

        static::deleting(function ($deliveryOrder) {
            if ($deliveryOrder->isForceDeleting()) {
                $deliveryOrder->deliveryOrderItem()->forceDelete();
            } else {
                $deliveryOrder->deliveryOrderItem()->delete();
            }
        });

        static::restoring(function ($deliveryOrder) {
            $deliveryOrder->deliveryOrderItem()->withTrashed()->restore();
        });
    }

    public function stockReservations()
    {
        return $this->hasMany(StockReservation::class, 'delivery_order_id');
    }

    public function journalEntries()
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id')->withDefault();
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id')->withDefault();
    }

    // WC records linked to this DO via polymorphic relationship (DO-centric flow)
    public function warehouseConfirmations()
    {
        return $this->morphMany(WarehouseConfirmation::class, 'confirmable');
    }

    /**
     * Update DO status based on all linked WC outcomes.
     * - ALL confirmed  → approved (auto)
     * - ANY rejected   → reject (auto)
     * - still pending  → stays request_stock
     */
    public function updateStatusFromWarehouseConfirmations(): void
    {
        $wcs = $this->warehouseConfirmations()->get();
        if ($wcs->isEmpty()) {
            if ($this->status !== 'request_stock') {
                $this->update(['status' => 'request_stock']);
            }

            return;
        }

        $statuses = $wcs->map(fn ($wc) => strtolower((string) $wc->status))->values();

        $allConfirmed = $statuses->every(fn ($status) => $status === 'confirmed');
        $anyRejected  = $statuses->contains('rejected');

        if ($allConfirmed) {
            $this->update(['status' => 'approved']);
        } elseif ($anyRejected) {
            $this->update(['status' => 'reject']);
        }
        // else: one or more WCs still pending → stay at request_stock
    }
}
