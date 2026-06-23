<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('account_receivables')) {
            Schema::table('account_receivables', function (Blueprint $table) {
                if (! Schema::hasColumn('account_receivables', 'currency_id')) {
                    $table->foreignId('currency_id')->nullable()->after('cabang_id')->constrained('currencies')->nullOnDelete();
                }
                if (! Schema::hasColumn('account_receivables', 'exchange_rate')) {
                    $table->decimal('exchange_rate', 18, 8)->default(1)->after('currency_id');
                }
                if (! Schema::hasColumn('account_receivables', 'total_original')) {
                    $table->decimal('total_original', 20, 4)->nullable()->after('exchange_rate');
                }
                if (! Schema::hasColumn('account_receivables', 'paid_original')) {
                    $table->decimal('paid_original', 20, 4)->nullable()->after('total_original');
                }
                if (! Schema::hasColumn('account_receivables', 'remaining_original')) {
                    $table->decimal('remaining_original', 20, 4)->nullable()->after('paid_original');
                }
            });

            DB::table('account_receivables')
                ->leftJoin('invoices', 'account_receivables.invoice_id', '=', 'invoices.id')
                ->select([
                    'account_receivables.id',
                    'account_receivables.total',
                    'account_receivables.paid',
                    'account_receivables.remaining',
                    'invoices.currency_id',
                    'invoices.exchange_rate',
                ])
                ->orderBy('account_receivables.id')
                ->chunkById(200, function ($rows) {
                    foreach ($rows as $row) {
                        $rate = (float) ($row->exchange_rate ?? 1);
                        $rate = $rate > 0 ? $rate : 1.0;

                        DB::table('account_receivables')
                            ->where('id', $row->id)
                            ->update([
                                'currency_id' => $row->currency_id,
                                'exchange_rate' => $rate,
                                'total_original' => round((float) $row->total / $rate, 4),
                                'paid_original' => round((float) $row->paid / $rate, 4),
                                'remaining_original' => round((float) $row->remaining / $rate, 4),
                            ]);
                    }
                }, 'account_receivables.id', 'id');
        }

        if (Schema::hasTable('customer_receipts')) {
            Schema::table('customer_receipts', function (Blueprint $table) {
                if (! Schema::hasColumn('customer_receipts', 'currency_id')) {
                    $table->foreignId('currency_id')->nullable()->after('invoice_receipts')->constrained('currencies')->nullOnDelete();
                }
                if (! Schema::hasColumn('customer_receipts', 'exchange_rate')) {
                    $table->decimal('exchange_rate', 18, 8)->default(1)->after('currency_id');
                }
                if (! Schema::hasColumn('customer_receipts', 'total_payment_idr')) {
                    $table->decimal('total_payment_idr', 20, 2)->default(0)->after('total_payment');
                }
            });
        }

        if (Schema::hasTable('customer_receipt_items')) {
            Schema::table('customer_receipt_items', function (Blueprint $table) {
                if (! Schema::hasColumn('customer_receipt_items', 'currency_id')) {
                    $table->foreignId('currency_id')->nullable()->after('invoice_id')->constrained('currencies')->nullOnDelete();
                }
                if (! Schema::hasColumn('customer_receipt_items', 'exchange_rate')) {
                    $table->decimal('exchange_rate', 18, 8)->default(1)->after('currency_id');
                }
                if (! Schema::hasColumn('customer_receipt_items', 'amount_idr')) {
                    $table->decimal('amount_idr', 20, 2)->default(0)->after('amount');
                }
            });

            DB::table('customer_receipt_items')
                ->leftJoin('invoices', 'customer_receipt_items.invoice_id', '=', 'invoices.id')
                ->select([
                    'customer_receipt_items.id',
                    'customer_receipt_items.customer_receipt_id',
                    'customer_receipt_items.amount',
                    'invoices.currency_id',
                    'invoices.exchange_rate',
                ])
                ->orderBy('customer_receipt_items.id')
                ->chunkById(200, function ($rows) {
                    foreach ($rows as $row) {
                        $rate = (float) ($row->exchange_rate ?? 1);
                        $rate = $rate > 0 ? $rate : 1.0;
                        $amountIdr = (float) $row->amount;

                        DB::table('customer_receipt_items')
                            ->where('id', $row->id)
                            ->update([
                                'currency_id' => $row->currency_id,
                                'exchange_rate' => $rate,
                                'amount_idr' => round($amountIdr, 2),
                            ]);
                    }
                }, 'customer_receipt_items.id', 'id');

            DB::table('customer_receipts')->orderBy('id')->chunkById(200, function ($receipts) {
                foreach ($receipts as $receipt) {
                    $firstDetail = DB::table('customer_receipt_items')
                        ->where('customer_receipt_id', $receipt->id)
                        ->whereNotNull('currency_id')
                        ->orderBy('id')
                        ->first();

                    DB::table('customer_receipts')
                        ->where('id', $receipt->id)
                        ->update([
                            'currency_id' => $firstDetail?->currency_id,
                            'exchange_rate' => (float) ($firstDetail?->exchange_rate ?? 1),
                            'total_payment_idr' => (float) DB::table('customer_receipt_items')
                                ->where('customer_receipt_id', $receipt->id)
                                ->sum('amount_idr'),
                        ]);
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customer_receipt_items')) {
            Schema::table('customer_receipt_items', function (Blueprint $table) {
                foreach (['amount_idr', 'exchange_rate'] as $column) {
                    if (Schema::hasColumn('customer_receipt_items', $column)) {
                        $table->dropColumn($column);
                    }
                }
                if (Schema::hasColumn('customer_receipt_items', 'currency_id')) {
                    $table->dropConstrainedForeignId('currency_id');
                }
            });
        }

        if (Schema::hasTable('customer_receipts')) {
            Schema::table('customer_receipts', function (Blueprint $table) {
                foreach (['total_payment_idr', 'exchange_rate'] as $column) {
                    if (Schema::hasColumn('customer_receipts', $column)) {
                        $table->dropColumn($column);
                    }
                }
                if (Schema::hasColumn('customer_receipts', 'currency_id')) {
                    $table->dropConstrainedForeignId('currency_id');
                }
            });
        }

        if (Schema::hasTable('account_receivables')) {
            Schema::table('account_receivables', function (Blueprint $table) {
                foreach (['remaining_original', 'paid_original', 'total_original', 'exchange_rate'] as $column) {
                    if (Schema::hasColumn('account_receivables', $column)) {
                        $table->dropColumn($column);
                    }
                }
                if (Schema::hasColumn('account_receivables', 'currency_id')) {
                    $table->dropConstrainedForeignId('currency_id');
                }
            });
        }
    }
};
