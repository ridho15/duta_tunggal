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
        Schema::create('warehouse_confirmation_warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_confirmation_id');
            $table->foreignId('warehouse_id');
            $table->enum('status', ['request', 'confirmed', 'partial_confirmed', 'rejected'])->default('request');
            $table->foreignId('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('warehouse_confirmation_id', 'wcw_wc_id_fk')->references('id')->on('warehouse_confirmations')->onDelete('cascade');
            $table->foreign('warehouse_id', 'wcw_wh_id_fk')->references('id')->on('warehouses')->onDelete('cascade');
            $table->foreign('confirmed_by', 'wcw_cb_id_fk')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_confirmation_warehouses');
    }
};
