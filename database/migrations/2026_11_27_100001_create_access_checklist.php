<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Peran & Hak Akses berbasis checklist (keputusan Ko Handy 27 Nov 2026).
 *  - roles.base_role / roles.description : peran hasil duplikat / template mewarisi aturan lama peran asalnya.
 *  - user_access_overrides : akses khusus per user (allowed 1 = ditambah, 0 = dicabut dari peran).
 *  - access_change_logs    : jejak perubahan akses (siapa, kapan, apa).
 *  - Akses bawaan semua peran yang ada = akses sebelum fitur ini (AccessDefaults), agar tidak ada yang berubah saat dipasang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (! Schema::hasColumn('roles', 'base_role')) {
                $table->string('base_role')->nullable();
            }
            if (! Schema::hasColumn('roles', 'description')) {
                $table->string('description', 500)->nullable();
            }
        });

        if (! Schema::hasTable('user_access_overrides')) {
            Schema::create('user_access_overrides', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('access_key');
                $table->boolean('allowed');
                $table->timestamps();
                $table->unique(['user_id', 'access_key']);
            });
        }

        if (! Schema::hasTable('access_change_logs')) {
            Schema::create('access_change_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('target_type', 20);   // role | user
                $table->unsignedBigInteger('target_id');
                $table->string('target_name')->nullable();
                $table->string('action', 40);        // update | create | clone | delete | copy_from_user | reset
                $table->json('added')->nullable();
                $table->json('removed')->nullable();
                $table->string('note', 500)->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        \App\Support\AccessDefaults::installAll();
    }

    public function down(): void
    {
        Schema::dropIfExists('access_change_logs');
        Schema::dropIfExists('user_access_overrides');
        \Spatie\Permission\Models\Permission::query()->where('name', 'like', 'akses:%')->delete();
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['base_role', 'description']);
        });
    }
};
