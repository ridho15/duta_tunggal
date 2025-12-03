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
        Schema::table('delivery_order_logs', function (Blueprint $table) {
            $table->string('action')->nullable()->after('status');
            $table->text('comments')->nullable()->after('confirmed_by');
            $table->unsignedBigInteger('user_id')->nullable()->after('comments');
            $table->string('old_value')->nullable()->after('user_id');
            $table->string('new_value')->nullable()->after('old_value');
            $table->text('notes')->nullable()->after('new_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_order_logs', function (Blueprint $table) {
            $table->dropColumn(['action', 'comments', 'user_id', 'old_value', 'new_value', 'notes']);
        });
    }
};
