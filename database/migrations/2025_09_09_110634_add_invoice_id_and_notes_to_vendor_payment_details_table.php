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
        Schema::table('vendor_payment_details', function (Blueprint $table) {
            $table->integer('invoice_id')->nullable()->after('vendor_payment_id');
            $table->text('notes')->nullable()->after('coa_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_payment_details', function (Blueprint $table) {
            $table->dropColumn(['invoice_id', 'notes']);
        });
    }
};
