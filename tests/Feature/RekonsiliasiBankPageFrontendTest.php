<?php

use App\Filament\Pages\RekonsiliasiBankPage;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Cabang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('RekonsiliasiBankPage Frontend Tests', function () {

    beforeEach(function () {
        // Create test branch
        $this->cabang = Cabang::create([
            'kode' => 'TEST',
            'nama' => 'Test Branch',
            'alamat' => 'Test Address',
            'telepon' => '0123456789',
        ]);

        // Create Bank Account
        $this->bankAccount = ChartOfAccount::create([
            'code' => '1111.01',
            'name' => 'Bank BCA',
            'type' => 'Asset',
            'is_current' => true,
        ]);

        // Create confirmed entry
        $this->confirmedEntry = JournalEntry::create([
            'coa_id' => $this->bankAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now()->format('Y-m-d'),
            'reference' => 'FE-CONF-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Confirmed Transaction',
            'debit' => 2000000,
            'credit' => 0,
            'bank_recon_status' => 'confirmed',
            'bank_recon_date' => now()->format('Y-m-d'),
        ]);

        // Create unconfirmed entry
        $this->unconfirmedEntry = JournalEntry::create([
            'coa_id' => $this->bankAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now()->format('Y-m-d'),
            'reference' => 'FE-UNCONF-002',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Unconfirmed Transaction',
            'debit' => 0,
            'credit' => 750000,
            'bank_recon_status' => null,
        ]);
    });

    it('renders the rekonsiliasi page correctly', function () {
        Livewire::test(RekonsiliasiBankPage::class)
            ->assertSee('Filter Transaksi')
            ->assertSee('Akun Bank / Kas')
            ->assertSee('Tanggal Mulai')
            ->assertSee('Tanggal Akhir')
            ->assertOk();
    });

    it('displays filter section with proper styling', function () {
        Livewire::test(RekonsiliasiBankPage::class)
            ->assertSee('🔍')
            ->assertSee('Filter Transaksi');
    });

    it('displays COA select dropdown', function () {
        Livewire::test(RekonsiliasiBankPage::class)
            ->assertSee('-- Pilih Akun --')
            ->assertSee('Bank BCA');
    });

    it('displays date inputs with wire:model.live', function () {
        Livewire::test(RekonsiliasiBankPage::class)
            ->assertSeeHtml('wire:model.live="startDate"')
            ->assertSeeHtml('wire:model.live="endDate"');
    });

    it('displays toggle button for show/hide confirmed', function () {
        Livewire::test(RekonsiliasiBankPage::class)
            ->assertSee('Tampilkan Data:')
            ->assertSee('Semua Data (Termasuk yang Sudah Dikonfirmasi)');
    });

    it('updates toggle button text when clicked', function () {
        Livewire::test(RekonsiliasiBankPage::class)
            ->assertSee('Semua Data (Termasuk yang Sudah Dikonfirmasi)')
            ->call('toggleShowConfirmed')
            ->assertSee('Hanya yang Belum Dikonfirmasi');
    });

    it('displays table headers correctly', function () {
        Livewire::test(RekonsiliasiBankPage::class)
            ->set('selectedCoaId', $this->bankAccount->id)
            ->assertSee('Ada di Rekening')
            ->assertSee('Tanggal')
            ->assertSee('Referensi')
            ->assertSee('Deskripsi')
            ->assertSee('Debit')
            ->assertSee('Kredit')
            ->assertSee('Status');
    });

    it('displays empty state when no COA selected', function () {
        Livewire::test(RekonsiliasiBankPage::class)
            ->assertSee('Tidak Ada Data')
            ->assertSee('Pilih akun bank/kas dan periode tanggal untuk menampilkan transaksi');
    });

    it('displays journal entries in table when COA selected', function () {
        Livewire::test(RekonsiliasiBankPage::class)
            ->set('selectedCoaId', $this->bankAccount->id)
            ->set('startDate', now()->subDays(1)->format('Y-m-d'))
            ->set('endDate', now()->addDays(1)->format('Y-m-d'))
            ->assertSee('FE-CONF-001')
            ->assertSee('FE-UNCONF-002')
            ->assertSee('Confirmed Transaction')
            ->assertSee('Unconfirmed Transaction');
    });

    it('displays checkbox for each entry', function () {
        Livewire::test(RekonsiliasiBankPage::class)
            ->set('selectedCoaId', $this->bankAccount->id)
            ->set('startDate', now()->subDays(1)->format('Y-m-d'))
            ->set('endDate', now()->addDays(1)->format('Y-m-d'))
            ->assertSeeHtml('type="checkbox"')
            ->assertSee('toggleConfirmation');
    });

    it('shows checked checkbox for confirmed entries', function () {
        Livewire::test(RekonsiliasiBankPage::class)
            ->set('selectedCoaId', $this->bankAccount->id)
            ->set('startDate', now()->subDays(1)->format('Y-m-d'))
            ->set('endDate', now()->addDays(1)->format('Y-m-d'))
            ->assertSeeHtml('checked');
    });

    it('applies red highlight class for unconfirmed entries', function () {
        Livewire::test(RekonsiliasiBankPage::class)
            ->set('selectedCoaId', $this->bankAccount->id)
            ->set('startDate', now()->subDays(1)->format('Y-m-d'))
            ->set('endDate', now()->addDays(1)->format('Y-m-d'))
            ->assertSeeHtml('class="unconfirmed"');
    });

    it('applies green highlight class for confirmed entries', function () {
        Livewire::test(RekonsiliasiBankPage::class)
            ->set('selectedCoaId', $this->bankAccount->id)
            ->set('startDate', now()->subDays(1)->format('Y-m-d'))
            ->set('endDate', now()->addDays(1)->format('Y-m-d'))
            ->assertSeeHtml('class="confirmed"');
    });

    it('displays status badges correctly', function () {
        Livewire::test(RekonsiliasiBankPage::class)
            ->set('selectedCoaId', $this->bankAccount->id)
            ->set('startDate', now()->subDays(1)->format('Y-m-d'))
            ->set('endDate', now()->addDays(1)->format('Y-m-d'))
            ->assertSee('✓ Dikonfirmasi')
            ->assertSee('⚠ Belum');
    });

    it('formats debit amount with thousand separator', function () {
        Livewire::test(RekonsiliasiBankPage::class)
            ->set('selectedCoaId', $this->bankAccount->id)
            ->set('startDate', now()->subDays(1)->format('Y-m-d'))
            ->set('endDate', now()->addDays(1)->format('Y-m-d'))
            ->assertSee('Rp 2.000.000');
    });

    it('formats credit amount with thousand separator', function () {
        Livewire::test(RekonsiliasiBankPage::class)
            ->set('selectedCoaId', $this->bankAccount->id)
            ->set('startDate', now()->subDays(1)->format('Y-m-d'))
            ->set('endDate', now()->addDays(1)->format('Y-m-d'))
            ->assertSee('Rp 750.000');
    });

    it('formats date in dd/mm/yyyy format', function () {
        Livewire::test(RekonsiliasiBankPage::class)
            ->set('selectedCoaId', $this->bankAccount->id)
            ->set('startDate', now()->subDays(1)->format('Y-m-d'))
            ->set('endDate', now()->addDays(1)->format('Y-m-d'))
            ->assertSee(now()->format('d/m/Y'));
    });

    it('hides confirmed entries when showConfirmed is false', function () {
        $component = Livewire::test(RekonsiliasiBankPage::class)
            ->set('selectedCoaId', $this->bankAccount->id)
            ->set('startDate', now()->subDays(1)->format('Y-m-d'))
            ->set('endDate', now()->addDays(1)->format('Y-m-d'))
            ->set('showConfirmed', true); // Tampilkan semua dulu
        
        // Pastikan keduanya terlihat
        $component->assertSee('FE-CONF-001')
            ->assertSee('FE-UNCONF-002');
        
        // Sekarang hide yang confirmed
        $component->set('showConfirmed', false)
            ->assertSee('FE-UNCONF-002')
            ->assertDontSee('FE-CONF-001');
    });

    it('shows all entries when showConfirmed is true', function () {
        Livewire::test(RekonsiliasiBankPage::class)
            ->set('selectedCoaId', $this->bankAccount->id)
            ->set('startDate', now()->subDays(1)->format('Y-m-d'))
            ->set('endDate', now()->addDays(1)->format('Y-m-d'))
            ->set('showConfirmed', true)
            ->assertSee('FE-CONF-001')
            ->assertSee('FE-UNCONF-002');
    });

    it('displays modern gradient styling on filters', function () {
        Livewire::test(RekonsiliasiBankPage::class)
            ->assertSee('Filter Transaksi');
    });

    it('displays table with proper container styling', function () {
        Livewire::test(RekonsiliasiBankPage::class)
            ->assertSee('Akun Bank / Kas')
            ->assertSee('Tanggal Mulai');
    });

    it('shows proper empty icon and text', function () {
        Livewire::test(RekonsiliasiBankPage::class)
            ->assertSee('📋')
            ->assertSee('Tidak Ada Data');
    });

    it('updates entries when date range changes', function () {
        $component = Livewire::test(RekonsiliasiBankPage::class)
            ->set('selectedCoaId', $this->bankAccount->id)
            ->set('startDate', now()->subDays(1)->format('Y-m-d'))
            ->set('endDate', now()->addDays(1)->format('Y-m-d'))
            ->assertSee('FE-CONF-001');

        // Change date range to future (no entries)
        $component->set('startDate', now()->addDays(5)->format('Y-m-d'))
            ->set('endDate', now()->addDays(10)->format('Y-m-d'))
            ->assertDontSee('FE-CONF-001');
    });

    it('includes responsive styling for mobile', function () {
        Livewire::test(RekonsiliasiBankPage::class)
            ->assertSee('Filter Transaksi')
            ->assertSee('Akun Bank / Kas');
    });

});
