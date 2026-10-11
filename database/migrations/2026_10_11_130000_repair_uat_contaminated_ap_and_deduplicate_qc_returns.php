<?php

use App\Enums\PaymentStatus;
use App\Models\AccountPayable;
use App\Models\Invoice;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\UnitOfMeasure;
use App\Models\Product;
use App\Models\VendorPaymentDetail;
use App\Services\PurchaseReturnService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Deduplicate QC-derived Purchase Returns
        try {
            $duplicateQcReturns = PurchaseReturn::whereNotNull('quality_control_id')
                ->select('quality_control_id', DB::raw('COUNT(*) as count'))
                ->groupBy('quality_control_id')
                ->having('count', '>', 1)
                ->get();

            foreach ($duplicateQcReturns as $dup) {
                $qcReturns = PurchaseReturn::where('quality_control_id', $dup->quality_control_id)
                    ->orderBy('id', 'asc')
                    ->get();

                // Keep the first, delete subsequent duplicates
                $keep = $qcReturns->first();
                $duplicates = $qcReturns->slice(1);

                foreach ($duplicates as $duplicate) {
                    Log::info("Cleaning duplicate QC purchase return: {$duplicate->nota_retur} (ID: {$duplicate->id}) for QC ID: {$dup->quality_control_id}");
                    PurchaseReturnItem::where('purchase_return_id', $duplicate->id)->delete();
                    $duplicate->delete();
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Migration repair QC returns: {$e->getMessage()}");
        }

        // 2. Repair contaminated Account Payable for PINV-TEST-RL-HASSEE-20261010 & general AP sync
        try {
            $contaminatedInvoices = Invoice::where('invoice_number', 'like', '%TEST-RL-HASSEE%')
                ->orWhereHas('accountPayable', function ($q) {
                    $q->where('status', '!=', 'cancelled');
                })
                ->get();

            $returnService = app(PurchaseReturnService::class);

            foreach ($contaminatedInvoices as $invoice) {
                $ap = AccountPayable::where('invoice_id', $invoice->id)->first();
                if (! $ap) {
                    continue;
                }

                $exchangeRate = (float) ($ap->exchange_rate ?? $invoice->exchange_rate ?? 1);
                $exchangeRate = $exchangeRate > 0 ? $exchangeRate : 1.0;
                $totalIdr = (float) ($ap->total ?? $invoice->total ?? 0);
                $totalOriginal = (float) ($ap->total_original ?? 0);
                if ($totalOriginal <= 0 || ($totalIdr > 0 && abs(($totalOriginal * $exchangeRate) - $totalIdr) > 1.0)) {
                    $totalOriginal = round($totalIdr / $exchangeRate, 2);
                }

                // Legitimate payments made
                $totalPaidOriginal = (float) VendorPaymentDetail::where('invoice_id', $invoice->id)
                    ->whereHas('vendorPayment', function ($query) {
                        $query->whereIn('status', ['partial', 'paid']);
                    })
                    ->sum('amount');

                $totalAdjustmentOriginal = (float) VendorPaymentDetail::where('invoice_id', $invoice->id)
                    ->whereHas('vendorPayment', function ($query) {
                        $query->whereIn('status', ['partial', 'paid']);
                    })
                    ->sum('adjustment_amount');

                // Legitimate non-QC returns
                $totalReturnGrossIdr = $returnService->calculateApprovedReturnsTotalForInvoice($invoice);
                $totalReturnOriginal = round($totalReturnGrossIdr / $exchangeRate, 2);

                $totalPaidOrCreditedOriginal = $totalPaidOriginal + $totalReturnOriginal;
                $newPaidOriginal = min($totalPaidOrCreditedOriginal, $totalOriginal);
                $newRemainingOriginal = max(0, $totalOriginal - $newPaidOriginal - $totalAdjustmentOriginal);

                $newPaid = round($newPaidOriginal * $exchangeRate, 2);
                $newRemaining = round($newRemainingOriginal * $exchangeRate, 2);
                $isPaid = $newRemainingOriginal <= 1.00 || $newRemaining <= 1.00;

                $ap->paid_original = $newPaidOriginal;
                $ap->remaining_original = $newRemainingOriginal;
                $ap->paid = $newPaid;
                $ap->remaining = $newRemaining;
                $ap->status = $isPaid ? PaymentStatus::PAID->value : PaymentStatus::UNPAID->value;
                $ap->save();

                // Sync invoice status
                $newInvoiceStatus = $isPaid
                    ? Invoice::STATUS_PAID
                    : ($newPaidOriginal > 0 ? Invoice::STATUS_PARTIALLY_PAID : Invoice::STATUS_APPROVED);

                if ($invoice->status !== $newInvoiceStatus && $invoice->status !== Invoice::STATUS_CANCELLED) {
                    $invoice->status = $newInvoiceStatus;
                    $invoice->save();
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Migration repair AP: {$e->getMessage()}");
        }

        // 3. Clean up UOM 'bh]' to 'BH'
        try {
            $typoUom = UnitOfMeasure::where('name', 'bh]')
                ->orWhere('abbreviation', 'bh]')
                ->first();

            if ($typoUom) {
                $correctUom = UnitOfMeasure::where(function ($q) {
                    $q->where('name', 'BH')->orWhere('abbreviation', 'BH');
                })->where('id', '!=', $typoUom->id)->first();

                if ($correctUom) {
                    // Reassign products pointing to typoUom
                    Product::where('uom_id', $typoUom->id)->update(['uom_id' => $correctUom->id]);
                    $typoUom->delete();
                    Log::info("Merged UOM 'bh]' (ID: {$typoUom->id}) into 'BH' (ID: {$correctUom->id})");
                } else {
                    $typoUom->update([
                        'name' => 'BH',
                        'abbreviation' => 'BH',
                    ]);
                    Log::info("Renamed UOM 'bh]' to 'BH'");
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Migration cleanup UOM: {$e->getMessage()}");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data repair migration does not need rollback logic.
    }
};
