<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasTable('quotations')) {
            return;
        }

        Schema::table('quotations', function (Blueprint $table) {
            $table->unsignedBigInteger('currency_id')->nullable()->after('id');
            $table->decimal('exchange_rate', 18, 8)->nullable()->after('currency_id');
        });

        // Backfill existing rows with IDR if available and set exchange_rate = 1
        $idr = DB::table('currencies')->whereRaw('UPPER(code) = ?', ['IDR'])->value('id');
        if ($idr) {
            DB::table('quotations')->whereNull('currency_id')->update([
                'currency_id' => $idr,
                'exchange_rate' => 1,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (! Schema::hasTable('quotations')) {
            return;
        }

        Schema::table('quotations', function (Blueprint $table) {
            if (Schema::hasColumn('quotations', 'exchange_rate')) {
                $table->dropColumn('exchange_rate');
            }
            if (Schema::hasColumn('quotations', 'currency_id')) {
                $table->dropColumn('currency_id');
            }
        });
    }
};
