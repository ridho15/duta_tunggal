<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vendor_payments', function (Blueprint $table) {
            // Add COA relationship for main payment
            $table->unsignedBigInteger('coa_id')->nullable()->after('total_payment');
            
            // Add payment method field
            $table->string('payment_method')->nullable()->after('coa_id');
            
            // Add foreign key constraint
            $table->foreign('coa_id')->references('id')->on('chart_of_accounts')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_payments', function (Blueprint $table) {
            $table->dropForeign(['coa_id']);
            $table->dropColumn(['coa_id', 'payment_method']);
        });
    }
};
