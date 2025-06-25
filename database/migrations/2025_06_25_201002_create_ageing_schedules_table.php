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
        Schema::create('ageing_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_payable_id');
            $table->date('invoice_date');
            $table->date('due_date');
            $table->integer('days_outstanding');
            $table->enum('bucket', ['Current', '31–60', '61–90', '>90']);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ageing_schedules');
    }
};
