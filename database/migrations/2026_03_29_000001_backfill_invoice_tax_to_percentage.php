<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill legacy invoice tax values so the `tax` column stores a percentage rate.
     *
     * Historical purchase invoices stored a nominal tax amount in `tax` while also
     * having `ppn_rate`. New code now treats `tax` as the percentage rate, so we
     * normalize existing rows to the same convention.
     */
    public function up(): void
    {
        DB::table('invoices')
            ->select(['id', 'subtotal', 'tax', 'ppn_rate'])
            ->orderBy('id')
            ->chunkById(200, function ($invoices) {
                foreach ($invoices as $invoice) {
                    $subtotal = (float) ($invoice->subtotal ?? 0);
                    $legacyTax = (float) ($invoice->tax ?? 0);
                    $existingPpnRate = (float) ($invoice->ppn_rate ?? 0);

                    if ($existingPpnRate > 0) {
                        DB::table('invoices')
                            ->where('id', $invoice->id)
                            ->update([
                                'tax' => $existingPpnRate,
                                'ppn_rate' => $existingPpnRate,
                            ]);

                        continue;
                    }

                    if ($legacyTax <= 0) {
                        continue;
                    }

                    $derivedRate = $legacyTax <= 100
                        ? $legacyTax
                        : ($subtotal > 0 ? round(($legacyTax / $subtotal) * 100, 2) : null);

                    if ($derivedRate === null) {
                        continue;
                    }

                    DB::table('invoices')
                        ->where('id', $invoice->id)
                        ->update([
                            'tax' => $derivedRate,
                            'ppn_rate' => $derivedRate,
                        ]);
                }
            }, 'id');
    }

    /**
     * This migration is data-normalization only; the original nominal values cannot
     * be reconstructed reliably from the backfilled rate.
     */
    public function down(): void
    {
        // Intentionally left blank.
    }
};