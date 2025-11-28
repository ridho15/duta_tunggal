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
        Schema::create('voucher_requests', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_number')->unique()->comment('Nomor pengajuan voucher (auto-generated)');
            $table->date('voucher_date')->comment('Tanggal pengajuan (bisa backdate)');
            $table->decimal('amount', 15, 2)->comment('Nominal pengajuan');
            $table->string('related_party')->comment('Pihak terkait (customer/supplier/lainnya)');
            $table->text('description')->nullable()->comment('Keterangan/catatan pengajuan');
            
            // Status & Approval Fields
            $table->enum('status', ['draft', 'pending', 'approved', 'rejected', 'cancelled'])->default('draft')->comment('Status pengajuan');
            
            // User Tracking
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->comment('User yang membuat pengajuan');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->comment('User yang approve');
            $table->timestamp('approved_at')->nullable()->comment('Waktu approval');
            $table->text('approval_notes')->nullable()->comment('Catatan approval/rejection');
            
            // Integration dengan Kas & Bank
            $table->foreignId('cash_bank_transaction_id')->nullable()->constrained('cash_bank_transactions')->nullOnDelete()->comment('Link ke transaksi kas/bank setelah approved');
            
            // Branch/Department (optional, sesuai kebutuhan)
            $table->foreignId('cabang_id')->nullable()->constrained('cabangs')->nullOnDelete()->comment('Cabang terkait');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('voucher_date');
            $table->index('status');
            $table->index('created_by');
            $table->index('approved_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voucher_requests');
    }
};
