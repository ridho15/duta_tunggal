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
        Schema::table('delivery_schedules', function (Blueprint $table) {
            $table->string('delivery_method')->nullable()->after('vehicle_id')
                ->comment('internal, kurir_internal, ekspedisi');
            $table->string('driver_name')->nullable()->after('delivery_method')
                ->comment('Manual driver name for ekspedisi');
            $table->string('vehicle_info')->nullable()->after('driver_name')
                ->comment('Manual vehicle info / ekspedisi name for ekspedisi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_schedules', function (Blueprint $table) {
            $table->dropColumn(['delivery_method', 'driver_name', 'vehicle_info']);
        });
    }
};
