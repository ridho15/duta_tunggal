<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add delivery_failed to the status enum
        DB::statement("ALTER TABLE `delivery_orders` MODIFY `status` ENUM(
            'draft','sent','confirmed','received','supplier','completed',
            'request_approve','approved','request_close','closed','reject','delivery_failed'
        ) NOT NULL DEFAULT 'draft'");

        // Make driver_id and vehicle_id nullable so DO can be auto-created
        // even when no driver/vehicle master data exists yet
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->integer('driver_id')->nullable()->change();
            $table->integer('vehicle_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE `delivery_orders` MODIFY `status` ENUM(
            'draft','sent','confirmed','received','supplier','completed',
            'request_approve','approved','request_close','closed','reject'
        ) NOT NULL DEFAULT 'draft'");

        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->integer('driver_id')->nullable(false)->change();
            $table->integer('vehicle_id')->nullable(false)->change();
        });
    }
};
