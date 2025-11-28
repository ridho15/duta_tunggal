<?php

use App\Filament\Pages\RekonsiliasiBankPage;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Cabang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('RekonsiliasiBankPage Backend Tests', function () {

    beforeEach(function () {
        // Create test branch
        $this->cabang = Cabang::create([
            'kode' => 'TEST',
            'nama' => 'Test Branch',
            'alamat' => 'Test Address',
            'telepon' => '0123456789',
        ]);

        // Create Bank/Cash Account
        $this->bankAccount = ChartOfAccount::create([
            'code' => '1111.01',
            'name' => 'Bank BCA',
            'type' => 'Asset',
            'is_current' => true,
        ]);

        $this->otherAccount = ChartOfAccount::create([
            'code' => '4-1001',
            'name' => 'Pendapatan',
            'type' => 'Revenue',
        ]);

        // Create journal entries
        $this->confirmedEntry = JournalEntry::create([
            'coa_id' => $this->bankAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now()->format('Y-m-d'),
            'reference' => 'REKON-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Entry Already Confirmed',
            'debit' => 1000000,
            'credit' => 0,
            'bank_recon_status' => 'confirmed',
            'bank_recon_date' => now()->format('Y-m-d'),
        ]);

        $this->unconfirmedEntry = JournalEntry::create([
            'coa_id' => $this->bankAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now()->format('Y-m-d'),
            'reference' => 'REKON-002',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Entry Not Confirmed',
            'debit' => 0,
            'credit' => 500000,
            'bank_recon_status' => null,
        ]);
    });

    it('can mount the page with default values', function () {
        Livewire::test(RekonsiliasiBankPage::class)
            ->assertSet('showConfirmed', true)
            ->assertSet('startDate', now()->startOfMonth()->format('Y-m-d'))
            ->assertSet('endDate', now()->endOfMonth()->format('Y-m-d'))
            ->assertOk();
    });

    it('loads COA options for bank and cash accounts', function () {
        $component = Livewire::test(RekonsiliasiBankPage::class);

        $coaOptions = $component->get('coaOptions');
        
        expect($coaOptions)->toBeArray();
        expect(count($coaOptions))->toBeGreaterThan(0);
        
        $found = collect($coaOptions)->firstWhere('id', $this->bankAccount->id);
        expect($found)->not->toBeNull();
        expect($found['label'])->toContain('Bank BCA');
    });

    it('loads journal entries when COA is selected', function () {
        $component = Livewire::test(RekonsiliasiBankPage::class)
            ->set('selectedCoaId', $this->bankAccount->id)
            ->set('startDate', now()->subDays(1)->format('Y-m-d'))
            ->set('endDate', now()->addDays(1)->format('Y-m-d'))
            ->call('loadEntries');

        $entries = $component->get('entries');
        
        expect($entries)->toBeArray();
        expect(count($entries))->toBe(2); // Both confirmed and unconfirmed
    });

    it('filters out confirmed entries when showConfirmed is false', function () {
        $component = Livewire::test(RekonsiliasiBankPage::class)
            ->set('selectedCoaId', $this->bankAccount->id)
            ->set('startDate', now()->subDays(1)->format('Y-m-d'))
            ->set('endDate', now()->addDays(1)->format('Y-m-d'))
            ->set('showConfirmed', false)
            ->call('loadEntries');

        $entries = $component->get('entries');
        
        expect($entries)->toBeArray();
        expect(count($entries))->toBe(1); // Only unconfirmed entry
        expect($entries[0]['is_confirmed'])->toBeFalse();
    });

    it('can toggle confirmation status from unconfirmed to confirmed', function () {
        Livewire::test(RekonsiliasiBankPage::class)
            ->set('selectedCoaId', $this->bankAccount->id)
            ->set('startDate', now()->subDays(1)->format('Y-m-d'))
            ->set('endDate', now()->addDays(1)->format('Y-m-d'))
            ->call('toggleConfirmation', $this->unconfirmedEntry->id);

        $entry = JournalEntry::find($this->unconfirmedEntry->id);
        
        expect($entry->bank_recon_status)->toBe('confirmed');
        expect($entry->bank_recon_date)->not->toBeNull();
    });

    it('can toggle confirmation status from confirmed to unconfirmed', function () {
        Livewire::test(RekonsiliasiBankPage::class)
            ->set('selectedCoaId', $this->bankAccount->id)
            ->set('startDate', now()->subDays(1)->format('Y-m-d'))
            ->set('endDate', now()->addDays(1)->format('Y-m-d'))
            ->call('toggleConfirmation', $this->confirmedEntry->id);

        $entry = JournalEntry::find($this->confirmedEntry->id);
        
        expect($entry->bank_recon_status)->toBeNull();
        expect($entry->bank_recon_date)->toBeNull();
    });

    it('toggles showConfirmed state correctly', function () {
        $component = Livewire::test(RekonsiliasiBankPage::class)
            ->assertSet('showConfirmed', true)
            ->call('toggleShowConfirmed')
            ->assertSet('showConfirmed', false)
            ->call('toggleShowConfirmed')
            ->assertSet('showConfirmed', true);
    });

    it('auto loads entries when selectedCoaId changes', function () {
        $component = Livewire::test(RekonsiliasiBankPage::class)
            ->set('startDate', now()->subDays(1)->format('Y-m-d'))
            ->set('endDate', now()->addDays(1)->format('Y-m-d'))
            ->set('selectedCoaId', $this->bankAccount->id);

        $entries = $component->get('entries');
        expect(count($entries))->toBe(2);
    });

    it('auto loads entries when date range changes', function () {
        $component = Livewire::test(RekonsiliasiBankPage::class)
            ->set('selectedCoaId', $this->bankAccount->id)
            ->set('startDate', now()->subDays(10)->format('Y-m-d'))
            ->set('endDate', now()->subDays(5)->format('Y-m-d'));

        $entries = $component->get('entries');
        expect(count($entries))->toBe(0); // No entries in that date range
    });

    it('returns empty array when no COA is selected', function () {
        $component = Livewire::test(RekonsiliasiBankPage::class)
            ->set('selectedCoaId', null)
            ->set('startDate', now()->format('Y-m-d'))
            ->set('endDate', now()->format('Y-m-d'))
            ->call('loadEntries');

        $entries = $component->get('entries');
        expect($entries)->toBeArray();
        expect(count($entries))->toBe(0);
    });

    it('includes both debit and credit entries', function () {
        $component = Livewire::test(RekonsiliasiBankPage::class)
            ->set('selectedCoaId', $this->bankAccount->id)
            ->set('startDate', now()->subDays(1)->format('Y-m-d'))
            ->set('endDate', now()->addDays(1)->format('Y-m-d'))
            ->call('loadEntries');

        $entries = $component->get('entries');
        
        $hasDebit = collect($entries)->contains(fn($e) => $e['debit'] > 0);
        $hasCredit = collect($entries)->contains(fn($e) => $e['credit'] > 0);
        
        expect($hasDebit)->toBeTrue();
        expect($hasCredit)->toBeTrue();
    });

    it('sends success notification when toggling confirmation', function () {
        Livewire::test(RekonsiliasiBankPage::class)
            ->set('selectedCoaId', $this->bankAccount->id)
            ->call('toggleConfirmation', $this->unconfirmedEntry->id)
            ->assertNotified();
    });

    it('orders entries by date descending', function () {
        // Create older entry
        $olderEntry = JournalEntry::create([
            'coa_id' => $this->bankAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now()->subDays(5)->format('Y-m-d'),
            'reference' => 'REKON-OLD',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Older Entry',
            'debit' => 100000,
            'credit' => 0,
        ]);

        $component = Livewire::test(RekonsiliasiBankPage::class)
            ->set('selectedCoaId', $this->bankAccount->id)
            ->set('startDate', now()->subDays(10)->format('Y-m-d'))
            ->set('endDate', now()->addDays(1)->format('Y-m-d'))
            ->call('loadEntries');

        $entries = $component->get('entries');
        
        // First entry should be the newest
        expect($entries[0]['date'])->toBeGreaterThanOrEqual($entries[count($entries) - 1]['date']);
    });

});
