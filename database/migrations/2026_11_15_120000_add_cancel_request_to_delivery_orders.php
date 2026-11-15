<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            // Alur Ko Handy #4: Sales mengajukan pembatalan DO; Gudang/Manager yang menghapus.
            $table->timestamp('cancel_requested_at')->nullable()->after('returned_at');
            $table->unsignedBigInteger('cancel_requested_by')->nullable()->after('cancel_requested_at');
            $table->string('cancel_request_reason', 500)->nullable()->after('cancel_requested_by');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropColumn(['cancel_requested_at', 'cancel_requested_by', 'cancel_request_reason']);
        });
    }
};
