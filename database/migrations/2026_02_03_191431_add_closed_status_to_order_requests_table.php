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
        Schema::table('order_requests', function (Blueprint $table) {
            // Modify status enum to include 'closed'
            $table->enum('status', ['draft', 'approved', 'rejected', 'closed'])->default('draft')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_requests', function (Blueprint $table) {
            // Revert back to original enum values
            $table->enum('status', ['draft', 'approved', 'rejected'])->default('draft')->change();
        });
    }
};
