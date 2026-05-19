<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        if (! Schema::hasTable('order_requests')) {
            return;
        }

        Schema::table('order_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('order_requests', 'tax_type')) {
                $table->string('tax_type')->nullable()->after('note');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        if (! Schema::hasTable('order_requests')) {
            return;
        }

        Schema::table('order_requests', function (Blueprint $table) {
            if (Schema::hasColumn('order_requests', 'tax_type')) {
                $table->dropColumn('tax_type');
            }
        });
    }
};
