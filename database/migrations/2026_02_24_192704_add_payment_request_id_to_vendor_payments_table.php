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
        if (! Schema::hasTable('vendor_payments') || Schema::hasColumn('vendor_payments', 'payment_request_id')) {
            return;
        }

        Schema::table('vendor_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_request_id')->nullable()->after('id')
                  ->comment('Linked PaymentRequest (Task 15c)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('vendor_payments')) {
            return;
        }

        Schema::table('vendor_payments', function (Blueprint $table) {
            if (Schema::hasColumn('vendor_payments', 'payment_request_id')) {
                $table->dropColumn('payment_request_id');
            }
        });
    }
};
