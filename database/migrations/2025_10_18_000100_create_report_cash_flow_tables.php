<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_cash_flow_sections', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('report_cash_flow_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')
                ->constrained('report_cash_flow_sections')
                ->cascadeOnDelete();
            $table->string('key')->unique();
            $table->string('label');
            $table->enum('type', ['inflow', 'outflow', 'net'])->default('outflow');
            $table->string('resolver')->nullable();
            $table->boolean('include_assets')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('report_cash_flow_item_prefixes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')
                ->constrained('report_cash_flow_items')
                ->cascadeOnDelete();
            $table->string('prefix', 32);
            $table->boolean('is_asset')->default(false);
            $table->timestamps();
            $table->index(['item_id', 'is_asset']);
        });

        Schema::create('report_cash_flow_item_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')
                ->constrained('report_cash_flow_items')
                ->cascadeOnDelete();
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('report_cash_flow_cash_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('prefix', 32)->unique();
            $table->string('label')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_cash_flow_cash_accounts');
        Schema::dropIfExists('report_cash_flow_item_sources');
        Schema::dropIfExists('report_cash_flow_item_prefixes');
        Schema::dropIfExists('report_cash_flow_items');
        Schema::dropIfExists('report_cash_flow_sections');
    }
};
