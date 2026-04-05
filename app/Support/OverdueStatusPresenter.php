<?php

namespace App\Support;

use App\Enums\PaymentStatus;
use Carbon\Carbon;

class OverdueStatusPresenter
{
    public const DELETED_INVOICE = 'DELETED INVOICE';

    public function daysOverdue(object $record): int|string
    {
        if ($this->isDeletedInvoice($record)) {
            return 'DELETED';
        }

        if (($record->status ?? null) !== PaymentStatus::UNPAID->value) {
            return 0;
        }

        $dueDate = $record->invoice->due_date ?? null;
        if (! $dueDate || Carbon::parse($dueDate)->isFuture()) {
            return 0;
        }

        return Carbon::parse($dueDate)->diffInDays(now());
    }

    public function overdueGroupLabel(object $record): string
    {
        if ($this->isDeletedInvoice($record)) {
            return '🗑️ DELETED INVOICE';
        }

        if (($record->status ?? null) === PaymentStatus::PAID->value) {
            return '✅ PAID';
        }

        $daysOverdue = $this->daysOverdue($record);
        if (! is_int($daysOverdue) || $daysOverdue <= 0) {
            return '💚 CURRENT';
        }

        if ($daysOverdue > 60) {
            return '🚨 OVERDUE 60+ Days';
        }

        if ($daysOverdue > 30) {
            return '⚠️ OVERDUE 30+ Days';
        }

        return '⏰ OVERDUE';
    }

    public function daysOverdueColor(int|string $state): string
    {
        if ($state === 'DELETED') {
            return 'danger';
        }

        if (is_int($state) || is_float($state)) {
            if ($state > 30) {
                return 'danger';
            }

            if ($state > 0) {
                return 'warning';
            }
        }

        return 'success';
    }

    public function dueDateColor(object $record): string
    {
        if ($this->isDeletedInvoice($record)) {
            return 'danger';
        }

        $dueDate = $record->invoice->due_date ?? null;
        if (($record->status ?? null) === PaymentStatus::UNPAID->value && $dueDate && Carbon::parse($dueDate)->isPast()) {
            return 'danger';
        }

        return 'gray';
    }

    private function isDeletedInvoice(object $record): bool
    {
        return ($record->overdue_group ?? null) === self::DELETED_INVOICE;
    }
}