<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class ChangeOtherFeeToJsonInInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add new JSON column
        Schema::table('invoices', function (Blueprint $table) {
            $table->json('other_fees')->nullable()->after('other_fee');
        });

        // Convert existing data
        DB::table('invoices')->get()->each(function ($invoice) {
            $otherFees = [];
            if ($invoice->other_fee > 0) {
                $otherFees[] = [
                    'name' => 'Biaya Lain',
                    'amount' => $invoice->other_fee
                ];
            }
            DB::table('invoices')->where('id', $invoice->id)->update(['other_fees' => json_encode($otherFees)]);
        });

        // Drop old column and rename
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('other_fee');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->renameColumn('other_fees', 'other_fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add old column back
        Schema::table('invoices', function (Blueprint $table) {
            $table->bigInteger('other_fees')->default(0)->after('other_fee');
        });

        // Convert back
        DB::table('invoices')->get()->each(function ($invoice) {
            $total = 0;
            if ($invoice->other_fee) {
                $fees = json_decode($invoice->other_fee, true);
                $total = collect($fees)->sum('amount');
            }
            DB::table('invoices')->where('id', $invoice->id)->update(['other_fees' => $total]);
        });

        // Drop and rename
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('other_fee');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->renameColumn('other_fees', 'other_fee');
        });
    }
}
