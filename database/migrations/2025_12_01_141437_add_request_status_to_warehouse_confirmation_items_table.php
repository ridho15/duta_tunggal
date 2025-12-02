<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('warehouse_confirmation_items', function (Blueprint $table) {
            DB::statement("ALTER TABLE warehouse_confirmation_items MODIFY COLUMN status ENUM('request', 'confirmed', 'partial_confirmed', 'rejected') NOT NULL");
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_confirmation_items', function (Blueprint $table) {
            DB::statement("ALTER TABLE warehouse_confirmation_items MODIFY COLUMN status ENUM('confirmed', 'partial_confirmed', 'rejected') NOT NULL");
        });
    }
};
