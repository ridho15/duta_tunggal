<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('pph22_amount', 18, 2)->default(0)->after('ppn_rate');
            $table->decimal('bea_masuk_amount', 18, 2)->default(0)->after('pph22_amount');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['pph22_amount', 'bea_masuk_amount']);
        });
    }
};