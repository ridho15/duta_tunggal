<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('schedule_number')->unique();
            $table->dateTime('scheduled_date');
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->enum('status', ['pending', 'on_the_way', 'delivered', 'partial_delivered', 'failed', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('cabang_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('driver_id')->references('id')->on('drivers')->nullOnDelete();
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('cabang_id')->references('id')->on('cabangs')->nullOnDelete();
        });

        Schema::create('delivery_schedule_surat_jalans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_schedule_id');
            $table->unsignedBigInteger('surat_jalan_id');
            $table->timestamps();

            $table->foreign('delivery_schedule_id')->references('id')->on('delivery_schedules')->cascadeOnDelete();
            $table->foreign('surat_jalan_id')->references('id')->on('surat_jalans')->cascadeOnDelete();

            $table->unique(['delivery_schedule_id', 'surat_jalan_id'], 'ds_sj_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_schedule_surat_jalans');
        Schema::dropIfExists('delivery_schedules');
    }
};
