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
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('discount', 15, 2)->default(0)->after('price');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('discount');
            $table->decimal('tax_amount', 15, 2)->default(0)->after('tax_rate');
            $table->decimal('subtotal', 15, 2)->default(0)->after('tax_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn(['discount', 'tax_rate', 'tax_amount', 'subtotal']);
        });
    }
};
