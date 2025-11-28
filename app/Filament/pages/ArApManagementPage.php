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
use Illuminate\Support\Facades\Artisan;
use Filament\Notifications\Notification;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Carbon\Carbon;

class ArApManagementPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static string $view = 'filament.pages.ar-ap-management-page';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'AR & AP Management';

    protected static ?int $navigationSort = 6;

    // Add this to make sure it's accessible
    protected static ?string $slug = 'ar-ap-management';
    
    // Make sure the page is always visible (remove any permission restrictions)
    public static function canAccess(): bool
    {
        return true;
    }
    
    // Add this to help with debugging
    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

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
            ->defaultSort('created_at', 'desc')
            ->filters($this->getTableFilters())
            ->actions([
                Action::make('view_details')
                    ->label('View Payment')
                    ->icon('heroicon-m-eye')
                    ->color('primary')
                    ->size('sm')
                    ->button()
                    ->visible(function ($record) {
                        try {
                            if ($this->activeTab === 'ar') {
                                return \App\Models\CustomerReceipt::where('invoice_id', $record->invoice_id)->exists();
                            } else {
                                return \App\Models\VendorPayment::where('invoice_id', $record->invoice_id)->exists();
                            }
                        } catch (\Exception $e) {
                            return false;
                        }
                    })
                    ->url(function ($record) {
                        try {
                            if ($this->activeTab === 'ar') {
                                $customerReceipt = \App\Models\CustomerReceipt::where('invoice_id', $record->invoice_id)->first();
                                if ($customerReceipt) {
                                    return route("filament.admin.resources.customer-receipts.view", ['record' => $customerReceipt->id]);
                                }
                            } else {
                                $vendorPayment = \App\Models\VendorPayment::where('invoice_id', $record->invoice_id)->first();
                                if ($vendorPayment) {
                                    return route("filament.admin.resources.vendor-payments.view", ['record' => $vendorPayment->id]);
                                }
                            }
                            return '#';
                        } catch (\Exception $e) {
                            return '#';
                        }
                    })
                    ->openUrlInNewTab(),
                Action::make('edit_payment')
                    ->label('Edit Payment')
                    ->icon('heroicon-m-pencil-square')
                    ->color('warning')
                    ->size('sm')
                    ->button()
                    ->visible(function ($record) {
                        try {
                            if ($this->activeTab === 'ar') {
                                return \App\Models\CustomerReceipt::where('invoice_id', $record->invoice_id)->exists();
                            } else {
                                return \App\Models\VendorPayment::where('invoice_id', $record->invoice_id)->exists();
                            }
                        } catch (\Exception $e) {
                            return false;
                        }
                    })
                    ->url(function ($record) {
                        try {
                            if ($this->activeTab === 'ar') {
                                $customerReceipt = \App\Models\CustomerReceipt::where('invoice_id', $record->invoice_id)->first();
                                if ($customerReceipt) {
                                    return route("filament.admin.resources.customer-receipts.edit", ['record' => $customerReceipt->id]);
                                }
                            } else {
                                $vendorPayment = \App\Models\VendorPayment::where('invoice_id', $record->invoice_id)->first();
                                if ($vendorPayment) {
                                    return route("filament.admin.resources.vendor-payments.edit", ['record' => $vendorPayment->id]);
                                }
                            }
                            return '#';
                        } catch (\Exception $e) {
                            return '#';
                        }
                    }),
                Action::make('create_payment')
                    ->label('Create Payment')
                    ->icon('heroicon-m-plus')
                    ->color('success')
                    ->size('sm')
                    ->button()
                    ->visible(function ($record) {
                        try {
                            // Only show for unpaid invoices that don't have payment records yet
                            if ($record->status !== 'Belum Lunas') {
                                return false;
                            }
                            
                            if ($this->activeTab === 'ar') {
                                return !\App\Models\CustomerReceipt::where('invoice_id', $record->invoice_id)->exists();
                            } else {
                                return !\App\Models\VendorPayment::where('invoice_id', $record->invoice_id)->exists();
                            }
                        } catch (\Exception $e) {
                            return false;
                        }
                    })
                    ->url(function ($record) {
                        try {
                            if ($this->activeTab === 'ar') {
                                return route("filament.admin.resources.customer-receipts.create", [
                                    'invoice_id' => $record->invoice_id ?? null
                                ]);
                            } else {
                                return route("filament.admin.resources.vendor-payments.create", [
                                    'invoice_id' => $record->invoice_id ?? null
                                ]);
                            }
                        } catch (\Exception $e) {
                            return '#';
                        }
                    }),
                Action::make('add_payment')
                    ->label('Add Payment')
                    ->icon('heroicon-m-banknotes')
                    ->color('info')
                    ->size('sm')
                    ->button()
                    ->visible(function ($record) {
                        try {
                            // Show for unpaid invoices that already have payment records (for additional payments)
                            if ($record->status !== 'Belum Lunas') {
                                return false;
                            }
                            
                            if ($this->activeTab === 'ar') {
                                return \App\Models\CustomerReceipt::where('invoice_id', $record->invoice_id)->exists();
                            } else {
                                return \App\Models\VendorPayment::where('invoice_id', $record->invoice_id)->exists();
                            }
                        } catch (\Exception $e) {
                            return false;
                        }
                    })
                    ->url(function ($record) {
                        try {
                            if ($this->activeTab === 'ar') {
                                return route("filament.admin.resources.customer-receipts.create", [
                                    'invoice_id' => $record->invoice_id ?? null
                                ]);
                            } else {
                                return route("filament.admin.resources.vendor-payments.create", [
                                    'invoice_id' => $record->invoice_id ?? null
                                ]);
                            }
                        } catch (\Exception $e) {
                            return '#';
                        }
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
            
                
            TextColumn::make('id')
                ->label('ID')
                ->searchable()
                ->sortable(),
                
            TextColumn::make('invoice.invoice_number')
                ->label('Invoice Number')
                ->searchable()
                ->sortable()
                ->copyable()
                ->icon('heroicon-m-document-text')
                ->color('primary'),
                
            TextColumn::make($entityColumn)
                ->label($entityLabel)
                ->formatStateUsing(function ($state) {
                    if ($state) {
                        return "({$state->code}) {$state->name}";
                    }
                    return '-';
                })
                ->searchable(['code', 'name'])
                ->sortable(),
            
            TextColumn::make('invoice.due_date')
                ->label('Due Date')
                ->date('d/m/Y')
                ->sortable()
                ->color(function ($state) {
                    if (!$state) return 'gray';
                    $dueDate = Carbon::parse($state);
                    $now = Carbon::now();
                    
                    if ($dueDate->isPast()) {
                        return 'danger'; // Red for overdue
                    } elseif ($dueDate->diffInDays($now) <= 7) {
                        return 'warning'; // Yellow for due soon
                    }
                    return 'success'; // Green for normal
                })
                ->icon(function ($state) {
                    if (!$state) return null;
                    $dueDate = Carbon::parse($state);
                    $now = Carbon::now();
                    
                    if ($dueDate->isPast()) {
                        return 'heroicon-m-exclamation-triangle';
                    } elseif ($dueDate->diffInDays($now) <= 7) {
                        return 'heroicon-m-clock';
                    }
                    return 'heroicon-m-calendar-days';
                }),

            // Days Overdue (computed)
            TextColumn::make('days_overdue')
                ->label('Days Overdue')
                ->state(function ($record) {
                    $due = $record->invoice?->due_date ? Carbon::parse($record->invoice->due_date) : null;
                    if (!$due) return 0;
                    $days = $due->isPast() ? $due->diffInDays(Carbon::now()) : 0;
                    return $days;
                })
                ->sortable()
                ->alignRight()
                ->color(fn ($state) => $state > 60 ? 'danger' : ($state > 30 ? 'warning' : 'gray')),
                
            TextColumn::make('total')
                ->label('Total')
                ->money('IDR')
                ->sortable(),
                
            TextColumn::make('paid')
                ->label('Paid')
                ->money('IDR')
                ->sortable()
                ->color('success'),
                
            TextColumn::make('remaining')
                ->label('Outstanding')
                ->money('IDR')
                ->sortable()
                ->color(fn ($state) => $state > 0 ? 'warning' : 'success')
                ->weight('bold'),
                
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

    protected function getTableFilters(): array
    {
        return [
            SelectFilter::make('status')
                ->options([
                    'Belum Lunas' => 'Outstanding',
                    'Lunas' => 'Paid',
                ])
                ->multiple(),

            // Overdue toggle (only unpaid and past due)
            Filter::make('overdue')
                ->label('Overdue')
                ->query(function (Builder $query) {
                    return $query
                        ->where('status', 'Belum Lunas')
                        ->whereHas('invoice', function (Builder $q) {
                            $q->where('due_date', '<', Carbon::now());
                        });
                }),

            // Overdue days bucket
            SelectFilter::make('overdue_days')
                ->label('Overdue Period')
                ->options([
                    '1-30' => '1–30 days',
                    '31-60' => '31–60 days',
                    '60+' => '60+ days',
                ])
                ->query(function (Builder $query, array $data) {
                    if (!($data['value'] ?? null)) {
                        return $query;
                    }
                    return $query->where('status', 'Belum Lunas')->whereHas('invoice', function (Builder $q) use ($data) {
                        $now = Carbon::now()->toDateString();
                        if ($data['value'] === '1-30') {
                            $q->whereRaw('DATEDIFF(?, due_date) BETWEEN 1 AND 30', [$now]);
                        } elseif ($data['value'] === '31-60') {
                            $q->whereRaw('DATEDIFF(?, due_date) BETWEEN 31 AND 60', [$now]);
                        } elseif ($data['value'] === '60+') {
                            $q->whereRaw('DATEDIFF(?, due_date) > 60', [$now]);
                        }
                    });
                }),
        ];
    }

    public function getTitle(): string
    {
        $type = $this->activeTab === 'ar' ? 'Account Receivable' : 'Account Payable';
        return "{$type} Management";
    }

    // Livewire action to trigger sync from the UI
    public function syncAll(): void
    {
        try {
            Artisan::call('ar-ap:sync', ['--force' => true]);
            Notification::make()
                ->title('Sync selesai')
                ->success()
                ->body('AR & AP telah disinkronisasi dari invoice.')
                ->send();
            // Table will re-render automatically
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Sync gagal')
                ->danger()
                ->body($e->getMessage())
                ->send();
        }
    }
}
