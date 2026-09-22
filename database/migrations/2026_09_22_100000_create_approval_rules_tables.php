<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T3.1 — aturan persetujuan yang dapat diatur (D6/D25) + audit override (D24).
     * Nilai awal IDENTIK dengan konstanta lama ApprovalControlService (≤ Rp10 jt: Sales Manager ke atas; di atasnya: peran puncak)
     * sehingga tidak ada perubahan perilaku sampai aturan diubah di layar.
     */
    public function up(): void
    {
        if (! Schema::hasTable('approval_rules')) {
            Schema::create('approval_rules', function (Blueprint $table) {
                $table->id();
                $table->string('document_type', 50);          // quotation | sale_order
                $table->string('label', 120);
                $table->decimal('above_amount', 18, 2)->nullable();   // berlaku bila nilai > batas ini (null = dari 0)
                $table->decimal('up_to_amount', 18, 2)->nullable();   // dan nilai <= batas ini (null = tanpa batas atas)
                $table->json('roles');                         // peran yang boleh menyetujui
                $table->string('approver_label', 255);         // teks untuk pesan penolakan
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->index(['document_type', 'is_active']);
            });
        }

        if (! Schema::hasTable('approval_overrides')) {
            Schema::create('approval_overrides', function (Blueprint $table) {
                $table->id();
                $table->string('document_type', 50);
                $table->unsignedBigInteger('document_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('approval_rule_id')->nullable();
                $table->decimal('amount', 18, 2)->nullable();
                $table->text('reason');
                $table->json('context')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->index(['document_type', 'document_id']);
            });
        }

        if (DB::table('approval_rules')->count() === 0) {
            $tier1 = ['Sales Manager', 'Super Admin', 'Owner', 'Finance Manager', 'Admin'];
            $top = ['Super Admin', 'Owner', 'Finance Manager', 'Admin'];
            $now = now();

            foreach (['quotation' => 'Quotation', 'sale_order' => 'Sales Order'] as $type => $label) {
                DB::table('approval_rules')->insert([
                    [
                        'document_type' => $type, 'label' => "{$label} sampai Rp 10.000.000", 'above_amount' => null, 'up_to_amount' => 10000000,
                        'roles' => json_encode($tier1), 'approver_label' => 'Sales Manager / Direktur', 'is_active' => true,
                        'notes' => 'Nilai awal = perilaku sebelum aturan dapat diatur', 'created_at' => $now, 'updated_at' => $now,
                    ],
                    [
                        'document_type' => $type, 'label' => "{$label} di atas Rp 10.000.000", 'above_amount' => 10000000, 'up_to_amount' => null,
                        'roles' => json_encode($top), 'approver_label' => 'Direktur / Owner / Finance Manager', 'is_active' => true,
                        'notes' => 'Nilai awal = perilaku sebelum aturan dapat diatur', 'created_at' => $now, 'updated_at' => $now,
                    ],
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_overrides');
        Schema::dropIfExists('approval_rules');
    }
};
