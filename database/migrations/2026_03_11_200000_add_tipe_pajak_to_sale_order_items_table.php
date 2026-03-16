<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_order_items', function (Blueprint $table) {
            $table->string('tipe_pajak', 20)->default('Exclusive')->after('tax')
                  ->comment('Tax type: Exclusive (PPN added on top) or Inclusive (PPN inside price)');
        });
    }

    public function down(): void
    {
        Schema::table('sale_order_items', function (Blueprint $table) {
            $table->dropColumn('tipe_pajak');
        });
    }
};
