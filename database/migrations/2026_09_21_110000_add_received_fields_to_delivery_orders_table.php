<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** T2.3 (D23) — catatan penerimaan customer: kapan dan siapa yang menerima barang. */
    public function up(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_orders', 'received_at')) {
                $table->timestamp('received_at')->nullable();
            }
            if (! Schema::hasColumn('delivery_orders', 'received_by_name')) {
                $table->string('received_by_name', 150)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            foreach (['received_by_name', 'received_at'] as $column) {
                if (Schema::hasColumn('delivery_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
