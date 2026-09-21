<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** T4.2/T4.3 (D32/D33) — kode lama customer (`legacy_code`) dan penanda customer yang sudah digabung (`merged_into`). */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'legacy_code')) {
                $table->string('legacy_code')->nullable()->index();
            }
            if (! Schema::hasColumn('customers', 'merged_into')) {
                $table->unsignedBigInteger('merged_into')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            foreach (['merged_into', 'legacy_code'] as $column) {
                if (Schema::hasColumn('customers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
