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
        if (! Schema::hasTable('order_request_items')) {
            return;
        }

        Schema::table('order_request_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_request_items', 'fulfilled_quantity')) {
                $table->decimal('fulfilled_quantity', 15, 2)->default(0)->after('quantity');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('order_request_items')) {
            return;
        }

        Schema::table('order_request_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_request_items', 'fulfilled_quantity')) {
                $table->dropColumn('fulfilled_quantity');
            }
        });
    }
};
