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
        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'rejected'])->default('draft')->after('notes');
            $table->unsignedBigInteger('approved_by')->nullable()->after('status');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('approval_notes')->nullable()->after('approved_at');
            $table->unsignedBigInteger('rejected_by')->nullable()->after('approval_notes');
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            $table->text('rejection_notes')->nullable()->after('rejected_at');
            $table->string('credit_note_number')->nullable()->after('rejection_notes');
            $table->date('credit_note_date')->nullable()->after('credit_note_number');
            $table->decimal('credit_note_amount', 15, 2)->nullable()->after('credit_note_date');
            $table->decimal('refund_amount', 15, 2)->nullable()->after('credit_note_amount');
            $table->date('refund_date')->nullable()->after('refund_amount');
            $table->string('refund_method')->nullable()->after('refund_date');

            $table->foreign('approved_by')->references('id')->on('users');
            $table->foreign('rejected_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['rejected_by']);
            $table->dropColumn([
                'status',
                'approved_by',
                'approved_at',
                'approval_notes',
                'rejected_by',
                'rejected_at',
                'rejection_notes',
                'credit_note_number',
                'credit_note_date',
                'credit_note_amount',
                'refund_amount',
                'refund_date',
                'refund_method',
            ]);
        });
    }
};
