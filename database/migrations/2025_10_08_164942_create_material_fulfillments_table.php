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
        Schema::create('material_fulfillments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_plan_id')->constrained('production_plans')->onDelete('cascade');
            $table->foreignId('material_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('uom_id')->constrained('unit_of_measures')->onDelete('cascade');
            $table->decimal('required_quantity', 15, 4)->default(0);
            $table->decimal('current_stock', 15, 4)->default(0);
            $table->decimal('issued_quantity', 15, 4)->default(0);
            $table->decimal('remaining_to_issue', 15, 4)->default(0);
            $table->decimal('availability_percentage', 5, 2)->default(0);
            $table->decimal('usage_percentage', 5, 2)->default(0);
            $table->timestamp('last_updated_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['production_plan_id', 'material_id']);
            $table->index('last_updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_fulfillments');
    }
};
