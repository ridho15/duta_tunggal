<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use App\Traits\CascadesJournalEntries;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Deposit extends Model
{
    use HasFactory, SoftDeletes, LogsGlobalActivity, CascadesJournalEntries;
    protected $table = 'deposits';
    protected $fillable = [
        'from_model_type',
        'from_model_id',
        'amount',
        'used_amount',
        'remaining_amount',
        'coa_id',
        'payment_coa_id',
        'note',
        'status', //active,closed
        'created_by',
        'deposit_number'
    ];

    public function coa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_id')->withDefault();
    }

    public function paymentCoa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'payment_coa_id')->withDefault();
    }

    public function fromModel()
    {
        return $this->morphTo(__FUNCTION__, 'from_model_type', 'from_model_id')->withDefault();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')->withDefault();
    }

    public function depositLog()
    {
        return $this->hasMany(DepositLog::class, 'deposit_id');
    }

    public function depositLogRef()
    {
        return $this->morphMany(DepositLog::class, 'reference');
    }

    public function journalEntry()
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    /**
     * Akun liabilitas Deposit Pelanggan (Pengaturan Akuntansi → config coa.customer_deposit, bawaan 2160.04).
     * Semua titip dana / kelebihan bayar customer dikredit ke akun ini dan pemakaiannya didebit dari akun ini.
     */
    public static function customerDepositAccount(): ?ChartOfAccount
    {
        return app(\App\Services\AccountingSettings::class)->first('customer_deposit');
    }

    /**
     * Saldo titip yang sedang DICADANGKAN (verifikasi kasir COD, belum menjadi penerimaan) = Σ reserve − Σ release.
     * Tanpa $saleOrderId: seluruh SO customer.
     */
    public static function reservedForCustomer(int $customerId, ?int $saleOrderId = null): float
    {
        $sum = fn (string $type) => (float) DepositLog::query()
            ->join('deposits', 'deposits.id', '=', 'deposit_logs.deposit_id')
            ->whereNull('deposits.deleted_at')
            ->where('deposits.from_model_type', Customer::class)->where('deposits.from_model_id', $customerId)
            ->where('deposit_logs.type', $type)->where('deposit_logs.reference_type', SaleOrder::class)
            ->when($saleOrderId, fn ($q) => $q->where('deposit_logs.reference_id', $saleOrderId))
            ->sum('deposit_logs.amount');

        return max(0.0, round($sum('reserve') - $sum('release'), 2));
    }

    /** Saldo titip customer yang bebas dipakai = Σ sisa deposit aktif − cadangan COD yang belum direalisasi. */
    public static function availableForCustomer(int $customerId): float
    {
        $remaining = (float) self::query()->where('from_model_type', Customer::class)->where('from_model_id', $customerId)
            ->where('status', 'active')->sum('remaining_amount');

        return max(0.0, round($remaining - self::reservedForCustomer($customerId), 2));
    }

    protected static function booted()
    {
        static::deleting(function ($deposit) {
            if ($deposit->isForceDeleting()) {
                $deposit->depositLog()->forceDelete();
            } else {
                $deposit->depositLog()->delete();
            }
        });

        static::restoring(function ($deposit) {
            $deposit->depositLog()->withTrashed()->restore();
        });
    }
}
