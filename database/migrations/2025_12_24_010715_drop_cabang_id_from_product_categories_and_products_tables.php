<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop cabang_id from product_categories if exists
        if (Schema::hasColumn('product_categories', 'cabang_id')) {
            // Try to drop foreign key first, ignore if doesn't exist
            try {
                DB::statement('ALTER TABLE product_categories DROP FOREIGN KEY product_categories_cabang_id_foreign');
            } catch (\Exception $e) {
                // Foreign key might not exist, continue
            }
            
            Schema::table('product_categories', function (Blueprint $table) {
                $table->dropColumn('cabang_id');
            });
        }

        // Drop cabang_id from products if exists
        if (Schema::hasColumn('products', 'cabang_id')) {
            // Try to drop foreign key first, ignore if doesn't exist
            try {
                DB::statement('ALTER TABLE products DROP FOREIGN KEY products_cabang_id_foreign');
            } catch (\Exception $e) {
                // Foreign key might not exist, continue
            }
            
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('cabang_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back cabang_id to product_categories if not exists
        if (!Schema::hasColumn('product_categories', 'cabang_id')) {
            Schema::table('product_categories', function (Blueprint $table) {
                $table->unsignedBigInteger('cabang_id')->nullable();
                $table->foreign('cabang_id')->references('id')->on('cabangs')->onDelete('cascade');
            });
        }

        // Add back cabang_id to products if not exists
        if (!Schema::hasColumn('products', 'cabang_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unsignedBigInteger('cabang_id')->nullable();
            });
        }
    }
};
