<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nomor resi ekspedisi dipisahkan dari `vehicle_info` (sebelumnya satu kolom untuk
     * plat / nama ekspedisi / resi) supaya dapat dicetak di Surat Jalan dan dicari.
     */
    public function up(): void
    {
        Schema::table('delivery_schedules', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_schedules', 'tracking_number')) {
                $table->string('tracking_number', 255)->nullable()->after('vehicle_info');
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_schedules', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_schedules', 'tracking_number')) {
                $table->dropColumn('tracking_number');
            }
        });
    }
};
