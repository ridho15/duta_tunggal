<?php

namespace App\Models;

use App\Models\Scopes\CabangScope;
use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashBankTransaction extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;

    protected $fillable = [
        'number', 'date', 'type', 'account_coa_id', 'offset_coa_id', 'amount', 'counterparty', 'description', 'attachment_path',
        'cabang_id', 'department_id', 'project_id', 'cash_bank_account_id', 'voucher_request_id', 'voucher_number',
        'voucher_usage_type', 'voucher_amount_used'
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'voucher_amount_used' => 'decimal:2',
    ];

    public function accountCoa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_coa_id');
    }

    public function offsetCoa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'offset_coa_id');
    }

    public function transactionDetails()
    {
        return $this->hasMany(CashBankTransactionDetail::class);
    }

    public function voucherRequest()
    {
        return $this->belongsTo(VoucherRequest::class, 'voucher_request_id');
    }

    /**
     * Check if transaction uses a voucher
     */
    public function usesVoucher(): bool
    {
        return !is_null($this->voucher_request_id);
    }

    /**
     * Check if voucher is single use
     */
    public function isSingleUseVoucher(): bool
    {
        return $this->voucher_usage_type === 'single_use';
    }

    /**
     * Check if voucher is multi use
     */
    public function isMultiUseVoucher(): bool
    {
        return $this->voucher_usage_type === 'multi_use';
    }

    /**
     * Get remaining voucher amount
     */
    public function getRemainingVoucherAmount(): float
    {
        if (!$this->usesVoucher() || !$this->voucherRequest) {
            return 0;
        }

        $totalUsed = static::where('voucher_request_id', $this->voucher_request_id)
            ->where('id', '!=', $this->id) // exclude current transaction
            ->sum('voucher_amount_used');

        return $this->voucherRequest->amount - $totalUsed;
    }

    protected static function booted()
    {
        static::addGlobalScope(new CabangScope);
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id')->withDefault();
    }
}
