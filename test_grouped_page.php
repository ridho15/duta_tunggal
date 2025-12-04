<?php

// Simple test script to check if the grouped journal entries page loads
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Testing GroupedJournalEntries page...\n";

// Try to instantiate the page
try {
    $page = new \App\Filament\Resources\JournalEntryResource\Pages\GroupedJournalEntries();
    echo "Page instantiated successfully\n";

    // Try to call mount
    $page->mount();
    echo "Mount method called successfully\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}