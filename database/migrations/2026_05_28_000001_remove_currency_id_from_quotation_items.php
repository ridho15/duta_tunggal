<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasTable('quotation_items')) {
            return;
        }

        Schema::table('quotation_items', function (Blueprint $table) {
            if (Schema::hasColumn('quotation_items', 'currency_id')) {
                // Attempt to drop foreign key first if it exists, then drop the column
                try {
                    $table->dropForeign(['currency_id']);
                } catch (\Throwable $e) {
                    // ignore if foreign key does not exist
                }

                $table->dropColumn('currency_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (! Schema::hasTable('quotation_items')) {
            return;
        }

        Schema::table('quotation_items', function (Blueprint $table) {
            if (! Schema::hasColumn('quotation_items', 'currency_id')) {
                $table->unsignedBigInteger('currency_id')->nullable()->after('unit_price_idr');
                if (Schema::hasTable('currencies')) {
                    $table->foreign('currency_id')->references('id')->on('currencies')->nullOnDelete();
                }
            }
        });
    }
};
