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
        // First, clean up duplicate codes and empty codes
        // DB::statement("
        //     UPDATE suppliers 
        //     SET code = CONCAT('SUPP-', LPAD(id, 5, '0'))
        //     WHERE code = '' OR code IS NULL OR code IN (
        //         SELECT code FROM (
        //             SELECT code 
        //             FROM suppliers 
        //             WHERE code != '' AND code IS NOT NULL
        //             GROUP BY code 
        //             HAVING COUNT(*) > 1
        //         ) AS duplicates
        //     )
        // ");
        
        Schema::table('suppliers', function (Blueprint $table) {
            // Make code NOT NULL and add unique constraint
            $table->string('code')->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->string('code')->nullable()->change();
        });
    }
};
