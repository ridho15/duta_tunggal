<?php

namespace App\Observers;

use App\Models\FinishedGoodsCompletion;
use App\Services\ManufacturingJournalService;
use Illuminate\Support\Facades\Log;

class FinishedGoodsCompletionObserver
{
    /**
     * Handle the FinishedGoodsCompletion "updated" event.
     */
    public function updated(FinishedGoodsCompletion $completion): void
    {
        // Check if status changed to 'completed'
        $originalStatus = $completion->getOriginal('status');
        if ($originalStatus !== 'completed' && $completion->status === 'completed') {
            $this->generateJournalForCompletion($completion);
        }
    }

    /**
     * Handle the FinishedGoodsCompletion "created" event.
     */
    public function created(FinishedGoodsCompletion $completion): void
    {
        // If created directly as completed, generate journal
        if ($completion->status === 'completed') {
            $this->generateJournalForCompletion($completion);
        }
    }

    /**
     * Generate journal entries for finished goods completion
     */
    protected function generateJournalForCompletion(FinishedGoodsCompletion $completion): void
    {
        try {
            $journalService = app(ManufacturingJournalService::class);
            $journalService->createFinishedGoodsCompletionJournal($completion);
        } catch (\Throwable $e) {
            // Log error but don't break the update flow
            Log::error('Failed to generate journal for finished goods completion: ' . $e->getMessage(), [
                'completion_id' => $completion->id,
                'completion_number' => $completion->completion_number
            ]);
        }
    }
}