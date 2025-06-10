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
        Schema::table('sale_orders', function (Blueprint $table) {
            $table->integer('created_by')->nullable();
            $table->integer('request_approve_by')->nullable();
            $table->dateTime('request_approve_at')->nullable();
            $table->integer('request_close_by')->nullable();
            $table->dateTime('request_close_at')->nullable();
            $table->integer('approve_by')->nullable();
            $table->dateTime('approve_at')->nullable();
            $table->integer('close_by')->nullable();
            $table->dateTime('close_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->enum('status', ['draft', 'request_approve', 'request_close', 'approved', 'closed', 'completed', 'confirmed', 'received', 'canceled'])->default('draft')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_orders', function (Blueprint $table) {
            //
        });
    }
};
