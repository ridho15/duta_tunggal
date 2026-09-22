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
        Schema::table('order_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('order_requests', 'required_date')) {
                $table->date('required_date')->nullable()->after('request_date');
            }
            if (!Schema::hasColumn('order_requests', 'purpose')) {
                $table->string('purpose', 255)->nullable()->after('required_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_requests', function (Blueprint $table) {
            if (Schema::hasColumn('order_requests', 'required_date')) {
                $table->dropColumn('required_date');
            }
            if (Schema::hasColumn('order_requests', 'purpose')) {
                $table->dropColumn('purpose');
            }
        });
    }
};
