<?php

namespace App\Filament\Resources\DepositResource\Pages;

use App\Filament\Resources\DepositResource;
use App\Http\Controllers\HelperController;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Filament\Actions;
use Filament\Notifications\Notification;
use App\Services\DepositNumberGenerator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateDeposit extends CreateRecord
{
    protected static string $resource = DepositResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Auto-calculate remaining amount
        $data['remaining_amount'] = $data['amount'] - ($data['used_amount'] ?? 0);
        
        // Convert status boolean to string for database
        $data['status'] = $data['status'] ? 'active' : 'closed';
        
        // Set creator
        $data['created_by'] = Auth::id();

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generate_deposit_number')
                ->label('Generate Nomor')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    $number = app(DepositNumberGenerator::class)->generate();
                    // Fill the form deposit_number field with generated value
                    $this->form->fill([ 'deposit_number' => $number ]);

                    Notification::make()
                        ->title('Nomor deposit ter-generate: ' . $number)
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function afterCreate(): void
    {
        // Create initial deposit log
        $this->record->depositLogRef()->create([
            'deposit_id' => $this->record->id,
            'type' => 'create',
            'amount' => $this->record->amount,
            'note' => 'Initial deposit created: ' . ($this->record->note ?? 'No additional notes'),
            'created_by' => Auth::id()
        ]);

        // Create journal entries for deposit creation
        $this->createDepositJournalEntries();

        HelperController::sendNotification(
            isSuccess: true, 
            title: 'Success', 
            message: "Deposit successfully created for " . $this->record->fromModel->name
        );
    }

    public function createDepositJournalEntries(): void
    {
        $branchId = app(\App\Services\JournalBranchResolver::class)->resolve($this->record);
        $departmentId = app(\App\Services\JournalBranchResolver::class)->resolveDepartment($this->record);
        $projectId = app(\App\Services\JournalBranchResolver::class)->resolveProject($this->record);

        if ($this->record->from_model_type === 'App\Models\Supplier') {
            // For supplier deposit (uang muka pembelian):
            // Dr: Uang Muka Pembelian (1150.01/1150.02)
            // Cr: Kas/Bank (coa_id from deposit)

            // DEBIT: Uang Muka Pembelian
            $this->record->journalEntry()->create([
                'coa_id' => $this->record->coa_id, // Uang Muka Pembelian
                'date' => now(),
                'reference' => 'DEP-' . $this->record->id,
                'description' => 'Deposit ke supplier - ' . $this->record->fromModel->name,
                'debit' => $this->record->amount,
                'journal_type' => 'deposit',
                'source_type' => \App\Models\Deposit::class,
                'source_id' => $this->record->id,
                'cabang_id' => $branchId,
                'department_id' => $departmentId,
                'project_id' => $projectId,
            ]);

            // Get bank/cash COA from the form data (assuming it's passed)
            $bankCoaId = $this->record->payment_coa_id ?? null;
            if (!$bankCoaId) {
                // Try to find default bank/cash COA
                $bankCoaId = ChartOfAccount::where('code', 'LIKE', '111%')->first()?->id;
            }

            if ($bankCoaId) {
                // CREDIT: Kas/Bank
                JournalEntry::create([
                    'coa_id' => $bankCoaId,
                    'date' => now(),
                    'reference' => 'DEP-' . $this->record->id,
                    'description' => 'Pembayaran deposit ke supplier - ' . $this->record->fromModel->name,
                    'credit' => $this->record->amount,
                    'journal_type' => 'deposit',
                    'source_type' => \App\Models\Deposit::class,
                    'source_id' => $this->record->id,
                    'cabang_id' => $branchId,
                    'department_id' => $departmentId,
                    'project_id' => $projectId,
                ]);
            }

        } elseif ($this->record->from_model_type === 'App\Models\Customer') {
            // For customer deposit (deposit dari customer):
            // Dr: Kas/Bank (coa_id from deposit)
            // Cr: Hutang Titipan Konsumen (2160.04)

            // DEBIT: Kas/Bank
            $this->record->journalEntry()->create([
                'coa_id' => $this->record->coa_id, // Kas/Bank
                'date' => now(),
                'reference' => 'DEP-' . $this->record->id,
                'description' => 'Deposit dari customer - ' . $this->record->fromModel->name,
                'debit' => $this->record->amount,
                'journal_type' => 'deposit',
                'source_type' => \App\Models\Deposit::class,
                'source_id' => $this->record->id,
                'cabang_id' => $branchId,
                'department_id' => $departmentId,
                'project_id' => $projectId,
            ]);

            // CREDIT: Hutang Titipan Konsumen
            $liabilityCoaId = ChartOfAccount::where('code', '2160.04')->first()?->id;
            if ($liabilityCoaId) {
                JournalEntry::create([
                    'coa_id' => $liabilityCoaId,
                    'date' => now(),
                    'reference' => 'DEP-' . $this->record->id,
                    'description' => 'Deposit dari customer - ' . $this->record->fromModel->name,
                    'credit' => $this->record->amount,
                    'journal_type' => 'deposit',
                    'source_type' => \App\Models\Deposit::class,
                    'source_id' => $this->record->id,
                    'cabang_id' => $branchId,
                    'department_id' => $departmentId,
                    'project_id' => $projectId,
                ]);
            }
        }
    }

    public function getTitle(): string
    {
        return 'Create New Deposit';
    }
}
