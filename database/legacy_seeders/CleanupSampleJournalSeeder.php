<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\JournalEntry;

class CleanupSampleJournalSeeder extends Seeder
{
    public function run(): void
    {
        $refs = [
            'JE-2025-001',
            'JE-2025-002',
            'JE-2025-003',
            'JE-2025-004',
            'JE-2025-005',
        ];
        $count = JournalEntry::whereIn('reference', $refs)->count();
        JournalEntry::whereIn('reference', $refs)->delete();
        $this->command?->info("Deleted {$count} sample journal entries (JE-2025-001..005)");
    }
}
