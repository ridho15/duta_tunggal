<?php

namespace App\Models;

use App\Models\Scopes\CabangScope;
use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalEntry extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'journal_entries';
    protected $fillable = [
        'coa_id',
        'date',
        'reference',
        'description',
        'debit',
        'credit',
        'journal_type',
        'cabang_id',
        'department_id',
        'project_id',
        'currency_id',
        'exchange_rate',
        'amount_original_currency',
        'source_type',
        'source_id',
        'transaction_id',
        'bank_recon_id',
        'bank_recon_status',
        'bank_recon_date',
        'is_reversal',
        'reversal_of_transaction_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date' => 'date',
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'exchange_rate' => 'decimal:8',
        'amount_original_currency' => 'decimal:4',
        'is_reversal' => 'boolean',
    ];

    public function coa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_id')->withDefault();
    }

    public function source()
    {
        return $this->morphTo(__FUNCTION__, 'source_type', 'source_id')->withDefault();
    }

    public function cabang()
    {
        return $this->belongsTo(\App\Models\Cabang::class, 'cabang_id')->withDefault();
    }

    protected static function booted()
    {
        static::addGlobalScope(new CabangScope);

        static::creating(function (JournalEntry $entry): void {
            // Auto-fill created_by from the authenticated user when not explicitly set.
            if (! $entry->created_by && \Illuminate\Support\Facades\Auth::check()) {
                $entry->created_by = \Illuminate\Support\Facades\Auth::id();
            }

            // Safety-net: auto-resolve cabang_id and currency context from source.
            // Both lookups share a single source query to avoid N+1.
            if ($entry->source_type && $entry->source_id) {
                try {
                    $source = ($entry->source_type)::find($entry->source_id);
                    if ($source) {
                        // Auto-resolve cabang_id if not explicitly provided.
                        if (! $entry->cabang_id) {
                            $resolved = app(\App\Services\JournalBranchResolver::class)->resolve($source);
                            if ($resolved) {
                                $entry->cabang_id = $resolved;
                            }
                        }

                        // Auto-fill currency context (FIN-003) if not explicitly provided.
                        if (! $entry->currency_id) {
                            $currency = null;

                            // 1. Source has currency_id directly (e.g. PurchaseReceipt)
                            if (isset($source->currency_id) && $source->currency_id) {
                                $currency = \App\Models\Currency::find($source->currency_id);
                            }

                            // 2. Source has a PurchaseOrder parent with currency settings
                            if (! $currency && method_exists($source, 'purchaseOrderCurrency')) {
                                $poCurrency = $source->purchaseOrderCurrency()->first();
                                $currency = $poCurrency?->currency;
                            }

                            // 3. Source is an Invoice or similar with fromModel → PO
                            if (! $currency && ($source->from_model_type ?? null)) {
                                try {
                                    $fromModel = $source->fromModel;
                                    if ($fromModel && method_exists($fromModel, 'purchaseOrderCurrency')) {
                                        $poCurrency = $fromModel->purchaseOrderCurrency()->first();
                                        $currency = $poCurrency?->currency;
                                    }
                                } catch (\Throwable) {
                                    // ignore
                                }
                            }

                            // 4. Source is VendorPayment — look up currency from first linked invoice's PO
                            if (! $currency && $entry->source_type === \App\Models\VendorPayment::class) {
                                $invoiceIds = collect($source->selected_invoices ?? [])
                                    ->map(fn ($item) => is_array($item) ? ($item['invoice_id'] ?? null) : $item)
                                    ->filter()->values();
                                if ($invoiceIds->isNotEmpty()) {
                                    $linkedInvoice = \App\Models\Invoice::find($invoiceIds->first());
                                    if ($linkedInvoice && $linkedInvoice->from_model_type === \App\Models\PurchaseOrder::class) {
                                        $po = $linkedInvoice->fromModel;
                                        $poCurrency = $po?->purchaseOrderCurrency()->first();
                                        $currency = $poCurrency?->currency;
                                    }
                                }
                            }

                            // Fallback: use IDR currency row
                            if (! $currency || ! $currency->id) {
                                $currency = \App\Models\Currency::where('code', 'IDR')
                                    ->orWhere('to_rupiah', 1)
                                    ->first();
                            }

                            if ($currency && $currency->id) {
                                $exchangeRate = max(0.0001, (float) ($currency->to_rupiah ?? 1.0));
                                $idrAmount = max((float) ($entry->debit ?? 0), (float) ($entry->credit ?? 0));
                                $entry->currency_id              = $currency->id;
                                $entry->exchange_rate            = $exchangeRate;
                                $entry->amount_original_currency = round($idrAmount / $exchangeRate, 4);
                            }
                        }
                    }
                } catch (\Throwable) {
                    // Silently ignore — cabang_id and currency stay null rather than blocking the save
                }
            }
            AccountingPeriod::ensureDateIsOpen($entry->date ?? now(), $entry->cabang_id);
        });

        static::updating(function (JournalEntry $entry): void {
            // Auto-fill updated_by from the authenticated user.
            if (\Illuminate\Support\Facades\Auth::check()) {
                $entry->updated_by = \Illuminate\Support\Facades\Auth::id();
            }
            AccountingPeriod::ensureDateIsOpen($entry->date ?? now(), $entry->cabang_id);
        });

        static::deleting(function (JournalEntry $entry): void {
            AccountingPeriod::ensureDateIsOpen($entry->date ?? now(), $entry->cabang_id);
        });
    }
}
