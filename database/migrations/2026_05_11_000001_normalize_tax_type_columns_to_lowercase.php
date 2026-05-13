<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE purchase_order_items MODIFY tipe_pajak VARCHAR(20) NOT NULL DEFAULT 'eklusif'");

        DB::statement("UPDATE purchase_order_items SET tipe_pajak = CASE
            WHEN LOWER(TRIM(tipe_pajak)) IN ('non pajak', 'none', 'non-pajak', 'nonpajak') THEN 'none'
            WHEN LOWER(TRIM(tipe_pajak)) IN ('inklusif', 'ppn included', 'included', 'ppn-included') THEN 'inklusif'
            ELSE 'eklusif'
        END");

        DB::statement("ALTER TABLE order_request_items MODIFY tipe_pajak VARCHAR(20) NOT NULL DEFAULT 'eklusif'");

        DB::statement("UPDATE order_request_items SET tipe_pajak = CASE
            WHEN LOWER(TRIM(tipe_pajak)) IN ('non pajak', 'none', 'non-pajak', 'nonpajak') THEN 'none'
            WHEN LOWER(TRIM(tipe_pajak)) IN ('inklusif', 'ppn included', 'included', 'ppn-included') THEN 'inklusif'
            ELSE 'eklusif'
        END");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE purchase_order_items MODIFY tipe_pajak ENUM('Non Pajak', 'Inklusif', 'Eklusif') NOT NULL DEFAULT 'Eklusif'");
        DB::statement("ALTER TABLE order_request_items MODIFY tipe_pajak ENUM('Non Pajak', 'Inklusif', 'Eklusif') NOT NULL DEFAULT 'Eklusif'");
    }
};
