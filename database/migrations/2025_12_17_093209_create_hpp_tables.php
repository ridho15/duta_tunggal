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
        if (!Schema::hasTable('report_hpp_prefixes')) {
            Schema::create('report_hpp_prefixes', function (Blueprint $table) {
                $table->id();
                $table->string('category');
                $table->string('prefix');
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('report_hpp_overhead_items')) {
            Schema::create('report_hpp_overhead_items', function (Blueprint $table) {
                $table->id();
                $table->string('key');
                $table->string('label');
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('report_hpp_overhead_item_prefixes')) {
            Schema::create('report_hpp_overhead_item_prefixes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('overhead_item_id')->constrained('report_hpp_overhead_items')->onDelete('cascade');
                $table->string('prefix');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_hpp_overhead_item_prefixes');
        Schema::dropIfExists('report_hpp_overhead_items');
        Schema::dropIfExists('report_hpp_prefixes');
    }
};
