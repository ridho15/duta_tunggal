<?php

namespace App\Observers;

use App\Models\JournalEntry;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class JournalEntryObserver
{
    public function created(JournalEntry $entry): void
    {
        $amount = $entry->debit > 0 ? $entry->debit : $entry->credit;

        $title = sprintf('Journal Entry: %s', $entry->reference ?? $entry->id);
        $body = $entry->description ?: (sprintf('%s ID: %s — Rp%s', $entry->journal_type ?? 'Journal', $entry->id, number_format($amount, 0, ',', '.')));

        $notification = Notification::make()
            ->title($title)
            ->body($body)
            ->success();

        // Send notification after commit
        DB::afterCommit(function () use ($entry, $title, $body) {
            $notification = Notification::make()
                ->title($title)
                ->body($body)
                ->success()
                ->actions([
                    Action::make('View')
                        ->url('/admin/journal-entries/' . $entry->id)
                ]);

            foreach (User::all() as $user) {
                $notification->sendToDatabase($user);
            }
        });
    }
}
