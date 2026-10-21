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
        // Ubah kolom status dari enum('Draft','Partial','Paid') menjadi VARCHAR(50)
        // agar mendukung status 'Cancelled'
        DB::statement("ALTER TABLE `vendor_payments` MODIFY `status` VARCHAR(50) NOT NULL DEFAULT 'Draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE `vendor_payments` MODIFY `status` ENUM('Draft', 'Partial', 'Paid') NOT NULL DEFAULT 'Draft'");
    }
};
