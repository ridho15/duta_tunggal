<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class JournalEntryCreated extends Notification
{
    protected $journalEntry;

    /**
     * Create a new notification instance.
     */
    public function __construct($journalEntry)
    {
        $this->journalEntry = $journalEntry;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->line('New journal entry has been created.')
            ->action('View Journal Entry', url('/admin/journal-entries/' . $this->journalEntry->id))
            ->line('Reference: ' . $this->journalEntry->reference);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $amount = $this->journalEntry->debit > 0 ? $this->journalEntry->debit : $this->journalEntry->credit;

        return [
            'format' => 'filament',
            'title' => sprintf('Journal Entry: %s', $this->journalEntry->reference ?? $this->journalEntry->id),
            'body' => $this->journalEntry->description ?: sprintf('%s ID: %s — Rp%s',
                $this->journalEntry->journal_type ?? 'Journal',
                $this->journalEntry->id,
                number_format($amount, 0, ',', '.')
            ),
            'status' => 'success',
            'actions' => [
                [
                    'name' => 'view_journal_entry',
                    'label' => 'View Journal Entry',
                    'url' => '/admin/journal-entries/' . $this->journalEntry->id,
                ]
            ],
            'journal_entry_id' => $this->journalEntry->id,
            'journal_type' => $this->journalEntry->journal_type,
            'amount' => $amount,
        ];
    }
}
