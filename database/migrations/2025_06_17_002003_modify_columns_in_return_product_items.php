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
        Schema::table('return_product_items', function (Blueprint $table) {
            $table->integer('from_item_model_id')->nullable()->change();
            $table->string('from_item_model_type')->nullable()->change();
            $table->integer('rak_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('return_product_items', function (Blueprint $table) {
            //
        });
    }
};
