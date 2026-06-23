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
        // Skip if column doesn't exist (will be removed in later migration)
        if (Schema::hasColumn('order_requests', 'tax_type')) {
            Schema::table('order_requests', function (Blueprint $table) {
                $table->string('tax_type', 20)->default('None')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_requests', function (Blueprint $table) {
            //
        });
    }
};
