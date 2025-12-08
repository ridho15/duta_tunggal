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
        Schema::table('assets', function (Blueprint $table) {
            $table->string('code')->unique()->nullable()->after('id');
        });

        // Generate codes for existing assets
        $assets = DB::table('assets')->get();
        foreach ($assets as $asset) {
            $code = 'AST-' . str_pad($asset->id, 4, '0', STR_PAD_LEFT);
            DB::table('assets')->where('id', $asset->id)->update(['code' => $code]);
        }

        // Make code not nullable after populating
        Schema::table('assets', function (Blueprint $table) {
            $table->string('code')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
