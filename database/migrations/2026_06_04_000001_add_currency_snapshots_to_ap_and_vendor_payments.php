<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('account_payables')) {
            Schema::table('account_payables', function (Blueprint $table) {
                if (! Schema::hasColumn('account_payables', 'currency_id')) {
                    $table->foreignId('currency_id')->nullable()->after('cabang_id')->constrained('currencies')->nullOnDelete();
                }
                if (! Schema::hasColumn('account_payables', 'exchange_rate')) {
                    $table->decimal('exchange_rate', 18, 8)->default(1)->after('currency_id');
                }
                if (! Schema::hasColumn('account_payables', 'total_original')) {
                    $table->decimal('total_original', 20, 4)->nullable()->after('remaining');
                }
                if (! Schema::hasColumn('account_payables', 'paid_original')) {
                    $table->decimal('paid_original', 20, 4)->nullable()->after('total_original');
                }
                if (! Schema::hasColumn('account_payables', 'remaining_original')) {
                    $table->decimal('remaining_original', 20, 4)->nullable()->after('paid_original');
                }
            });

            if (Schema::hasTable('invoices')) {
                DB::table('account_payables')
                    ->leftJoin('invoices', 'account_payables.invoice_id', '=', 'invoices.id')
                    ->select(
                        'account_payables.id',
                        'account_payables.total',
                        'account_payables.paid',
                        'account_payables.remaining',
                        'invoices.currency_id',
                        'invoices.exchange_rate'
                    )
                    ->orderBy('account_payables.id')
                    ->chunkById(200, function ($rows) {
                        foreach ($rows as $row) {
                            $rate = (float) ($row->exchange_rate ?? 1);
                            $rate = $rate > 0 ? $rate : 1.0;

                            DB::table('account_payables')
                                ->where('id', $row->id)
                                ->update([
                                    'currency_id' => $row->currency_id,
                                    'exchange_rate' => $rate,
                                    'total_original' => $row->total,
                                    'paid_original' => $row->paid,
                                    'remaining_original' => $row->remaining,
                                    'total' => round((float) $row->total * $rate, 2),
                                    'paid' => round((float) $row->paid * $rate, 2),
                                    'remaining' => round((float) $row->remaining * $rate, 2),
                                ]);
                        }
                    }, 'account_payables.id', 'id');
            }
        }

        if (Schema::hasTable('vendor_payments')) {
            Schema::table('vendor_payments', function (Blueprint $table) {
                if (! Schema::hasColumn('vendor_payments', 'currency_id')) {
                    $table->foreignId('currency_id')->nullable()->after('invoice_receipts')->constrained('currencies')->nullOnDelete();
                }
                if (! Schema::hasColumn('vendor_payments', 'exchange_rate')) {
                    $table->decimal('exchange_rate', 18, 8)->default(1)->after('currency_id');
                }
                if (! Schema::hasColumn('vendor_payments', 'total_payment_idr')) {
                    $table->decimal('total_payment_idr', 20, 2)->default(0)->after('total_payment');
                }
            });
        }

        if (Schema::hasTable('vendor_payment_details')) {
            Schema::table('vendor_payment_details', function (Blueprint $table) {
                if (! Schema::hasColumn('vendor_payment_details', 'currency_id')) {
                    $table->foreignId('currency_id')->nullable()->after('invoice_id')->constrained('currencies')->nullOnDelete();
                }
                if (! Schema::hasColumn('vendor_payment_details', 'exchange_rate')) {
                    $table->decimal('exchange_rate', 18, 8)->default(1)->after('currency_id');
                }
                if (! Schema::hasColumn('vendor_payment_details', 'amount_idr')) {
                    $table->decimal('amount_idr', 20, 2)->default(0)->after('amount');
                }
            });

            DB::table('vendor_payment_details')
                ->leftJoin('invoices', 'vendor_payment_details.invoice_id', '=', 'invoices.id')
                ->select(
                    'vendor_payment_details.id',
                    'vendor_payment_details.amount',
                    'invoices.currency_id',
                    'invoices.exchange_rate'
                )
                ->orderBy('vendor_payment_details.id')
                ->chunkById(200, function ($rows) {
                    foreach ($rows as $row) {
                        $rate = (float) ($row->exchange_rate ?? 1);
                        $rate = $rate > 0 ? $rate : 1.0;

                        DB::table('vendor_payment_details')
                            ->where('id', $row->id)
                            ->update([
                                'currency_id' => $row->currency_id,
                                'exchange_rate' => $rate,
                                'amount_idr' => round((float) $row->amount * $rate, 2),
                            ]);
                    }
                }, 'vendor_payment_details.id', 'id');

            if (Schema::hasTable('vendor_payments')) {
                $payments = DB::table('vendor_payments')->select('id')->orderBy('id')->get();
                foreach ($payments as $payment) {
                    $firstDetail = DB::table('vendor_payment_details')
                        ->where('vendor_payment_id', $payment->id)
                        ->whereNotNull('currency_id')
                        ->orderBy('id')
                        ->first();

                    DB::table('vendor_payments')
                        ->where('id', $payment->id)
                        ->update([
                            'currency_id' => $firstDetail?->currency_id,
                            'exchange_rate' => (float) ($firstDetail?->exchange_rate ?? 1),
                            'total_payment_idr' => (float) DB::table('vendor_payment_details')
                                ->where('vendor_payment_id', $payment->id)
                                ->sum('amount_idr'),
                        ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vendor_payment_details')) {
            Schema::table('vendor_payment_details', function (Blueprint $table) {
                foreach (['amount_idr', 'exchange_rate'] as $column) {
                    if (Schema::hasColumn('vendor_payment_details', $column)) {
                        $table->dropColumn($column);
                    }
                }
                if (Schema::hasColumn('vendor_payment_details', 'currency_id')) {
                    $table->dropConstrainedForeignId('currency_id');
                }
            });
        }

        if (Schema::hasTable('vendor_payments')) {
            Schema::table('vendor_payments', function (Blueprint $table) {
                foreach (['total_payment_idr', 'exchange_rate'] as $column) {
                    if (Schema::hasColumn('vendor_payments', $column)) {
                        $table->dropColumn($column);
                    }
                }
                if (Schema::hasColumn('vendor_payments', 'currency_id')) {
                    $table->dropConstrainedForeignId('currency_id');
                }
            });
        }

        if (Schema::hasTable('account_payables')) {
            Schema::table('account_payables', function (Blueprint $table) {
                foreach (['remaining_original', 'paid_original', 'total_original', 'exchange_rate'] as $column) {
                    if (Schema::hasColumn('account_payables', $column)) {
                        $table->dropColumn($column);
                    }
                }
                if (Schema::hasColumn('account_payables', 'currency_id')) {
                    $table->dropConstrainedForeignId('currency_id');
                }
            });
        }
    }
};
