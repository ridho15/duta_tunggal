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
    // Expose form-like data property so tests that set it directly won't cause null offsets
    public ?array $data = [];

    protected static string $resource = DepositResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // If tests set the public $data property directly, prefer those values when the
        // incoming $data is empty. This keeps compatibility with tests that mutate
        // component state instead of Filament form state.
        if (empty($data) && !empty($this->data) && is_array($this->data)) {
            $data = $this->data;
        }

        // Guard against missing keys to avoid array offset on null
        $amount = $data['amount'] ?? 0;
        $used = $data['used_amount'] ?? 0;

        // Auto-calculate remaining amount
        $data['remaining_amount'] = $amount - $used;
        
        // Convert status boolean to string for database
        $data['status'] = isset($data['status']) ? ($data['status'] ? 'active' : 'closed') : 'active';
        
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

        $fromName = $this->record->fromModel->perusahaan ?? $this->record->fromModel->name ?? ($this->record->fromModel->code ?? '');

        HelperController::sendNotification(
            isSuccess: true, 
            title: 'Success', 
            message: "Deposit successfully created for " . $fromName
        );
    }

    public function create(bool $another = false): void
    {
        $logPath = storage_path('logs/create_deposit_debug.log');
        file_put_contents($logPath, '[' . now()->toDateTimeString() . '] create called, component_data: ' . var_export($this->data ?? null, true) . PHP_EOL, FILE_APPEND);
        try {
            parent::create($another);
            file_put_contents($logPath, '[' . now()->toDateTimeString() . '] parent::create succeeded, record_id: ' . ($this->record->id ?? 'null') . ', form_state: ' . var_export(method_exists($this->form, 'getState') ? $this->form->getState() : null, true) . PHP_EOL, FILE_APPEND);
        } catch (\Throwable $e) {
            // write debug file
            file_put_contents($logPath, '[' . now()->toDateTimeString() . '] create error: ' . $e->getMessage() . PHP_EOL . $e->getTraceAsString() . PHP_EOL, FILE_APPEND);
            logger()->error('CreateDeposit::create error: ' . $e->getMessage(), [
                'exception' => $e,
                'component_data' => $this->data ?? null,
            ]);
            throw $e;
        }
    }

    // When tests set the Livewire public $data property directly, Livewire will call
    // the updatedData hook. Ensure the Filament form is filled with that data so
    // downstream form logic (validation, afterStateUpdated closures, etc.) operate
    // on an actual form state array rather than null.
    public function updatedData(): void
    {
        if (!is_array($this->data)) {
            logger()->warning('CreateDeposit::updatedData called with non-array data', ['data_type' => gettype($this->data), 'data' => $this->data]);
            return;
        }

        try {
            // Log the payload to help trace the source of "array offset on null"
            logger()->debug('CreateDeposit::updatedData payload', ['data' => $this->data]);

            // Also write to a dedicated debug file since storage/logs/laravel.log may not exist
            $logPath = storage_path('logs/create_deposit_payload.log');
            file_put_contents($logPath, '[' . now()->toDateTimeString() . '] updatedData payload: ' . var_export($this->data, true) . PHP_EOL, FILE_APPEND);

            $this->form->fill($this->data);
        } catch (\Throwable $e) {
            logger()->error('CreateDeposit::updatedData error while filling form: ' . $e->getMessage(), [
                'exception' => $e,
                'component_data' => $this->data,
                'component_form_state' => method_exists($this->form, 'getState') ? $this->form->getState() : null,
            ]);

            // Also write exception details to the debug file for quick access in CI/test runs
            file_put_contents($logPath ?? storage_path('logs/create_deposit_payload.log'), '[' . now()->toDateTimeString() . '] updatedData error: ' . $e->getMessage() . '\n' . $e->getTraceAsString() . PHP_EOL, FILE_APPEND);

            // Re-throw so tests still see the failure; the log will hold details for analysis.
            throw $e;
        }
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
                'description' => 'Deposit ke supplier - ' . ($this->record->fromModel->perusahaan ?? $this->record->fromModel->name ?? ($this->record->fromModel->code ?? '')), 
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
                    'description' => 'Pembayaran deposit ke supplier - ' . ($this->record->fromModel->perusahaan ?? $this->record->fromModel->name ?? ($this->record->fromModel->code ?? '')), 
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
                'description' => 'Deposit dari customer - ' . ($this->record->fromModel->perusahaan ?? $this->record->fromModel->name ?? ($this->record->fromModel->code ?? '')),
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
                    'description' => 'Deposit dari customer - ' . ($this->record->fromModel->perusahaan ?? $this->record->fromModel->name ?? ($this->record->fromModel->code ?? '')),
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
