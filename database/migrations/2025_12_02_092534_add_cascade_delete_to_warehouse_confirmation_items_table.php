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
        Schema::table('warehouse_confirmation_items', function (Blueprint $table) {
            // Drop existing foreign key
            $table->dropForeign(['warehouse_confirmation_id']);
            
            // Recreate foreign key with CASCADE DELETE
            $table->foreign('warehouse_confirmation_id')
                  ->references('id')
                  ->on('warehouse_confirmations')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouse_confirmation_items', function (Blueprint $table) {
            // Drop the CASCADE foreign key
            $table->dropForeign(['warehouse_confirmation_id']);
            
            // Recreate the original foreign key without CASCADE
            $table->foreign('warehouse_confirmation_id')
                  ->references('id')
                  ->on('warehouse_confirmations');
        });
    }
};
