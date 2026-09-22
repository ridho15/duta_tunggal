<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;

trait CascadesJournalEntries
{
    protected static function bootCascadesJournalEntries(): void
    {
        static::deleting(function (Model $model): void {
            if (method_exists($model, 'isForceDeleting') && $model->isForceDeleting()) {
                return;
            }

            $model->deleteCascadedJournalEntries(false);
        });

        static::forceDeleted(function (Model $model): void {
            $model->deleteCascadedJournalEntries(true);
        });

        static::restoring(function (Model $model): void {
            $model->restoreCascadedJournalEntries();
        });
    }

    protected function deleteCascadedJournalEntries(bool $forceDelete = false): void
    {
        foreach ($this->cascadedJournalEntryRelationNames() as $relationName) {
            if (! method_exists($this, $relationName)) {
                continue;
            }

            $relation = $this->{$relationName}();

            if ($forceDelete) {
                if (method_exists($relation, 'withTrashed')) {
                    $relation->withTrashed()->forceDelete();
                } else {
                    $relation->forceDelete();
                }

                continue;
            }

            $relation->delete();
        }
    }

    protected function restoreCascadedJournalEntries(): void
    {
        foreach ($this->cascadedJournalEntryRelationNames() as $relationName) {
            if (! method_exists($this, $relationName)) {
                continue;
            }

            $relation = $this->{$relationName}();

            if (method_exists($relation, 'withTrashed')) {
                $relation->withTrashed()->restore();
            }
        }
    }

    protected function cascadedJournalEntryRelationNames(): array
    {
        if (method_exists($this, 'journalEntries')) {
            return ['journalEntries'];
        }

        if (method_exists($this, 'journalEntry')) {
            return ['journalEntry'];
        }

        return [];
    }
}