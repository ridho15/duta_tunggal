<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The account_payables table was originally created without a branch
     * (cabang_id) column.  Recent audit work has ensured that AR and other
     * financial tables are scoped by branch so records are visible only to the
     * appropriate cabang.  The InvoiceObserver still attempts to set
     * cabang_id during AP creation, and several seeders likewise assume the
     * column exists.  Add it here to avoid SQL errors on fresh installs.
     *
     * We make the column nullable so existing records are unaffected and we
     * don't need to backfill anything on upgrade.
     *
     * @return void
     */
    public function up(): void
    {
        if (! Schema::hasColumn('account_payables', 'cabang_id')) {
            Schema::table('account_payables', function (Blueprint $table) {
                $table->unsignedBigInteger('cabang_id')->nullable()->after('status');
                $table->index('cabang_id');
                $table->foreign('cabang_id')
                    ->references('id')
                    ->on('cabangs')
                    ->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        if (Schema::hasColumn('account_payables', 'cabang_id')) {
            Schema::table('account_payables', function (Blueprint $table) {
                $table->dropForeign(['cabang_id']);
                $table->dropIndex(['cabang_id']);
                $table->dropColumn('cabang_id');
            });
        }
    }
};