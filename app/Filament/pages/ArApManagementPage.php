<?php

namespace App\Filament\Pages;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms;
use Filament\Tables\Actions\Action;

class ArApManagementPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static string $view = 'filament.pages.ar-ap-management-page';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'AR & AP Management';

    protected static ?int $navigationSort = 20;

    public string $activeTab = 'ar';

    public function mount(): void
    {
        $this->activeTab = request()->get('tab', 'ar');
    }

    public function table(Table $table): Table
    {
        $baseQuery = $this->activeTab === 'ar' 
            ? AccountReceivable::query() 
            : AccountPayable::query();

        return $table
            ->query($baseQuery->with([
                'invoice',
                $this->activeTab === 'ar' ? 'customer' : 'supplier'
            ]))
            ->columns($this->getTableColumns())
            ->defaultSort([
                ['invoice.due_date', 'asc'],
                ['remaining', 'desc']
            ])
            ->groups($this->getTableGroups())
            ->filters($this->getTableFilters())
            ->actions([
                Action::make('view_details')
                    ->label('View')
                    ->icon('heroicon-m-eye')
                    ->url(function ($record) {
                        $resource = $this->activeTab === 'ar' 
                            ? 'account-receivables' 
                            : 'account-payables';
                        return route("filament.admin.resources.{$resource}.view", ['record' => $record]);
                    })
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('sync_selected')
                    ->label('Sync Selected')
                    ->icon('heroicon-m-arrow-path')
                    ->action(function ($records) {
                        $this->syncRecords($records);
                    })
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('sync_all')
                    ->label('Sync All AR & AP')
                    ->icon('heroicon-m-arrow-path')
                    ->color('success')
                    ->action(function () {
                        \Artisan::call('ar-ap:sync --force');
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Synchronization Complete')
                            ->success()
                            ->body('All AR & AP records have been synchronized with invoices.')
                            ->send();
                    })
                    ->requiresConfirmation(),
                    
                Tables\Actions\Action::make('switch_view')
                    ->label($this->activeTab === 'ar' ? 'Switch to AP' : 'Switch to AR')
                    ->icon($this->activeTab === 'ar' ? 'heroicon-m-arrow-right' : 'heroicon-m-arrow-left')
                    ->color('info')
                    ->url(function () {
                        $newTab = $this->activeTab === 'ar' ? 'ap' : 'ar';
                        return request()->url() . "?tab={$newTab}";
                    }),
            ])
            ->striped()
            ->paginated([25, 50, 100]);
    }

    protected function getTableColumns(): array
    {
        $entityColumn = $this->activeTab === 'ar' ? 'customer' : 'supplier';
        $entityLabel = $this->activeTab === 'ar' ? 'Customer' : 'Supplier';

        return [
            TextColumn::make('invoice.invoice_number')
                ->label('Invoice')
                ->searchable()
                ->sortable()
                ->copyable(),
                
            TextColumn::make($entityColumn)
                ->label($entityLabel)
                ->formatStateUsing(function ($state) {
                    return "({$state->code}) {$state->name}";
                })
                ->searchable(['code', 'name'])
                ->sortable(),
                
            TextColumn::make('invoice.invoice_date')
                ->label('Invoice Date')
                ->date('M j, Y')
                ->sortable(),
                
            TextColumn::make('invoice.due_date')
                ->label('Due Date')
                ->date('M j, Y')
                ->sortable()
                ->color(function ($record) {
                    if ($record->invoice->due_date < now() && $record->status === 'Belum Lunas') {
                        return 'danger';
                    }
                    return 'gray';
                }),
                
            TextColumn::make('total')
                ->label('Total')
                ->money('idr')
                ->sortable()
                ->summarize([
                    Tables\Columns\Summarizers\Sum::make()->money('idr')
                ]),
                
            TextColumn::make('paid')
                ->label('Paid')
                ->money('idr')
                ->sortable()
                ->color('success')
                ->summarize([
                    Tables\Columns\Summarizers\Sum::make()->money('idr')
                ]),
                
            TextColumn::make('remaining')
                ->label('Outstanding')
                ->money('idr')
                ->sortable()
                ->color(fn ($state) => $state > 0 ? 'warning' : 'success')
                ->weight('bold')
                ->summarize([
                    Tables\Columns\Summarizers\Sum::make()->money('idr')
                ]),
                
            TextColumn::make('days_overdue')
                ->label('Days Overdue')
                ->getStateUsing(function ($record) {
                    if ($record->status === 'Belum Lunas' && $record->invoice->due_date < now()) {
                        return now()->diffInDays($record->invoice->due_date);
                    }
                    return 0;
                })
                ->color(function ($state) {
                    if ($state > 30) return 'danger';
                    if ($state > 0) return 'warning';
                    return 'success';
                })
                ->badge(),
                
            TextColumn::make('status')
                ->badge()
                ->color(function ($state) {
                    return match ($state) {
                        'Belum Lunas' => 'warning',
                        'Lunas' => 'success',
                        default => 'gray'
                    };
                }),
        ];
    }

    protected function getTableGroups(): array
    {
        $entityField = $this->activeTab === 'ar' ? 'customer.name' : 'supplier.name';
        $entityLabel = $this->activeTab === 'ar' ? 'Customer' : 'Supplier';
        $entityIcon = $this->activeTab === 'ar' ? '👤' : '🏢';

        return [
            Tables\Grouping\Group::make($entityField)
                ->label($entityLabel)
                ->titlePrefixedWithLabel(false)
                ->getTitleFromRecordUsing(function ($record) use ($entityIcon) {
                    $entity = $this->activeTab === 'ar' ? $record->customer : $record->supplier;
                    return "{$entityIcon} ({$entity->code}) {$entity->name}";
                })
                ->collapsible(),
                
            Tables\Grouping\Group::make('status')
                ->titlePrefixedWithLabel(false)
                ->getTitleFromRecordUsing(function ($record) {
                    return $record->status === 'Lunas' ? '✅ PAID' : '⏳ OUTSTANDING';
                })
                ->collapsible(),
        ];
    }

    protected function getTableFilters(): array
    {
        $entityFilter = $this->activeTab === 'ar' ? 'customer_id' : 'supplier_id';
        $entityModel = $this->activeTab === 'ar' ? 'customer' : 'supplier';
        $entityLabel = $this->activeTab === 'ar' ? 'Customer' : 'Supplier';

        return [
            Tables\Filters\SelectFilter::make($entityFilter)
                ->label($entityLabel)
                ->relationship($entityModel, 'name')
                ->searchable()
                ->preload()
                ->multiple()
                ->getOptionLabelFromRecordUsing(function ($record) {
                    return "({$record->code}) {$record->name}";
                }),
                
            Tables\Filters\SelectFilter::make('status')
                ->options([
                    'Belum Lunas' => 'Outstanding',
                    'Lunas' => 'Paid',
                ])
                ->multiple(),
                
            Tables\Filters\Filter::make('outstanding_only')
                ->label('Outstanding Only')
                ->query(fn (Builder $query): Builder => $query->where('remaining', '>', 0))
                ->toggle(),
                
            Tables\Filters\Filter::make('overdue')
                ->label('Overdue')
                ->query(function (Builder $query): Builder {
                    return $query->whereHas('invoice', function (Builder $query) {
                        $query->where('due_date', '<', now());
                    })->where('status', 'Belum Lunas');
                })
                ->toggle(),
        ];
    }

    protected function syncRecords($records): void
    {
        foreach ($records as $record) {
            // Recalculate paid and remaining amounts based on payments
            if ($this->activeTab === 'ar') {
                $totalPaid = \App\Models\CustomerReceipt::whereJsonContains('selected_invoices', (string)$record->invoice_id)
                    ->sum('total_payment');
            } else {
                $totalPaid = \App\Models\VendorPayment::whereJsonContains('selected_invoices', (string)$record->invoice_id)
                    ->sum('total_payment');
            }
            
            $remaining = max(0, $record->total - $totalPaid);
            $status = $remaining > 0 ? 'Belum Lunas' : 'Lunas';
            
            $record->update([
                'paid' => $totalPaid,
                'remaining' => $remaining,
                'status' => $status
            ]);
        }
        
        \Filament\Notifications\Notification::make()
            ->title('Records Synchronized')
            ->success()
            ->body(count($records) . ' records have been synchronized.')
            ->send();
    }

    public function getTitle(): string
    {
        $type = $this->activeTab === 'ar' ? 'Account Receivable' : 'Account Payable';
        return "{$type} Management";
    }
}
