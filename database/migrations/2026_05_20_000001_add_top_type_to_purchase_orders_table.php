<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('purchase_orders', 'top_type')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->string('top_type', 50)->nullable()->after('approval_signed_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('purchase_orders', 'top_type')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->dropColumn('top_type');
            });
        }
    }
};