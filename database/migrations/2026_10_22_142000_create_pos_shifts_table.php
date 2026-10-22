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
        Schema::create('pos_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('shift_number')->unique()->index();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('cabang_id')->nullable()->constrained('cabangs')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->dateTime('opened_at')->index();
            $table->dateTime('closed_at')->nullable()->index();
            $table->decimal('starting_cash', 15, 2)->default(0);
            $table->decimal('cash_sales_total', 15, 2)->default(0);
            $table->decimal('non_cash_sales_total', 15, 2)->default(0);
            $table->decimal('total_sales', 15, 2)->default(0);
            $table->unsignedInteger('total_transactions')->default(0);
            $table->decimal('cash_actual', 15, 2)->nullable();
            $table->decimal('cash_difference', 15, 2)->nullable();
            $table->string('status', 20)->default('open')->index(); // 'open', 'closed'
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_shifts');
    }
};
