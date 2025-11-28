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
        Schema::table('sale_orders', function (Blueprint $table) {
            // Check if column doesn't exist before adding
            if (!Schema::hasColumn('sale_orders', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('reason_close');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_orders', function (Blueprint $table) {
            if (Schema::hasColumn('sale_orders', 'created_by')) {
                // Cek foreign key dengan query ke information_schema
                $conn = Schema::getConnection();
                $dbName = $conn->getDatabaseName();
                $fkExists = $conn->selectOne("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'sale_orders' AND COLUMN_NAME = 'created_by' AND REFERENCED_TABLE_NAME = 'users'", [$dbName]);
                if ($fkExists && isset($fkExists->CONSTRAINT_NAME)) {
                    $table->dropForeign($fkExists->CONSTRAINT_NAME);
                }
                $table->dropColumn('created_by');
            }
        });
    }
};