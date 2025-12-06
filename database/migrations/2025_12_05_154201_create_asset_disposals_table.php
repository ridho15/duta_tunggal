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
        Schema::create('asset_disposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->onDelete('cascade');
            $table->date('disposal_date');
            $table->enum('disposal_type', ['sale', 'scrap', 'donation', 'theft', 'other']);
            $table->decimal('sale_price', 15, 2)->nullable();
            $table->decimal('book_value_at_disposal', 15, 2);
            $table->decimal('gain_loss_amount', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('disposal_document')->nullable(); // path to document
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->enum('status', ['pending', 'approved', 'completed', 'cancelled'])->default('pending');
            $table->timestamps();

            $table->index(['asset_id', 'status']);
            $table->index('disposal_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_disposals');
    }
};
