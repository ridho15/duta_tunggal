<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates `cash_bank_accounts` table if it does not exist.
     * Includes `coa_id` to map to ChartOfAccount.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('cash_bank_accounts')) {
            Schema::create('cash_bank_accounts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('bank_name')->nullable();
                $table->string('account_number')->nullable();
                $table->unsignedBigInteger('coa_id')->nullable()->index();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        } else {
            if (!Schema::hasColumn('cash_bank_accounts', 'coa_id')) {
                Schema::table('cash_bank_accounts', function (Blueprint $table) {
                    $table->unsignedBigInteger('coa_id')->nullable()->after('account_number')->index();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('cash_bank_accounts')) {
            // Only drop the table if it was created by this migration (best effort)
            // To be safe, only drop the column if table existed previously
            if (Schema::hasColumn('cash_bank_accounts', 'coa_id')) {
                Schema::table('cash_bank_accounts', function (Blueprint $table) {
                    $table->dropColumn('coa_id');
                });
            }
        }
    }
};
