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
        Schema::create('payment_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique()->comment('Auto-generated PR number');
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('cabang_id');
            $table->unsignedBigInteger('requested_by')->comment('User who created the request');
            $table->unsignedBigInteger('approved_by')->nullable()->comment('User who approved/rejected');
            $table->date('request_date');
            $table->date('payment_date')->nullable()->comment('Requested payment date');
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->json('selected_invoices')->nullable()->comment('Invoice IDs to be paid');
            $table->text('notes')->nullable();
            $table->text('approval_notes')->nullable();
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'rejected', 'paid'])
                  ->default('draft');
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('vendor_payment_id')->nullable()->comment('Linked VendorPayment after paid');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_requests');
    }
};
