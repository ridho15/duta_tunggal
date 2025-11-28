<?php

namespace App\Console\Commands;

use App\Models\JournalEntry;
use App\Services\JournalBranchResolver;
use Illuminate\Console\Command;

class BackfillJournalDeptProject extends Command
{
    protected $signature = 'journals:backfill-dept-project {--chunk=500}';
    protected $description = 'Backfill department_id and project_id for journal_entries based on their source models.';

    public function handle(JournalBranchResolver $resolver)
    {
        $chunk = (int) $this->option('chunk');
        $updated = 0;
        JournalEntry::orderBy('id')->chunk($chunk, function ($rows) use ($resolver, &$updated) {
            foreach ($rows as $row) {
                $departmentId = null;
                $projectId = null;
                try {
                    $departmentId = $resolver->resolveDepartment($row->source);
                    $projectId = $resolver->resolveProject($row->source);
                } catch (\Throwable $e) {
                    // ignore
                }
                $changed = false;
                if ($departmentId && !$row->department_id) {
                    $row->department_id = $departmentId;
                    $changed = true;
                }
                if ($projectId && !$row->project_id) {
                    $row->project_id = $projectId;
                    $changed = true;
                }
                if ($changed) {
                    $row->save();
                    $updated++;
                }
            }
        });
        $this->info("Backfill completed. Updated: {$updated}");
        return self::SUCCESS;
    }
}
