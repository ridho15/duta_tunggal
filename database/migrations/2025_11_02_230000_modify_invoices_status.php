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
        Schema::table('invoices', function (Blueprint $table) {
            // Add 'unpaid' to invoices.status enum to match tests and domain usage
            $table->enum('status', ['draft', 'sent', 'paid', 'partially_paid', 'overdue', 'unpaid'])->default('draft')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->enum('status', ['draft', 'sent', 'paid', 'partially_paid', 'overdue'])->default('draft')->change();
        });
    }
};
