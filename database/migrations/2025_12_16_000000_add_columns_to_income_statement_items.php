<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('income_statement_items')) {
            Schema::create('income_statement_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('income_statement_id')->nullable()->constrained('income_statements')->nullOnDelete();
                $table->string('name');
                $table->string('type')->nullable();
                $table->decimal('balance', 15, 2)->default(0);
                $table->timestamps();
            });
        }

        Schema::table('income_statement_items', function (Blueprint $table) {
            if (!Schema::hasColumn('income_statement_items', 'code')) {
                $table->string('code')->nullable()->after('id');
            }
            if (!Schema::hasColumn('income_statement_items', 'description')) {
                $table->text('description')->nullable()->after('code');
            }
            if (!Schema::hasColumn('income_statement_items', 'amount')) {
                $table->decimal('amount', 15, 2)->default(0)->after('balance');
            }
            if (!Schema::hasColumn('income_statement_items', 'row_type')) {
                $table->string('row_type')->nullable()->after('amount');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('income_statement_items')) {
            return;
        }

        Schema::table('income_statement_items', function (Blueprint $table) {
            if (Schema::hasColumn('income_statement_items', 'row_type')) {
                $table->dropColumn('row_type');
            }
            if (Schema::hasColumn('income_statement_items', 'amount')) {
                $table->dropColumn('amount');
            }
            if (Schema::hasColumn('income_statement_items', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('income_statement_items', 'code')) {
                $table->dropColumn('code');
            }
        });
    }
};
