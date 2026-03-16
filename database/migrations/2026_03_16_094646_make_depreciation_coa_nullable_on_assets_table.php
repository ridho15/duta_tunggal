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
        Schema::table('assets', function (Blueprint $table) {
            $table->unsignedBigInteger('accumulated_depreciation_coa_id')->nullable()->change();
            $table->unsignedBigInteger('depreciation_expense_coa_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->unsignedBigInteger('accumulated_depreciation_coa_id')->nullable(false)->change();
            $table->unsignedBigInteger('depreciation_expense_coa_id')->nullable(false)->change();
        });
    }
};
