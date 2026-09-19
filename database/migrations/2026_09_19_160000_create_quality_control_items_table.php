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
        Schema::table('quality_controls', function (Blueprint $table) {
            if (!Schema::hasColumn('quality_controls', 'purchase_order_id')) {
                $table->foreignId('purchase_order_id')
                    ->nullable()
                    ->after('qc_number')
                    ->constrained('purchase_orders')
                    ->nullOnDelete();
            }
            if (Schema::hasColumn('quality_controls', 'product_id')) {
                $table->unsignedBigInteger('product_id')->nullable()->change();
            }
        });

        if (!Schema::hasTable('quality_control_items')) {
            Schema::create('quality_control_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quality_control_id')
                    ->constrained('quality_controls')
                    ->cascadeOnDelete();
                $table->foreignId('purchase_order_item_id')
                    ->constrained('purchase_order_items')
                    ->cascadeOnDelete();
                $table->foreignId('product_id')
                    ->constrained('products')
                    ->cascadeOnDelete();
                $table->decimal('quantity_received', 15, 2)->default(0);
                $table->decimal('passed_quantity', 15, 2)->default(0);
                $table->decimal('rejected_quantity', 15, 2)->default(0);
                $table->string('failed_qc_action', 50)->nullable()->default('wait_next_delivery');
                $table->text('reason_reject')->nullable();
                $table->foreignId('rak_id')->nullable()->constrained('raks')->nullOnDelete();
                $table->tinyInteger('status')->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quality_control_items');

        Schema::table('quality_controls', function (Blueprint $table) {
            if (Schema::hasColumn('quality_controls', 'purchase_order_id')) {
                $table->dropForeign(['purchase_order_id']);
                $table->dropColumn('purchase_order_id');
            }
        });
    }
};
