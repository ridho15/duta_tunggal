<?php

use Livewire\Livewire;
use App\Filament\Resources\Reports\BalanceSheetResource\Pages\ViewBalanceSheet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('printPdf does not throw when as_of_date is set', function () {
    // Instantiate directly to avoid Filament/Livewire mount complexity in tests
    $page = new ViewBalanceSheet();
    $page->as_of_date = now()->format('Y-m-d');

    // Call printPdf; ensure no exception is thrown
    $result = $page->printPdf();

    // Should return a RedirectResponse or StreamedResponse; assert it's not null
    expect($result)->not()->toBeNull();
});
