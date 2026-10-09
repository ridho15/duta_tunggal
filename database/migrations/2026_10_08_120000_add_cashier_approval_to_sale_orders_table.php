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
            $table->foreignId('cashier_approved_by')
                ->nullable()
                ->after('backorder_approved_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('cashier_approved_at')
                ->nullable()
                ->after('cashier_approved_by');
            $table->text('cashier_note')
                ->nullable()
                ->after('cashier_approved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_orders', function (Blueprint $table) {
            $table->dropForeign(['cashier_approved_by']);
            $table->dropColumn([
                'cashier_approved_by',
                'cashier_approved_at',
                'cashier_note',
            ]);
        });
    }
};
