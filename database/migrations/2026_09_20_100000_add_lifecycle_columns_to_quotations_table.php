<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Siklus hidup Quotation:
     *  - status baru "expired" (Kedaluwarsa)
     *  - revisi lewat versi baru: revision_of_id, revision_no
     *  - superseded_at : versi lama yang sudah digantikan revisi yang disetujui
     *  - expired_at    : kapan ditandai kedaluwarsa oleh job harian
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE quotations MODIFY COLUMN status ENUM('draft','request_approve','approve','reject','expired') NOT NULL DEFAULT 'draft'");
        }

        Schema::table('quotations', function (Blueprint $table) {
            if (! Schema::hasColumn('quotations', 'revision_of_id')) {
                $table->unsignedBigInteger('revision_of_id')->nullable()->after('quotation_number')->index();
            }
            if (! Schema::hasColumn('quotations', 'revision_no')) {
                $table->unsignedSmallInteger('revision_no')->default(0)->after('revision_of_id');
            }
            if (! Schema::hasColumn('quotations', 'superseded_at')) {
                $table->timestamp('superseded_at')->nullable()->after('approve_at');
            }
            if (! Schema::hasColumn('quotations', 'expired_at')) {
                $table->timestamp('expired_at')->nullable()->after('superseded_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            foreach (['expired_at', 'superseded_at', 'revision_no'] as $column) {
                if (Schema::hasColumn('quotations', $column)) {
                    $table->dropColumn($column);
                }
            }
            if (Schema::hasColumn('quotations', 'revision_of_id')) {
                $table->dropIndex(['revision_of_id']);
                $table->dropColumn('revision_of_id');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::table('quotations')->where('status', 'expired')->update(['status' => 'approve']);
            DB::statement("ALTER TABLE quotations MODIFY COLUMN status ENUM('draft','request_approve','approve','reject') NOT NULL DEFAULT 'draft'");
        }
    }
};
