<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rename branch_id → cabang_id on customer_returns to match
     * the rest of the codebase and CabangScope convention.
     */
    public function up(): void
    {
        Schema::table('customer_returns', function (Blueprint $table) {
            // Drop the old foreign key first
            $table->dropForeign(['branch_id']);
            $table->renameColumn('branch_id', 'cabang_id');
            // Re-add FK on the renamed column
            $table->foreign('cabang_id')->references('id')->on('cabangs')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('customer_returns', function (Blueprint $table) {
            $table->dropForeign(['cabang_id']);
            $table->renameColumn('cabang_id', 'branch_id');
            $table->foreign('branch_id')->references('id')->on('cabangs')->onDelete('set null');
        });
    }
};
