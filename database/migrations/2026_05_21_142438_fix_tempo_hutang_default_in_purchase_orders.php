<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds a DEFAULT value of 0 to the tempo_hutang column in purchase_orders
     * so that it is never missing from an INSERT statement (SQLSTATE[HY000] 1364).
     *
     * The column was originally created as INT NOT NULL without a default value.
     * Any code path that omits tempo_hutang in the create() payload would cause
     * a database error. Setting DEFAULT 0 mirrors the suppliers.tempo_hutang
     * behaviour and is safe because the service layer always sets the real value
     * from the linked supplier.
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->integer('tempo_hutang')->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->integer('tempo_hutang')->nullable(false)->change();
        });
    }
};
