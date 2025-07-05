<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
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
        'status', // darft, sent, paid, partially_paid, overdue
        'ppn_rate',
        'dpp', //Dasar penggunaan pajak,
    ];

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

    protected static function booted()
    {
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
}
