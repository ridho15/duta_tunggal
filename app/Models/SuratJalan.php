<?php

namespace App\Models;

use App\Models\Scopes\CabangScope;
use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SuratJalan extends Model
{
    use SoftDeletes, HasFactory,LogsGlobalActivity;

    /**
     * Siklus hidup (kolom `status` bertipe tinyint):
     *  0 Draft      : belum terbit, masih boleh diubah/dihapus (data lama / hasil impor)
     *  1 Terbit     : dokumen resmi — TERKUNCI. Hanya unggahan dokumen bertanda tangan dan pembatalan yang diizinkan
     *  2 Dibatalkan : tidak berlaku lagi; DO-nya dapat dibuatkan Surat Jalan baru
     */
    public const STATUS_DRAFT = 0;
    public const STATUS_ISSUED = 1;
    public const STATUS_CANCELLED = 2;

    /** Belum dibatalkan: masih "memegang" Delivery Order-nya. */
    public const ACTIVE_STATUSES = [self::STATUS_DRAFT, self::STATUS_ISSUED];

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_ISSUED => 'Terbit',
        self::STATUS_CANCELLED => 'Dibatalkan',
    ];

    public const STATUS_COLORS = [
        self::STATUS_DRAFT => 'gray',
        self::STATUS_ISSUED => 'success',
        self::STATUS_CANCELLED => 'danger',
    ];

    /** Jadwal berstatus ini masih memakai Surat Jalan; Surat Jalan tidak boleh dibatalkan selama tertaut. */
    public const BLOCKING_SCHEDULE_STATUSES = ['pending', 'on_the_way', 'partial_delivered', 'delivered'];

    protected $table = 'surat_jalans';
    protected $fillable = [
        'sj_number',
        'issued_at',
        'signed_by',
        'status',
        'created_by',
        'document_path',
        'cabang_id',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'status' => 'integer',
    ];

    public static function statusLabel(int|string|null $status): string
    {
        return self::STATUS_LABELS[(int) $status] ?? '-';
    }

    public static function statusColor(int|string|null $status): string
    {
        return self::STATUS_COLORS[(int) $status] ?? 'gray';
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusLabel($this->status);
    }

    public function getStatusColorAttribute(): string
    {
        return self::statusColor($this->status);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isIssued(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /** Hanya Draft yang boleh diubah/dihapus; yang terbit dikoreksi lewat Batalkan + terbitkan ulang. */
    public function isEditable(): bool
    {
        return $this->isDraft();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn($query->getModel()->getTable() . '.status', self::ACTIVE_STATUSES);
    }

    /**
     * Jadwal pengiriman yang masih memakai Surat Jalan ini (menahan pembatalan).
     */
    public function blockingDeliverySchedules(): Collection
    {
        $scheduleIds = DB::table('delivery_schedule_surat_jalans')
            ->where('surat_jalan_id', $this->id)
            ->pluck('delivery_schedule_id');

        return DeliverySchedule::withoutGlobalScope(CabangScope::class)
            ->whereIn('id', $scheduleIds)
            ->whereIn('status', self::BLOCKING_SCHEDULE_STATUSES)
            ->orderBy('schedule_number')
            ->get();
    }

    public function deliveryOrder()
    {
        return $this->belongsToMany(DeliveryOrder::class, 'surat_jalan_delivery_orders', 'surat_jalan_id', 'delivery_order_id');
    }

    public function deliverySchedules()
    {
        return $this->belongsToMany(
            DeliverySchedule::class,
            'delivery_schedule_surat_jalans',
            'surat_jalan_id',
            'delivery_schedule_id'
        )->withTimestamps();
    }

    /**
     * Jadwal yang menentukan driver/kendaraan pada dokumen: jadwal terbaru yang masih aktif;
     * bila semua jadwal gagal/dibatalkan, jadwal terbaru apa pun. Memakai relasi yang sudah
     * dimuat (eager load) bila ada supaya daftar tidak N+1.
     */
    public function primaryDeliverySchedule(): ?DeliverySchedule
    {
        $schedules = $this->relationLoaded('deliverySchedules')
            ? $this->deliverySchedules
            : $this->deliverySchedules()->with(['driver', 'vehicle'])->get();

        if ($schedules->isEmpty()) {
            return null;
        }

        $ordered = $schedules->sortBy([['scheduled_date', 'desc'], ['id', 'desc']])->values();
        $active = $ordered->first(fn (DeliverySchedule $schedule) => in_array($schedule->status, self::BLOCKING_SCHEDULE_STATUSES, true));

        return $active ?? $ordered->first();
    }

    public function getSenderDisplayNameAttribute(): string
    {
        $deliverySchedule = $this->primaryDeliverySchedule();

        if (! $deliverySchedule) {
            return '-';
        }

        return $deliverySchedule->senderName();
    }

    public function getShippingMethodLabelAttribute(): string
    {
        $deliverySchedule = $this->primaryDeliverySchedule();

        if (! $deliverySchedule) {
            return '-';
        }

        return $deliverySchedule->delivery_method_label;
    }

    public function signedBy()
    {
        return $this->belongsTo(User::class, 'signed_by')->withDefault();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')->withDefault();
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by')->withDefault();
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id')->withDefault();
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
