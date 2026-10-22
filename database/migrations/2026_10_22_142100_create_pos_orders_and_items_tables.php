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
        Schema::create('pos_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique()->index();
            $table->foreignId('pos_shift_id')->nullable()->constrained('pos_shifts')->nullOnDelete();
            $table->foreignId('cabang_id')->nullable()->constrained('cabangs')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('customer_name')->default('Pelanggan Umum');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('order_date')->index();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->string('payment_method', 30)->default('cash'); // 'cash', 'transfer', 'qris', 'debit'
            $table->decimal('payment_amount', 15, 2)->default(0); // Uang yang diterima
            $table->decimal('change_amount', 15, 2)->default(0);  // Uang kembalian
            $table->foreignId('coa_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete(); // Akun kas penerimaan
            $table->string('status', 20)->default('completed')->index(); // 'completed', 'cancelled'
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pos_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_order_id')->constrained('pos_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('harga_batas', 15, 2)->default(0); // Batas minimum harga saat transaksi
            $table->decimal('cost_price', 15, 2)->default(0);  // HPP snapshot
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_order_items');
        Schema::dropIfExists('pos_orders');
    }
};
