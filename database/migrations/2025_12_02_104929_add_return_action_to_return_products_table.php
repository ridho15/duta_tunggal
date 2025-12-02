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
        Schema::table('return_products', function (Blueprint $table) {
            $table->enum('return_action', [
                'reduce_quantity_only',
                'close_do_partial',
                'close_so_complete'
            ])->default('reduce_quantity_only')->after('reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('return_products', function (Blueprint $table) {
            $table->dropColumn('return_action');
        });
    }
};
