<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Clean up legacy payment_requests rows that stored '-' as a date value.
 * The PaymentRequest model already has accessor/mutator guards, but existing
 * records with '-' will still throw DateMalformedStringException when Filament
 * reads them for the table / infolist views before the accessor fires.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE payment_requests SET payment_date = NULL WHERE CAST(payment_date AS CHAR) IN ('-', '')");
        DB::statement("UPDATE payment_requests SET request_date = NULL WHERE CAST(request_date AS CHAR) IN ('-', '')");
    }

    public function down(): void
    {
        // Intentionally irreversible — bad data should not be restored.
    }
};
