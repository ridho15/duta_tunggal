<?php

namespace App\Observers;

use App\Enums\PaymentStatus;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Invoice;
use App\Services\LedgerPostingService;
use App\Support\CurrencyConversionResolver;
use App\Support\ProcurementFailureNotifier;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class InvoiceObserver
{
    protected $ledger;

    public function __construct()
    {
        $this->ledger = new LedgerPostingService();
    }

    public function created(Invoice $invoice)
    {
        Log::info('InvoiceObserver: created method called', [
            'invoice_id' => $invoice->id,
            'customer_name_before' => $invoice->customer_name,
            'customer_phone_before' => $invoice->customer_phone,
            'from_model_type' => $invoice->from_model_type,
        ]);

        // Create AP or AR depending on source
        if ($invoice->from_model_type == 'App\\Models\\PurchaseOrder') {
            $fromModel = $invoice->fromModel;
            if (! $fromModel) {
                Log::warning('InvoiceObserver: fromModel is null for purchase invoice, skipping AP creation', [
                    'invoice_id'      => $invoice->id,
                    'from_model_type' => $invoice->from_model_type,
                    'from_model_id'   => $invoice->from_model_id,
                ]);
                return;
            }
            // Create Account Payable
            $data = [
                'invoice_id' => $invoice->id,
                'supplier_id' => $fromModel->supplier_id,
                'total' => $invoice->total,
                'paid' => 0,
                'remaining' => $invoice->total,
                'status' => PaymentStatus::UNPAID->value,
            ];
            // branch column may not exist on older installs; include only when present
            if (\Illuminate\Support\Facades\Schema::hasColumn('account_payables', 'cabang_id')) {
                $data['cabang_id'] = $invoice->cabang_id;
            }
            $accountPayable = AccountPayable::create($data);
            // Create Ageing Schedule
            try {
                $daysOutstanding = ($invoice->invoice_date && $invoice->due_date)
                    ? Carbon::parse($invoice->invoice_date)->diffInDays(Carbon::parse($invoice->due_date))
                    : 0;
            } catch (\Exception $e) {
                $daysOutstanding = 0;
            }
            $accountPayable->ageingSchedule()->create([
                'invoice_date' => $invoice->invoice_date,
                'due_date' => $invoice->due_date,
                'days_outstanding' => $daysOutstanding,
                'bucket' => 'Current'
            ]);

            // Post journal entries for purchase invoice (accrual basis)
            try {
                $this->ledger->postInvoice($invoice);
            } catch (Throwable $exception) {
                Log::error('InvoiceObserver: failed to post purchase invoice journal on create', [
                    'invoice_id' => $invoice->id,
                    'error'      => $exception->getMessage(),
                ]);
                if (! app()->runningInConsole()) {
                    ProcurementFailureNotifier::danger(
                        'Gagal Posting Jurnal Invoice',
                        $exception,
                        'Invoice berhasil dibuat, tetapi jurnal pembelian belum dapat diposting.'
                    );
                }
            }
        } elseif ($invoice->from_model_type == 'App\\Models\\SaleOrder') {
            $fromModel = $invoice->fromModel;
            if (! $fromModel) {
                Log::warning('InvoiceObserver: fromModel is null for sales invoice, skipping AR creation', [
                    'invoice_id'      => $invoice->id,
                    'from_model_type' => $invoice->from_model_type,
                    'from_model_id'   => $invoice->from_model_id,
                ]);
                return;
            }
            // Create Account Receivable
            $accountReceivable = AccountReceivable::firstOrCreate(
                ['invoice_id' => $invoice->id],
                [
                    'customer_id' => $fromModel->customer_id,
                    'total' => $invoice->total,
                    'paid' => 0,
                    'remaining' => $invoice->total,
                    'status' => PaymentStatus::UNPAID->value,
                    'cabang_id' => $invoice->cabang_id, // FIX #5: propagate branch scope so AR is visible to branch users
                ]
            );

            if ($accountReceivable->wasRecentlyCreated === false) {
                $accountReceivable->forceFill([
                    'customer_id' => $fromModel->customer_id,
                    'total' => $invoice->total,
                    'remaining' => $invoice->total - (float) $accountReceivable->paid,
                    'status' => PaymentStatus::UNPAID->value,
                    'cabang_id' => $invoice->cabang_id,
                ])->save();
            }

            $hasInvoiceItems = $invoice->invoiceItem()->exists();
            $isAutoGeneratedFromCompletedSaleOrder = str_contains((string) $invoice->notes, 'Auto-generated from completed Sale Order');

            if (! $hasInvoiceItems && ! $isAutoGeneratedFromCompletedSaleOrder) {
                $this->postSalesInvoice($invoice);
            }
            // Create Ageing Schedule
            try {
                $daysOutstanding = ($invoice->invoice_date && $invoice->due_date)
                    ? Carbon::parse($invoice->invoice_date)->diffInDays(Carbon::parse($invoice->due_date))
                    : 0;
            } catch (\Exception $e) {
                $daysOutstanding = 0;
            }
            $accountReceivable->ageingSchedule()->create([
                'invoice_date' => $invoice->invoice_date,
                'due_date' => $invoice->due_date,
                'days_outstanding' => $daysOutstanding,
                'bucket' => 'Current'
            ]);

            // Note: postSalesInvoice will be called manually by SaleOrderObserver after invoice items are created
        }

        // If invoice already paid on creation, post to ledger
        if (strtolower($invoice->status) === Invoice::STATUS_PAID) {
            try {
                $this->ledger->postInvoice($invoice);
            } catch (Throwable $exception) {
                Log::error('InvoiceObserver: failed to post paid invoice', [
                    'invoice_id' => $invoice->id,
                    'error'      => $exception->getMessage(),
                ]);
                if (! app()->runningInConsole()) {
                    ProcurementFailureNotifier::danger(
                        'Gagal Posting Invoice Lunas',
                        $exception,
                        'Invoice lunas berhasil disimpan, tetapi jurnal belum dapat diposting.'
                    );
                }
            }
        }
    }

    public function updated(Invoice $invoice)
    {
        Log::info('InvoiceObserver: updated method called', [
            'invoice_id' => $invoice->id,
            'customer_name_before' => $invoice->getOriginal('customer_name'),
            'customer_name_after' => $invoice->customer_name,
            'customer_phone_before' => $invoice->getOriginal('customer_phone'),
            'customer_phone_after' => $invoice->customer_phone,
            'changed_attributes' => $invoice->getChanges(),
        ]);

        // Check if critical financial fields changed (amounts, dates, etc.)
        $financialFields = ['subtotal', 'total', 'ppn_rate', 'invoice_date', 'other_fee'];
        $financialChanged = false;
        foreach ($financialFields as $field) {
            if ($invoice->wasChanged($field)) {
                $financialChanged = true;
                break;
            }
        }

        // If financial fields changed, reverse existing journal entries and re-post
        if ($financialChanged) {
            Log::info('Invoice financial fields changed, reversing and re-posting journal entries', [
                'invoice_id' => $invoice->id,
                'changed_fields' => array_intersect_key($invoice->getChanges(), array_flip($financialFields))
            ]);

            // Delete existing journal entries
            \App\Models\JournalEntry::where('source_type', Invoice::class)
                ->where('source_id', $invoice->id)
                ->delete();

            // Re-post journal entries with new amounts
            try {
                if ($invoice->from_model_type == 'App\\Models\\SaleOrder') {
                    $this->postSalesInvoice($invoice);
                } else {
                    $this->ledger->postInvoice($invoice);
                }
            } catch (Throwable $exception) {
                Log::error('InvoiceObserver: failed to re-post journal on update', [
                    'invoice_id' => $invoice->id,
                    'error'      => $exception->getMessage(),
                ]);
                if (! app()->runningInConsole()) {
                    ProcurementFailureNotifier::warning(
                        'Gagal Memperbarui Jurnal Invoice',
                        $exception,
                        'Perubahan invoice berhasil disimpan, tetapi jurnal belum dapat diperbarui.'
                    );
                }
            }
        }

        // When invoice status becomes 'paid', post to ledger (if not already posted)
        if (strtolower($invoice->status) === Invoice::STATUS_PAID) {
            try {
                if ($invoice->from_model_type == 'App\Models\SaleOrder') {
                    $this->postSalesInvoice($invoice);
                } else {
                    $this->ledger->postInvoice($invoice);
                }
            } catch (Throwable $exception) {
                Log::error('InvoiceObserver: failed to post on status=paid', [
                    'invoice_id' => $invoice->id,
                    'error'      => $exception->getMessage(),
                ]);
                if (! app()->runningInConsole()) {
                    ProcurementFailureNotifier::danger(
                        'Gagal Posting Jurnal Invoice',
                        $exception,
                        'Invoice berhasil disimpan, tetapi jurnal belum dapat diposting.'
                    );
                }
            }
        }

        // When invoice status becomes 'approved', post sales invoice journal entries
        if ($invoice->wasChanged('status') && strtolower($invoice->status) === 'approved') {
            try {
                if ($invoice->from_model_type == 'App\\Models\\SaleOrder') {
                    $this->postSalesInvoice($invoice);
                }
            } catch (\Throwable $e) {
                Log::error('InvoiceObserver: failed to post on status=approved', [
                    'invoice_id' => $invoice->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }
    }

    public function deleting(Invoice $invoice)
    {
        // Hapus account payable dan account receivable ketika invoice dihapus
        if ($invoice->from_model_type == 'App\\Models\\PurchaseOrder') {
            $accountPayable = AccountPayable::where('invoice_id', $invoice->id)->first();
            if ($accountPayable) {
                $accountPayable->delete(); // Ini akan trigger deleting di AccountPayable yang menghapus ageing schedule
            }
        } elseif ($invoice->from_model_type == 'App\\Models\\SaleOrder') {
            $accountReceivable = AccountReceivable::where('invoice_id', $invoice->id)->first();
            if ($accountReceivable) {
                $accountReceivable->delete(); // Asumsikan AccountReceivable juga punya logic serupa
            }
        }
    }

    public function deleted(Invoice $invoice)
    {
        // Hapus journal entries yang terkait dengan invoice yang dihapus
        \App\Models\JournalEntry::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->delete();

        Log::info('Invoice deleted, related journal entries cleaned up', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
        ]);
    }

    public function postSalesInvoice(Invoice $invoice)
    {
        // Prevent duplicate posting
        if (\App\Models\JournalEntry::where('source_type', Invoice::class)->where('source_id', $invoice->id)->exists()) {
            Log::info('postSalesInvoice: invoice already posted, skipping', ['invoice_id' => $invoice->id]);
            return;
        }

        Log::info('postSalesInvoice: starting ledger posting', [
            'invoice_id'     => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'total'          => $invoice->total,
            'subtotal'       => $invoice->subtotal,
            'tax'            => $invoice->tax,
        ]);

        DB::transaction(function () use ($invoice) {
            $this->executeSalesInvoicePosting($invoice);
        });

        Log::info('postSalesInvoice: ledger posting completed', [
            'invoice_id'     => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
        ]);
    }

    private function executeSalesInvoicePosting(Invoice $invoice): void
    {
        $date = $invoice->invoice_date ?? Carbon::now()->toDateString();

        // Get COAs from invoice or fallback to defaults
        $arCodes = $this->getConfiguredSalesCoaCodes('accounts_receivable', ['1120']);
        $revenueCodes = $this->getConfiguredSalesCoaCodes('sales_revenue', ['4000', '4111']);

        $arCoa = $invoice->arCoa?->exists ? $invoice->arCoa : $this->resolveCoaByCodes($arCodes);
        $revenueCoa = $invoice->revenueCoa?->exists ? $invoice->revenueCoa : $this->resolveCoaByCodes($revenueCodes, 'Revenue');
        $ppnKeluaranCoa = $invoice->ppnKeluaranCoa?->exists
            ? $invoice->ppnKeluaranCoa
            : $this->resolveCoaByCodes($this->getConfiguredSalesCoaCodes('sales_output_vat', ['2120.06']), 'Liability');
        $discountCoa = $this->resolveCoaByCodes($this->getConfiguredSalesCoaCodes('sales_discount', ['4100.01']), 'Expense');
        $biayaPengirimanCoa = $invoice->biayaPengirimanCoa?->exists
            ? $invoice->biayaPengirimanCoa
            : $this->resolveCoaByCodes($this->getConfiguredSalesCoaCodes('sales_shipping', ['6100.02']), 'Expense');

        if (!$arCoa || !$revenueCoa) {
            Log::error('postSalesInvoice: essential COA mapping missing — cannot post invoice', [
                'invoice_id'    => $invoice->id,
                'ar_coa_found'  => $arCoa  ? $arCoa->code  : null,
                'rev_coa_found' => $revenueCoa ? $revenueCoa->code : null,
                'expected_ar_codes' => $arCodes,
                'expected_revenue_codes' => $revenueCodes,
            ]);
            throw new \RuntimeException(
                "COA mapping tidak ditemukan untuk invoice {$invoice->invoice_number} — "
                . 'Piutang Dagang: ' . ($arCoa ? 'OK' : 'TIDAK ADA') . ' [' . implode(', ', $arCodes) . '], '
                . 'Penjualan: ' . ($revenueCoa ? 'OK' : 'TIDAK ADA') . ' [' . implode(', ', $revenueCodes) . ']'
            );
        }

        $invoice->loadMissing('invoiceItem.product', 'fromModel.saleOrderItem.product', 'fromModel.saleOrderItem.currency');

        $invoiceItems = $invoice->invoiceItem;
        if ($invoiceItems->isEmpty() && $invoice->from_model_type === 'App\\Models\\SaleOrder') {
            $invoiceItems = $invoice->fromModel?->saleOrderItem ?? collect();
        }

        // Calculate totals from invoice items for detailed breakdown
        $totalRevenue = 0;
        $totalTax = 0;
        $totalDiscount = 0;
        $otherFeeTotal = $invoice->getOtherFeeTotalAttribute();

        // DEBIT: Accounts Receivable (customer owes money) - total amount
        $grandTotal = $invoice->total;

        \App\Models\JournalEntry::create([
            'coa_id' => $arCoa->id,
            'date' => $date,
            'reference' => $invoice->invoice_number,
            'description' => 'Sales Invoice - Accounts Receivable',
            'debit' => $grandTotal,
            'credit' => 0,
            'journal_type' => 'sales',
            'source_type' => Invoice::class,
            'source_id' => $invoice->id,
            'cabang_id' => $invoice->cabang_id,
        ]);

        // Create detailed CREDIT entries for each invoice item
        foreach ($invoiceItems as $item) {
            $productName = $item->product->name ?? 'Unknown Product';

            // CREDIT: Revenue/Sales for this item
            // item->subtotal is already net of discount, so no separate discount entry is needed
            // (using net method: revenue is recorded after discount, keeping entries balanced)
            $lineTotal = (float) ($item->total ?? $item->subtotal ?? 0);
            $lineSubtotal = (float) ($item->subtotal ?? $lineTotal);
            if ($lineTotal <= 0 && $invoice->from_model_type === 'App\\Models\\SaleOrder') {
                $quantity = max(0, (float) ($item->quantity ?? 0));
                $unitPrice = max(0, (float) ($item->unit_price ?? 0));
                $discount = max(0.0, min(100.0, (float) ($item->discount ?? 0)));
                $itemCurrencyId = is_numeric($item->currency_id ?? null) ? (int) $item->currency_id : null;

                if ($quantity > 0 && $unitPrice > 0) {
                    $lineOriginal = round($quantity * $unitPrice * (1 - ($discount / 100)), 4);
                    $lineTotal = CurrencyConversionResolver::convertToIdr($lineOriginal, $itemCurrencyId);
                    $lineSubtotal = $lineTotal;
                }
            }
            if ($lineTotal > 0) {
                $itemCoaId = $item->product->sales_coa_id ?? $revenueCoa->id;
                \App\Models\JournalEntry::create([
                    'coa_id' => $itemCoaId,
                    'date' => $date,
                    'reference' => $invoice->invoice_number,
                    'description' => "Sales Invoice - Revenue: {$productName}",
                    'debit' => 0,
                    'credit' => round($lineSubtotal, 2), // Revenue net of discount, stored in IDR
                    'journal_type' => 'sales',
                    'source_type' => Invoice::class,
                    'source_id' => $invoice->id,
                    'cabang_id' => $invoice->cabang_id,
                ]);
                $totalRevenue += round($lineSubtotal, 2);
            }

        }

        // CREDIT: PPn Keluaran at invoice level.
        // Use sum of invoice items' pre-computed tax_amount as primary source.
        // Fallback to ppn_rate-based computation (preferred for sales invoices which store ppn_rate only).
        // Legacy fallback: if neither items nor ppn_rate provide a value, use invoice->tax as rate.
        // NOTE: We deliberately do NOT combine ppn_rate + invoice->tax to avoid double PPN.
        $totalTaxAmount = (float) $invoiceItems->sum('tax_amount');
        if ($totalTaxAmount <= 0) {
            $ppnRateVal = (float) ($invoice->ppn_rate ?? 0);
            if ($ppnRateVal > 0) {
                $totalTaxAmount = max(0.0, (float) $invoice->subtotal * ($ppnRateVal / 100));
            } elseif ((float) $invoice->tax > 0) {
                // Legacy: tax stores percentage rate (e.g. 11 for 11%)
                $totalTaxAmount = max(0.0, (float) $invoice->subtotal * ((float) $invoice->tax / 100));
            }
        }
        
        if ($totalTaxAmount > 0 && $ppnKeluaranCoa) {
            \App\Models\JournalEntry::create([
                'coa_id' => $ppnKeluaranCoa->id,
                'date' => $date,
                'reference' => $invoice->invoice_number,
                'description' => 'Sales Invoice - PPn Keluaran',
                'debit' => 0,
                'credit' => $totalTaxAmount,
                'journal_type' => 'sales',
                'source_type' => Invoice::class,
                'source_id' => $invoice->id,
                'cabang_id' => $invoice->cabang_id,
            ]);
        }

        // CREDIT: Biaya Pengiriman (shipping/other costs)
        if ($otherFeeTotal > 0 && $biayaPengirimanCoa) {
            \App\Models\JournalEntry::create([
                'coa_id' => $biayaPengirimanCoa->id,
                'date' => $date,
                'reference' => $invoice->invoice_number,
                'description' => 'Sales Invoice - Biaya Pengiriman',
                'debit' => 0,
                'credit' => $otherFeeTotal,
                'journal_type' => 'sales',
                'source_type' => Invoice::class,
                'source_id' => $invoice->id,
                'cabang_id' => $invoice->cabang_id,
            ]);
        }

        Log::info('postSalesInvoice: journal entries created', [
            'invoice_id'     => $invoice->id,
            'total_revenue'  => $totalRevenue,
            'total_tax'      => $totalTax,
            'total_discount' => $totalDiscount,
            'other_fees'     => $otherFeeTotal,
            'grand_total'    => $grandTotal,
        ]);

        $this->postCostOfSalesEntries($invoice, $date);
    }

    private function getConfiguredSalesCoaCodes(string $configKey, array $fallbacks = []): array
    {
        return array_values(array_unique(array_filter([
            config('coa.' . $configKey),
            ...$fallbacks,
        ])));
    }

    private function resolveCoaByCodes(array $codes, ?string $type = null): ?\App\Models\ChartOfAccount
    {
        if (empty($codes)) {
            return null;
        }

        $query = \App\Models\ChartOfAccount::query()
            ->whereIn('code', $codes)
            ->where('is_active', true);

        if ($type !== null) {
            $query->where('type', $type);
        }

        $accounts = $query->get()->keyBy('code');

        foreach ($codes as $code) {
            if ($accounts->has($code)) {
                return $accounts->get($code);
            }
        }

        return null;
    }

    protected function postCostOfSalesEntries(Invoice $invoice, string $date): void
    {
        $invoice->loadMissing([
            'invoiceItem.product.cogsCoa',
            'invoiceItem.product.goodsDeliveryCoa',
            'fromModel.saleOrderItem.product.cogsCoa',
            'fromModel.saleOrderItem.product.goodsDeliveryCoa',
        ]);

        $invoiceItems = $invoice->invoiceItem;
        if ($invoiceItems->isEmpty() && $invoice->from_model_type === 'App\\Models\\SaleOrder') {
            $invoiceItems = $invoice->fromModel?->saleOrderItem ?? collect();
        }

        // Allow fallback sources (delivery orders) when invoice items are absent

        $defaultGoodsDeliveryCoa = \App\Models\ChartOfAccount::where('code', '1140.20')->first()
            ?? \App\Models\ChartOfAccount::where('code', '1180.10')->first();
        $defaultCogsCoa = \App\Models\ChartOfAccount::where('code', '5100.10')->first()
            ?? \App\Models\ChartOfAccount::where('code', '5000')->first();

        $debitTotals = [];
        $creditTotals = [];

        foreach ($invoiceItems as $item) {
            $quantity = max(0, (float) ($item->quantity ?? 0));
            $costPrice = (float) ($item->product?->cost_price ?? 0);

            if ($quantity <= 0 || $costPrice <= 0) {
                continue;
            }

            $lineAmount = round($quantity * $costPrice, 2);
            if ($lineAmount <= 0) {
                continue;
            }

            $cogsCoa = $item->product?->resolveCogsCoaOrDefault() ?? $defaultCogsCoa;
            $goodsDeliveryCoa = $item->product?->resolveGoodsDeliveryCoaOrDefault() ?? $defaultGoodsDeliveryCoa;

            $this->pushCostTotals($debitTotals, $creditTotals, $lineAmount, $cogsCoa, $goodsDeliveryCoa);
        }

        if (empty($debitTotals) || empty($creditTotals)) {
            $this->accumulateFromDeliveryOrders($invoice, $debitTotals, $creditTotals, $defaultCogsCoa, $defaultGoodsDeliveryCoa);
        }

        if (empty($debitTotals) || empty($creditTotals)) {
            throw new \RuntimeException(
                'Sales invoice tidak dapat diposting karena HPP / release barang terkirim tidak dapat dihitung. Pastikan item invoice atau delivery order memiliki cost_price dan mapping COA yang valid.'
            );
        }

        foreach ($debitTotals as $debitData) {
            \App\Models\JournalEntry::create([
                'coa_id' => $debitData['coa']->id,
                'date' => $date,
                'reference' => $invoice->invoice_number,
                'description' => 'Sales Invoice - Cost of Goods Sold for ' . $invoice->invoice_number,
                'debit' => round($debitData['amount'], 2),
                'credit' => 0,
                'journal_type' => 'sales',
                'source_type' => Invoice::class,
                'source_id' => $invoice->id,
                'cabang_id' => $invoice->cabang_id,
            ]);
        }

        foreach ($creditTotals as $creditData) {
            \App\Models\JournalEntry::create([
                'coa_id' => $creditData['coa']->id,
                'date' => $date,
                'reference' => $invoice->invoice_number,
                'description' => 'Sales Invoice - Release Barang Terkirim for ' . $invoice->invoice_number,
                'debit' => 0,
                'credit' => round($creditData['amount'], 2),
                'journal_type' => 'sales',
                'source_type' => Invoice::class,
                'source_id' => $invoice->id,
                'cabang_id' => $invoice->cabang_id,
            ]);
        }
    }

    protected function accumulateFromDeliveryOrders(Invoice $invoice, array &$debitTotals, array &$creditTotals, $defaultCogsCoa, $defaultGoodsDeliveryCoa): void
    {
        $deliveryOrderIds = array_filter((array) $invoice->delivery_orders);
        if (empty($deliveryOrderIds)) {
            return;
        }

        $deliveryOrders = \App\Models\DeliveryOrder::with([
            'deliveryOrderItem.product.cogsCoa',
            'deliveryOrderItem.product.goodsDeliveryCoa',
        ])->whereIn('id', $deliveryOrderIds)->get();

        foreach ($deliveryOrders as $deliveryOrder) {
            foreach ($deliveryOrder->deliveryOrderItem as $item) {
                $quantity = max(0, (float) ($item->quantity ?? 0));
                $costPrice = (float) ($item->product?->cost_price ?? 0);

                if ($quantity <= 0 || $costPrice <= 0) {
                    continue;
                }

                $amount = round($quantity * $costPrice, 2);
                $cogsCoa = $item->product?->resolveCogsCoaOrDefault() ?? $defaultCogsCoa;
                $goodsDeliveryCoa = $item->product?->resolveGoodsDeliveryCoaOrDefault() ?? $defaultGoodsDeliveryCoa;

                $this->pushCostTotals($debitTotals, $creditTotals, $amount, $cogsCoa, $goodsDeliveryCoa);
            }
        }
    }

    protected function pushCostTotals(array &$debitTotals, array &$creditTotals, float $amount, $cogsCoa, $goodsDeliveryCoa): void
    {
        if (! $cogsCoa || ! $goodsDeliveryCoa || $amount <= 0) {
            return;
        }

        $debitTotals[$cogsCoa->id]['coa'] = $cogsCoa;
        $debitTotals[$cogsCoa->id]['amount'] = ($debitTotals[$cogsCoa->id]['amount'] ?? 0) + $amount;

        $creditTotals[$goodsDeliveryCoa->id]['coa'] = $goodsDeliveryCoa;
        $creditTotals[$goodsDeliveryCoa->id]['amount'] = ($creditTotals[$goodsDeliveryCoa->id]['amount'] ?? 0) + $amount;
    }
}
