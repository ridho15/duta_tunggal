<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Skip if column doesn't exist (will be removed in later migration)
        if (Schema::hasColumn('order_requests', 'tax_type')) {
            Schema::table('order_requests', function (Blueprint $table) {
                // Change from enum to varchar so 'None' is also a valid value
                $table->string('tax_type', 20)->default('PPN Excluded')->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_requests', 'tax_type')) {
            Schema::table('order_requests', function (Blueprint $table) {
                $table->enum('tax_type', ['PPN Included', 'PPN Excluded'])->default('PPN Excluded')->change();
            });
        }
    }
};
