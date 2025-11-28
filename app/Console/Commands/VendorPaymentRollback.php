<?php

namespace App\Console\Commands;

use App\Models\AccountPayable;
use App\Models\JournalEntry;
use App\Models\VendorPayment;
use App\Models\VendorPaymentDetail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VendorPaymentRollback extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Usage:
     *  php artisan vendor-payment:rollback --invoice-id=1 [--dry-run]
     *  php artisan vendor-payment:rollback --payment-id=3 [--dry-run]
     *
     * @var string
     */
    protected $signature = 'vendor-payment:rollback
                            {--invoice-id= : Invoice ID to rollback payments for}
                            {--payment-id= : VendorPayment ID to rollback}
                            {--dry-run : Show what would be changed, do not execute}
                            {--yes : Execute without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rollback (remove) vendor payment details/payments and related journal entries and restore AccountPayable paid/remaining values';

    public function handle()
    {
        $invoiceId = $this->option('invoice-id');
        $paymentId = $this->option('payment-id');
        $dryRun = $this->option('dry-run');
        $autoYes = $this->option('yes');

        if (empty($invoiceId) && empty($paymentId)) {
            $this->error('Provide either --invoice-id or --payment-id');
            return 1;
        }

        // Determine affected details and payments
        if ($invoiceId) {
            $details = VendorPaymentDetail::where('invoice_id', $invoiceId)->get();
            $paymentIds = $details->pluck('vendor_payment_id')->unique()->filter()->values()->all();
        } else {
            $paymentIds = [$paymentId];
            $details = VendorPaymentDetail::whereIn('vendor_payment_id', $paymentIds)->get();
        }

        if ($details->isEmpty() && empty($paymentIds)) {
            $this->info('No vendor payment details found for given criteria. Nothing to do.');
            return 0;
        }

        // Summarize
        $this->info('Affected VendorPayment IDs: ' . implode(', ', $paymentIds ?: ['(none)']));
        $this->info('VendorPaymentDetail rows to remove: ' . $details->count());

        $groupedByInvoice = $details->groupBy('invoice_id')->map(function ($col) {
            return $col->sum('amount');
        })->toArray();

        foreach ($groupedByInvoice as $inv => $sum) {
            $this->info("Invoice {$inv} - total detail amount to remove: " . number_format($sum, 2));
        }

        if ($dryRun) {
            $this->info('Dry-run mode: no changes will be made.');
            return 0;
        }

        if (!$autoYes) {
            if (!$this->confirm('Proceed to delete listed records and update AccountPayable?')) {
                $this->info('Aborted.');
                return 0;
            }
        }

        DB::beginTransaction();
        try {
            $deletedDetailIds = [];
            $affectedPayments = VendorPayment::whereIn('id', $paymentIds)->get();

            // Delete details (soft delete)
            foreach ($details as $detail) {
                $deletedDetailIds[] = $detail->id;
                $detail->delete();
            }

            // Delete JournalEntry rows related to affected payments
            $journalCount = JournalEntry::whereIn('source_id', $paymentIds)
                ->where('source_type', VendorPayment::class)
                ->count();

            JournalEntry::whereIn('source_id', $paymentIds)
                ->where('source_type', VendorPayment::class)
                ->delete();

            // For each payment, if it has no (non-deleted) details left, delete the payment
            foreach ($affectedPayments as $payment) {
                $remainingDetails = $payment->vendorPaymentDetail()->count();
                if ($remainingDetails <= 0) {
                    $this->line("Deleting VendorPayment id={$payment->id} because it has no details");
                    $payment->delete();
                } else {
                    // Recalculate total_payment
                    $payment->recalculateTotalPayment();
                }
            }

            // Recalculate AccountPayable for affected invoices
            $affectedInvoiceIds = $details->pluck('invoice_id')->unique()->filter()->values()->all();
            foreach ($affectedInvoiceIds as $invId) {
                $ap = AccountPayable::where('invoice_id', $invId)->first();
                if (!$ap) continue;

                $paid = (float) VendorPaymentDetail::where('invoice_id', $invId)->sum('amount');
                $paid = round($paid, 2);
                $remaining = max(0, round($ap->total - $paid, 2));

                $status = $remaining <= 0.01 ? 'Lunas' : 'Belum Lunas';

                $ap->update([
                    'paid' => $paid,
                    'remaining' => $remaining,
                    'status' => $status,
                ]);

                $this->line("Updated AccountPayable for invoice_id={$invId}: paid={$paid}, remaining={$remaining}, status={$status}");
            }

            DB::commit();

            $this->info('Rollback completed.');
            $this->info('Deleted VendorPaymentDetail IDs: ' . implode(', ', $deletedDetailIds ?: ['(none)']));
            $this->info('Deleted JournalEntry rows: ' . $journalCount);
            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error during rollback: ' . $e->getMessage());
            return 1;
        }
    }
}
