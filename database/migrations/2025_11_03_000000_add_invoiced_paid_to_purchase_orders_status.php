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
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Add 'invoiced' and 'paid' to allowed statuses for purchase orders
            $table->enum('status', ['draft', 'approved', 'partially_received', 'completed', 'invoiced', 'paid', 'closed', 'request_close', 'request_approval'])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Revert to previous enum values
            $table->enum('status', ['draft', 'approved', 'partially_received', 'completed', 'closed', 'request_close', 'request_approval'])->change();
        });
    }
};
