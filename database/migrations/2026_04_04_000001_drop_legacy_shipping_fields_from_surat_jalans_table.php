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
        Schema::table('surat_jalans', function (Blueprint $table) {
            if (Schema::hasColumn('surat_jalans', 'shipping_method')) {
                $table->dropColumn('shipping_method');
            }

            if (Schema::hasColumn('surat_jalans', 'sender_name')) {
                $table->dropColumn('sender_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('surat_jalans', function (Blueprint $table) {
            if (! Schema::hasColumn('surat_jalans', 'sender_name')) {
                $table->string('sender_name')->nullable()->after('document_path');
            }

            if (! Schema::hasColumn('surat_jalans', 'shipping_method')) {
                $table->string('shipping_method')->nullable()->after('sender_name');
            }
        });
    }
};