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
        Schema::table('quality_controls', function (Blueprint $table) {
            $table->decimal('passed_quantity', 10,2)->default(0)->change();
            $table->decimal('rejected_quantity', 10,2)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quality_controls', function (Blueprint $table) {
            //
        });
    }
};
