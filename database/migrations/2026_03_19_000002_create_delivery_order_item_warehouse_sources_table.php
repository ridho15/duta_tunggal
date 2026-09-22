<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('delivery_order_item_warehouse_sources')) {
            Schema::create('delivery_order_item_warehouse_sources', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('delivery_order_item_id');
                $table->unsignedBigInteger('warehouse_id');
                $table->unsignedBigInteger('rak_id')->nullable();
                $table->decimal('quantity', 15, 2);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        try {
            Schema::table('delivery_order_item_warehouse_sources', function (Blueprint $table) {
                $table->index(['delivery_order_item_id', 'warehouse_id'], 'do_item_warehouse_source_idx');
            });
        } catch (\Throwable $e) {
        }

        try {
            DB::statement('ALTER TABLE delivery_order_item_warehouse_sources ADD CONSTRAINT doiws_doi_fk FOREIGN KEY (delivery_order_item_id) REFERENCES delivery_order_items(id) ON DELETE CASCADE');
        } catch (\Throwable $e) {
        }

        try {
            DB::statement('ALTER TABLE delivery_order_item_warehouse_sources ADD CONSTRAINT doiws_wh_fk FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)');
        } catch (\Throwable $e) {
        }

        try {
            DB::statement('ALTER TABLE delivery_order_item_warehouse_sources ADD CONSTRAINT doiws_rak_fk FOREIGN KEY (rak_id) REFERENCES raks(id) ON DELETE SET NULL');
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_order_item_warehouse_sources');
    }
};
