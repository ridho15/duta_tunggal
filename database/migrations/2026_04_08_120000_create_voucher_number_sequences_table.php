<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->date('sequence_date')->unique();
            $table->unsignedInteger('last_sequence')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_number_sequences');
    }
};
