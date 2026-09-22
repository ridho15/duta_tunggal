<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'pajak')) {
            return;
        }

        DB::statement('ALTER TABLE products MODIFY pajak DOUBLE NULL');
    }

    public function down(): void
    {
        if (! Schema::hasColumn('products', 'pajak')) {
            return;
        }

        DB::table('products')
            ->whereNull('pajak')
            ->update(['pajak' => 0]);

        DB::statement('ALTER TABLE products MODIFY pajak DOUBLE NOT NULL DEFAULT 0');
    }
};
