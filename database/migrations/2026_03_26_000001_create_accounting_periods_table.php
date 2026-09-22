<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 20)->default('open');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('cabang_id')->nullable()->constrained('cabangs')->nullOnDelete();
            $table->timestamps();

            $table->index(['cabang_id', 'period_start', 'period_end'], 'accounting_periods_cabang_period_idx');
            $table->index(['cabang_id', 'status'], 'accounting_periods_cabang_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_periods');
    }
};
