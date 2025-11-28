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
        if (! Schema::hasTable('voucher_requests')) {
            return;
        }

        Schema::table('voucher_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('voucher_requests', 'requested_to_owner_at')) {
                $table->timestamp('requested_to_owner_at')->nullable()->after('approval_notes')->comment('Waktu saat request dikirim ke Owner');
            }

            if (! Schema::hasColumn('voucher_requests', 'requested_to_owner_by')) {
                $table->foreignId('requested_to_owner_by')->nullable()->constrained('users')->nullOnDelete()->after('requested_to_owner_at')->comment('User yang meminta Owner diberi tahu');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('voucher_requests')) {
            return;
        }

        Schema::table('voucher_requests', function (Blueprint $table) {
            if (Schema::hasColumn('voucher_requests', 'requested_to_owner_by')) {
                $table->dropConstrainedForeignId('requested_to_owner_by');
            }

            if (Schema::hasColumn('voucher_requests', 'requested_to_owner_at')) {
                $table->dropColumn('requested_to_owner_at');
            }
        });
    }
};
