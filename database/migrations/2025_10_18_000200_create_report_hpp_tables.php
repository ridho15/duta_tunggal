<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_hpp_prefixes', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->string('prefix', 32);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['category', 'sort_order']);
        });

        Schema::create('report_hpp_overhead_items', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('report_hpp_overhead_item_prefixes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('overhead_item_id')
                ->constrained('report_hpp_overhead_items')
                ->cascadeOnDelete();
            $table->string('prefix', 32);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_hpp_overhead_item_prefixes');
        Schema::dropIfExists('report_hpp_overhead_items');
        Schema::dropIfExists('report_hpp_prefixes');
    }
};
