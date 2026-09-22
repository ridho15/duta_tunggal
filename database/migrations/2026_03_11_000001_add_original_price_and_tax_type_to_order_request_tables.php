<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * - Adds original_price to order_request_items (price from master item, never edited by user)
     * - Adds tax_type to order_requests (PPN Included vs PPN Excluded)
     */
    public function up(): void
    {
        Schema::table('order_request_items', function (Blueprint $table) {
            // Store the original master-item price; unit_price stores the user override
            $table->decimal('original_price', 15, 2)->default(0)->after('unit_price');
        });

        Schema::table('order_requests', function (Blueprint $table) {
            // Whether the item prices already include PPN or PPN is added on top
            $table->enum('tax_type', ['PPN Included', 'PPN Excluded'])
                ->default('PPN Excluded')
                ->after('note');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_request_items', function (Blueprint $table) {
            $table->dropColumn('original_price');
        });

        Schema::table('order_requests', function (Blueprint $table) {
            $table->dropColumn('tax_type');
        });
    }
};
