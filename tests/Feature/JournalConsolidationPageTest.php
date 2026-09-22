<?php

use App\Filament\Pages\JournalConsolidationPage;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->branchA = Cabang::factory()->create([
        'kode' => 'JCP-A',
        'nama' => 'Journal Consolidation Branch A',
    ]);

    $this->branchB = Cabang::factory()->create([
        'kode' => 'JCP-B',
        'nama' => 'Journal Consolidation Branch B',
    ]);

    $this->cash = ChartOfAccount::factory()->create([
        'code' => '1-1001',
        'name' => 'Kas',
        'type' => 'Asset',
        'is_active' => true,
    ]);

    $this->revenue = ChartOfAccount::factory()->create([
        'code' => '4-1001',
        'name' => 'Pendapatan',
        'type' => 'Revenue',
        'is_active' => true,
    ]);

    JournalEntry::create([
        'coa_id' => $this->cash->id,
        'date' => '2026-04-10',
        'reference' => 'JCP-001',
        'description' => 'journal consolidation page debit',
        'debit' => 500000,
        'credit' => 0,
        'journal_type' => 'manual',
        'cabang_id' => $this->branchA->id,
        'source_type' => User::class,
        'source_id' => $this->user->id,
    ]);

    JournalEntry::create([
        'coa_id' => $this->revenue->id,
        'date' => '2026-04-10',
        'reference' => 'JCP-001',
        'description' => 'journal consolidation page credit',
        'debit' => 0,
        'credit' => 500000,
        'journal_type' => 'manual',
        'cabang_id' => $this->branchA->id,
        'source_type' => User::class,
        'source_id' => $this->user->id,
    ]);
});

it('builds the preview URL from the current filter state', function () {
    $component = Livewire::test(JournalConsolidationPage::class)
        ->set('start_date', '2026-04-01')
        ->set('end_date', '2026-04-30')
        ->set('branch_ids', [$this->branchA->id, $this->branchB->id])
        ->set('journal_type', 'manual')
        ->set('group_by_branch', false);

    $url = urldecode($component->instance()->getPreviewUrl());

    expect($url)->toContain('/reports/journal-consolidation/preview')
        ->toContain('start_date=2026-04-01')
        ->toContain('end_date=2026-04-30')
        ->toContain('journal_type=manual')
        ->toContain('group_by_branch=0')
        ->toContain('branch_ids[0]=' . $this->branchA->id)
        ->toContain('branch_ids[1]=' . $this->branchB->id);
});

it('can export journal consolidation to excel', function () {
    $component = Livewire::test(JournalConsolidationPage::class)
        ->set('start_date', '2026-04-01')
        ->set('end_date', '2026-04-30')
        ->set('branch_ids', [$this->branchA->id])
        ->set('journal_type', 'manual')
        ->set('group_by_branch', true);

    expect(fn () => $component->call('export', 'excel'))->not->toThrow(Exception::class);
});

it('can export journal consolidation to pdf', function () {
    $component = Livewire::test(JournalConsolidationPage::class)
        ->set('start_date', '2026-04-01')
        ->set('end_date', '2026-04-30')
        ->set('branch_ids', [$this->branchA->id])
        ->set('journal_type', 'manual')
        ->set('group_by_branch', true);

    expect(fn () => $component->call('export', 'pdf'))->not->toThrow(Exception::class);
});

it('returns grouped totals from the shared service payload', function () {
    $component = Livewire::test(JournalConsolidationPage::class)
        ->set('showPreview', true)
        ->set('start_date', '2026-04-01')
        ->set('end_date', '2026-04-30')
        ->set('branch_ids', [$this->branchA->id])
        ->set('journal_type', 'manual')
        ->set('group_by_branch', true);

    $data = $component->instance()->getConsolidationData();

    expect($data['count'])->toBe(2)
        ->and($data['total_debit'])->toBe(500000.0)
        ->and($data['total_credit'])->toBe(500000.0)
        ->and($data['balanced'])->toBeTrue()
        ->and($data['grouped'][0]['cabang_name'])->toBe('Journal Consolidation Branch A');
});
