<?php

namespace App\Support;

use App\Enums\PaymentStatus;
use App\Models\AccountReceivable;
use Illuminate\Database\Eloquent\Builder;

class AccountReceivableQuery
{
    public static function base(): Builder
    {
        return AccountReceivable::query()
            ->where('account_receivables.status', '!=', PaymentStatus::PAID->value)
            ->whereNull('deleted_at');
    }

    public static function applyTableFilters(Builder $query, ?array $tableFilters = []): Builder
    {
        $tableFilters = $tableFilters ?? [];

        if (empty($tableFilters)) {
            return $query;
        }

        if (isset($tableFilters['customer_id']['values']) && !empty($tableFilters['customer_id']['values'])) {
            $query->whereIn('customer_id', $tableFilters['customer_id']['values']);
        }

        if (isset($tableFilters['status']['values']) && !empty($tableFilters['status']['values'])) {
            $query->whereIn('account_receivables.status', $tableFilters['status']['values']);
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
            $query->whereHas('invoice', function (Builder $query) {
                $query->where('due_date', '<', now());
            })->where('account_receivables.status', PaymentStatus::UNPAID->value);
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
            $value = $tableFilters['overdue_days']['value'];

            $query->whereHas('invoice', function (Builder $query) use ($value) {
                $now = now();

                switch ($value) {
                    case '1-30':
                        $query->whereBetween('due_date', [$now->copy()->subDays(30), $now->copy()->subDay()]);
                        break;
                    case '31-60':
                        $query->whereBetween('due_date', [$now->copy()->subDays(60), $now->copy()->subDays(31)]);
                        break;
                    case '60+':
                        $query->where('due_date', '<', $now->copy()->subDays(60));
                        break;
                }
            })->where('account_receivables.status', PaymentStatus::UNPAID->value);
        }

        return $query;
    }

    public static function filtered(?array $tableFilters = []): Builder
    {
        return self::applyTableFilters(self::base(), $tableFilters);
    }
}