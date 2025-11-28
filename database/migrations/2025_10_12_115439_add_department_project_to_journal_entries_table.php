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
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('department_id')->nullable()->after('cabang_id');
            $table->unsignedBigInteger('project_id')->nullable()->after('department_id');
            $table->index('department_id');
            $table->index('project_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex(['department_id']);
            $table->dropIndex(['project_id']);
            $table->dropColumn(['department_id', 'project_id']);
        });
    }
};
