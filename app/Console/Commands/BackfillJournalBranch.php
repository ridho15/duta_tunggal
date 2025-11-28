<?php

namespace App\Console\Commands;

use App\Models\JournalEntry;
use App\Services\JournalBranchResolver;
use Illuminate\Console\Command;

class BackfillJournalBranch extends Command
{
    protected $signature = 'journals:backfill-branch {--chunk=500}';
    protected $description = 'Backfill cabang_id for journal_entries based on their source models.';

    public function handle(JournalBranchResolver $resolver)
    {
        $chunk = (int) $this->option('chunk');
        $updated = 0;
        JournalEntry::whereNull('cabang_id')->orderBy('id')->chunk($chunk, function ($rows) use ($resolver, &$updated) {
            foreach ($rows as $row) {
                $branchId = null;
                try {
                    $branchId = $resolver->resolve($row->source);
                } catch (\Throwable $e) {
                    // ignore
                }
                if ($branchId) {
                    $row->cabang_id = $branchId;
                    $row->save();
                    $updated++;
                }
            }
        });
        $this->info("Backfill completed. Updated: {$updated}");
        return self::SUCCESS;
    }
}
