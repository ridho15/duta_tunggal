<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_movements', 'value')) {
                $table->decimal('value', 18, 2)->nullable()->after('quantity');
            }

            if (!Schema::hasColumn('stock_movements', 'meta')) {
                $table->json('meta')->nullable()->after('notes');
            }
        });

        $this->updateTypeEnum([
            'purchase_in',
            'sales',
            'transfer_in',
            'transfer_out',
            'manufacture_in',
            'manufacture_out',
            'adjustment_in',
            'adjustment_out',
        ]);

        DB::statement("UPDATE stock_movements SET type = 'purchase_in' WHERE type = 'purchase'");
        DB::statement("UPDATE stock_movements SET type = 'adjustment_in' WHERE type = 'adjustment'");
    }

    public function down(): void
    {
        DB::statement("UPDATE stock_movements SET type = 'purchase' WHERE type = 'purchase_in'");
        DB::statement("UPDATE stock_movements SET type = 'adjustment' WHERE type IN ('adjustment_in','adjustment_out')");

        $this->updateTypeEnum([
            'purchase',
            'sales',
            'transfer_in',
            'transfer_out',
            'manufacture_in',
            'manufacture_out',
            'adjustment',
        ]);

        Schema::table('stock_movements', function (Blueprint $table) {
            if (Schema::hasColumn('stock_movements', 'meta')) {
                $table->dropColumn('meta');
            }

            if (Schema::hasColumn('stock_movements', 'value')) {
                $table->dropColumn('value');
            }
        });
    }

    private function updateTypeEnum(array $allowed): void
    {
        $connection = Schema::getConnection()->getDriverName();

        if ($connection === 'mysql') {
            $enum = implode("','", $allowed);
            DB::statement("ALTER TABLE stock_movements MODIFY COLUMN type ENUM('{$enum}') NOT NULL");
        } elseif ($connection === 'sqlite') {
            $current = DB::table('sqlite_master')
                ->where('type', 'table')
                ->where('name', 'stock_movements')
                ->value('sql');

            if ($current && preg_match('/CHECK\s*\(\s*"type"\s+in\s*\(([^\)]*)\)\)/i', $current, $matches)) {
                $currentList = $matches[1];
                $newList = "'" . implode("','", $allowed) . "'";

                if ($currentList !== $newList) {
                    $updated = str_replace($currentList, $newList, $current);

                    DB::statement('PRAGMA writable_schema = 1;');
                    DB::statement(
                        "UPDATE sqlite_master SET sql = ? WHERE type = 'table' AND name = 'stock_movements'",
                        [$updated]
                    );
                    DB::statement('PRAGMA writable_schema = 0;');
                    DB::statement('VACUUM');
                }
            }
        }
    }
};
