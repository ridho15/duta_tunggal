<?php

namespace App\Support;

use App\Enums\PaymentStatus;
use App\Models\AccountPayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AccountPayableQuery
{
    public static function base(): Builder
    {
        return AccountPayable::query()
            ->where('account_payables.status', '!=', PaymentStatus::PAID->value)
            ->whereNull('account_payables.deleted_at');
    }

    public static function withOverdueGrouping(Builder $query, ?Carbon $today = null): Builder
    {
        $todaySql = DB::getPdo()->quote(($today ?? now())->toDateString());

        return $query
            ->leftJoin('invoices', 'account_payables.invoice_id', '=', 'invoices.id')
            ->select('account_payables.*')
            ->addSelect(DB::raw(self::overdueGroupSelect($todaySql)));
    }

    public static function applyOverdueFilter(Builder $query, ?Carbon $today = null): Builder
    {
        $today = ($today ?? now())->copy()->startOfDay();

        return $query->whereHas('invoice', function (Builder $query) use ($today) {
            $query->whereDate('due_date', '<', $today);
        })->where('account_payables.status', PaymentStatus::UNPAID->value);
    }

    public static function applyOverdueDaysFilter(Builder $query, string $value, ?Carbon $today = null): Builder
    {
        $today = ($today ?? now())->copy()->startOfDay();

        return $query->whereHas('invoice', function (Builder $query) use ($value, $today) {
            switch ($value) {
                case '1-30':
                    $query->whereBetween('due_date', [$today->copy()->subDays(30), $today->copy()->subDay()]);
                    break;
                case '31-60':
                    $query->whereBetween('due_date', [$today->copy()->subDays(60), $today->copy()->subDays(31)]);
                    break;
                case '60+':
                    $query->whereDate('due_date', '<', $today->copy()->subDays(60));
                    break;
            }
        })->where('account_payables.status', PaymentStatus::UNPAID->value);
    }

    protected static function overdueGroupSelect(string $todaySql): string
    {
        return "CASE
            WHEN invoices.deleted_at IS NOT NULL THEN 'DELETED INVOICE'
            WHEN invoices.due_date < {$todaySql} AND DATEDIFF({$todaySql}, invoices.due_date) > 60 THEN 'OVERDUE 60+ Days'
            WHEN invoices.due_date < {$todaySql} AND DATEDIFF({$todaySql}, invoices.due_date) > 30 THEN 'OVERDUE 30+ Days'
            WHEN invoices.due_date < {$todaySql} THEN 'OVERDUE'
            ELSE 'CURRENT'
        END AS overdue_group";
    }

    public static function applyTableFilters(Builder $query, array $tableFilters = []): Builder
    {
        if (empty($tableFilters)) {
            return $query;
        }

        if (isset($tableFilters['supplier_id']['values']) && !empty($tableFilters['supplier_id']['values'])) {
            $query->whereIn('supplier_id', $tableFilters['supplier_id']['values']);
        }

        if (isset($tableFilters['status']['values']) && !empty($tableFilters['status']['values'])) {
            $query->whereIn('account_payables.status', $tableFilters['status']['values']);
        }

        if (isset($tableFilters['amount_range']) && !empty($tableFilters['amount_range'])) {
            $data = $tableFilters['amount_range'];

            if (isset($data['amount_from']) && $data['amount_from'] !== null) {
                $query->where('total', '>=', $data['amount_from']);
            }

            if (isset($data['amount_to']) && $data['amount_to'] !== null) {
                $query->where('total', '<=', $data['amount_to']);
            }
        }

        if (isset($tableFilters['outstanding_only']['isActive']) && $tableFilters['outstanding_only']['isActive']) {
            $query->where('remaining', '>', 0);
        }

        if (isset($tableFilters['overdue']['isActive']) && $tableFilters['overdue']['isActive']) {
            self::applyOverdueFilter($query);
        }

        if (isset($tableFilters['date_range']) && !empty($tableFilters['date_range'])) {
            $data = $tableFilters['date_range'];

            if (isset($data['created_from']) && $data['created_from'] !== null) {
                $query->whereDate('created_at', '>=', $data['created_from']);
            }

            if (isset($data['created_until']) && $data['created_until'] !== null) {
                $query->whereDate('created_at', '<=', $data['created_until']);
            }
        }

        if (isset($tableFilters['due_date_range']) && !empty($tableFilters['due_date_range'])) {
            $data = $tableFilters['due_date_range'];

            $query->whereHas('invoice', function (Builder $query) use ($data) {
                if (isset($data['due_from']) && $data['due_from'] !== null) {
                    $query->whereDate('due_date', '>=', $data['due_from']);
                }

                if (isset($data['due_until']) && $data['due_until'] !== null) {
                    $query->whereDate('due_date', '<=', $data['due_until']);
                }
            });
        }

        if (isset($tableFilters['overdue_days']['value']) && !empty($tableFilters['overdue_days']['value'])) {
            self::applyOverdueDaysFilter($query, $tableFilters['overdue_days']['value']);
        }

        return $query;
    }

    public static function filtered(array $tableFilters = []): Builder
    {
        return self::applyTableFilters(self::base(), $tableFilters);
    }
}